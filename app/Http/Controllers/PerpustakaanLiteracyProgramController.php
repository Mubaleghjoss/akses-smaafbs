<?php

namespace App\Http\Controllers;

use App\Exceptions\LiteracySubmissionQueueBusy;
use App\Jobs\AnalyzeLiteracyResponseSimilarity;
use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiQuestion;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSubmissionTicket;
use App\Support\Perpustakaan\LiteracyReceiptClassStatus;
use App\Support\Perpustakaan\LiteracySocialThumbnail;
use App\Support\Perpustakaan\LiteracySubmissionEventRecorder;
use App\Support\Perpustakaan\LiteracySubmissionQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PerpustakaanLiteracyProgramController extends Controller
{
    public function index(): View
    {
        $resolveMaterials = fn () => PerpustakaanLiterasiMaterial::query()
            ->availableForPublic()
            ->withCount('questions')
            ->orderByDesc('created_at')
            ->paginate(12);

        $materials = app()->environment('testing')
            ? $resolveMaterials()
            : Cache::remember(
                'literacy:public-materials:page-'.max(1, (int) request()->query('page', 1)),
                now()->addSeconds(45),
                $resolveMaterials,
            );

        return view('library.literacy.index', [
            'title' => 'Literasi Numerasi',
            'materials' => $materials,
            'instructionsHtml' => PerpustakaanLiterasiMaterial::defaultInstructionsHtml(),
        ]);
    }

    public function show(string $slug): Response
    {
        $material = $this->resolveDirectLinkMaterial($slug);

        if (! $material->hasOpened()) {
            return $this->noStoreView('library.literacy.upcoming', [
                'title' => 'Materi Belum Dibuka',
                'material' => $material,
            ]);
        }

        $material->loadMissing('questions');
        $description = $material->readingContentPreview(180);

        if ($description === '') {
            $description = 'Materi '.$material->programCategoryLabel().' SMA AFBS. Baca materi dan kerjakan pertanyaannya melalui halaman ini.';
        }

        return $this->noStoreView('library.literacy.show', [
            'title' => $material->title,
            'material' => $material,
            'students' => $this->studentOptions(),
            'hasLatex' => $material->containsLatex(),
            'isDirectLinkOnly' => ! $material->isListedPublicly(),
            'meta' => [
                'description' => $description,
                'canonical_url' => $material->publicUrl(),
                'og_title' => $material->title,
                'og_description' => $description,
                'og_image' => $material->socialThumbnailUrl(),
                'og_image_secure_url' => $material->socialThumbnailUrl(),
                'og_image_type' => 'image/jpeg',
                'og_image_width' => LiteracySocialThumbnail::WIDTH,
                'og_image_height' => LiteracySocialThumbnail::HEIGHT,
                'og_image_alt' => 'Thumbnail materi '.$material->title,
                'og_url' => $material->publicUrl(),
                'twitter_title' => $material->title,
                'twitter_description' => $description,
                'twitter_image' => $material->socialThumbnailUrl(),
            ],
        ]);
    }

    public function socialThumbnail(
        string $slug,
        LiteracySocialThumbnail $thumbnail,
    ): BinaryFileResponse|RedirectResponse {
        return $thumbnail->response($this->resolveDirectLinkMaterial($slug));
    }

    public function store(Request $request, string $slug, LiteracySubmissionQueue $submissionQueue): RedirectResponse|JsonResponse
    {
        $material = $this->resolveDirectLinkMaterial($slug);
        $this->ensureMaterialHasOpened($material);
        $questions = $material->questions()->get();

        if ($questions->isEmpty()) {
            return back()
                ->withErrors(['answers' => 'Materi ini belum memiliki pertanyaan.'])
                ->withInput();
        }

        $requestId = $this->submissionRequestId($request);
        $studentId = (int) $request->input('student_id');

        try {
            $ticket = $submissionQueue->claimNewSubmission(
                $request,
                $material,
                $studentId,
                $requestId,
                $request->string('submission_ticket')->toString(),
            );
        } catch (LiteracySubmissionQueueBusy $exception) {
            return $this->queueWaitResponse($exception);
        }

        $ticketCompleted = $ticket?->status === 'completed';

        try {
            if ($ticketCompleted && $ticket?->result_response_id) {
                $completedResponse = PerpustakaanLiterasiResponse::query()->find($ticket->result_response_id);

                if ($completedResponse) {
                    $this->recordSubmissionDelivery($completedResponse, $ticket, $request);

                    return $this->successfulSubmissionRedirect($completedResponse, 'Jawaban sudah berhasil dikirim.');
                }
            }

            $validated = $request->validate(
                $this->answerValidationRules($questions) + [
                    'student_id' => ['required', 'integer', 'exists:data_siswa,id'],
                    'student_verification' => ['nullable', 'string', 'max:80'],
                    'submission_request_id' => ['nullable', 'uuid'],
                    'submission_ticket' => ['nullable', 'string', 'max:64'],
                    'submission_queue_waited' => ['nullable', 'boolean'],
                    'submission_retry_statuses' => ['nullable', 'string', 'max:120'],
                ] + $this->integrityValidationRules(),
                $this->answerValidationMessages($questions, $request),
                $this->answerValidationAttributes($questions),
            );

            $student = DataSiswa::query()
                ->whereKey((int) $validated['student_id'])
                ->where('status', 'aktif')
                ->first();

            if (! $student) {
                return back()
                    ->withErrors(['student_id' => 'Pilih siswa aktif dari data master.'])
                    ->withInput();
            }

            if (($material->student_verification_enabled ?? true)
                && ! $this->studentVerificationMatches($student, (string) ($validated['student_verification'] ?? ''))) {
                return back()
                    ->withErrors(['student_verification' => $this->studentVerificationErrorMessage($student, (string) ($validated['student_verification'] ?? ''))])
                    ->withInput();
            }

            $existingResponse = PerpustakaanLiterasiResponse::withTrashed()
                ->where('material_id', $material->getKey())
                ->where('data_siswa_id', $student->getKey())
                ->first();

            if ($existingResponse) {
                if ($existingResponse->trashed()) {
                    return back()
                        ->withErrors(['student_id' => 'Jawaban siswa ini berada di Sampah. Hubungi Guru / Tim Literasi Numerasi untuk merestore jawaban lama atau menghapusnya permanen sebelum mengerjakan ulang.'])
                        ->withInput();
                }

                return back()
                    ->withErrors(['student_id' => 'Siswa ini sudah mengirim jawaban. Gunakan kode unik untuk mengedit jawaban. Jika nama sudah mengisi dan lupa kode editnya, hubungi Guru / Tim Literasi Numerasi agar kode edit dicek.'])
                    ->withInput();
            }

            $response = DB::transaction(function () use ($request, $material, $questions, $student, $validated): PerpustakaanLiterasiResponse {
                $response = PerpustakaanLiterasiResponse::query()->create([
                    'material_id' => $material->getKey(),
                    'data_siswa_id' => $student->getKey(),
                    'student_name_snapshot' => trim((string) $student->nama),
                    'student_class_snapshot' => trim((string) $student->rombel_saat_ini) ?: null,
                    'submitted_at' => now(),
                    'submitted_ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                ]);

                $this->syncAnswers($response, $questions, $validated['answers'] ?? []);
                $response->addIntegrityCounts($this->integrityCountsFromValidated($validated));

                return $response;
            });

            AnalyzeLiteracyResponseSimilarity::queueFor($response);
            $this->recordSubmissionDelivery($response, $ticket, $request);
            $submissionQueue->complete($ticket, $response);
            $ticketCompleted = true;

            return $this->successfulSubmissionRedirect($response, 'Jawaban berhasil dikirim. Simpan kode unik untuk mengedit jawaban.');
        } catch (ValidationException $exception) {
            app(LiteracySubmissionEventRecorder::class)->record('validation_failed', [
                'material_id' => $material->getKey(),
                'data_siswa_id' => $studentId,
                'ticket_id' => $ticket?->getKey(),
                'http_status' => 422,
                'context' => [
                    'operation' => 'create',
                    'fields' => array_keys($exception->errors()),
                ],
            ]);

            throw $exception;
        } finally {
            if (! $ticketCompleted) {
                $submissionQueue->release($ticket);
            }
        }
    }

    public function editLookup(Request $request): RedirectResponse
    {
        $code = Str::upper(trim((string) $request->query('code', '')));

        if ($code === '') {
            return back()
                ->withErrors(['code' => 'Masukkan kode unik jawaban.'])
                ->withInput();
        }

        $response = $this->findResponseByEditCode($code);

        if (! $response) {
            return back()
                ->withErrors(['code' => 'Kode unik jawaban tidak ditemukan.'])
                ->withInput();
        }

        return redirect()->route('library.literacy.edit', $response->shortEditCode());
    }

    public function edit(string $code): Response
    {
        $response = $this->resolveResponseByEditCode($code);
        $response->loadMissing(['material.questions', 'answers']);

        return $this->noStoreView('library.literacy.edit', [
            'title' => 'Edit Jawaban Literasi Numerasi',
            'response' => $response,
            'material' => $response->material,
            'answerMap' => $response->answers->keyBy('question_id'),
            'hasLatex' => $response->material->containsLatex(),
        ]);
    }

    public function update(
        Request $request,
        string $code,
        LiteracySubmissionQueue $submissionQueue
    ): RedirectResponse|JsonResponse {
        $response = $this->resolveResponseByEditCode($code);
        $response->loadMissing('material.questions');
        $questions = $response->material->questions;

        $requestId = $this->submissionRequestId($request);

        try {
            $ticket = $submissionQueue->claimEditSubmission(
                $request,
                $response,
                $requestId,
                $request->string('submission_ticket')->toString(),
            );
        } catch (LiteracySubmissionQueueBusy $exception) {
            return $this->queueWaitResponse($exception);
        }

        $ticketCompleted = $ticket?->status === 'completed';

        try {
            if ($ticketCompleted) {
                return $this->successfulSubmissionRedirect($response, 'Perubahan jawaban sudah berhasil disimpan.');
            }

            $validated = $request->validate(
                $this->answerValidationRules($questions) + [
                    'submission_request_id' => ['nullable', 'uuid'],
                    'submission_ticket' => ['nullable', 'string', 'max:64'],
                    'submission_queue_waited' => ['nullable', 'boolean'],
                    'submission_retry_statuses' => ['nullable', 'string', 'max:120'],
                ] + $this->integrityValidationRules(),
                $this->answerValidationMessages($questions, $request),
                $this->answerValidationAttributes($questions),
            );

            DB::transaction(function () use ($response, $questions, $validated): void {
                $this->syncAnswers($response, $questions, $validated['answers'] ?? []);
                $response->addIntegrityCounts($this->integrityCountsFromValidated($validated));
                $response->forceFill([
                    'last_edited_at' => now(),
                ])->save();
            });

            AnalyzeLiteracyResponseSimilarity::queueFor($response);
            $submissionQueue->complete($ticket, $response);
            $ticketCompleted = true;

            return $this->successfulSubmissionRedirect($response, 'Jawaban berhasil diperbarui.');
        } catch (ValidationException $exception) {
            app(LiteracySubmissionEventRecorder::class)->record('validation_failed', [
                'material_id' => $response->material_id,
                'response_id' => $response->getKey(),
                'data_siswa_id' => $response->data_siswa_id,
                'ticket_id' => $ticket?->getKey(),
                'http_status' => 422,
                'context' => [
                    'operation' => 'update',
                    'fields' => array_keys($exception->errors()),
                ],
            ]);

            throw $exception;
        } finally {
            if (! $ticketCompleted) {
                $submissionQueue->release($ticket);
            }
        }
    }

    protected function recordSubmissionDelivery(
        PerpustakaanLiterasiResponse $response,
        ?PerpustakaanLiterasiSubmissionTicket $ticket,
        Request $request,
    ): void {
        $reportedStatuses = collect(explode(',', (string) $request->input('submission_retry_statuses', '')))
            ->map(fn (string $status): string => trim($status))
            ->filter(fn (string $status): bool => preg_match('/^(?:0|[1-5][0-9]{2})$/', $status) === 1)
            ->merge($response->submission_retry_statuses ?? [])
            ->unique()
            ->take(12)
            ->values();

        $queueWaitSeconds = (int) ($response->submission_queue_wait_seconds ?? 0);

        if ($ticket?->requested_at && $ticket?->admitted_at) {
            $queueWaitSeconds = max(
                $queueWaitSeconds,
                (int) $ticket->requested_at->diffInSeconds($ticket->admitted_at),
            );
        }

        if ($request->boolean('submission_queue_waited')) {
            $queueWaitSeconds = max(1, $queueWaitSeconds);
        }

        $deliveryCode = match (true) {
            $reportedStatuses->contains('503') => PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_RETRY_503,
            $reportedStatuses->contains('429') => PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_RETRY_429,
            $reportedStatuses->isNotEmpty() => PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_RETRY_OTHER,
            $queueWaitSeconds > 0 => PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_QUEUED,
            default => PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_DIRECT,
        };

        $response->forceFill([
            'submission_delivery_code' => $deliveryCode,
            'submission_queue_wait_seconds' => $queueWaitSeconds,
            'submission_retry_statuses' => $reportedStatuses->all() ?: null,
        ])->save();
    }

    public function requestStoreTicket(
        Request $request,
        string $slug,
        LiteracySubmissionQueue $submissionQueue,
    ): JsonResponse {
        $material = $this->resolveDirectLinkMaterial($slug);
        $this->ensureMaterialHasOpened($material);
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:data_siswa,id'],
            'submission_request_id' => ['required', 'uuid'],
        ]);

        $student = DataSiswa::query()
            ->whereKey((int) $validated['student_id'])
            ->where('status', 'aktif')
            ->firstOrFail();

        $ticket = $submissionQueue->requestNewTicket(
            $request,
            $material,
            $student->getKey(),
            (string) $validated['submission_request_id'],
        );

        return $this->ticketResponse($submissionQueue->payloadFor($ticket));
    }

    public function requestUpdateTicket(
        Request $request,
        string $code,
        LiteracySubmissionQueue $submissionQueue,
    ): JsonResponse {
        $response = $this->resolveResponseByEditCode($code);
        $validated = $request->validate([
            'submission_request_id' => ['required', 'uuid'],
        ]);

        $ticket = $submissionQueue->requestEditTicket(
            $request,
            $response,
            (string) $validated['submission_request_id'],
        );

        return $this->ticketResponse($submissionQueue->payloadFor($ticket));
    }

    public function submissionTicketStatus(
        Request $request,
        string $token,
        LiteracySubmissionQueue $submissionQueue,
    ): JsonResponse {
        return $this->ticketResponse($submissionQueue->status($request, $token));
    }

    public function cancelSubmissionTicket(
        Request $request,
        string $token,
        LiteracySubmissionQueue $submissionQueue,
    ): JsonResponse {
        return response()->json($submissionQueue->cancel($request, $token));
    }

    public function recordSubmissionEvent(
        Request $request,
        string $slug,
        LiteracySubmissionEventRecorder $recorder,
    ) {
        $material = $this->resolveDirectLinkMaterial($slug);
        $this->ensureMaterialHasOpened($material);
        $validated = $request->validate([
            'event_code' => ['required', 'in:client_retry_exhausted,unexpected_success_payload'],
            'submission_ticket' => ['nullable', 'string', 'max:64'],
            'submission_request_id' => ['nullable', 'uuid'],
            'retry_statuses' => ['nullable', 'string', 'max:120'],
            'http_status' => ['nullable', 'integer', 'min:0', 'max:599'],
            'content_type' => ['nullable', 'string', 'max:100'],
            'payload_status' => ['nullable', 'string', 'max:60'],
        ]);
        $ticket = filled($validated['submission_ticket'] ?? null)
            ? PerpustakaanLiterasiSubmissionTicket::query()
                ->where('public_token', $validated['submission_ticket'])
                ->where('material_id', $material->getKey())
                ->first()
            : null;

        $eventCode = (string) $validated['event_code'];
        $recorder->record($eventCode, [
            'material_id' => $material->getKey(),
            'data_siswa_id' => $ticket?->data_siswa_id,
            'ticket_id' => $ticket?->getKey(),
            'http_status' => isset($validated['http_status'])
                ? (int) $validated['http_status']
                : collect(explode(',', (string) ($validated['retry_statuses'] ?? '')))
                    ->map(fn (string $status): int => (int) trim($status))
                    ->filter()
                    ->last(),
            'retry_statuses' => $validated['retry_statuses'] ?? null,
            'context' => [
                'operation' => $ticket?->operation ?? 'unknown',
                'reason' => $eventCode === 'unexpected_success_payload'
                    ? 'success_payload_missing_receipt_redirect'
                    : 'retry_window_exhausted',
                'request_kind' => 'final_submission',
                'content_type' => $validated['content_type'] ?? null,
                'payload_status' => $validated['payload_status'] ?? null,
            ],
        ]);

        return response()->noContent();
    }

    public function recordIntegrity(Request $request, string $code)
    {
        $response = $this->resolveResponseByEditCode($code);
        $validated = $request->validate($this->integrityValidationRules());

        $response->addIntegrityCounts($this->integrityCountsFromValidated($validated));

        return response()->noContent();
    }

    public function completed(Request $request, LiteracyReceiptClassStatus $classStatus): Response
    {
        $receipt = $request->session()->get('literacy_submission_receipt');

        return $this->noStoreView('library.literacy.completed', [
            'title' => 'Jawaban Berhasil Disimpan',
            'receipt' => $receipt,
            'classStatus' => is_array($receipt) ? $classStatus->forReceipt($receipt) : null,
        ]);
    }

    protected function resolveDirectLinkMaterial(string $slug): PerpustakaanLiterasiMaterial
    {
        return PerpustakaanLiterasiMaterial::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    protected function ensureMaterialHasOpened(PerpustakaanLiterasiMaterial $material): void
    {
        if ($material->hasOpened()) {
            return;
        }

        throw ValidationException::withMessages([
            'material' => 'Materi ini belum dibuka. Silakan kembali pada '
                .$material->opens_at?->format('d/m/Y H:i').'.',
        ]);
    }

    protected function resolveResponseByEditCode(string $code): PerpustakaanLiterasiResponse
    {
        $response = $this->findResponseByEditCode($code);

        if (! $response) {
            abort(404);
        }

        return $response;
    }

    protected function findResponseByEditCode(string $code): ?PerpustakaanLiterasiResponse
    {
        $normalized = Str::upper(trim($code));

        $query = PerpustakaanLiterasiResponse::query();

        if (preg_match('/^[A-Z0-9]+-\d{6}-[A-Z0-9]{6}$/', $normalized)) {
            $query->where('edit_code', $normalized);
        } elseif (preg_match('/^[A-Z0-9]{6}$/', $normalized)) {
            $query->where('edit_code', 'like', '%-'.$normalized);
        } else {
            return null;
        }

        return $query->first();
    }

    /**
     * @param  Collection<int, PerpustakaanLiterasiQuestion>  $questions
     * @return array<string, mixed>
     */
    protected function answerValidationRules($questions): array
    {
        $rules = [
            'answers' => ['required', 'array'],
        ];

        foreach ($questions as $question) {
            $base = $question->is_required ? ['required'] : ['nullable'];

            if ($question->isTrueFalse()) {
                $expectedIds = collect($question->trueFalseItems())->pluck('id')->sort()->values();
                $rules['answers.'.$question->getKey()] = array_merge($base, [
                    'array',
                    function (string $attribute, mixed $value, \Closure $fail) use ($expectedIds): void {
                        $items = is_array($value) && is_array($value['items'] ?? null)
                            ? collect($value['items'])
                            : collect();

                        if ($items->isEmpty()) {
                            $fail('Pilih Benar atau Salah untuk setiap pernyataan.');

                            return;
                        }

                        $submittedIds = $items->keys()->map(fn (mixed $id): string => (string) $id)->sort()->values();
                        $validValues = $items->every(fn (mixed $answer): bool => in_array((string) $answer, ['0', '1'], true));

                        if (! $validValues || $submittedIds->all() !== $expectedIds->all()) {
                            $fail('Jawaban Benar/Salah harus lengkap dan sesuai dengan pernyataan yang tersedia.');
                        }
                    },
                ]);

                continue;
            }

            if ($question->isMatching()) {
                $expectedLeftIds = collect($question->matchingLeftItems())->pluck('id')->sort()->values();
                $availableRightIds = collect($question->matchingRightItems())->pluck('id');
                $rules['answers.'.$question->getKey()] = array_merge($base, [
                    'array',
                    function (string $attribute, mixed $value, \Closure $fail) use ($expectedLeftIds, $availableRightIds): void {
                        $pairs = is_array($value) && is_array($value['pairs'] ?? null)
                            ? collect($value['pairs'])
                            : collect();

                        if ($pairs->isEmpty()) {
                            $fail('Pilih pasangan untuk setiap item.');

                            return;
                        }

                        $submittedLeftIds = $pairs->keys()->map(fn (mixed $id): string => (string) $id)->sort()->values();
                        $selectedRightIds = $pairs->values()->map(fn (mixed $id): string => (string) $id);
                        $validTargets = $selectedRightIds->every(fn (string $id): bool => $availableRightIds->contains($id));

                        if ($submittedLeftIds->all() !== $expectedLeftIds->all() || ! $validTargets) {
                            $fail('Jawaban Menjodohkan harus lengkap dan sesuai dengan pilihan yang tersedia.');

                            return;
                        }

                        if ($selectedRightIds->duplicates()->isNotEmpty()) {
                            $fail('Satu pilihan kanan hanya boleh digunakan untuk satu pasangan.');
                        }
                    },
                ]);

                continue;
            }

            $max = max(1, (int) ($question->max_characters ?: 1000));
            $min = min($max, max(0, (int) ($question->min_characters ?: 0)));

            $rules['answers.'.$question->getKey()] = array_merge($base, ['string', 'min:'.$min, 'max:'.$max]);
        }

        return $rules;
    }

    /**
     * @param  Collection<int, PerpustakaanLiterasiQuestion>  $questions
     * @return array<string, string>
     */
    protected function answerValidationAttributes($questions): array
    {
        return $questions->mapWithKeys(fn (PerpustakaanLiterasiQuestion $question): array => [
            'answers.'.$question->getKey() => 'jawaban untuk pertanyaan '.$question->sort_order,
        ])->all();
    }

    /**
     * @param  Collection<int, PerpustakaanLiterasiQuestion>  $questions
     * @return array<string, string>
     */
    protected function answerValidationMessages($questions, Request $request): array
    {
        $messages = [
            'answers.required' => 'Jawaban belum diisi.',
            'answers.array' => 'Format jawaban tidak sesuai. Muat ulang halaman lalu coba lagi.',
        ];

        foreach ($questions as $question) {
            $field = 'answers.'.$question->getKey();
            $position = max(1, (int) $question->sort_order);

            if (! $question->isEssay()) {
                $messages[$field.'.required'] = "Jawaban pertanyaan {$position} wajib diisi lengkap.";
                $messages[$field.'.array'] = "Format jawaban pertanyaan {$position} tidak sesuai. Muat ulang halaman lalu coba lagi.";

                continue;
            }

            $max = max(1, (int) ($question->max_characters ?: 1000));
            $min = min($max, max(0, (int) ($question->min_characters ?: 0)));
            $length = mb_strlen((string) $request->input($field, ''));

            $messages[$field.'.required'] = "Jawaban pertanyaan {$position} wajib diisi.";
            $messages[$field.'.string'] = "Jawaban pertanyaan {$position} harus berupa teks.";
            $messages[$field.'.min'] = sprintf(
                'Jawaban pertanyaan %d minimal %s karakter. Saat ini jawaban Anda berisi %s karakter.',
                $position,
                number_format($min, 0, ',', '.'),
                number_format($length, 0, ',', '.'),
            );
            $messages[$field.'.max'] = sprintf(
                'Jawaban pertanyaan %d maksimal %s karakter. Saat ini jawaban Anda berisi %s karakter.',
                $position,
                number_format($max, 0, ',', '.'),
                number_format($length, 0, ',', '.'),
            );
        }

        return $messages;
    }

    protected function integrityValidationRules(): array
    {
        return [
            'integrity' => ['nullable', 'array'],
            'integrity.tab_switch_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'integrity.app_hidden_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'integrity.page_leave_attempt_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return array{tab_switch_count: int, app_hidden_count: int, page_leave_attempt_count: int}
     */
    protected function integrityCountsFromValidated(array $validated): array
    {
        $integrity = is_array($validated['integrity'] ?? null) ? $validated['integrity'] : [];

        return [
            'tab_switch_count' => max(0, (int) ($integrity['tab_switch_count'] ?? 0)),
            'app_hidden_count' => max(0, (int) ($integrity['app_hidden_count'] ?? 0)),
            'page_leave_attempt_count' => max(0, (int) ($integrity['page_leave_attempt_count'] ?? 0)),
        ];
    }

    /**
     * @param  Collection<int, PerpustakaanLiterasiQuestion>  $questions
     * @param  array<int|string, mixed>  $answers
     */
    protected function syncAnswers(PerpustakaanLiterasiResponse $response, $questions, array $answers): void
    {
        $gradingColumnsAvailable = Schema::hasColumn('perpustakaan_literasi_answers', 'is_correct');
        $structuredColumnsAvailable = Schema::hasColumn('perpustakaan_literasi_answers', 'answer_payload');
        $scoreColumnsAvailable = Schema::hasColumn('perpustakaan_literasi_answers', 'score_earned');

        foreach ($questions as $question) {
            $existingAnswer = $response->answers()
                ->where('question_id', $question->getKey())
                ->first();

            if (! $question->isEssay()) {
                $objective = $this->objectiveAnswerPayload(
                    $question,
                    $answers[$question->getKey()] ?? null,
                );
                $payload = [
                    'answer_text' => $objective['answer_text'],
                    'character_count' => mb_strlen($objective['answer_text']),
                ];

                if ($structuredColumnsAvailable) {
                    $payload['answer_payload'] = $objective['answer_payload'];
                }

                if ($gradingColumnsAvailable) {
                    $payload = array_merge($payload, [
                        'is_correct' => $objective['score_earned'] !== null
                            ? $objective['score_earned'] === $objective['score_possible']
                            : null,
                        'graded_by' => null,
                        'graded_at' => $objective['score_earned'] !== null ? now() : null,
                        'grading_note' => $objective['score_earned'] !== null
                            ? 'Dinilai otomatis per butir soal objektif.'
                            : null,
                    ]);
                }

                if ($scoreColumnsAvailable) {
                    $payload = array_merge($payload, [
                        'score_earned' => $objective['score_earned'],
                        'score_possible' => $objective['score_possible'],
                        'grading_source' => $objective['score_earned'] !== null ? 'automatic' : null,
                    ]);
                }

                $response->answers()->updateOrCreate(['question_id' => $question->getKey()], $payload);

                continue;
            }

            $answerText = trim((string) ($answers[$question->getKey()] ?? ''));
            $payload = [
                'answer_text' => $answerText,
                'character_count' => mb_strlen($answerText),
            ];

            if ($structuredColumnsAvailable) {
                $payload['answer_payload'] = null;
            }

            if ($scoreColumnsAvailable) {
                $payload['score_possible'] = 1;
            }

            $answerKeyGradingPayload = $gradingColumnsAvailable
                ? $this->answerKeyGradingPayload($question, $answerText, $existingAnswer)
                : null;

            if ($answerKeyGradingPayload !== null) {
                $payload = array_merge($payload, $answerKeyGradingPayload);

                if ($scoreColumnsAvailable) {
                    $payload['score_earned'] = $answerKeyGradingPayload['is_correct'] === true ? 1 : null;
                    $payload['grading_source'] = $answerKeyGradingPayload['is_correct'] === true ? 'answer_key' : null;
                }
            } elseif ($gradingColumnsAvailable
                && $existingAnswer
                && (string) $existingAnswer->answer_text !== $answerText) {
                $payload = array_merge($payload, [
                    'is_correct' => null,
                    'graded_by' => null,
                    'graded_at' => null,
                    'grading_note' => null,
                ]);

                if ($scoreColumnsAvailable) {
                    $payload['score_earned'] = null;
                    $payload['grading_source'] = null;
                }
            } elseif (! $existingAnswer && $scoreColumnsAvailable) {
                $payload['score_earned'] = null;
                $payload['grading_source'] = null;
            }

            $response->answers()->updateOrCreate(['question_id' => $question->getKey()], $payload);
        }
    }

    /**
     * @return array{answer_text:string,answer_payload:?array,score_earned:?int,score_possible:int}
     */
    protected function objectiveAnswerPayload(PerpustakaanLiterasiQuestion $question, mixed $answer): array
    {
        if ($question->isTrueFalse()) {
            $submitted = is_array($answer) && is_array($answer['items'] ?? null)
                ? collect($answer['items'])
                : collect();
            $items = collect($question->trueFalseItems());
            $complete = $submitted->count() === $items->count();
            $score = $complete
                ? $items->sum(fn (array $item): int => (string) $submitted->get($item['id']) === ($item['correct'] ? '1' : '0') ? 1 : 0)
                : null;
            $stored = $submitted->mapWithKeys(fn (mixed $value, mixed $id): array => [
                (string) $id => (string) $value === '1',
            ])->all();
            $text = $items->map(function (array $item, int $index) use ($submitted): string {
                $value = $submitted->get($item['id']);
                $label = $value === null ? 'Belum dijawab' : ((string) $value === '1' ? 'Benar' : 'Salah');

                return ($index + 1).'. '.$item['statement'].': '.$label;
            })->implode("\n");

            return [
                'answer_text' => $text,
                'answer_payload' => $submitted->isEmpty() ? null : ['version' => 1, 'items' => $stored],
                'score_earned' => $score,
                'score_possible' => $items->count(),
            ];
        }

        $submitted = is_array($answer) && is_array($answer['pairs'] ?? null)
            ? collect($answer['pairs'])->mapWithKeys(fn (mixed $value, mixed $id): array => [(string) $id => (string) $value])
            : collect();
        $leftItems = collect($question->matchingLeftItems());
        $rightItems = collect($question->matchingRightItems())->keyBy('id');
        $complete = $submitted->count() === $leftItems->count();
        $score = $complete
            ? $leftItems->sum(fn (array $item): int => $submitted->get($item['id']) === $item['correct_target_id'] ? 1 : 0)
            : null;
        $text = $leftItems->map(function (array $item, int $index) use ($submitted, $rightItems): string {
            $target = $rightItems->get($submitted->get($item['id']));

            return ($index + 1).'. '.$item['label'].' → '.($target['label'] ?? 'Belum dipilih');
        })->implode("\n");

        return [
            'answer_text' => $text,
            'answer_payload' => $submitted->isEmpty() ? null : ['version' => 1, 'pairs' => $submitted->all()],
            'score_earned' => $score,
            'score_possible' => $leftItems->count(),
        ];
    }

    /**
     * @return array{is_correct: bool|null, graded_by: int|null, graded_at: Carbon|null, grading_note: string|null}|null
     */
    protected function answerKeyGradingPayload(
        PerpustakaanLiterasiQuestion $question,
        string $answerText,
        ?PerpustakaanLiterasiAnswer $existingAnswer
    ): ?array {
        if (! $question->shouldAutoGradeByAnswerKey()) {
            return null;
        }

        if (! $question->matchesAnswerKey($answerText)) {
            return [
                'is_correct' => null,
                'graded_by' => null,
                'graded_at' => null,
                'grading_note' => null,
            ];
        }

        $autoNote = 'Dinilai otomatis berdasarkan kunci jawaban.';
        $gradedAt = $existingAnswer?->is_correct === true
            && $existingAnswer->graded_by === null
            && $existingAnswer->grading_note === $autoNote
                ? ($existingAnswer->graded_at ?: now())
                : now();

        return [
            'is_correct' => true,
            'graded_by' => null,
            'graded_at' => $gradedAt,
            'grading_note' => $autoNote,
        ];
    }

    /**
     * @return array<int, array{id:int,label:string,name:string,class:string,verification_required:bool}>
     */
    protected function studentOptions(): array
    {
        if (! Schema::hasTable('data_siswa')) {
            return [];
        }

        if (! app()->environment('testing')) {
            return Cache::remember(
                'literacy:active-student-options:v1',
                now()->addMinutes(5),
                fn (): array => $this->queryStudentOptions(),
            );
        }

        return $this->queryStudentOptions();
    }

    /**
     * @return array<int, array{id:int,label:string,name:string,class:string,verification_required:bool}>
     */
    protected function queryStudentOptions(): array
    {
        return DataSiswa::query()
            ->select(['id', 'nama', 'rombel_saat_ini', 'status', 'nisn', 'tanggal_lahir'])
            ->where('status', 'aktif')
            ->whereNotNull('nama')
            ->where('nama', '!=', '')
            ->orderBy('nama')
            ->limit(1000)
            ->get()
            ->map(function (DataSiswa $student): array {
                $class = trim((string) $student->rombel_saat_ini);
                $name = trim((string) $student->nama);

                return [
                    'id' => (int) $student->getKey(),
                    'name' => $name,
                    'class' => $class,
                    'label' => $class !== '' ? $name.' - '.$class : $name,
                    'verification_required' => $this->studentRequiresVerification($student),
                ];
            })
            ->values()
            ->all();
    }

    protected function studentRequiresVerification(DataSiswa $student): bool
    {
        return filled($student->nisn) || $student->tanggal_lahir !== null;
    }

    protected function studentVerificationMatches(DataSiswa $student, string $value): bool
    {
        if (! $this->studentRequiresVerification($student)) {
            return true;
        }

        $value = trim($value);

        if ($value === '') {
            return false;
        }

        $studentNisn = preg_replace('/\D+/', '', (string) $student->nisn) ?: '';
        $valueDigits = preg_replace('/\D+/', '', $value) ?: '';

        if ($studentNisn !== '' && hash_equals($studentNisn, $valueDigits)) {
            return true;
        }

        $birthDate = $student->tanggal_lahir?->format('Y-m-d');

        if ($birthDate === null) {
            return false;
        }

        return in_array($birthDate, $this->normalizedVerificationDates($value), true);
    }

    protected function studentVerificationErrorMessage(DataSiswa $student, string $value): string
    {
        return trim($value) === ''
            ? 'Isi NISN atau tanggal lahir siswa yang dipilih untuk verifikasi.'
            : 'NISN atau tanggal lahir tidak cocok dengan data siswa yang dipilih.';
    }

    /**
     * @return array<int, string>
     */
    protected function normalizedVerificationDates(string $value): array
    {
        $value = trim($value);
        $formats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'Ymd', 'dmY'];
        $dates = [];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            $hasErrors = is_array($errors)
                && (((int) ($errors['warning_count'] ?? 0)) > 0 || ((int) ($errors['error_count'] ?? 0)) > 0);

            if ($date === false || $hasErrors) {
                continue;
            }

            $dates[] = $date->format('Y-m-d');
        }

        return array_values(array_unique($dates));
    }

    protected function submissionRequestId(Request $request): string
    {
        $requestId = trim((string) $request->input('submission_request_id'));

        if (! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
            $request->merge(['submission_request_id' => $requestId]);
        }

        return $requestId;
    }

    protected function ticketResponse(array $payload): JsonResponse
    {
        $status = ($payload['status'] ?? null) === 'waiting' ? 202 : 201;

        return response()
            ->json($payload, $status)
            ->header('Retry-After', (string) ($payload['retry_after_seconds'] ?? 5));
    }

    protected function queueWaitResponse(LiteracySubmissionQueueBusy $exception): RedirectResponse|JsonResponse
    {
        $payload = $exception->queuePayload;
        $position = max(1, (int) ($payload['position'] ?? 1));
        $seconds = max(1, (int) ($payload['estimated_wait_seconds'] ?? 5));

        if (request()->expectsJson()) {
            return response()
                ->json($payload + [
                    'message' => 'Server sedang melayani pengiriman lain. Posisi antrean Anda '.$position
                        .', perkiraan tunggu '.$seconds.' detik.',
                ], 425)
                ->header('Retry-After', (string) ($payload['retry_after_seconds'] ?? 5));
        }

        request()->merge([
            'submission_ticket' => $payload['ticket'] ?? request('submission_ticket'),
        ]);

        return redirect()
            ->back(303)
            ->withErrors([
                'submission_queue' => 'Server sedang melayani pengiriman lain. Posisi antrean Anda '.$position
                    .', perkiraan tunggu '.$seconds.' detik. Silakan tekan Kirim kembali setelah hitung mundur.',
            ])
            ->withInput()
            ->header('Retry-After', (string) ($payload['retry_after_seconds'] ?? 5));
    }

    protected function successfulSubmissionRedirect(
        PerpustakaanLiterasiResponse $response,
        string $message,
    ): RedirectResponse|JsonResponse {
        $response->loadMissing('material');
        $redirectUrl = route('library.literacy.completed');
        $receipt = [
            'student_id' => $response->data_siswa_id,
            'student_name' => $response->student_name_snapshot,
            'student_class' => $response->student_class_snapshot,
            'material_id' => $response->material_id,
            'material_title' => $response->material?->title,
            'material_slug' => $response->material?->slug,
            'submitted_at' => ($response->last_edited_at ?? $response->submitted_at)?->toIso8601String(),
            'submission_status' => $response->submissionDeliveryLabel(),
            'submission_status_detail' => $response->submissionDeliveryDescription(),
            'edit_code' => $response->edit_code,
            'submission_request_id' => request()->string('submission_request_id')->toString(),
            'draft_key' => $response->last_edited_at
                ? 'update:'.$response->getKey()
                : 'create:'.$response->material_id,
        ];

        request()->session()->flash('literacy_submission_receipt', $receipt);
        request()->session()->flash('success', $message);

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'completed',
                'message' => $message,
                'redirect_url' => $redirectUrl,
                'edit_code' => $response->edit_code,
            ]);
        }

        return redirect()->to($redirectUrl, 303);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function noStoreView(string $view, array $data): Response
    {
        return response()
            ->view($view, $data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
