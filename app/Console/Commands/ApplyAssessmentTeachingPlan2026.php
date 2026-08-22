<?php

namespace App\Console\Commands;

use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\SubjectCategory;
use App\Models\Assessment\TeachingAssignment;
use App\Models\GuruTendik;
use App\Models\Rombel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ApplyAssessmentTeachingPlan2026 extends Command
{
    protected $signature = 'assessment:teaching-plan-2026 {--apply : Terapkan setelah preview bersih}';

    protected $description = 'Preview atau terapkan plotting guru-mapel 2026/2027 Ganjil dari matriks resmi sekolah.';

    public function handle(): int
    {
        $semester = Semester::query()->where('code', '2026-2027-GANJIL')->first();
        if (! $semester) {
            $this->error('Semester 2026-2027-GANJIL tidak ditemukan.');

            return self::FAILURE;
        }

        try {
            $context = $this->resolveContext($semester);
            $preview = $this->buildPreview($semester, $context);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Mapel', 'Guru cocok', 'Kelas', 'Kategori', 'Aksi'],
            $preview['rows'],
        );
        $this->newLine();
        $this->line("19 mapel; {$preview['desired']} plotting aktif; {$preview['disable']} plotting lama akan dinonaktifkan.");

        if (! $this->option('apply')) {
            $this->info('Mode preview: database belum diubah. Jalankan ulang dengan --apply setelah semua hasil cocok.');

            return self::SUCCESS;
        }

        $summary = DB::transaction(fn (): array => $this->applyPlan($semester, $context), 3);
        $this->info("Selesai: {$summary['subjects_created']} mapel dibuat, {$summary['subjects_updated']} diperbarui, {$summary['assignments_created']} plotting dibuat, {$summary['assignments_updated']} diperbarui, {$summary['assignments_disabled']} dinonaktifkan.");
        $this->info('Tidak ada period assignment, nilai, snapshot, atau PDF lama yang diubah.');

        return self::SUCCESS;
    }

    /** @return array{teachers:array<string,GuruTendik>,rombels:array<string,Rombel>,categories:array<string,SubjectCategory>} */
    private function resolveContext(Semester $semester): array
    {
        $categories = SubjectCategory::query()->whereIn('code', ['WAJIB', 'PILIHAN'])->get()->keyBy('code');
        if ($categories->count() !== 2) {
            throw new RuntimeException('Kategori WAJIB dan PILIHAN belum lengkap. Jalankan migration terlebih dahulu.');
        }

        $rombels = Rombel::query()->whereIn('nama', $this->classNames())->get()->keyBy('nama');
        $missingClasses = array_values(array_diff($this->classNames(), $rombels->keys()->all()));
        if ($missingClasses !== []) {
            throw new RuntimeException('Rombel tidak ditemukan: '.implode(', ', $missingClasses).'.');
        }

        $linkedTeachers = GuruTendik::query()->with('userAccount')->whereHas('userAccount')->get();
        $teachers = [];
        foreach (array_keys($this->teacherPlan()) as $expectedName) {
            $matches = $this->teacherMatches($expectedName, $linkedTeachers);
            if ($matches->count() !== 1) {
                $names = $matches->pluck('nama')->implode(', ');
                throw new RuntimeException("Pencocokan guru '{$expectedName}' harus tepat satu akun tertaut; ditemukan {$matches->count()}".($names ? ": {$names}" : '').'.');
            }
            $teachers[$expectedName] = $matches->first();
        }

        return ['teachers' => $teachers, 'rombels' => $rombels->all(), 'categories' => $categories->all()];
    }

    /** @return \Illuminate\Support\Collection<int,GuruTendik> */
    private function teacherMatches(string $expectedName, $teachers)
    {
        $needle = $this->normalizeTeacherName($expectedName);
        $exact = $teachers->filter(fn (GuruTendik $teacher): bool => $this->normalizeTeacherName($teacher->nama) === $needle);
        if ($exact->isNotEmpty()) {
            return $exact->values();
        }

        $scored = $teachers->map(function (GuruTendik $teacher) use ($needle): array {
            $candidate = $this->normalizeTeacherName($teacher->nama);
            $distance = levenshtein($needle, $candidate);
            $similarity = 1 - ($distance / max(strlen($needle), strlen($candidate), 1));

            return ['teacher' => $teacher, 'similarity' => $similarity];
        })->sortByDesc('similarity')->values();
        $best = $scored->first();
        if (! $best || $best['similarity'] < 0.82) {
            return collect();
        }

        return $scored
            ->filter(fn (array $row): bool => abs($row['similarity'] - $best['similarity']) < 0.02)
            ->pluck('teacher')
            ->values();
    }

    private function normalizeTeacherName(string $name): string
    {
        $name = Str::lower(Str::ascii($name));
        $name = preg_replace('/\b(s\.?\s*pdi|s\.?\s*pd|s\.?\s*t|s\.?\s*h|m\.?\s*m|s\.?\s*si|s\.?\s*kom|m\.?\s*pd|dra|drs)\b/u', ' ', $name) ?? $name;
        $name = preg_replace('/\b[a-z]\b/u', ' ', $name) ?? $name;

        return preg_replace('/[^a-z0-9]+/', '', $name) ?? '';
    }

    /** @param array<string,mixed> $context @return array{rows:array<int,array<int,string>>,desired:int,disable:int} */
    private function buildPreview(Semester $semester, array $context): array
    {
        $rows = [];
        $desiredKeys = [];
        foreach ($this->teacherPlan() as $expectedTeacher => $subjects) {
            $teacher = $context['teachers'][$expectedTeacher];
            foreach ($subjects as $subjectCode => $categories) {
                foreach ($categories as $categoryCode => $classes) {
                    foreach ($classes as $className) {
                        $subject = Subject::query()->where('code', $subjectCode)->first();
                        $rombel = $context['rombels'][$className];
                        $record = $subject ? TeachingAssignment::query()->where([
                            'assessment_semester_id' => $semester->getKey(),
                            'assessment_subject_id' => $subject->getKey(),
                            'teacher_id' => $teacher->getKey(),
                            'rombel_id' => $rombel->getKey(),
                        ])->first() : null;
                        $action = ! $record ? 'BUAT' : (
                            $record->is_active && (int) $record->assessment_subject_category_id === (int) $context['categories'][$categoryCode]->getKey()
                                ? 'TETAP' : 'PERBARUI'
                        );
                        $key = $subjectCode.'|'.$className;
                        if (isset($desiredKeys[$key])) {
                            throw new RuntimeException("Matriks sumber memiliki guru ganda untuk {$this->subjects()[$subjectCode]} kelas {$className}. Proses dibatalkan.");
                        }
                        $rows[] = [$this->subjects()[$subjectCode], $teacher->nama, $className, $context['categories'][$categoryCode]->name, $action];
                        $desiredKeys[$key] = (int) $teacher->getKey();
                    }
                }
            }
        }

        $subjectIds = Subject::query()->whereIn('code', array_keys($this->subjects()))->pluck('id', 'code');
        $rombelNames = collect($context['rombels'])->mapWithKeys(fn (Rombel $rombel): array => [$rombel->getKey() => $rombel->nama]);
        $desiredCount = count($rows);
        $toDisable = TeachingAssignment::query()
            ->with(['subject:id,code,name', 'teacher:id,nama', 'rombel:id,nama', 'category:id,name'])
            ->where('assessment_semester_id', $semester->getKey())
            ->whereIn('assessment_subject_id', $subjectIds->values())
            ->whereIn('rombel_id', $rombelNames->keys())
            ->where('is_active', true)
            ->get()
            ->filter(function (TeachingAssignment $assignment) use ($subjectIds, $rombelNames, $desiredKeys): bool {
                $code = $subjectIds->search($assignment->assessment_subject_id, true);
                $class = $rombelNames->get($assignment->rombel_id);

                $key = $code.'|'.$class;

                return ! isset($desiredKeys[$key]) || (int) $assignment->teacher_id !== (int) $desiredKeys[$key];
            });

        foreach ($toDisable as $assignment) {
            $rows[] = [
                $assignment->subject?->name ?? $assignment->subject_name_snapshot,
                $assignment->teacher?->nama ?? $assignment->teacher_name_snapshot,
                $assignment->rombel?->nama ?? $assignment->rombel_name_snapshot,
                $assignment->category?->name ?? 'Tanpa kategori',
                'NONAKTIFKAN',
            ];
        }

        return ['rows' => $rows, 'desired' => $desiredCount, 'disable' => $toDisable->count()];
    }

    /** @param array<string,mixed> $context @return array<string,int> */
    private function applyPlan(Semester $semester, array $context): array
    {
        $summary = [
            'subjects_created' => 0,
            'subjects_updated' => 0,
            'assignments_created' => 0,
            'assignments_updated' => 0,
            'assignments_disabled' => 0,
        ];
        $subjects = [];
        foreach ($this->subjects() as $code => $name) {
            $subject = Subject::query()->where('code', $code)->lockForUpdate()->first();
            $values = [
                'name' => $name,
                'description' => 'Master plotting resmi 2026/2027 Ganjil.',
                'is_active' => true,
                'sort_order' => (array_search($code, array_keys($this->subjects()), true) + 1) * 10,
            ];
            if (! $subject) {
                $subject = Subject::query()->create([
                    'code' => $code,
                    ...$values,
                    'report_group_code' => 'PILIHAN',
                    'report_group_name' => 'Mapel Pilihan',
                    'report_group_sort_order' => 20,
                ]);
                $summary['subjects_created']++;
            } elseif ($subject->only(array_keys($values)) !== $values) {
                $subject->forceFill($values)->save();
                $summary['subjects_updated']++;
            }
            $subjects[$code] = $subject;
        }

        $desired = [];
        foreach ($this->teacherPlan() as $expectedTeacher => $subjectPlan) {
            $teacher = $context['teachers'][$expectedTeacher];
            foreach ($subjectPlan as $subjectCode => $categoryPlan) {
                foreach ($categoryPlan as $categoryCode => $classes) {
                    foreach ($classes as $className) {
                        $rombel = $context['rombels'][$className];
                        $subject = $subjects[$subjectCode];
                        $category = $context['categories'][$categoryCode];
                        $desired[$subjectCode.'|'.$className] = true;
                        $assignment = TeachingAssignment::query()->where([
                            'assessment_semester_id' => $semester->getKey(),
                            'assessment_subject_id' => $subject->getKey(),
                            'teacher_id' => $teacher->getKey(),
                            'rombel_id' => $rombel->getKey(),
                        ])->lockForUpdate()->first();
                        $values = [
                            'assessment_subject_category_id' => $category->getKey(),
                            'teacher_name_snapshot' => $teacher->nama,
                            'subject_name_snapshot' => $subject->name,
                            'rombel_name_snapshot' => $rombel->nama,
                            'is_active' => true,
                        ];
                        if (! $assignment) {
                            TeachingAssignment::query()->create([
                                'assessment_semester_id' => $semester->getKey(),
                                'assessment_subject_id' => $subject->getKey(),
                                'teacher_id' => $teacher->getKey(),
                                'rombel_id' => $rombel->getKey(),
                                ...$values,
                            ]);
                            $summary['assignments_created']++;
                        } elseif ($assignment->only(array_keys($values)) !== $values) {
                            $assignment->forceFill($values)->save();
                            $summary['assignments_updated']++;
                        }
                    }
                }
            }
        }

        $subjectCodesById = collect($subjects)->mapWithKeys(fn (Subject $subject, string $code): array => [$subject->getKey() => $code]);
        $rombelNamesById = collect($context['rombels'])->mapWithKeys(fn (Rombel $rombel): array => [$rombel->getKey() => $rombel->nama]);
        $otherAssignments = TeachingAssignment::query()
            ->where('assessment_semester_id', $semester->getKey())
            ->whereIn('assessment_subject_id', $subjectCodesById->keys())
            ->whereIn('rombel_id', $rombelNamesById->keys())
            ->where('is_active', true)
            ->lockForUpdate()
            ->get();
        foreach ($otherAssignments as $assignment) {
            $key = $subjectCodesById->get($assignment->assessment_subject_id).'|'.$rombelNamesById->get($assignment->rombel_id);
            $expectedTeacherId = $this->expectedTeacherIdForKey($key, $context);
            if (isset($desired[$key]) && (int) $assignment->teacher_id === $expectedTeacherId) {
                continue;
            }
            $assignment->forceFill(['is_active' => false])->save();
            $summary['assignments_disabled']++;
        }

        if (DB::getSchemaBuilder()->hasTable('assessment_audit_logs')) {
            DB::table('assessment_audit_logs')->insert([
                'assessment_period_id' => null,
                'actor_id' => null,
                'event' => 'master.teaching_plan_2026_applied',
                'subject_type' => 'assessment_teaching_plan',
                'subject_id' => $semester->getKey(),
                'old_values' => null,
                'new_values' => json_encode($summary, JSON_UNESCAPED_UNICODE),
                'reason' => 'Plotting resmi dari matriks guru-mapel 2026/2027 Ganjil.',
                'ip_address' => null,
                'user_agent' => 'artisan',
                'created_at' => now(),
            ]);
        }

        return $summary;
    }

    /** @param array<string,mixed> $context */
    private function expectedTeacherIdForKey(string $key, array $context): int
    {
        [$subjectCode, $className] = explode('|', $key, 2);
        foreach ($this->teacherPlan() as $expectedTeacher => $subjects) {
            foreach ($subjects[$subjectCode] ?? [] as $classes) {
                if (in_array($className, $classes, true)) {
                    return (int) $context['teachers'][$expectedTeacher]->getKey();
                }
            }
        }

        return 0;
    }

    /** @return array<string,string> */
    private function subjects(): array
    {
        return [
            'FIS' => 'Fisika', 'PKWU' => 'PKWU', 'GEO' => 'Geografi', 'BIG' => 'Bahasa Inggris',
            'PPKN' => 'PPKN', 'SOS' => 'Sosiologi', 'TIK' => 'TIK', 'KIM' => 'Kimia',
            'BIO' => 'Biologi', 'EKO' => 'Ekonomi', 'MTK' => 'Matematika', 'MTK-TL' => 'Matematika Tingkat Lanjut',
            'SBD' => 'SBD', 'PJOK' => 'PJOK', 'SEJ-IND' => 'Sejarah Indonesia', 'SEJ-TL' => 'Sejarah Tingkat Lanjut',
            'BIG-TL' => 'Bahasa Inggris Tingkat Lanjut', 'PAI' => 'Pendidikan Agama Islam', 'BIN' => 'Bahasa Indonesia',
        ];
    }

    /** @return list<string> */
    private function classNames(): array
    {
        return ['X 1', 'X 2', 'XI 1', 'XI 2', 'XII 1', 'XII 2', 'XII 3'];
    }

    /** @return array<string,array<string,array<string,list<string>>>> */
    private function teacherPlan(): array
    {
        return [
            'Ahmad Tri Anggoro' => [
                'FIS' => ['PILIHAN' => ['X 1', 'X 2', 'XI 1', 'XII 1']],
                'PKWU' => ['PILIHAN' => $this->classNames()],
            ],
            'Aisyah Sekar Tri Wardani' => ['GEO' => ['PILIHAN' => ['X 1', 'X 2', 'XI 2', 'XII 2']]],
            'Fitri Nurfadhilah' => ['BIG' => ['PILIHAN' => ['X 1', 'X 2'], 'WAJIB' => ['XI 1', 'XI 2', 'XII 1', 'XII 2', 'XII 3']]],
            'Khoiriyah' => [
                'PPKN' => ['PILIHAN' => ['X 1', 'X 2', 'XII 3'], 'WAJIB' => ['XI 1', 'XI 2', 'XII 1', 'XII 2']],
                'SOS' => ['PILIHAN' => ['X 1', 'X 2', 'XI 2', 'XII 2', 'XII 3']],
            ],
            'Kholifin Hilman Suharno' => [
                'TIK' => ['PILIHAN' => $this->classNames()],
                'SEJ-IND' => ['PILIHAN' => ['XII 1', 'XII 2']],
            ],
            'Komariyah' => [
                'KIM' => ['PILIHAN' => ['X 1', 'X 2', 'XI 1', 'XII 1', 'XII 3']],
                'BIO' => ['PILIHAN' => ['X 1', 'X 2', 'XI 1', 'XII 1', 'XII 3']],
            ],
            'M. Fandakir' => ['EKO' => ['PILIHAN' => ['X 1', 'X 2', 'XI 2', 'XII 2', 'XII 3']]],
            'Menik Putri Lestari' => [
                'MTK' => ['PILIHAN' => ['X 1', 'X 2'], 'WAJIB' => ['XI 1', 'XI 2', 'XII 1', 'XII 2', 'XII 3']],
                'MTK-TL' => ['PILIHAN' => ['XI 1']],
            ],
            'Zahki Maulana' => [
                'SBD' => ['WAJIB' => ['XI 1', 'XI 2', 'XII 1', 'XII 2', 'XII 3']],
                'PJOK' => ['PILIHAN' => ['X 1', 'X 2'], 'WAJIB' => ['XI 1', 'XI 2']],
            ],
            'Mulky Fauzan' => [
                'SEJ-TL' => ['PILIHAN' => ['XI 2']],
                'SEJ-IND' => ['PILIHAN' => ['X 1', 'X 2', 'XII 3'], 'WAJIB' => ['XI 1', 'XI 2']],
                'BIG-TL' => ['PILIHAN' => ['XII 2']],
            ],
            'Nurul Afifah' => [
                'PJOK' => ['WAJIB' => ['XII 1', 'XII 2', 'XII 3']],
                'PAI' => ['PILIHAN' => ['X 1', 'X 2'], 'WAJIB' => ['XI 1', 'XI 2', 'XII 1', 'XII 2', 'XII 3']],
            ],
            'Nurul Izzah Nisfulaily' => ['BIN' => ['PILIHAN' => ['X 1', 'X 2'], 'WAJIB' => ['XI 1', 'XI 2', 'XII 1', 'XII 2', 'XII 3']]],
        ];
    }
}
