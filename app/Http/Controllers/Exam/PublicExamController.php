<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\Exam\Answer;
use App\Models\Exam\Attempt;
use App\Models\Exam\Event;
use App\Models\Exam\Schedule;
use App\Models\Exam\StudentToken;
use App\Services\Exam\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicExamController extends Controller
{
    public function index(): View
    {
        $schedules = Schedule::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->with(['tokens' => fn ($query) => $query->select(['id', 'schedule_id', 'student_name', 'class_name'])])
            ->orderBy('class_name')
            ->get(['id', 'class_name']);

        $studentsByClass = $schedules
            ->flatMap(fn (Schedule $schedule) => $schedule->tokens
                ->where('class_name', $schedule->class_name)
                ->map(fn (StudentToken $token) => ['class_name' => $schedule->class_name, 'student_name' => $token->student_name]))
            ->groupBy('class_name')
            ->map(fn ($students) => $students->pluck('student_name')->unique()->sort()->values())
            ->all();

        return view('exam.verify', [
            'classes' => $schedules->pluck('class_name')->unique()->values(),
            'studentsByClass' => $studentsByClass,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $activeClasses = Schedule::query()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->pluck('class_name')
            ->unique()
            ->all();
        $data = $request->validate(
            ['class_name' => ['required', 'string', Rule::in($activeClasses)], 'student_name' => ['required', 'string'], 'nisn' => ['required', 'string'], 'birth_date' => ['required', 'date'], 'exam_code' => ['required', 'string', 'regex:/^[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/']],
            ['exam_code.regex' => 'Gunakan Kode Siswa/Token Ujian dari pengawas, contoh ABCD-1234. Jangan isi kode jadwal ujian.']
        );
        $token = StudentToken::query()
            ->where('token_hash', StudentToken::hashToken($data['exam_code']))
            ->where('class_name', $data['class_name'])
            ->where('nisn', $data['nisn'])
            ->whereDate('birth_date', $data['birth_date'])
            ->whereRaw('LOWER(student_name) = ?', [Str::lower(trim($data['student_name']))])
            ->whereHas('schedule', fn ($query) => $query->where('class_name', $data['class_name'])->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now()))
            ->with('schedule')
            ->first();
        if (! $token || ! $token->matchesToken($data['exam_code'])) return back()->withErrors(['exam_code' => 'Kode Siswa/Token Ujian tidak valid, atau identitas dan jadwal ujian tidak sesuai.'])->withInput();
        $schedule = $token->schedule;
        $token->update(['verified_at' => now()]);
        $attempt = Attempt::firstOrCreate(['schedule_id' => $schedule->id, 'student_token_id' => $token->id], ['public_id' => (string) Str::uuid(), 'status' => 'verified']);
        $request->session()->put('exam_attempt_id', $attempt->id);
        return redirect()->route('exam.work', $attempt->public_id);
    }

    public function work(Request $request, string $publicId): View
    {
        $attempt = Attempt::with(['schedule.questionSet', 'studentToken'])
            ->where('public_id', $publicId)
            ->first();

        if ($attempt === null) {
            return view('exam.status', ['status' => 'inactive', 'attempt' => null]);
        }

        if ($attempt->status === 'submitted') {
            return view('exam.status', ['attempt' => $attempt, 'status' => 'submitted']);
        }

        if ((int) $request->session()->get('exam_attempt_id') !== $attempt->id) {
            return view('exam.status', ['attempt' => $attempt, 'status' => 'inactive']);
        }

        $attempt->load(['schedule.questionSet.questions', 'studentToken', 'answers']);
        return view('exam.work', compact('attempt'));
    }

    public function start(Request $request, string $publicId): JsonResponse
    {
        $attempt = $this->attempt($request, $publicId);
        abort_if($attempt->status === 'submitted', 409);

        if (! $attempt->started_at) {
            $attempt->update(['started_at' => now(), 'status' => 'started']);
        }

        return response()->json(['started' => true]);
    }

    public function saveAnswer(Request $request, string $publicId, ScoringService $scoring): JsonResponse
    {
        $attempt = $this->attempt($request, $publicId);
        abort_if($attempt->status === 'submitted', 409);
        $data = $request->validate(['question_id' => ['required', 'integer'], 'answer' => ['nullable']]);
        $question = $attempt->schedule->questionSet->questions()->findOrFail($data['question_id']);
        $answer = Answer::updateOrCreate(['attempt_id' => $attempt->id, 'question_id' => $question->id], ['answer' => is_array($data['answer']) ? $data['answer'] : [$data['answer']], 'saved_at' => now()]);
        $answer->setRelation('question', $question); $scoring->scoreAnswer($answer);
        return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
    }

    public function event(Request $request, string $publicId): JsonResponse
    {
        $attempt = $this->attempt($request, $publicId);
        $data = $request->validate(['type' => ['required', 'in:fullscreen_exit,visibility_hidden,window_blur,beforeunload,offline,online,emergency_export'], 'metadata' => ['nullable', 'array']]);
        Event::create(['attempt_id' => $attempt->id, 'type' => $data['type'], 'metadata' => $data['metadata'] ?? null, 'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')), 'occurred_at' => now()]);
        if (in_array($data['type'], ['fullscreen_exit', 'visibility_hidden', 'window_blur'], true)) $attempt->increment('exit_count');
        if ($data['type'] === 'offline') $attempt->increment('offline_count');
        return response()->json(['logged' => true]);
    }

    public function unlock(Request $request, string $publicId): JsonResponse
    {
        $attempt = $this->attempt($request, $publicId);
        $request->validate(['supervisor_code' => ['required', 'string']]);
        abort_unless($attempt->schedule->supervisorCodeMatches($request->string('supervisor_code')), 422, 'Kode pengawas salah.');
        return response()->json(['unlocked' => true]);
    }

    public function submit(Request $request, string $publicId, ScoringService $scoring): RedirectResponse
    {
        $attempt = $this->attempt($request, $publicId);
        DB::transaction(function () use ($attempt, $scoring): void { $attempt->update(['status' => 'submitted', 'submitted_at' => now()]); $scoring->refreshAttempt($attempt); });
        $request->session()->forget('exam_attempt_id');
        return redirect()->route('exam.index')->with('success', 'Jawaban berhasil dikirim. Nilai essay menunggu koreksi guru.');
    }

    public function emergency(Request $request, string $publicId): Response
    {
        $attempt = $this->attempt($request, $publicId)->load(['studentToken', 'schedule.questionSet.questions', 'answers', 'events']);
        $exportedAt = now();
        $rows = [
            ['IDENTITAS'],
            ['Field', 'Nilai'],
            ['Nama', $attempt->studentToken->student_name],
            ['Kelas', $attempt->studentToken->class_name],
            ['NISN', $attempt->studentToken->nisn],
            ['Ujian', $attempt->schedule->questionSet->title],
            ['Mapel', $attempt->schedule->questionSet->subject],
            ['Kode jadwal', $attempt->schedule->exam_code],
            ['Status attempt', $attempt->status],
            ['Waktu mulai', $attempt->started_at?->toIso8601String()],
            ['Waktu submit', $attempt->submitted_at?->toIso8601String()],
            ['Waktu ekspor', $exportedAt->toIso8601String()],
            ['Device/browser', $request->userAgent()],
            ['Public ID', $attempt->public_id],
            [],
            ['JAWABAN SISWA LENGKAP'],
            ['Nomor', 'Tipe soal', 'Bobot', 'Soal', 'Pilihan jawaban', 'Jawaban siswa', 'Status tersimpan', 'Skor auto', 'Skor manual', 'Feedback guru'],
        ];

        foreach ($attempt->schedule->questionSet->questions as $i => $question) {
            $answer = $attempt->answers->firstWhere('question_id', $question->id);
            $rows[] = [
                $i + 1,
                $question->type,
                $question->weight,
                trim(strip_tags($question->prompt)),
                implode(' | ', $question->options ?? []),
                implode(' | ', $answer?->answer ?? []),
                $answer?->saved_at?->toIso8601String() ?? 'Belum tersimpan',
                $answer?->auto_score,
                $answer?->manual_score,
                $answer?->teacher_feedback,
            ];
        }

        $rows[] = [];
        $rows[] = ['LOG KEAMANAN/EVENT'];
        $rows[] = ['Tipe', 'Waktu', 'Metadata ringkas'];
        foreach ($attempt->events as $event) {
            $rows[] = [$event->type, $event->occurred_at?->toIso8601String(), json_encode($event->metadata ?? [], JSON_UNESCAPED_SLASHES)];
        }
        $rows[] = [];
        $rows[] = ['VALIDASI'];
        $rows[] = ['Catatan', 'File ini tidak berisi kunci jawaban, pembahasan, atau rubrik.'];

        $csv = collect($rows)->map(function (array $row): string {
            $stream = fopen('php://temp', 'r+');
            fputcsv($stream, $row);
            rewind($stream);

            return rtrim((string) stream_get_contents($stream));
        })->implode("\r\n");
        $hash = hash('sha256', $csv);
        $csv .= "\r\nHash integritas,{$hash}";
        DB::table('exam_emergency_exports')->insert(['attempt_id' => $attempt->id, 'content_hash' => $hash, 'exported_at' => $exportedAt, 'created_at' => $exportedAt, 'updated_at' => $exportedAt]);

        return response("\xEF\xBB\xBF".$csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="jawaban-darurat-'.$attempt->public_id.'.csv"']);
    }

    private function attempt(Request $request, string $publicId): Attempt
    {
        $attempt = Attempt::with('schedule.questionSet')->where('public_id', $publicId)->firstOrFail();
        abort_unless((int) $request->session()->get('exam_attempt_id') === $attempt->id, 403);
        return $attempt;
    }
}
