<?php

namespace App\Console\Commands;

use App\Actions\Assessment\CloseAssessmentEntryAction;
use App\Actions\Assessment\LockAssessmentPeriodAction;
use App\Actions\Assessment\PublishAssessmentPeriodAction;
use App\Actions\Assessment\SaveAssessmentScoresAction;
use App\Actions\Assessment\StartAssessmentVerificationAction;
use App\Actions\Assessment\SubmitAssessmentAssignmentAction;
use App\Actions\Assessment\VerifyAssessmentAssignmentAction;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Enums\Assessment\ScoreSource;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\AssessmentScore;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\HomeroomReport;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\SubjectCategory;
use App\Models\Assessment\TeachingAssignment;
use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Assessment\AssessmentSchemeResolver;
use App\Support\Assessment\Reporting\AssessmentReportRenderer;
use App\Support\Assessment\Reporting\AssessmentReportStorage;
use App\Support\Assessment\Reporting\ScheduleReportClassesAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class SeedAssessmentDemoData extends Command
{
    protected $signature = 'assessment:demo-data
        {--tahun=2025/2026 : Tahun ajaran yang disimulasikan}
        {--penuh : Bangun ASTS dan ASAS ganjil serta ASAT genap sampai published}
        {--tipe=asts : Jenis penilaian tunggal: asts, asas, atau asat}
        {--siswa=12 : Jumlah siswa per kelas}
        {--rombel= : Opsi lama; diabaikan karena matriks resmi menetapkan tujuh kelas}
        {--status=locked : Kompatibilitas mode tunggal: verification, locked, atau published}
        {--terapkan : Simpan data (tanpa opsi ini hanya pratinjau)}
        {--ganti-lama : Bersihkan data milik generator lalu bangun ulang}
        {--bersihkan : Hapus hanya data milik generator lalu keluar}
        {--cetak : Buat dan simpan PDF rapor siswa serta kelas}';

    protected $description = 'Menyimulasikan data Penilaian 2025/2026 dari matriks resmi pembagian tugas mengajar';

    private const MARKER = '[DEMO assessment:demo-data]';

    /** @var array<string, bool> */
    private array $mapelDipinjam = [];

    /** @var array<string, bool> */
    private array $mapelDibuat = [];

    /** @var array<string, bool> */
    private array $rombelDipinjam = [];

    /** @var array<string, bool> */
    private array $rombelDibuat = [];

    /** @var array<string,array{name:string,category:string}> */
    public const SUBJECTS = [
        'FIS' => ['name' => 'Fisika', 'category' => 'PILIHAN'],
        'PKWU' => ['name' => 'PKWU', 'category' => 'PILIHAN'],
        'GEO' => ['name' => 'Geografi', 'category' => 'PILIHAN'],
        'BIG' => ['name' => 'Bahasa Inggris', 'category' => 'WAJIB'],
        'PPKN' => ['name' => 'PPKN', 'category' => 'WAJIB'],
        'SOS' => ['name' => 'Sosiologi', 'category' => 'PILIHAN'],
        'TIK' => ['name' => 'TIK', 'category' => 'PILIHAN'],
        'KIM' => ['name' => 'Kimia', 'category' => 'PILIHAN'],
        'BIO' => ['name' => 'Biologi', 'category' => 'PILIHAN'],
        'EKO' => ['name' => 'Ekonomi', 'category' => 'PILIHAN'],
        'MTK' => ['name' => 'Matematika', 'category' => 'WAJIB'],
        'MTK-TL' => ['name' => 'Matematika Tingkat Lanjut', 'category' => 'PILIHAN'],
        'SBD' => ['name' => 'SBD (Seni Budaya)', 'category' => 'WAJIB'],
        'PJOK' => ['name' => 'PJOK', 'category' => 'WAJIB'],
        'SEJ-TL' => ['name' => 'Sejarah Tingkat Lanjut', 'category' => 'PILIHAN'],
        'SEJ-IND' => ['name' => 'Sejarah Indonesia', 'category' => 'WAJIB'],
        'BIG-TL' => ['name' => 'Bahasa Inggris Tingkat Lanjut', 'category' => 'PILIHAN'],
        'PAI' => ['name' => 'Pendidikan Agama Islam', 'category' => 'WAJIB'],
        'BIN' => ['name' => 'Bahasa Indonesia', 'category' => 'WAJIB'],
    ];

    public const CLASSES = ['X 1', 'X 2', 'XI 1', 'XI 2', 'XII 1', 'XII 2', 'XII 3'];

    /** Matriks resmi dokumen 2026/2027, dipakai untuk simulasi tahun yang diminta. */
    public const TEACHER_PLAN = [
        'Ahmad Tri Anggoro, S.T' => ['FIS' => ['X 1', 'X 2', 'XI 1', 'XII 1'], 'PKWU' => self::CLASSES],
        'Aisyah Sekar Triwardani, S.Pd' => ['GEO' => ['X 1', 'X 2', 'XI 2', 'XII 2']],
        'Fitri Nurfadilah, S.Pd' => ['BIG' => self::CLASSES],
        'Khoiriyah, S.Pd' => ['PPKN' => self::CLASSES, 'SOS' => ['X 1', 'X 2', 'XI 2', 'XII 2', 'XII 3']],
        'Kholifin Suharno Hilman, S.Pd' => ['TIK' => self::CLASSES],
        'Komariyah, S.Si' => ['KIM' => ['X 1', 'X 2', 'XI 1', 'XII 1', 'XII 3'], 'BIO' => ['X 1', 'X 2', 'XI 1', 'XII 1', 'XII 3']],
        'M. Fandakir, S.Pd' => ['EKO' => ['X 1', 'X 2', 'XI 2', 'XII 2', 'XII 3']],
        'Menik Putri Lestari, S.T' => ['MTK' => self::CLASSES, 'MTK-TL' => ['XI 1', 'XII 1']],
        'M. Zakhi Maulana, S.H' => ['SBD' => ['XI 1', 'XI 2', 'XII 1', 'XII 2', 'XII 3'], 'PJOK' => ['X 1', 'X 2', 'XI 1', 'XI 2']],
        'Mulky Fauzan, S.T., M.M' => ['SEJ-TL' => ['XI 2'], 'SEJ-IND' => self::CLASSES, 'BIG-TL' => ['XII 2']],
        'Nurul Afifah, S.Pdi' => ['PJOK' => ['XII 1', 'XII 2', 'XII 3'], 'PAI' => self::CLASSES],
        'Nurul Izzah Nisfulaily, S.Pd' => ['BIN' => self::CLASSES],
    ];

    public const HOMEROOMS = [
        'X 1' => 'Ahmad Tri Anggoro, S.T', 'X 2' => 'Aisyah Sekar Triwardani, S.Pd',
        'XI 1' => 'Fitri Nurfadilah, S.Pd', 'XI 2' => 'Khoiriyah, S.Pd',
        'XII 1' => 'Kholifin Suharno Hilman, S.Pd', 'XII 2' => 'Komariyah, S.Si',
        'XII 3' => 'M. Fandakir, S.Pd',
    ];

    private const STUDENT_NAMES = [
        'Demo Aditya Pratama', 'Demo Aisyah Rahmani', 'Demo Bagas Mahendra', 'Demo Citra Lestari',
        'Demo Daffa Ramadhan', 'Demo Farah Nabila', 'Demo Galang Saputra', 'Demo Hana Safitri',
        'Demo Ilham Nugraha', 'Demo Jasmine Putri', 'Demo Kenzie Akbar', 'Demo Laila Maharani',
    ];

    public function handle(): int
    {
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->components->error('DITOLAK: generator data demo tidak boleh dijalankan di lingkungan production.');

            return self::FAILURE;
        }
        if (! $this->tablesReady()) {
            $this->components->error('Tabel Penilaian, pengguna, guru, siswa, rombel, atau permission belum tersedia.');

            return self::FAILURE;
        }

        $studentCount = filter_var($this->option('siswa'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 40]]);
        $year = trim((string) $this->option('tahun'));
        $full = (bool) $this->option('penuh');
        $type = AssessmentType::tryFrom(strtolower((string) $this->option('tipe')));
        $target = $full ? AssessmentPeriodStatus::PUBLISHED : AssessmentPeriodStatus::tryFrom(strtolower((string) $this->option('status')));
        if ($studentCount === false || ! preg_match('/^\d{4}\/\d{4}$/', $year) || ! $type || ! in_array($target, [AssessmentPeriodStatus::VERIFICATION, AssessmentPeriodStatus::LOCKED, AssessmentPeriodStatus::PUBLISHED], true)) {
            $this->components->error('Opsi tidak valid. Gunakan --tahun=YYYY/YYYY, --siswa=1..40, --tipe=asts|asas|asat.');

            return self::INVALID;
        }

        $print = $full || (bool) $this->option('cetak');
        $this->components->info($this->option('terapkan') ? 'MODE: TERAPKAN (menyimpan)' : 'MODE: PRATINJAU (tidak menyimpan)');
        $this->tampilkanRencana($year, (int) $studentCount, $full, $type, $target, $print);
        if (! $this->option('terapkan')) {
            $this->components->warn('Pratinjau selesai. Tidak ada data yang ditulis. Tambahkan --terapkan untuk menyimpan.');

            return self::SUCCESS;
        }

        if ($this->option('bersihkan') || $this->option('ganti-lama')) {
            $exit = $this->bersihkan();
            if ($exit !== self::SUCCESS || $this->option('bersihkan')) {
                return $exit;
            }
        }

        config(['assessment.enabled' => true]);
        try {
            $specs = $full
                ? [[AssessmentType::ASTS, 'GANJIL'], [AssessmentType::ASAS, 'GANJIL'], [AssessmentType::ASAT, 'GENAP']]
                : [[$type, $type === AssessmentType::ASAT ? 'GENAP' : 'GANJIL']];
            $contexts = [];
            foreach ($specs as [$periodType, $semesterKind]) {
                $context = DB::transaction(fn (): array => $this->bangunPeriode($year, (int) $studentCount, $periodType, $semesterKind, $target), 3);
                $alreadyPublished = $context['period']->status === AssessmentPeriodStatus::PUBLISHED;
                $paths = $print
                    ? ($alreadyPublished ? $this->pdfPaths($context['period']) : $this->cetak($context['period'], $context['template'], $context['kurikulum']))
                    : [];
                if ($target === AssessmentPeriodStatus::PUBLISHED && ! $alreadyPublished) {
                    app(PublishAssessmentPeriodAction::class)->execute($context['kurikulum'], $context['period']->fresh());
                }
                $context['paths'] = $paths;
                $contexts[] = $context;
            }
        } catch (Throwable $exception) {
            report($exception);
            $message = $exception instanceof ValidationException ? collect($exception->errors())->flatten()->first() : $exception->getMessage();
            $this->components->error('Generator gagal: '.(string) $message);

            return self::FAILURE;
        }

        $this->ringkasan($contexts);

        return self::SUCCESS;
    }

    private function bangunPeriode(string $yearText, int $studentCount, AssessmentType $type, string $semesterKind, AssessmentPeriodStatus $target): array
    {
        $this->pasangPeranDanIzin();
        [$startYear, $endYear] = array_map('intval', explode('/', $yearText));
        $yearCode = "DEMO-{$startYear}-{$endYear}";
        $year = AcademicYear::query()->updateOrCreate(['code' => $yearCode], ['name' => "Demo {$yearText}", 'starts_on' => "{$startYear}-07-01", 'ends_on' => "{$endYear}-06-30", 'is_active' => true]);
        $semester = Semester::query()->updateOrCreate(
            ['assessment_academic_year_id' => $year->getKey(), 'code' => $yearCode.'-'.$semesterKind],
            ['name' => 'Demo '.ucfirst(strtolower($semesterKind)), 'starts_on' => $semesterKind === 'GANJIL' ? "{$startYear}-07-01" : "{$endYear}-01-01", 'ends_on' => $semesterKind === 'GANJIL' ? "{$startYear}-12-31" : "{$endYear}-06-30", 'is_active' => true],
        );
        $accounts = $this->buatAkun();
        $kurikulum = User::query()->where('username', 'demo-fitri-nurfadilah')->firstOrFail();
        $shortYear = substr((string) $startYear, 2).substr((string) $endYear, 2);
        $periodCode = 'DEMO-'.strtoupper($type->value).'-'.$shortYear.'-'.$semesterKind;
        $period = AssessmentPeriod::query()->where('code', $periodCode)->first();
        $template = $this->buatTemplate($type);
        if ($period && $period->status === $target && $period->students()->where('is_active', true)->count() === count(self::CLASSES) * $studentCount) {
            return compact('period', 'template', 'kurikulum', 'accounts');
        }
        if ($period) {
            throw ValidationException::withMessages(['period' => 'Data demo sudah ada dengan ukuran atau status berbeda. Gunakan --ganti-lama.']);
        }

        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->getKey(), 'assessment_semester_id' => $semester->getKey(),
            'code' => $periodCode, 'name' => 'Demo '.strtoupper($type->value).' '.$yearText.' '.ucfirst(strtolower($semesterKind)),
            'type' => $type, 'status' => AssessmentPeriodStatus::OPEN, 'entry_start_at' => now()->subDay(), 'entry_end_at' => now()->addDay(),
            'report_date' => $semesterKind === 'GANJIL' ? "{$startYear}-12-20" : "{$endYear}-06-20",
            'settings' => ['collect_promotion_status' => $type !== AssessmentType::ASTS, 'demo_owner' => self::MARKER], 'created_by' => $kurikulum->getKey(),
        ]);
        [$categories, $subjects] = $this->buatMapel();
        $rombels = $this->buatRombelDanSiswa($period, $studentCount);
        $this->buatSkema($period, $subjects, $type);
        $assignments = $this->buatPenugasan($period, $semester, $rombels, $subjects, $categories);
        $this->isiNilai($assignments);
        $this->buatRekapWali($period, $semester, $rombels);
        foreach ($assignments as $assignment) {
            $teacher = User::query()->where('guru_tendik_id', $assignment->teacher_id)->firstOrFail();
            app(SubmitAssessmentAssignmentAction::class)->execute($teacher, $assignment->fresh());
        }
        $period = app(CloseAssessmentEntryAction::class)->execute($kurikulum, $period->fresh());
        $period = app(StartAssessmentVerificationAction::class)->execute($kurikulum, $period);
        foreach ($assignments as $assignment) {
            app(VerifyAssessmentAssignmentAction::class)->execute($kurikulum, $assignment->fresh());
        }
        if (in_array($target, [AssessmentPeriodStatus::LOCKED, AssessmentPeriodStatus::PUBLISHED], true)) {
            $period = app(LockAssessmentPeriodAction::class)->execute($kurikulum, $period->fresh());
        }

        return compact('period', 'template', 'kurikulum', 'accounts');
    }

    private function pasangPeranDanIzin(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (InstallAssessmentDefaults::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (InstallAssessmentDefaults::ROLE_PERMISSIONS as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function username(string $name): string
    {
        return 'demo-'.Str::slug(preg_replace('/,.*$/', '', $name) ?? $name);
    }

    private function buatAkun(): array
    {
        $accounts = [];
        foreach (self::TEACHER_PLAN as $name => $plan) {
            $username = $this->username($name);
            $teacher = GuruTendik::query()->updateOrCreate(['nip' => 'DEMO-'.strtoupper(Str::slug($name))], ['nama' => $name, 'jenis_ptk' => 'Guru', 'jk' => 'L', 'status' => 'aktif']);
            $user = User::query()->updateOrCreate(['username' => $username], ['name' => $name, 'email' => $username.'@demo.invalid', 'password' => Hash::make('DemoPenilaian123!'), 'guru_tendik_id' => $teacher->getKey(), 'guru_mapel_label' => collect($plan)->keys()->map(fn ($code) => self::SUBJECTS[$code]['name'])->implode(', ')]);
            $roles = ['guru'];
            if ($name === 'Fitri Nurfadilah, S.Pd') {
                $roles[] = 'kurikulum';
            }
            if (in_array($name, self::HOMEROOMS, true)) {
                $roles[] = 'wali_kelas';
            }
            $user->syncRoles($roles);
            $accounts[$name] = ['username' => $username, 'nama' => $name, 'peran' => implode(' + ', $roles), 'aksi' => $this->teachingSummary($plan)];
        }
        $username = 'demo-kepala-sekolah';
        $kepala = User::query()->updateOrCreate(['username' => $username], ['name' => 'Demo Kepala Sekolah SMA AFBS', 'email' => $username.'@demo.invalid', 'password' => Hash::make('DemoPenilaian123!'), 'guru_tendik_id' => null]);
        $kepala->syncRoles(['kepala_sekolah']);
        $accounts['kepala'] = ['username' => $username, 'nama' => $kepala->name, 'peran' => 'kepala_sekolah', 'aksi' => 'melihat dan mencetak seluruh rapor'];

        return array_values($accounts);
    }

    private function teachingSummary(array $plan): string
    {
        return collect($plan)->map(fn ($classes, $code) => self::SUBJECTS[$code]['name'].' '.implode(', ', $classes))->implode('; ');
    }

    private function buatMapel(): array
    {
        $categories = [];
        foreach ([['DEMO-WAJIB', 'Demo Kelompok Wajib', 'wajib', 10], ['DEMO-PILIHAN', 'Demo Kelompok Pilihan', 'pilihan', 20]] as [$code, $name, $type, $order]) {
            $categories[str_replace('DEMO-', '', $code)] = SubjectCategory::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'sort_order' => $order, 'description' => self::MARKER, 'is_active' => true],
            );
        }
        $subjects = [];
        foreach (self::SUBJECTS as $code => $definition) {
            $existing = Subject::query()->where('code', $code)->first();
            if ($existing) {
                // Master sekolah dipakai apa adanya, termasuk pengelompokan rapornya.
                $this->mapelDipinjam[$code] = true;
                $subjects[$code] = $existing;

                continue;
            }

            $category = $categories[$definition['category']];
            $this->mapelDibuat[$code] = true;
            $subjects[$code] = Subject::query()->create(['code' => $code, 'name' => $definition['name'], 'description' => self::MARKER, 'report_group_code' => $definition['category'], 'report_group_name' => $category->name, 'report_group_sort_order' => $category->sort_order, 'is_active' => true, 'sort_order' => array_search($code, array_keys(self::SUBJECTS), true) + 1]);
        }

        return [$categories, $subjects];
    }

    private function buatSkema(AssessmentPeriod $period, array $subjects, AssessmentType $type): void
    {
        foreach ($subjects as $subject) {
            $scheme = AssessmentScheme::query()->create(['assessment_period_id' => $period->getKey(), 'assessment_subject_id' => $subject->getKey(), 'name' => 'DEMO-SKEMA-'.$subject->code, 'rounding_precision' => 2, 'minimum_score' => 0, 'maximum_score' => 100, 'settings' => ['kkm' => 75, 'predicates' => [['label' => 'A', 'minimum_score' => 90], ['label' => 'B', 'minimum_score' => 80], ['label' => 'C', 'minimum_score' => 70]], 'demo_owner' => self::MARKER], 'is_active' => true]);
            $components = $type === AssessmentType::ASAS
                ? [['ASTS-SNAPSHOT', 'Nilai ASTS', 40, ScoreSource::ASTS_SNAPSHOT], ['SUMATIF', 'Asesmen Sumatif', 60, ScoreSource::MANUAL]]
                : [['FORMATIF', 'Tugas dan Formatif', 40, ScoreSource::MANUAL], ['SUMATIF', 'Asesmen Sumatif', 60, ScoreSource::MANUAL]];
            foreach ($components as $order => [$code, $name, $weight, $source]) {
                AssessmentComponent::query()->create(['assessment_scheme_id' => $scheme->getKey(), 'code' => 'DEMO-'.$code, 'name' => $name, 'domain' => $name, 'weight' => $weight, 'maximum_score' => 100, 'is_required' => true, 'sort_order' => $order + 1, 'score_source' => $source, 'settings' => ['is_active' => true]]);
            }
        }
    }

    private function buatRombelDanSiswa(AssessmentPeriod $period, int $studentCount): array
    {
        $result = [];
        foreach (self::CLASSES as $classIndex => $name) {
            $existing = Rombel::query()->where('nama', $name)->first();
            if ($existing) {
                // Rombel sekolah hanya direferensikan oleh periode demo.
                $this->rombelDipinjam[$name] = true;
                $source = $existing;
            } else {
                $this->rombelDibuat[$name] = true;
                $source = Rombel::query()->create(['nama' => $name, 'angkatan' => 'DEMO-2526', 'is_active' => true, 'catatan' => self::MARKER]);
            }
            $rombel = AssessmentPeriodRombel::query()->create(['assessment_period_id' => $period->getKey(), 'source_rombel_id' => $source->getKey(), 'rombel_name_snapshot' => $name, 'grade_level' => Str::before($name, ' '), 'is_active' => true]);
            $result[$name] = $rombel;
            for ($i = 1; $i <= $studentCount; $i++) {
                $serial = $classIndex * $studentCount + $i;
                $fullName = self::STUDENT_NAMES[($serial - 1) % count(self::STUDENT_NAMES)].' '.str_pad((string) $serial, 3, '0', STR_PAD_LEFT);
                $sourceStudent = DataSiswa::query()->updateOrCreate(['nisn' => 'DEMO'.str_pad((string) $serial, 7, '0', STR_PAD_LEFT)], ['nama' => $fullName, 'rombel_saat_ini' => $name, 'jk' => $serial % 2 ? 'L' : 'P', 'status' => 'aktif']);
                AssessmentPeriodStudent::query()->create(['assessment_period_id' => $period->getKey(), 'student_id' => $sourceStudent->getKey(), 'assessment_period_rombel_id' => $rombel->getKey(), 'nis_snapshot' => 'DEMO-NIS-'.str_pad((string) $serial, 4, '0', STR_PAD_LEFT), 'nisn_snapshot' => $sourceStudent->nisn, 'student_name_snapshot' => $fullName, 'gender_snapshot' => $sourceStudent->jk, 'rombel_name_snapshot' => $name, 'is_active' => true]);
            }
        }

        return $result;
    }

    private function buatPenugasan(AssessmentPeriod $period, Semester $semester, array $rombels, array $subjects, array $categories): array
    {
        $assignments = [];
        $seen = [];
        foreach (self::TEACHER_PLAN as $teacherName => $plan) {
            $user = User::query()->where('username', $this->username($teacherName))->firstOrFail();
            foreach ($plan as $code => $classes) {
                foreach ($classes as $class) {
                    $key = $code.'|'.$class;
                    if (isset($seen[$key])) {
                        throw ValidationException::withMessages(['matrix' => "Guru ganda untuk {$key}."]);
                    }
                    $seen[$key] = true;
                    $subject = $subjects[$code];
                    $rombel = $rombels[$class];
                    $category = $categories[self::SUBJECTS[$code]['category']];
                    $source = TeachingAssignment::query()->updateOrCreate(['assessment_semester_id' => $semester->getKey(), 'assessment_subject_id' => $subject->getKey(), 'teacher_id' => $user->guru_tendik_id, 'rombel_id' => $rombel->source_rombel_id], ['assessment_subject_category_id' => $category->getKey(), 'teacher_name_snapshot' => $teacherName, 'subject_name_snapshot' => $subject->name, 'rombel_name_snapshot' => $class, 'is_active' => true]);
                    $assignments[] = AssessmentPeriodAssignment::query()->create(['assessment_period_id' => $period->getKey(), 'teacher_id' => $user->guru_tendik_id, 'assessment_subject_id' => $subject->getKey(), 'assessment_period_rombel_id' => $rombel->getKey(), 'source_teaching_assignment_id' => $source->getKey(), 'teacher_name_snapshot' => $teacherName, 'subject_name_snapshot' => $subject->name, 'rombel_name_snapshot' => $class, 'subject_group_code_snapshot' => $subject->report_group_code, 'subject_group_name_snapshot' => $subject->report_group_name, 'subject_group_sort_order_snapshot' => $subject->report_group_sort_order, 'subject_sort_order_snapshot' => $subject->sort_order, 'status' => AssignmentStatus::DRAFT, 'lock_version' => 1]);
                }
            }
        }

        return $assignments;
    }

    private function isiNilai(array $assignments): void
    {
        foreach ($assignments as $assignmentIndex => $assignment) {
            $teacher = User::query()->where('guru_tendik_id', $assignment->teacher_id)->firstOrFail();
            $components = app(AssessmentSchemeResolver::class)->forAssignment($assignment)->components;
            $students = AssessmentPeriodStudent::query()->where('assessment_period_rombel_id', $assignment->assessment_period_rombel_id)->where('is_active', true)->get();
            $rows = $students->values()->map(function ($student, $studentIndex) use ($components, $assignmentIndex) {
                $scores = [];
                foreach ($components as $componentIndex => $component) {
                    $scores[$component->getKey()] = ['score' => 70 + (($studentIndex * 7 + $assignmentIndex * 3 + $componentIndex * 11) % 26), 'notes' => 'Capaian demo konsisten dan berkembang baik.'];
                }

                return ['assessment_period_student_id' => $student->getKey(), 'scores' => $scores];
            })->all();
            app(SaveAssessmentScoresAction::class)->execute($teacher, $assignment, $rows, (int) $assignment->lock_version);
        }
    }

    private function buatRekapWali(AssessmentPeriod $period, Semester $semester, array $rombels): void
    {
        foreach ($rombels as $class => $rombel) {
            $name = self::HOMEROOMS[$class];
            $wali = User::query()->where('username', $this->username($name))->firstOrFail();
            $source = HomeroomAssignment::query()->updateOrCreate(['assessment_semester_id' => $semester->getKey(), 'rombel_id' => $rombel->source_rombel_id], ['teacher_id' => $wali->guru_tendik_id, 'teacher_name_snapshot' => $name, 'rombel_name_snapshot' => $class, 'is_active' => true]);
            AssessmentPeriodHomeroom::query()->create(['assessment_period_id' => $period->getKey(), 'assessment_period_rombel_id' => $rombel->getKey(), 'source_homeroom_assignment_id' => $source->getKey(), 'teacher_id' => $wali->guru_tendik_id, 'teacher_name_snapshot' => $name, 'rombel_name_snapshot' => $class]);
            foreach ($period->students()->where('assessment_period_rombel_id', $rombel->getKey())->get() as $index => $student) {
                HomeroomReport::query()->create(['assessment_period_id' => $period->getKey(), 'assessment_period_student_id' => $student->getKey(), 'sick_days' => $index % 3, 'permission_days' => $index % 2, 'absent_days' => 0, 'spiritual_predicate' => 'Baik', 'spiritual_description' => 'Konsisten menjalankan ibadah dan menunjukkan rasa syukur.', 'social_predicate' => 'Baik', 'social_description' => 'Santun, peduli, dan mampu bekerja sama.', 'extracurricular_data' => [['name' => 'Pramuka', 'predicate' => 'Baik']], 'achievement_data' => [], 'homeroom_note' => 'Pertahankan semangat belajar dan akhlak baik.', 'promotion_status' => 'naik', 'updated_by' => $wali->getKey()]);
            }
        }
    }

    private function buatTemplate(AssessmentType $type): ReportTemplate
    {
        return ReportTemplate::query()->updateOrCreate(['code' => 'DEMO-'.strtoupper($type->value).'-STANDARD', 'version' => 1], ['type' => $type, 'name' => 'Demo Template '.strtoupper($type->value), 'view_path' => match ($type) {
            AssessmentType::ASTS => 'assessment.reports.asts',
            AssessmentType::ASAS => 'assessment.reports.asas',
            AssessmentType::ASAT => 'assessment.reports.asat',
        }, 'settings' => ['school_name' => 'SMA Al Furqon Boarding School', 'place' => 'Tangerang', 'principal_name' => 'Demo Kepala Sekolah SMA AFBS', 'show_predicate' => true, 'show_description' => true, 'demo_owner' => self::MARKER], 'is_active' => true]);
    }

    private function cetak(AssessmentPeriod $period, ReportTemplate $template, User $actor): array
    {
        if ($period->status !== AssessmentPeriodStatus::LOCKED) {
            throw ValidationException::withMessages(['status' => 'PDF hanya dapat dibuat setelah periode dikunci.']);
        }
        app(ScheduleReportClassesAction::class)->execute($actor, $period, $template, $period->periodRombels()->pluck('id')->all());
        $renderer = app(AssessmentReportRenderer::class);
        $storage = app(AssessmentReportStorage::class);
        $paths = ClassReportArtifact::query()->where('assessment_period_id', $period->getKey())->whereNotNull('pdf_path')->pluck('pdf_path')->all();
        foreach (ReportSnapshot::query()->where('assessment_period_id', $period->getKey())->get() as $snapshot) {
            $stored = $storage->putAtomically($storage->individualPath($snapshot), $renderer->renderStudent($snapshot));
            $snapshot->forceFill(['delivery_mode' => 'stored', 'generation_status' => 'completed', 'pdf_path' => $stored['path'], 'checksum' => $stored['checksum'], 'generated_at' => now(), 'generated_by' => $actor->getKey()])->save();
            $paths[] = $stored['path'];
        }

        return array_values(array_unique($paths));
    }

    private function pdfPaths(AssessmentPeriod $period): array
    {
        return array_values(array_unique([
            ...ReportSnapshot::query()->where('assessment_period_id', $period->getKey())->whereNotNull('pdf_path')->pluck('pdf_path')->all(),
            ...ClassReportArtifact::query()->where('assessment_period_id', $period->getKey())->whereNotNull('pdf_path')->pluck('pdf_path')->all(),
        ]));
    }

    private function tampilkanRencana(string $year, int $students, bool $full, AssessmentType $type, AssessmentPeriodStatus $status, bool $print): void
    {
        $this->components->twoColumnDetail('Tahun ajaran', $year);
        $this->components->twoColumnDetail('Periode', $full ? 'ASTS Ganjil + ASAS Ganjil + ASAT Genap' : strtoupper($type->value));
        $this->components->twoColumnDetail('Status akhir', $status->value);
        $this->components->twoColumnDetail('Kelas / siswa', count(self::CLASSES).' / '.(count(self::CLASSES) * $students));
        $this->components->twoColumnDetail('Guru / mapel', count(self::TEACHER_PLAN).' / '.count(self::SUBJECTS));
        $this->components->twoColumnDetail('Cetak PDF', $print ? 'ya' : 'tidak');
    }

    private function ringkasan(array $contexts): void
    {
        $this->newLine();
        $this->components->info('Data demo Penilaian selesai dibuat.');
        foreach ($contexts as $context) {
            $period = $context['period']->fresh();
            $this->components->twoColumnDetail($period->code, implode(' | ', [strtoupper($period->type->value), $period->semester->name, $period->status->value, 'penugasan '.$period->assignments()->count(), 'nilai '.AssessmentScore::query()->whereHas('assignment', fn ($q) => $q->where('assessment_period_id', $period->getKey()))->count(), 'snapshot '.ReportSnapshot::query()->where('assessment_period_id', $period->getKey())->count(), 'PDF '.count($context['paths'])]));
        }
        $this->newLine();
        $this->components->twoColumnDetail('Mapel master dipinjam / dibuat generator', count($this->mapelDipinjam).' / '.count($this->mapelDibuat));
        $this->components->twoColumnDetail('Rombel master dipinjam / dibuat generator', count($this->rombelDipinjam).' / '.count($this->rombelDibuat));
        $this->components->warn('KREDENSIAL DEMO (ditampilkan sekali): password semua akun = DemoPenilaian123!');
        foreach ($contexts[0]['accounts'] as $account) {
            $this->components->twoColumnDetail($account['username'], $account['nama'].' | '.$account['peran'].' | '.$account['aksi']);
        }
        $this->newLine();
        $this->components->info('Wali kelas deterministik (dokumen tidak menetapkan wali kelas):');
        foreach (self::HOMEROOMS as $class => $teacher) {
            $this->components->twoColumnDetail($class, $teacher);
        }
        $this->components->warn('Fitri memverifikasi pengisiannya sendiri karena sekaligus guru dan kurikulum; ini konsekuensi matriks resmi.');
        foreach (['/admin/penilaian', '/admin/penilaian/matriks-penugasan', '/admin/penilaian/setelan-awal', '/admin/penilaian/progres-rapor'] as $url) {
            $this->components->twoColumnDetail('URL', $url);
        }
        foreach (['asts', 'asas', 'asat'] as $type) {
            foreach (['', '/input-nilai', '/status-pengumpulan', '/rekap-wali-kelas', '/cetak-rapor'] as $suffix) {
                $this->components->twoColumnDetail('URL '.strtoupper($type), '/admin/penilaian/'.$type.$suffix);
            }
        }
        foreach ($contexts as $context) {
            foreach ($context['paths'] as $path) {
                $this->components->twoColumnDetail('PDF', $path);
            }
        }
    }

    private function bersihkan(): int
    {
        $periods = AssessmentPeriod::query()->where('code', 'like', 'DEMO-%')->get();
        $users = User::query()->where('username', 'like', 'demo-%')->get();
        $teachers = GuruTendik::query()->where('nip', 'like', 'DEMO-%')->get();
        $students = DataSiswa::query()->where('nisn', 'like', 'DEMO%')->get();
        $rombels = Rombel::query()->where(fn ($query) => $query->where('catatan', 'like', '%'.self::MARKER.'%')->orWhere('nama', 'like', 'DEMO %'))->get();
        $subjects = Subject::query()->where(fn ($query) => $query->where('description', 'like', '%'.self::MARKER.'%')->orWhere('code', 'like', 'DEMO-%'))->get();
        $categories = SubjectCategory::query()->where('description', 'like', '%'.self::MARKER.'%')->get();
        $semesterIds = Semester::query()->where('code', 'like', 'DEMO-%')->pluck('id');
        $mapelSekolah = Subject::query()->whereIn('code', array_keys(self::SUBJECTS))->where(fn ($query) => $query->whereNull('description')->orWhere('description', 'not like', '%'.self::MARKER.'%'))->count();
        $rombelSekolah = Rombel::query()->whereIn('nama', self::CLASSES)->where(fn ($query) => $query->whereNull('catatan')->orWhere('catatan', 'not like', '%'.self::MARKER.'%'))->count();
        $kategoriSekolah = SubjectCategory::query()->whereIn('code', ['DEMO-WAJIB', 'DEMO-PILIHAN'])->where(fn ($query) => $query->whereNull('description')->orWhere('description', 'not like', '%'.self::MARKER.'%'))->count();
        foreach ([$periods->pluck('code'), $users->pluck('username'), $teachers->pluck('nip'), $students->pluck('nisn')] as $markers) {
            if ($markers->contains(fn ($marker) => ! Str::startsWith(strtoupper((string) $marker), 'DEMO'))) {
                $this->components->error('Pembersihan ditolak karena ditemukan baris tanpa penanda DEMO.');

                return self::FAILURE;
            }
        }
        foreach (['Periode' => $periods->count(), 'Akun' => $users->count(), 'Guru' => $teachers->count(), 'Siswa' => $students->count(), 'Rombel' => $rombels->count(), 'Mapel' => $subjects->count(), 'Kategori mapel' => $categories->count()] as $label => $count) {
            $this->components->twoColumnDetail($label.' akan dihapus', (string) $count);
        }
        foreach (['Mapel sekolah' => $mapelSekolah, 'Rombel sekolah' => $rombelSekolah, 'Kategori mapel sekolah' => $kategoriSekolah] as $label => $count) {
            $this->components->twoColumnDetail($label.' dipertahankan', (string) $count);
        }
        DB::transaction(function () use ($periods, $users, $teachers, $students, $rombels, $subjects, $categories, $semesterIds) {
            foreach ($periods as $period) {
                DB::table('assessment_report_share_links')->whereIn('assessment_report_snapshot_id', ReportSnapshot::query()->where('assessment_period_id', $period->getKey())->pluck('id'))->delete();
                DB::table('assessment_class_report_artifacts')->where('assessment_period_id', $period->getKey())->delete();
                DB::table('assessment_report_snapshots')->where('assessment_period_id', $period->getKey())->delete();
                DB::table('assessment_report_generation_runs')->where('assessment_period_id', $period->getKey())->delete();
                DB::table('assessment_audit_logs')->where('assessment_period_id', $period->getKey())->delete();
                // Hapus turunan ber-FK restrict sebelum cascade periode pada SQLite maupun MySQL.
                AssessmentPeriodHomeroom::query()->where('assessment_period_id', $period->getKey())->delete();
                AssessmentPeriodAssignment::query()->where('assessment_period_id', $period->getKey())->delete();
                AssessmentPeriodStudent::query()->where('assessment_period_id', $period->getKey())->delete();
                AssessmentPeriodRombel::query()->where('assessment_period_id', $period->getKey())->delete();
                $period->delete();
            }
            TeachingAssignment::query()->whereIn('assessment_semester_id', $semesterIds)->delete();
            HomeroomAssignment::query()->whereIn('assessment_semester_id', $semesterIds)->delete();
            ReportTemplate::query()->where('code', 'like', 'DEMO-%')->delete();
            $subjects->each->delete();
            $categories->each->delete();
            $users->each->delete();
            $teachers->each->delete();
            // Hindari event DataSiswa yang menghapus rombel kosong milik sekolah.
            DB::table('data_siswa')->whereIn('id', $students->modelKeys())->delete();
            $rombels->each->delete();
            Semester::query()->where('code', 'like', 'DEMO-%')->delete();
            AcademicYear::query()->where('code', 'like', 'DEMO-%')->delete();
        });
        $this->components->info('Pembersihan data DEMO selesai. Data lain tidak disentuh.');

        return self::SUCCESS;
    }

    private function tablesReady(): bool
    {
        return collect(['users', 'guru_tendik', 'data_siswa', 'rombels', 'roles', 'permissions', 'assessment_periods', 'assessment_subjects', 'assessment_report_templates'])->every(fn ($table) => Schema::hasTable($table));
    }
}
