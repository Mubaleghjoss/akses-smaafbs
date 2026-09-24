<?php

namespace App\Console\Commands;

use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\AssessmentExtracurricular;
use App\Models\Assessment\AssessmentExtracurricularParticipant;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoAssessmentExtracurricularAsts extends Command
{
    private const PREFIX = 'DEMO-EKSKUL-';

    protected $signature = 'assessment:demo-extracurricular-asts {--period= : ID atau kode periode ASTS} {--apply : Terapkan data demo}';
    protected $description = 'Siapkan data demo workflow nilai ekstrakurikuler ASTS secara idempoten';

    public function handle(): int
    {
        $period = $this->period();
        if (! $period) {
            $this->components->error('Periode ASTS tidak ditemukan.');
            return self::FAILURE;
        }

        $teachers = User::query()->whereNotNull('guru_tendik_id')->orderBy('id')->limit(3)->get();
        $students = $period->students()->where('is_active', true)->orderBy('rombel_name_snapshot')->orderBy('student_name_snapshot')->get()->unique('rombel_name_snapshot')->take(9)->values();
        if ($teachers->isEmpty() || $students->isEmpty()) {
            $this->components->error('Guru atau siswa snapshot belum tersedia untuk periode ini.');
            return self::FAILURE;
        }

        $this->components->info("Periode: {$period->name}; guru: {$teachers->count()}; sampel siswa: {$students->count()}");
        if (! $this->option('apply')) {
            $this->components->warn('Pratinjau saja. Tambahkan --apply untuk membuat data demo.');
            return self::SUCCESS;
        }

        $activities = ['Pramuka', 'Futsal', 'Tahfidz'];
        $scoreCount = 0;
        DB::transaction(function () use ($period, $teachers, $students, $activities, &$scoreCount): void {
            AssessmentExtracurricular::query()->where('assessment_period_id', $period->id)->where('code', 'like', self::PREFIX.'%')->delete();
            foreach ($activities as $index => $name) {
                $teacher = $teachers[$index % $teachers->count()];
                $activity = AssessmentExtracurricular::query()->create([
                    'assessment_period_id' => $period->id,
                    'name' => 'Demo '.$name,
                    'code' => self::PREFIX.strtoupper($name),
                    'description' => 'Data demo staging; aman diganti oleh command yang sama.',
                    'is_active' => true,
                    'created_by' => $teacher->id,
                ]);
                $activity->teachers()->attach($teacher->id);
                foreach ($students->slice($index * 2, 3) as $studentIndex => $student) {
                    $participant = AssessmentExtracurricularParticipant::query()->create([
                        'assessment_extracurricular_id' => $activity->id,
                        'assessment_period_student_id' => $student->id,
                        'assessment_period_rombel_id' => $student->assessment_period_rombel_id,
                        'source' => 'demo',
                    ]);
                    $participant->score()->create([
                        'predicate' => ['A', 'B', 'C'][($studentIndex + $index) % 3],
                        'status' => 'verified',
                        'description' => 'Nilai demo terverifikasi',
                        'submitted_at' => now(),
                        'submitted_by' => $teacher->id,
                        'verified_at' => now(),
                        'verified_by' => $teacher->id,
                        'updated_by' => $teacher->id,
                    ]);
                    $scoreCount++;
                }
            }
        });

        $sample = $students->first();
        $this->components->info(count($activities)." ekskul dan {$scoreCount} nilai verified dibuat.");
        $this->line("Sampel rapor: {$sample->student_name_snapshot} ({$sample->rombel_name_snapshot}).");

        return self::SUCCESS;
    }

    private function period(): ?AssessmentPeriod
    {
        $query = AssessmentPeriod::query()->where('type', AssessmentType::ASTS->value);
        if (filled($this->option('period'))) {
            $value = (string) $this->option('period');
            $query->where(fn ($builder) => $builder->where('id', is_numeric($value) ? (int) $value : 0)->orWhere('code', $value));
        } else {
            $query->orderByRaw("CASE WHEN code = 'DEMO-ASTS-2627-GANJIL' THEN 0 ELSE 1 END")->latest('id');
        }

        return $query->first();
    }
}
