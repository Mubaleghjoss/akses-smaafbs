<?php

namespace App\Http\Controllers;

use App\Exceptions\LiteracySubmissionQueueBusy;
use App\Jobs\AnalyzeLiteracyResponseSimilarity;
use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiQuestion;
use App\Models\PerpustakaanLiterasiResponse;
use App\Support\Perpustakaan\LiteracySubmissionQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PerpustakaanLiteracyProgramController extends Controller
{
    public function index(): View
    {
        $materials = PerpustakaanLiterasiMaterial::query()
            ->availableForPublic()
            ->withCount('questions')
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('library.literacy.index', [
            'title' => 'Literasi Numerasi',
            'materials' => $materials,
            'instructionsHtml' => PerpustakaanLiterasiMaterial::defaultInstructionsHtml(),
        ]);
    }

    public function show(string $slug): View
    {
        $material = $this->resolvePublicMaterial($slug);
        $material->loadMissing('questions');

        return view('library.literacy.show', [
            'title' => $material->title,
            'material' => $material,
            'students' => $this->studentOptions(),
        ]);
    }

    public function store(Request $request, string $slug, LiteracySubmissionQueue $submissionQueue): RedirectResponse
    {
        $material = $this->resolvePublicMaterial($slug);
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
                    return $this->successfulSubmissionRedirect($completedResponse, 'Jawaban sudah berhasil dikirim.');
                }
            }

            $validated = $request->validate(
                $this->answerValidationRules($questions) + [
                    'student_id' => ['required', 'integer', 'exists:data_siswa,id'],
                    'student_verification' => ['nullable', 'string', 'max:80'],
                    'submission_request_id' => ['nullable', 'uuid'],
                    'submission_ticket' => ['nullable', 'string', 'max:64'],
                ] + $this->integrityValidationRules(),
                [],
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
            $submissionQueue->complete($ticket, $response);
            $ticketCompleted = true;

            return $this->successfulSubmissionRedirect($response, 'Jawaban berhasil dikirim. Simpan kode unik untuk mengedit jawaban.');
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

    public function edit(string $code): View
    {
        $response = $this->resolveResponseByEditCode($code);
        $response->loadMissing(['material.questions', 'answers']);

        return view('library.literacy.edit', [
            'title' => 'Edit Jawaban Literasi Numerasi',
            'response' => $response,
            'material' => $response->material,
            'answerMap' => $response->answers->keyBy('question_id'),
        ]);
    }

    public function update(
        Request $request,
        string $code,
        LiteracySubmissionQueue $submissionQueue
    ): RedirectResponse {
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
                ] + $this->integrityValidationRules(),
                [],
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
        } finally {
            if (! $ticketCompleted) {
                $submissionQueue->release($ticket);
            }
        }
    }

    public function requestStoreTicket(
        Request $request,
        string $slug,
        LiteracySubmissionQueue $submissionQueue,
    ): JsonResponse {
        $material = $this->resolvePublicMaterial($slug);
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

    public function recordIntegrity(Request $request, string $code)
    {
        $response = $this->resolveResponseByEditCode($code);
        $validated = $request->validate($this->integrityValidationRules());

        $response->addIntegrityCounts($this->integrityCountsFromValidated($validated));

        return response()->noContent();
    }

    protected function resolvePublicMaterial(string $slug): PerpustakaanLiterasiMaterial
    {
        return PerpustakaanLiterasiMaterial::query()
            ->availableForPublic()
            ->where('slug', $slug)
            ->firstOrFail();
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

        foreach ($questions as $question) {
            $answerText = trim((string) ($answers[$question->getKey()] ?? ''));
            $existingAnswer = $response->answers()
                ->where('question_id', $question->getKey())
                ->first();
            $payload = [
                'answer_text' => $answerText,
                'character_count' => mb_strlen($answerText),
            ];
            $answerKeyGradingPayload = $gradingColumnsAvailable
                ? $this->answerKeyGradingPayload($question, $answerText, $existingAnswer)
                : null;

            if ($answerKeyGradingPayload !== null) {
                $payload = array_merge($payload, $answerKeyGradingPayload);
            } elseif ($gradingColumnsAvailable
                && $existingAnswer
                && (string) $existingAnswer->answer_text !== $answerText) {
                $payload = array_merge($payload, [
                    'is_correct' => null,
                    'graded_by' => null,
                    'graded_at' => null,
                    'grading_note' => null,
                ]);
            }

            $response->answers()->updateOrCreate(['question_id' => $question->getKey()], $payload);
        }
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

    protected function queueWaitResponse(LiteracySubmissionQueueBusy $exception): RedirectResponse
    {
        $payload = $exception->queuePayload;
        $position = max(1, (int) ($payload['position'] ?? 1));
        $seconds = max(1, (int) ($payload['estimated_wait_seconds'] ?? 5));

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
    ): RedirectResponse {
        return redirect()
            ->route('library.literacy.edit', $response->shortEditCode())
            ->with('success', $message)
            ->with('edit_code', $response->edit_code);
    }
}
