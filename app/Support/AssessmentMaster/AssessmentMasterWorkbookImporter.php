<?php

namespace App\Support\AssessmentMaster;

use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\TeachingAssignment;
use App\Models\GuruTendik;
use App\Models\Rombel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class AssessmentMasterWorkbookImporter
{
    public const REQUIRED_SHEETS = [
        'TAHUN_SEMESTER',
        'MAPEL',
        'PENUGASAN_GURU',
        'WALI_KELAS',
    ];

    public const REQUIRED_HEADERS = [
        'TAHUN_SEMESTER' => [
            'TAHUN_KODE',
            'TAHUN_NAMA',
            'TAHUN_MULAI',
            'TAHUN_SELESAI',
            'SEMESTER_KODE',
            'SEMESTER_NAMA',
            'SEMESTER_MULAI',
            'SEMESTER_SELESAI',
            'AKTIF',
        ],
        'MAPEL' => [
            'KODE_MAPEL',
            'NAMA_MAPEL',
            'DESKRIPSI',
            'KELOMPOK_KODE',
            'KELOMPOK_NAMA',
            'URUTAN_KELOMPOK',
            'URUTAN_MAPEL',
            'AKTIF',
        ],
        'PENUGASAN_GURU' => [
            'SEMESTER_KODE',
            'MAPEL_KODE',
            'NAMA_GURU',
            'ID_GURU_SISTEM',
            'NAMA_ROMBEL',
            'ID_ROMBEL_SISTEM',
            'AKTIF',
        ],
        'WALI_KELAS' => [
            'SEMESTER_KODE',
            'NAMA_GURU',
            'ID_GURU_SISTEM',
            'NAMA_ROMBEL',
            'ID_ROMBEL_SISTEM',
            'AKTIF',
        ],
    ];

    public const LEGACY_MAPEL_HEADERS = [
        'KODE_MAPEL',
        'NAMA_MAPEL',
        'DESKRIPSI',
        'URUTAN',
        'AKTIF',
    ];

    /**
     * @return array<string, mixed>
     */
    public function preview(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $errors = [];
        $warnings = [];

        foreach (self::REQUIRED_SHEETS as $sheetName) {
            if (! $spreadsheet->getSheetByName($sheetName)) {
                $errors[] = "Sheet {$sheetName} tidak ditemukan.";
            }
        }

        if ($errors === []) {
            foreach (self::REQUIRED_HEADERS as $sheetName => $expectedHeaders) {
                $actualHeaders = $this->headings($spreadsheet->getSheetByName($sheetName));

                $isLegacyMapel = $sheetName === 'MAPEL' && $actualHeaders === self::LEGACY_MAPEL_HEADERS;
                if ($isLegacyMapel) {
                    $warnings[] = 'Workbook memakai format MAPEL lama. Data tetap dapat diimpor, tetapi mapel akan ditandai Belum Dikelompokkan sampai kelompok rapor dilengkapi.';
                } elseif ($actualHeaders !== $expectedHeaders) {
                    $errors[] = "Header sheet {$sheetName} harus persis dan berurutan: "
                        .implode(', ', $expectedHeaders).'. Jangan mengganti, menghapus, atau memindahkan judul kolom.';
                }
            }
        }

        if ($errors !== []) {
            return $this->finishPreview([], $errors, $warnings);
        }

        $payload = [
            'academic_years' => [],
            'semesters' => [],
            'subjects' => [],
            'teaching_assignments' => [],
            'homeroom_assignments' => [],
        ];

        $yearRows = $this->rows($spreadsheet->getSheetByName('TAHUN_SEMESTER'));
        $seenYears = [];
        $seenSemesters = [];

        foreach ($yearRows as $rowNumber => $row) {
            $yearCode = $this->text($row['TAHUN_KODE'] ?? null);
            $semesterCode = $this->text($row['SEMESTER_KODE'] ?? null);

            if ($yearCode === '' && $semesterCode === '') {
                continue;
            }

            if ($yearCode === '') {
                $errors[] = "TAHUN_SEMESTER baris {$rowNumber}: TAHUN_KODE wajib diisi.";
                continue;
            }

            if (! isset($seenYears[$yearCode])) {
                $year = [
                    'code' => $yearCode,
                    'name' => $this->text($row['TAHUN_NAMA'] ?? null) ?: $yearCode,
                    'starts_on' => $this->date($row['TAHUN_MULAI'] ?? null, 'TAHUN_SEMESTER', $rowNumber, 'TAHUN_MULAI', $errors),
                    'ends_on' => $this->date($row['TAHUN_SELESAI'] ?? null, 'TAHUN_SEMESTER', $rowNumber, 'TAHUN_SELESAI', $errors),
                    'is_active' => $this->boolean($row['AKTIF'] ?? 'YA'),
                ];
                $this->validateDateRange(
                    $year['starts_on'],
                    $year['ends_on'],
                    'TAHUN_SEMESTER',
                    $rowNumber,
                    'tahun pelajaran',
                    $errors,
                );
                $year['action'] = $this->actionFor(AcademicYear::query()->where('code', $yearCode)->first(), $year);
                $payload['academic_years'][] = $year;
                $seenYears[$yearCode] = true;
            }

            if ($semesterCode === '') {
                $errors[] = "TAHUN_SEMESTER baris {$rowNumber}: SEMESTER_KODE wajib diisi.";
                continue;
            }

            if (isset($seenSemesters[$semesterCode])) {
                $errors[] = "TAHUN_SEMESTER baris {$rowNumber}: SEMESTER_KODE {$semesterCode} duplikat.";
                continue;
            }

            $semester = [
                'academic_year_code' => $yearCode,
                'code' => $semesterCode,
                'name' => $this->text($row['SEMESTER_NAMA'] ?? null) ?: $semesterCode,
                'starts_on' => $this->date($row['SEMESTER_MULAI'] ?? null, 'TAHUN_SEMESTER', $rowNumber, 'SEMESTER_MULAI', $errors),
                'ends_on' => $this->date($row['SEMESTER_SELESAI'] ?? null, 'TAHUN_SEMESTER', $rowNumber, 'SEMESTER_SELESAI', $errors),
                'is_active' => $this->boolean($row['AKTIF'] ?? 'YA'),
            ];
            $this->validateDateRange(
                $semester['starts_on'],
                $semester['ends_on'],
                'TAHUN_SEMESTER',
                $rowNumber,
                'semester',
                $errors,
            );
            $yearRecord = AcademicYear::query()->where('code', $yearCode)->first();
            $semesterRecord = $yearRecord
                ? Semester::query()
                    ->where('assessment_academic_year_id', $yearRecord->getKey())
                    ->where('code', $semesterCode)
                    ->first()
                : null;
            $semester['action'] = $this->actionFor($semesterRecord, collect($semester)->except('academic_year_code')->all());
            $payload['semesters'][] = $semester;
            $seenSemesters[$semesterCode] = true;
        }

        $yearsByCode = collect($payload['academic_years'])->keyBy('code');
        foreach ($payload['semesters'] as $semester) {
            $year = $yearsByCode->get($semester['academic_year_code']);
            if (! $year) {
                continue;
            }

            if ($semester['starts_on'] && $year['starts_on']
                && $semester['starts_on'] < $year['starts_on']) {
                $errors[] = "Semester {$semester['code']}: tanggal mulai berada sebelum tahun pelajaran {$year['code']}.";
            }
            if ($semester['ends_on'] && $year['ends_on']
                && $semester['ends_on'] > $year['ends_on']) {
                $errors[] = "Semester {$semester['code']}: tanggal selesai melewati tahun pelajaran {$year['code']}.";
            }
        }

        $seenSubjects = [];

        foreach ($this->rows($spreadsheet->getSheetByName('MAPEL')) as $rowNumber => $row) {
            $code = $this->text($row['KODE_MAPEL'] ?? null);
            $name = $this->text($row['NAMA_MAPEL'] ?? null);

            if ($code === '' && $name === '') {
                continue;
            }

            if ($code === '' || $name === '') {
                $errors[] = "MAPEL baris {$rowNumber}: KODE_MAPEL dan NAMA_MAPEL wajib diisi.";
                continue;
            }

            if (isset($seenSubjects[$code])) {
                $errors[] = "MAPEL baris {$rowNumber}: KODE_MAPEL {$code} duplikat.";
                continue;
            }

            $subject = [
                'code' => $code,
                'name' => $name,
                'description' => $this->nullableText($row['DESKRIPSI'] ?? null),
                'report_group_code' => $this->text($row['KELOMPOK_KODE'] ?? null) ?: 'BELUM',
                'report_group_name' => $this->text($row['KELOMPOK_NAMA'] ?? null) ?: 'Belum Dikelompokkan',
                'report_group_sort_order' => max(0, (int) ($row['URUTAN_KELOMPOK'] ?? 999)),
                'sort_order' => max(0, (int) ($row['URUTAN_MAPEL'] ?? $row['URUTAN'] ?? 0)),
                'is_active' => $this->boolean($row['AKTIF'] ?? 'YA'),
            ];
            if ($subject['report_group_code'] === 'BELUM') {
                $warnings[] = "MAPEL baris {$rowNumber}: {$name} belum memiliki kelompok rapor.";
            }
            $subject['action'] = $this->actionFor(Subject::query()->where('code', $code)->first(), $subject);
            $payload['subjects'][] = $subject;
            $seenSubjects[$code] = true;
        }

        $incomingSemesterYears = collect($payload['semesters'])
            ->mapWithKeys(fn (array $semester): array => [
                $semester['code'] => $semester['academic_year_code'],
            ])
            ->all();
        $subjectCodes = collect($payload['subjects'])->pluck('code')
            ->merge(Subject::query()->pluck('code'))
            ->unique()
            ->all();
        $seenTeaching = [];

        foreach ($this->rows($spreadsheet->getSheetByName('PENUGASAN_GURU')) as $rowNumber => $row) {
            if ($this->rowIsBlank($row)) {
                continue;
            }

            $semesterCode = $this->text($row['SEMESTER_KODE'] ?? null);
            $subjectCode = $this->text($row['MAPEL_KODE'] ?? null);
            $semesterReference = $this->resolveSemesterReference(
                $semesterCode,
                $incomingSemesterYears,
                $rowNumber,
                'PENUGASAN_GURU',
                $errors,
            );
            $teacher = $this->resolveTeacher($row['ID_GURU_SISTEM'] ?? null, $row['NAMA_GURU'] ?? null, $rowNumber, 'PENUGASAN_GURU', $errors);
            $rombel = $this->resolveRombel($row['ID_ROMBEL_SISTEM'] ?? null, $row['NAMA_ROMBEL'] ?? null, $rowNumber, 'PENUGASAN_GURU', $errors);

            if (! in_array($subjectCode, $subjectCodes, true)) {
                $errors[] = "PENUGASAN_GURU baris {$rowNumber}: mapel {$subjectCode} tidak ditemukan.";
            }
            if (! $teacher || ! $rombel || ! $semesterReference || ! in_array($subjectCode, $subjectCodes, true)) {
                continue;
            }

            if (! $teacher->userAccount()->exists()) {
                $warnings[] = "PENUGASAN_GURU baris {$rowNumber}: {$teacher->nama} belum mempunyai akun.";
            }
            if (! $rombel->is_active) {
                $warnings[] = "PENUGASAN_GURU baris {$rowNumber}: rombel {$rombel->nama} sedang tidak aktif.";
            }

            $key = implode('|', [$semesterCode, $subjectCode, $teacher->getKey(), $rombel->getKey()]);
            if (isset($seenTeaching[$key])) {
                $errors[] = "PENUGASAN_GURU baris {$rowNumber}: penugasan duplikat.";
                continue;
            }
            $seenTeaching[$key] = true;

            $assignment = [
                'semester_code' => $semesterCode,
                'semester_academic_year_code' => $semesterReference['academic_year_code'],
                'subject_code' => $subjectCode,
                'teacher_id' => (int) $teacher->getKey(),
                'teacher_name' => (string) $teacher->nama,
                'rombel_id' => (int) $rombel->getKey(),
                'rombel_name' => (string) $rombel->nama,
                'is_active' => $this->boolean($row['AKTIF'] ?? 'YA'),
            ];
            $payload['teaching_assignments'][] = $assignment;
        }

        $seenHomeroom = [];

        foreach ($this->rows($spreadsheet->getSheetByName('WALI_KELAS')) as $rowNumber => $row) {
            if ($this->rowIsBlank($row)) {
                continue;
            }

            $semesterCode = $this->text($row['SEMESTER_KODE'] ?? null);
            $semesterReference = $this->resolveSemesterReference(
                $semesterCode,
                $incomingSemesterYears,
                $rowNumber,
                'WALI_KELAS',
                $errors,
            );
            $teacher = $this->resolveTeacher($row['ID_GURU_SISTEM'] ?? null, $row['NAMA_GURU'] ?? null, $rowNumber, 'WALI_KELAS', $errors);
            $rombel = $this->resolveRombel($row['ID_ROMBEL_SISTEM'] ?? null, $row['NAMA_ROMBEL'] ?? null, $rowNumber, 'WALI_KELAS', $errors);

            if (! $teacher || ! $rombel || ! $semesterReference) {
                continue;
            }

            if (! $teacher->userAccount()->exists()) {
                $warnings[] = "WALI_KELAS baris {$rowNumber}: {$teacher->nama} belum mempunyai akun.";
            }
            if (! $rombel->is_active) {
                $warnings[] = "WALI_KELAS baris {$rowNumber}: rombel {$rombel->nama} sedang tidak aktif.";
            }

            $key = $semesterCode.'|'.$rombel->getKey();
            if (isset($seenHomeroom[$key])) {
                $errors[] = "WALI_KELAS baris {$rowNumber}: rombel {$rombel->nama} memiliki lebih dari satu wali kelas.";
                continue;
            }
            $seenHomeroom[$key] = true;

            $payload['homeroom_assignments'][] = [
                'semester_code' => $semesterCode,
                'semester_academic_year_code' => $semesterReference['academic_year_code'],
                'teacher_id' => (int) $teacher->getKey(),
                'teacher_name' => (string) $teacher->nama,
                'rombel_id' => (int) $rombel->getKey(),
                'rombel_name' => (string) $rombel->nama,
                'is_active' => $this->boolean($row['AKTIF'] ?? 'YA'),
            ];
        }

        $this->classifyAssignments($payload);

        return $this->finishPreview($payload, array_values(array_unique($errors)), array_values(array_unique($warnings)));
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, int>
     */
    public function apply(array $preview, ?int $userId): array
    {
        $payload = $preview['payload'] ?? [];
        $expected = $this->signature(
            $payload,
            array_values($preview['errors'] ?? []),
            array_values($preview['warnings'] ?? []),
        );

        if (! isset($preview['fingerprint']) || ! hash_equals($expected, (string) $preview['fingerprint'])) {
            throw new RuntimeException('Data pratinjau tidak valid atau telah berubah. Unggah ulang workbook.');
        }

        if (($preview['errors'] ?? []) !== []) {
            throw new RuntimeException('Impor tidak dapat diterapkan karena masih memiliki kesalahan.');
        }

        return DB::transaction(function () use ($payload, $userId): array {
            $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($payload['academic_years'] ?? [] as $row) {
                $this->upsert(AcademicYear::class, ['code' => $row['code']], $this->withoutPreviewFields($row), $summary);
            }

            foreach ($payload['semesters'] ?? [] as $row) {
                $year = AcademicYear::query()->where('code', $row['academic_year_code'])->firstOrFail();
                $values = $this->withoutPreviewFields($row);
                unset($values['academic_year_code']);
                $values['assessment_academic_year_id'] = $year->getKey();
                $this->upsert(
                    Semester::class,
                    ['assessment_academic_year_id' => $year->getKey(), 'code' => $row['code']],
                    $values,
                    $summary,
                );
            }

            foreach ($payload['subjects'] ?? [] as $row) {
                $this->upsert(Subject::class, ['code' => $row['code']], $this->withoutPreviewFields($row), $summary);
            }

            foreach ($payload['teaching_assignments'] ?? [] as $row) {
                $semester = $this->semesterForRow($row);
                $subject = Subject::query()->where('code', $row['subject_code'])->firstOrFail();
                $values = [
                    'assessment_semester_id' => $semester->getKey(),
                    'assessment_subject_id' => $subject->getKey(),
                    'teacher_id' => $row['teacher_id'],
                    'rombel_id' => $row['rombel_id'],
                    'teacher_name_snapshot' => $row['teacher_name'],
                    'subject_name_snapshot' => $subject->name,
                    'rombel_name_snapshot' => $row['rombel_name'],
                    'is_active' => $row['is_active'],
                ];
                $this->upsert(TeachingAssignment::class, [
                    'assessment_semester_id' => $semester->getKey(),
                    'assessment_subject_id' => $subject->getKey(),
                    'teacher_id' => $row['teacher_id'],
                    'rombel_id' => $row['rombel_id'],
                ], $values, $summary);
            }

            foreach ($payload['homeroom_assignments'] ?? [] as $row) {
                $semester = $this->semesterForRow($row);
                $values = [
                    'assessment_semester_id' => $semester->getKey(),
                    'teacher_id' => $row['teacher_id'],
                    'rombel_id' => $row['rombel_id'],
                    'teacher_name_snapshot' => $row['teacher_name'],
                    'rombel_name_snapshot' => $row['rombel_name'],
                    'is_active' => $row['is_active'],
                ];
                $this->upsert(HomeroomAssignment::class, [
                    'assessment_semester_id' => $semester->getKey(),
                    'rombel_id' => $row['rombel_id'],
                ], $values, $summary);
            }

            if (DB::getSchemaBuilder()->hasTable('assessment_audit_logs')) {
                DB::table('assessment_audit_logs')->insert([
                    'assessment_period_id' => null,
                    'actor_id' => $userId,
                    'event' => 'master.imported',
                    'subject_type' => 'assessment_master_import',
                    'subject_id' => 0,
                    'old_values' => null,
                    'new_values' => json_encode($summary, JSON_UNESCAPED_UNICODE),
                    'reason' => 'Impor workbook master resmi',
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'created_at' => now(),
                ]);
            }

            return $summary;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function classifyAssignments(array &$payload): void
    {
        $subjectNames = Subject::query()->pluck('name', 'code')
            ->merge(collect($payload['subjects'] ?? [])->pluck('name', 'code'));

        foreach ($payload['teaching_assignments'] as &$row) {
            $semesterId = $this->semesterForRow($row, fail: false)?->getKey();
            $subjectId = Subject::query()->where('code', $row['subject_code'])->value('id');
            $record = $semesterId && $subjectId
                ? TeachingAssignment::query()->where([
                    'assessment_semester_id' => $semesterId,
                    'assessment_subject_id' => $subjectId,
                    'teacher_id' => $row['teacher_id'],
                    'rombel_id' => $row['rombel_id'],
                ])->first()
                : null;
            $expectedSubjectName = (string) ($subjectNames[$row['subject_code']] ?? $row['subject_code']);
            $row['action'] = ! $record
                ? 'create'
                : (
                    $record->is_active === $row['is_active']
                    && (string) $record->teacher_name_snapshot === (string) $row['teacher_name']
                    && (string) $record->subject_name_snapshot === $expectedSubjectName
                    && (string) $record->rombel_name_snapshot === (string) $row['rombel_name']
                        ? 'unchanged'
                        : 'update'
                );
        }
        unset($row);

        foreach ($payload['homeroom_assignments'] as &$row) {
            $semesterId = $this->semesterForRow($row, fail: false)?->getKey();
            $record = $semesterId
                ? HomeroomAssignment::query()->where([
                    'assessment_semester_id' => $semesterId,
                    'rombel_id' => $row['rombel_id'],
                ])->first()
                : null;
            $row['action'] = ! $record
                ? 'create'
                : (
                    (int) $record->teacher_id === (int) $row['teacher_id']
                    && $record->is_active === $row['is_active']
                    && (string) $record->teacher_name_snapshot === (string) $row['teacher_name']
                    && (string) $record->rombel_name_snapshot === (string) $row['rombel_name']
                        ? 'unchanged'
                        : 'update'
                );
        }
        unset($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rows(?Worksheet $sheet): array
    {
        if (! $sheet) {
            return [];
        }

        $rawRows = $sheet->toArray(null, true, true, false);
        $headings = array_map(fn (mixed $heading): string => strtoupper(trim((string) $heading)), array_shift($rawRows) ?? []);
        $rows = [];

        foreach ($rawRows as $index => $rawRow) {
            $row = [];
            foreach ($headings as $column => $heading) {
                if ($heading !== '') {
                    $row[$heading] = $rawRow[$column] ?? null;
                }
            }
            $rows[$index + 2] = $row;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    protected function headings(?Worksheet $sheet): array
    {
        if (! $sheet) {
            return [];
        }

        $firstRow = $sheet->rangeToArray(
            'A1:'.$sheet->getHighestDataColumn().'1',
            null,
            true,
            true,
            false,
        )[0] ?? [];

        return collect($firstRow)
            ->map(fn (mixed $heading): string => mb_strtoupper(trim((string) $heading)))
            ->reject(fn (string $heading): bool => $heading === '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $incomingSemesterYears
     * @param  array<int, string>  $errors
     * @return array{code:string,academic_year_code:string}|null
     */
    protected function resolveSemesterReference(
        string $code,
        array $incomingSemesterYears,
        int $row,
        string $sheet,
        array &$errors,
    ): ?array {
        if ($code === '') {
            $errors[] = "{$sheet} baris {$row}: SEMESTER_KODE wajib diisi.";

            return null;
        }

        if (isset($incomingSemesterYears[$code])) {
            return [
                'code' => $code,
                'academic_year_code' => $incomingSemesterYears[$code],
            ];
        }

        $matches = Semester::query()
            ->with('academicYear:id,code')
            ->where('code', $code)
            ->get();

        if ($matches->isEmpty()) {
            $errors[] = "{$sheet} baris {$row}: semester {$code} tidak ditemukan.";

            return null;
        }

        if ($matches->count() > 1) {
            $errors[] = "{$sheet} baris {$row}: kode semester {$code} dipakai pada lebih dari satu tahun. Gunakan kode unik yang memuat tahun pelajaran pada workbook.";

            return null;
        }

        return [
            'code' => $code,
            'academic_year_code' => (string) $matches->first()->academicYear?->code,
        ];
    }

    protected function semesterForRow(array $row, bool $fail = true): ?Semester
    {
        $year = AcademicYear::query()
            ->where('code', $row['semester_academic_year_code'] ?? '')
            ->first();

        $semester = $year
            ? Semester::query()
                ->where('assessment_academic_year_id', $year->getKey())
                ->where('code', $row['semester_code'] ?? '')
                ->first()
            : null;

        if (! $semester && $fail) {
            throw new RuntimeException(
                'Semester '.$this->text($row['semester_code'] ?? null)
                .' pada tahun '.$this->text($row['semester_academic_year_code'] ?? null)
                .' tidak ditemukan saat apply.'
            );
        }

        return $semester;
    }

    protected function resolveTeacher(mixed $id, mixed $name, int $row, string $sheet, array &$errors): ?GuruTendik
    {
        $teacher = filled($id) ? GuruTendik::query()->find((int) $id) : null;
        $name = $this->text($name);

        if (! $teacher && $name !== '') {
            $matches = GuruTendik::query()->whereRaw('LOWER(nama) = ?', [mb_strtolower($name)])->get();
            if ($matches->count() > 1) {
                $errors[] = "{$sheet} baris {$row}: nama guru {$name} tidak unik; gunakan ID sistem.";
                return null;
            }
            $teacher = $matches->first();
        }

        if (! $teacher) {
            $errors[] = "{$sheet} baris {$row}: referensi guru tidak ditemukan.";
        }

        return $teacher;
    }

    protected function resolveRombel(mixed $id, mixed $name, int $row, string $sheet, array &$errors): ?Rombel
    {
        $rombel = filled($id) ? Rombel::query()->find((int) $id) : null;
        $name = $this->text($name);

        if (! $rombel && $name !== '') {
            $rombel = Rombel::query()->whereRaw('LOWER(nama) = ?', [mb_strtolower($name)])->first();
        }

        if (! $rombel) {
            $errors[] = "{$sheet} baris {$row}: referensi rombel tidak ditemukan.";
        }

        return $rombel;
    }

    protected function date(mixed $value, string $sheet, int $row, string $column, array &$errors): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value) && (float) $value >= 1 && (float) $value <= 2958465) {
                return Carbon::instance(
                    ExcelDate::excelToDateTimeObject((float) $value),
                )->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            $errors[] = "{$sheet} baris {$row}: {$column} bukan tanggal yang valid.";
            return null;
        }
    }

    protected function validateDateRange(
        ?string $startsOn,
        ?string $endsOn,
        string $sheet,
        int $row,
        string $label,
        array &$errors,
    ): void {
        if ($startsOn && $endsOn && $startsOn > $endsOn) {
            $errors[] = "{$sheet} baris {$row}: tanggal mulai {$label} harus sebelum atau sama dengan tanggal selesai.";
        }
    }

    protected function boolean(mixed $value): bool
    {
        return in_array(mb_strtoupper($this->text($value)), ['1', 'YA', 'Y', 'TRUE', 'AKTIF'], true);
    }

    protected function text(mixed $value): string
    {
        return trim((string) $value);
    }

    protected function nullableText(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === '' ? null : $text;
    }

    protected function rowIsBlank(array $row): bool
    {
        return collect($row)->filter(fn (mixed $value): bool => filled($value))->isEmpty();
    }

    protected function actionFor(?object $record, array $values): string
    {
        if (! $record) {
            return 'create';
        }

        foreach ($values as $key => $value) {
            $current = $record->{$key};
            if ($current instanceof \DateTimeInterface) {
                $current = $current->format('Y-m-d');
            }
            if (is_bool($value)) {
                $current = (bool) $current;
            }
            if ((string) ($current ?? '') !== (string) ($value ?? '')) {
                return 'update';
            }
        }

        return 'unchanged';
    }

    protected function upsert(string $modelClass, array $identity, array $values, array &$summary): void
    {
        $record = $modelClass::query()->where($identity)->first();
        $action = $record ? ($record->fill($values)->isDirty() ? 'updated' : 'unchanged') : 'created';

        if (! $record) {
            $record = new $modelClass;
            $record->fill($identity);
        }

        $record->fill($values);
        $record->save();
        $summary[$action]++;
    }

    protected function withoutPreviewFields(array $row): array
    {
        unset($row['action']);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    protected function finishPreview(array $payload, array $errors, array $warnings): array
    {
        $actions = collect($payload)
            ->flatten(1)
            ->pluck('action')
            ->countBy();

        return [
            'payload' => $payload,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'create' => (int) ($actions['create'] ?? 0),
                'update' => (int) ($actions['update'] ?? 0),
                'unchanged' => (int) ($actions['unchanged'] ?? 0),
                'errors' => count($errors),
                'warnings' => count($warnings),
            ],
            'fingerprint' => $this->signature($payload, $errors, $warnings),
        ];
    }

    protected function signature(array $payload, array $errors = [], array $warnings = []): string
    {
        return hash_hmac(
            'sha256',
            json_encode([
                'payload' => $payload,
                'errors' => $errors,
                'warnings' => $warnings,
            ], JSON_UNESCAPED_UNICODE),
            (string) config('app.key'),
        );
    }
}
