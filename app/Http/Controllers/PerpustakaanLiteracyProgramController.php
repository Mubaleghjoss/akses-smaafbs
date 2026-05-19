<?php

namespace App\Http\Controllers;

use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiQuestion;
use App\Models\PerpustakaanLiterasiResponse;
use App\Support\Perpustakaan\LiterasiSimilarityAnalyzer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'title' => 'Literacy Habituation Program',
            'materials' => $materials,
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

    public function store(Request $request, string $slug, LiterasiSimilarityAnalyzer $analyzer): RedirectResponse
    {
        $material = $this->resolvePublicMaterial($slug);
        $questions = $material->questions()->get();

        if ($questions->isEmpty()) {
            return back()
                ->withErrors(['answers' => 'Materi ini belum memiliki pertanyaan.'])
                ->withInput();
        }

        $validated = $request->validate(
            $this->answerValidationRules($questions) + [
                'student_id' => ['required', 'integer', 'exists:data_siswa,id'],
            ],
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

        $existingResponse = PerpustakaanLiterasiResponse::query()
            ->where('material_id', $material->getKey())
            ->where('data_siswa_id', $student->getKey())
            ->first();

        if ($existingResponse) {
            return back()
                ->withErrors(['student_id' => 'Siswa ini sudah mengirim jawaban. Gunakan kode unik untuk mengedit jawaban.'])
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

            return $response;
        });

        $analyzer->analyzeResponse($response->fresh(['answers']));

        return redirect()
            ->route('library.literacy.edit', $response->shortEditCode())
            ->with('success', 'Jawaban berhasil dikirim. Simpan kode unik untuk mengedit jawaban.')
            ->with('edit_code', $response->edit_code);
    }

    public function editLookup(Request $request): RedirectResponse
    {
        $code = Str::upper(trim((string) $request->query('code', '')));

        if ($code === '') {
            return redirect()
                ->route('library.literacy.index')
                ->withErrors(['code' => 'Masukkan kode unik jawaban.']);
        }

        return redirect()->route('library.literacy.edit', $code);
    }

    public function edit(string $code): View
    {
        $response = $this->resolveResponseByEditCode($code);
        $response->loadMissing(['material.questions', 'answers']);

        return view('library.literacy.edit', [
            'title' => 'Edit Jawaban Literasi',
            'response' => $response,
            'material' => $response->material,
            'answerMap' => $response->answers->keyBy('question_id'),
        ]);
    }

    public function update(
        Request $request,
        string $code,
        LiterasiSimilarityAnalyzer $analyzer
    ): RedirectResponse {
        $response = $this->resolveResponseByEditCode($code);
        $response->loadMissing('material.questions');
        $questions = $response->material->questions;

        $validated = $request->validate(
            $this->answerValidationRules($questions),
            [],
            $this->answerValidationAttributes($questions),
        );

        DB::transaction(function () use ($response, $questions, $validated): void {
            $this->syncAnswers($response, $questions, $validated['answers'] ?? []);
            $response->forceFill([
                'last_edited_at' => now(),
            ])->save();
        });

        $analyzer->analyzeResponse($response->fresh(['answers']));

        return redirect()
            ->route('library.literacy.edit', $response->shortEditCode())
            ->with('success', 'Jawaban berhasil diperbarui.')
            ->with('edit_code', $response->edit_code);
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
        $normalized = Str::upper(trim($code));

        $query = PerpustakaanLiterasiResponse::query();

        if (str_starts_with($normalized, 'LHP-')) {
            $query->where('edit_code', $normalized);
        } elseif (preg_match('/^[A-Z0-9]{6}$/', $normalized)) {
            $query->where('edit_code', 'like', '%-'.$normalized);
        } else {
            abort(404);
        }

        return $query->firstOrFail();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PerpustakaanLiterasiQuestion>  $questions
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
     * @param  \Illuminate\Support\Collection<int, PerpustakaanLiterasiQuestion>  $questions
     * @return array<string, string>
     */
    protected function answerValidationAttributes($questions): array
    {
        return $questions->mapWithKeys(fn (PerpustakaanLiterasiQuestion $question): array => [
            'answers.'.$question->getKey() => 'jawaban untuk pertanyaan '.$question->sort_order,
        ])->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PerpustakaanLiterasiQuestion>  $questions
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

            if ($gradingColumnsAvailable
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
     * @return array<int, array{id:int,label:string,name:string,class:string}>
     */
    protected function studentOptions(): array
    {
        if (! Schema::hasTable('data_siswa')) {
            return [];
        }

        return DataSiswa::query()
            ->select(['id', 'nama', 'rombel_saat_ini', 'status'])
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
                ];
            })
            ->values()
            ->all();
    }
}
