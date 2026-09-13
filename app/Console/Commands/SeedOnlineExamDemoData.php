<?php

namespace App\Console\Commands;

use App\Models\Exam\Answer;
use App\Models\Exam\Attempt;
use App\Models\Exam\Event;
use App\Models\Exam\Question;
use App\Models\Exam\QuestionSet;
use App\Models\Exam\Schedule;
use App\Models\Exam\StudentToken;
use App\Models\User;
use App\Services\Exam\ScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeedOnlineExamDemoData extends Command
{
    protected $signature = 'exam:demo-data {--apply : Simpan data demo; tanpa opsi ini hanya pratinjau}';

    protected $description = 'Membuat data demo MVP Ujian Online yang idempotent untuk staging/non-production';

    private const EXAM_CODE = 'DEMO-UJIAN-MVP-2026';
    private const TITLE = '[DEMO-UJIAN-MVP] BAHASA INDONESIA: Teks dan Penalaran';
    private const MARKER = '[DEMO-UJIAN-MVP]';

    public function handle(ScoringService $scoring): int
    {
        if (app()->environment('production')) {
            $this->error('DITOLAK: data demo ujian tidak boleh dibuat di production.');

            return self::FAILURE;
        }

        if (! $this->tablesReady()) {
            $this->error('Tabel ujian online, users, atau data_siswa belum tersedia.');

            return self::FAILURE;
        }

        $students = $this->students();
        if ($students->count() < 3) {
            $this->error('Minimal tiga siswa aktif diperlukan untuk data demo ujian.');

            return self::FAILURE;
        }

        $class = (string) $students->first()->rombel_saat_ini;
        $this->components->twoColumnDetail('Kelas terpilih', $class);
        $this->components->twoColumnDetail('Siswa peserta', (string) $students->count());
        $this->components->twoColumnDetail('Kode jadwal', self::EXAM_CODE);
        if (! $this->option('apply')) {
            $this->warn('Pratinjau selesai. Tambahkan --apply untuk menyimpan data demo.');

            return self::SUCCESS;
        }

        $teacher = User::query()->orderBy('id')->first();
        if (! $teacher) {
            $this->error('Tidak ada akun pengguna yang dapat menjadi pemilik paket soal demo.');

            return self::FAILURE;
        }

        [$schedule, $tokens] = DB::transaction(function () use ($teacher, $students, $class, $scoring): array {
            // Only the named demo schedule and its dependent rows are replaced.
            Schedule::query()->where('exam_code', self::EXAM_CODE)->delete();
            QuestionSet::query()
                ->where('title', 'like', self::MARKER.'%')
                ->where('title', '!=', self::TITLE)
                ->delete();

            $set = QuestionSet::query()->updateOrCreate(
                ['title' => self::TITLE],
                ['teacher_id' => $teacher->id, 'subject' => 'BAHASA INDONESIA', 'exam_type' => 'ASTS', 'academic_year' => '2026/2027', 'semester' => 'Ganjil', 'instructions' => self::MARKER.' Paket contoh untuk alur lengkap ujian online.', 'status' => 'published'],
            );
            $set->questions()->delete();
            $questions = $this->questions($set);
            $schedule = Schedule::query()->create([
                'question_set_id' => $set->id,
                'created_by' => $teacher->id,
                'class_name' => $class,
                'exam_code' => self::EXAM_CODE,
                'supervisor_code_hash' => bcrypt('DEMO-AWAS'),
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(30),
                'duration_minutes' => 90,
                'is_active' => true,
            ]);

            $tokens = collect();
            foreach ($students as $index => $student) {
                $plain = sprintf('DM%02d-%04d', $index + 1, ((int) $student->id) % 10000);
                $token = StudentToken::query()->create([
                    'schedule_id' => $schedule->id,
                    'student_id' => $student->id,
                    'student_name' => $student->nama,
                    'nisn' => $student->nisn ?: 'DEMO-'.$student->id,
                    'birth_date' => $student->tanggal_lahir ?: '2010-01-01',
                    'class_name' => $class,
                    'token_hash' => StudentToken::hashToken($plain),
                    'verified_at' => $index < 2 ? now()->subMinutes(30 - $index) : null,
                ]);
                $tokens->push(compact('token', 'plain', 'student'));
            }

            $this->attempt($schedule, $tokens[0]['token'], $questions, 'submitted', $scoring);
            $this->attempt($schedule, $tokens[1]['token'], $questions, 'started', $scoring);
            $attention = $this->attempt($schedule, $tokens[2]['token'], $questions, 'verified', $scoring);
            $attention->update(['exit_count' => 1, 'offline_count' => 1]);
            foreach (['fullscreen_exit', 'offline'] as $type) {
                Event::query()->create(['attempt_id' => $attention->id, 'type' => $type, 'metadata' => ['demo' => true], 'ip_hash' => hash('sha256', 'demo-event-'.$type), 'occurred_at' => now()->subMinutes(5)]);
            }

            return [$schedule, $tokens];
        });

        $this->info('Data demo Ujian Online selesai dibuat/diperbarui. Nilai final ditampilkan sebagai "Murni Ujian" pada halaman admin dan sebagai badge pada pratinjau/PDF rapor ASTS yang cocok; nilai rapor lama tidak diubah.');
        $this->components->twoColumnDetail('Admin', '/admin/penilaian/ujian-online');
        $this->components->twoColumnDetail('Ujian siswa', '/ujian');
        foreach ($tokens->take(3) as $item) {
            $this->components->twoColumnDetail('Token demo '.$item['student']->nama, $item['plain'].' | NISN '.($item['student']->nisn ?: 'DEMO-'.$item['student']->id).' | lahir '.($item['student']->tanggal_lahir ?: '2010-01-01'));
        }
        $this->warn('Token demo di atas deterministik untuk staging; hanya hash token yang disimpan di database.');

        return self::SUCCESS;
    }

    private function tablesReady(): bool
    {
        return collect(['users', 'data_siswa', 'exam_question_sets', 'exam_questions', 'exam_schedules', 'exam_student_tokens', 'exam_attempts', 'exam_answers', 'exam_events'])->every(fn (string $table) => Schema::hasTable($table));
    }

    private function students()
    {
        $base = DB::table('data_siswa')->select(['id', 'nama', 'nisn', 'tanggal_lahir', 'rombel_saat_ini'])->where('status', 'aktif')->whereNotNull('rombel_saat_ini');
        $preferred = (clone $base)->where('rombel_saat_ini', 'X 1')->orderBy('id')->limit(3)->get();
        if ($preferred->count() >= 3) return $preferred;

        $class = (clone $base)->select('rombel_saat_ini', DB::raw('count(*) as total'))->groupBy('rombel_saat_ini')->orderByDesc('total')->value('rombel_saat_ini');
        return (clone $base)->where('rombel_saat_ini', $class)->orderBy('id')->limit(3)->get();
    }

    private function questions(QuestionSet $set): array
    {
        $rows = [
            ['multiple_choice', 'Hasil dari 12 + 8 adalah ...', ['20', '18', '22', '24'], ['20'], 2, 'PG: jumlahkan dua bilangan bulat.'],
            ['multiple_response', 'Pilih semua bilangan genap.', ['2', '3', '4', '5'], ['2', '4'], 3, 'PG kompleks: semua pilihan benar harus dipilih.'],
            ['true_false', 'Pernyataan: 9 adalah bilangan ganjil.', ['Benar', 'Salah'], ['Benar'], 2, 'Benar karena 9 tidak habis dibagi 2.'],
            ['essay', 'Jelaskan dengan singkat langkahmu menyelesaikan 3 x (4 + 2).', null, ['18'], 5, null],
        ];
        return collect($rows)->map(function (array $row, int $position) use ($set) {
            return Question::query()->create(['question_set_id' => $set->id, 'type' => $row[0], 'prompt' => $row[1], 'options' => $row[2], 'answer_key' => $row[3], 'weight' => $row[4], 'explanation' => $row[5], 'rubric' => $row[0] === 'essay' ? 'Skor 5: langkah dan hasil benar; skor 3: langkah benar namun hasil kurang tepat; skor 1: usaha relevan.' : null, 'cognitive_level' => 'MOTS', 'position' => $position + 1]);
        })->all();
    }

    private function attempt(Schedule $schedule, StudentToken $token, array $questions, string $status, ScoringService $scoring): Attempt
    {
        $attempt = Attempt::query()->create(['public_id' => (string) Str::uuid(), 'schedule_id' => $schedule->id, 'student_token_id' => $token->id, 'status' => $status, 'started_at' => $status === 'verified' ? null : now()->subMinutes(20), 'submitted_at' => $status === 'submitted' ? now()->subMinutes(5) : null]);
        foreach ($questions as $index => $question) {
            if ($status === 'verified') break;
            $answer = $index === 0 ? ['20'] : ($index === 1 ? ($status === 'submitted' ? ['2', '4'] : ['2']) : ($index === 2 ? ['Salah'] : ['Saya menjumlahkan dahulu 4 + 2 = 6, lalu 3 x 6 = 18.']));
            $record = Answer::query()->create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'answer' => $answer, 'saved_at' => now()->subMinutes(10)]);
            $record->setRelation('question', $question);
            $scoring->scoreAnswer($record);
            if ($question->type === 'essay' && $status === 'submitted') $record->update(['manual_score' => 5, 'teacher_feedback' => 'Penalaran dan hasil sudah tepat.']);
        }
        $scoring->refreshAttempt($attempt);
        return $attempt;
    }
}
