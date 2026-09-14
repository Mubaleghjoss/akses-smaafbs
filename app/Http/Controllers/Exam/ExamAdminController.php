<?php

namespace App\Http\Controllers\Exam;

use App\Filament\Pages\Assessment\OnlineExamPage;
use App\Http\Controllers\Controller;
use App\Models\Exam\AiSetting;
use App\Models\Exam\Answer;
use App\Models\Exam\Attempt;
use App\Models\Exam\Event;
use App\Models\Exam\QuestionSet;
use App\Models\Exam\Schedule;
use App\Models\Exam\StudentToken;
use App\Services\Exam\AiQuestionService;
use App\Services\Exam\ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class ExamAdminController extends Controller
{
    public function saveAi(Request $request): RedirectResponse
    {
        $this->authorizeExamModule();
        abort_unless($request->user()->hasFullAdminAccess() || $request->user()->hasRole('kurikulum'), 403);
        $data = $request->validate(['provider' => ['required', 'string', 'max:60'], 'base_url' => ['nullable', 'url', 'max:255'], 'model' => ['nullable', 'string', 'max:100'], 'api_key' => ['nullable', 'string', 'max:1000'], 'enabled' => ['nullable', 'boolean'], 'daily_limit' => ['required', 'integer', 'min:0', 'max:100000'], 'per_request_limit' => ['required', 'integer', 'min:1', 'max:100']]);
        $setting = AiSetting::query()->firstOrNew();
        if (blank($data['api_key'] ?? null)) unset($data['api_key']);
        $setting->fill($data + ['enabled' => false, 'updated_by' => $request->user()->id])->save();
        return back()->with('exam_notice', 'Pengaturan AI disimpan; API key tidak pernah ditampilkan kembali.');
    }

    public function testAi(AiQuestionService $service): RedirectResponse
    {
        $this->authorizeExamModule();
        abort_unless(auth()->user()->hasFullAdminAccess() || auth()->user()->hasRole('kurikulum'), 403);
        try { $message = $service->testConnection()['message']; } catch (RuntimeException $e) { $message = $e->getMessage(); }
        return back()->with('exam_notice', $message);
    }

    public function createSchedule(Request $request): RedirectResponse
    {
        $this->authorizeExamModule();
        $data = $request->validate(['question_set_id' => ['required', 'exists:exam_question_sets,id'], 'class_name' => ['required', 'string', 'max:100'], 'exam_code' => ['required', 'alpha_dash', 'max:40', 'unique:exam_schedules,exam_code'], 'supervisor_code' => ['required', 'string', 'min:4', 'max:30'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'duration_minutes' => ['required', 'integer', 'min:5', 'max:600']]);
        $set = QuestionSet::findOrFail($data['question_set_id']);
        abort_unless($set->isOwnedBy($request->user()), 403);
        Schedule::create([...collect($data)->except('supervisor_code')->all(), 'exam_code' => Str::upper($data['exam_code']), 'supervisor_code_hash' => Hash::make($data['supervisor_code']), 'created_by' => $request->user()->id, 'is_active' => true]);
        return back()->with('exam_notice', 'Jadwal dibuat. Tambahkan peserta dari data siswa/token secara terkontrol.');
    }

    public function addStudent(Request $request, Schedule $schedule): RedirectResponse
    {
        $this->authorizeExamModule();
        abort_unless($schedule->questionSet->isOwnedBy($request->user()), 403);
        $data = $request->validate(['student_name' => ['required', 'string', 'max:255'], 'nisn' => ['required', 'string', 'max:30'], 'birth_date' => ['required', 'date'], 'class_name' => ['required', 'string', 'max:100']]);
        do {
            $plainToken = StudentToken::generatePlainToken();
            $tokenHash = StudentToken::hashToken($plainToken);
        } while (StudentToken::query()->where('token_hash', $tokenHash)->exists());

        StudentToken::create($data + ['schedule_id' => $schedule->id, 'token_hash' => $tokenHash]);
        return back()->with('exam_notice', 'Peserta ditambahkan. Catat dan berikan kode berikut kepada pengawas/siswa; kode hanya ditampilkan sekali.')->with('exam_student_token', $plainToken);
    }

    public function resetNotStarted(Request $request, Attempt $attempt): RedirectResponse
    {
        $this->authorizeExamModule();
        $attempt->load('schedule.questionSet');
        abort_unless($attempt->schedule->questionSet->isOwnedBy($request->user()), 403);
        abort_unless($attempt->status === 'verified' && $attempt->started_at === null && ! $attempt->answers()->exists(), 422, 'Hanya sesi terverifikasi yang belum dimulai dan belum memiliki jawaban yang dapat direset.');

        $attempt->studentToken->update(['verified_at' => null]);
        Event::create(['attempt_id' => $attempt->id, 'type' => 'admin_reset_not_started', 'metadata' => ['reason' => $request->input('reason'), 'user_id' => $request->user()->id], 'occurred_at' => now()]);

        return back()->with('exam_notice', 'Sesi belum mulai direset; siswa harus verifikasi ulang menggunakan token yang sama.');
    }

    public function reopen(Request $request, Attempt $attempt): RedirectResponse
    {
        $this->authorizeExamModule();
        $attempt->load(['schedule.questionSet', 'studentToken']);
        abort_unless($attempt->schedule->questionSet->isOwnedBy($request->user()), 403);
        abort_unless(in_array($attempt->status, ['started', 'submitted'], true), 422, 'Hanya attempt yang sudah dimulai atau dikirim yang dapat dibuka ulang.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $plainToken = DB::transaction(function () use ($attempt, $data, $request): string {
            do {
                $plainToken = StudentToken::generatePlainToken();
                $tokenHash = StudentToken::hashToken($plainToken);
            } while (StudentToken::query()->where('token_hash', $tokenHash)->exists());

            $oldToken = $attempt->studentToken;
            $newToken = StudentToken::create([
                'schedule_id' => $attempt->schedule_id,
                'student_id' => null,
                'student_name' => $oldToken->student_name,
                'nisn' => $oldToken->nisn,
                'birth_date' => $oldToken->birth_date,
                'class_name' => $oldToken->class_name,
                'token_hash' => $tokenHash,
                'verified_at' => now(),
            ]);
            Attempt::create(['public_id' => (string) Str::uuid(), 'schedule_id' => $attempt->schedule_id, 'student_token_id' => $newToken->id, 'status' => 'verified']);
            Event::create(['attempt_id' => $attempt->id, 'type' => 'admin_reopen_attempt', 'metadata' => ['reason' => $data['reason'], 'user_id' => $request->user()->id, 'new_student_token_id' => $newToken->id], 'occurred_at' => now()]);

            return $plainToken;
        });

        return back()->with('exam_notice', 'Attempt lama tetap tersimpan. Token baru untuk buka ulang hanya ditampilkan sekali:')->with('exam_student_token', $plainToken);
    }

    public function grade(Request $request, Answer $answer, ScoringService $scoring): RedirectResponse
    {
        $this->authorizeExamModule();
        $attempt = $answer->attempt()->with('schedule.questionSet')->firstOrFail();
        abort_unless($attempt->schedule->questionSet->isOwnedBy($request->user()), 403);
        abort_unless($answer->question->type === 'essay', 422);
        $data = $request->validate(['manual_score' => ['required', 'numeric', 'min:0', 'max:'.$answer->question->weight], 'teacher_feedback' => ['nullable', 'string', 'max:2000']]);
        $answer->update($data);
        $scoring->refreshAttempt($attempt);
        return back()->with('exam_notice', 'Nilai essay disimpan. Nilai rapor tidak diubah.');
    }

    private function authorizeExamModule(): void
    {
        abort_unless(OnlineExamPage::canAccess(), 403);
    }
}
