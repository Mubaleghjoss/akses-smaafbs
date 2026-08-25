<?php

namespace App\Support\Assessment;

use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\SubjectCategory;
use App\Models\Assessment\TeachingAssignment;
use App\Models\Rombel;
use Illuminate\Support\Collection;

/**
 * Memeriksa KESIAPAN SETELAN AWAL penilaian untuk satu semester.
 *
 * Latar belakang: untuk mulai menilai, admin harus menyentuh 7 tempat berbeda
 * (tahun ajaran, semester, kategori mapel, mapel, penugasan mengajar, wali
 * kelas, skema) tanpa satu pun halaman yang memberi tahu "langkah 3 belum
 * selesai". Kelas ini menyediakan status tiap langkah beserta APA yang kurang —
 * nama kelas, nama rombel — bukan hanya "belum lengkap".
 *
 * Hanya MEMBACA; tidak mengubah data.
 */
class AssessmentSetupStatus
{
    public const SELESAI = 'selesai';

    public const KURANG = 'kurang';

    public const BELUM = 'belum';

    /**
     * @return array{
     *   semester: Semester|null,
     *   siap: bool,
     *   langkah_selesai: int,
     *   total_langkah: int,
     *   langkah: array<int, array<string, mixed>>
     * }
     */
    public function untukSemester(?Semester $semester): array
    {
        $langkah = [
            $this->langkahTahunSemester($semester),
            $this->langkahMapel(),
            $this->langkahPenugasanMengajar($semester),
            $this->langkahWaliKelas($semester),
            $this->langkahSkema($semester),
            $this->langkahPeriode($semester),
        ];

        // Langkah berikutnya terkunci bila langkah sebelumnya belum hijau.
        $terkunci = false;
        foreach ($langkah as $i => $l) {
            $langkah[$i]['terkunci'] = $terkunci;

            if ($l['status'] !== self::SELESAI) {
                $terkunci = true;
            }
        }

        $selesai = collect($langkah)->where('status', self::SELESAI)->count();

        return [
            'semester' => $semester,
            'siap' => $selesai === count($langkah),
            'langkah_selesai' => $selesai,
            'total_langkah' => count($langkah),
            'langkah' => $langkah,
        ];
    }

    /**
     * Semester yang paling wajar ditampilkan: yang aktif, atau yang terbaru.
     */
    public function semesterBawaan(): ?Semester
    {
        return Semester::query()->where('is_active', true)->latest('starts_on')->first()
            ?? Semester::query()->latest('starts_on')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function langkahTahunSemester(?Semester $semester): array
    {
        $adaTahun = AcademicYear::query()->exists();

        if (! $semester) {
            return $this->baris(
                1,
                'Tahun ajaran & semester aktif',
                $adaTahun ? self::KURANG : self::BELUM,
                $adaTahun
                    ? 'Tahun ajaran sudah ada, tetapi belum ada semester yang dipilih.'
                    : 'Belum ada tahun ajaran maupun semester.',
                'Buat tahun ajaran dan semester lebih dulu — seluruh setelan lain menempel padanya.',
            );
        }

        $kind = app(SemesterKind::class)->dari($semester);

        return $this->baris(
            1,
            'Tahun ajaran & semester aktif',
            self::SELESAI,
            trim(($semester->academicYear?->name ?? '-').' · '.$semester->name
                .($kind ? ' ('.$kind.')' : '')),
            $kind === null
                ? 'Semester ini tidak dikenali ganjil/genap. ASAT tidak akan dibatasi — beri nama yang memuat kata "Ganjil"/"Genap" bila ingin dibatasi.'
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function langkahMapel(): array
    {
        $kategori = SubjectCategory::query()->count();
        $mapel = Subject::query()->where('is_active', true)->count();

        if ($mapel === 0) {
            return $this->baris(2, 'Mapel & kategori', self::BELUM,
                'Belum ada mata pelajaran aktif.',
                'Tambahkan mata pelajaran; kategori dipakai sebagai pengelompokan pada rapor.');
        }

        // Mapel tanpa kelompok rapor akan menumpuk di satu blok tak bernama.
        // PENTING: kolomnya BUKAN nullable — migrasi memberi nilai bawaan
        // 'BELUM' / 'Belum Dikelompokkan', jadi memeriksa null saja tidak cukup.
        $tanpaKelompok = Subject::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereNull('report_group_code')
                ->orWhere('report_group_code', '')
                ->orWhere('report_group_code', 'BELUM'))
            ->pluck('name');

        if ($tanpaKelompok->isNotEmpty()) {
            return $this->baris(2, 'Mapel & kategori', self::KURANG,
                sprintf('%d kategori · %d mapel · %d mapel belum punya kelompok rapor',
                    $kategori, $mapel, $tanpaKelompok->count()),
                'Belum berkelompok: '.$tanpaKelompok->take(6)->implode(', ')
                    .($tanpaKelompok->count() > 6 ? ', dan '.($tanpaKelompok->count() - 6).' lainnya' : ''));
        }

        return $this->baris(2, 'Mapel & kategori', self::SELESAI,
            sprintf('%d kategori · %d mapel aktif', $kategori, $mapel));
    }

    /**
     * @return array<string, mixed>
     */
    private function langkahPenugasanMengajar(?Semester $semester): array
    {
        if (! $semester) {
            return $this->baris(3, 'Guru mengajar apa, di kelas mana', self::BELUM,
                'Menunggu semester dipilih.');
        }

        $penugasan = TeachingAssignment::query()
            ->where('assessment_semester_id', $semester->getKey())
            ->where('is_active', true)
            ->get(['rombel_id', 'assessment_subject_id', 'teacher_id']);

        if ($penugasan->isEmpty()) {
            return $this->baris(3, 'Guru mengajar apa, di kelas mana', self::BELUM,
                'Belum ada penugasan mengajar untuk semester ini.',
                'Gunakan matriks kelas × mapel, atau salin dari semester sebelumnya.');
        }

        $rombelAktif = $this->rombelAktif();
        $mapelAktif = Subject::query()->where('is_active', true)->count();
        $terisiPerRombel = $penugasan->groupBy('rombel_id')->map->count();

        $kurang = $rombelAktif
            ->filter(fn (Rombel $r): bool => ($terisiPerRombel[$r->getKey()] ?? 0) < $mapelAktif)
            ->map(fn (Rombel $r): string => sprintf('%s (%d/%d)',
                $r->nama, $terisiPerRombel[$r->getKey()] ?? 0, $mapelAktif))
            ->values();

        if ($kurang->isNotEmpty()) {
            return $this->baris(3, 'Guru mengajar apa, di kelas mana', self::KURANG,
                sprintf('%d penugasan · %d kelas belum lengkap', $penugasan->count(), $kurang->count()),
                'Belum lengkap: '.$kurang->take(6)->implode(', ')
                    .($kurang->count() > 6 ? ', dan '.($kurang->count() - 6).' lainnya' : ''));
        }

        return $this->baris(3, 'Guru mengajar apa, di kelas mana', self::SELESAI,
            sprintf('%d penugasan · %d kelas lengkap', $penugasan->count(), $rombelAktif->count()));
    }

    /**
     * @return array<string, mixed>
     */
    private function langkahWaliKelas(?Semester $semester): array
    {
        if (! $semester) {
            return $this->baris(4, 'Wali kelas tiap rombel', self::BELUM, 'Menunggu semester dipilih.');
        }

        $wali = HomeroomAssignment::query()
            ->where('assessment_semester_id', $semester->getKey())
            ->where('is_active', true)
            ->pluck('rombel_id');

        $rombelAktif = $this->rombelAktif();
        $tanpaWali = $rombelAktif
            ->reject(fn (Rombel $r): bool => $wali->contains($r->getKey()))
            ->map(fn (Rombel $r): string => (string) $r->nama)
            ->values();

        if ($rombelAktif->isEmpty()) {
            return $this->baris(4, 'Wali kelas tiap rombel', self::BELUM,
                'Belum ada rombel aktif.', 'Aktifkan rombel lebih dulu di menu Rombel.');
        }

        if ($tanpaWali->isNotEmpty()) {
            return $this->baris(4, 'Wali kelas tiap rombel', self::KURANG,
                sprintf('%d dari %d rombel sudah punya wali', $wali->unique()->count(), $rombelAktif->count()),
                'Belum ada wali: '.$tanpaWali->take(8)->implode(', '));
        }

        return $this->baris(4, 'Wali kelas tiap rombel', self::SELESAI,
            sprintf('%d rombel sudah punya wali kelas', $rombelAktif->count()));
    }

    /**
     * @return array<string, mixed>
     */
    private function langkahSkema(?Semester $semester): array
    {
        if (! $semester) {
            return $this->baris(5, 'Skema & bobot komponen', self::BELUM, 'Menunggu semester dipilih.');
        }

        // Skema menempel pada PERIODE, jadi diperiksa lewat periode semester ini.
        $periodeIds = AssessmentPeriod::query()
            ->where('assessment_semester_id', $semester->getKey())
            ->pluck('id');

        if ($periodeIds->isEmpty()) {
            return $this->baris(5, 'Skema & bobot komponen', self::BELUM,
                'Belum ada periode, sehingga skema belum dapat dibuat.',
                'Skema menempel pada periode — buat periodenya lebih dulu (langkah 6).');
        }

        $skema = \App\Models\Assessment\AssessmentScheme::query()
            ->whereIn('assessment_period_id', $periodeIds->all())
            ->where('is_active', true)
            ->get(['id', 'name']);

        if ($skema->isEmpty()) {
            return $this->baris(5, 'Skema & bobot komponen', self::BELUM,
                'Belum ada skema penilaian aktif.',
                'Skema menentukan komponen nilai dan bobotnya (mis. UH 30 · Tugas 30 · Ujian 40).');
        }

        $tanpaKomponen = $skema->filter(fn ($s): bool => \App\Models\Assessment\AssessmentComponent::query()
            ->where('assessment_scheme_id', $s->id)->doesntExist());

        if ($tanpaKomponen->isNotEmpty()) {
            return $this->baris(5, 'Skema & bobot komponen', self::KURANG,
                sprintf('%d skema · %d belum punya komponen', $skema->count(), $tanpaKomponen->count()),
                'Skema tanpa komponen: '.$tanpaKomponen->pluck('name')->take(5)->implode(', '));
        }

        return $this->baris(5, 'Skema & bobot komponen', self::SELESAI,
            sprintf('%d skema aktif, semuanya sudah punya komponen', $skema->count()));
    }

    /**
     * @return array<string, mixed>
     */
    private function langkahPeriode(?Semester $semester): array
    {
        if (! $semester) {
            return $this->baris(6, 'Buka periode penilaian', self::BELUM, 'Menunggu semester dipilih.');
        }

        $periode = AssessmentPeriod::query()
            ->where('assessment_semester_id', $semester->getKey())
            ->get(['id', 'name', 'type', 'status']);

        if ($periode->isEmpty()) {
            return $this->baris(6, 'Buka periode penilaian', self::BELUM,
                'Belum ada periode pada semester ini.',
                'ASAT hanya dapat dibuka pada semester genap.');
        }

        $terbuka = $periode->filter(fn ($p): bool => in_array(
            $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status,
            ['open', 'verification'],
            true,
        ));

        $daftar = $periode
            ->map(fn ($p): string => ($p->type instanceof \BackedEnum ? strtoupper($p->type->value) : strtoupper((string) $p->type))
                .' · '.$p->name)
            ->implode('; ');

        return $this->baris(
            6,
            'Buka periode penilaian',
            $terbuka->isNotEmpty() ? self::SELESAI : self::KURANG,
            sprintf('%d periode · %d sedang berjalan', $periode->count(), $terbuka->count()),
            $daftar !== '' ? $daftar : null,
        );
    }

    /**
     * @return Collection<int, Rombel>
     */
    private function rombelAktif(): Collection
    {
        return Rombel::query()->where('is_active', true)->orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * @return array<string, mixed>
     */
    private function baris(int $nomor, string $judul, string $status, string $ringkasan, ?string $catatan = null): array
    {
        return [
            'nomor' => $nomor,
            'judul' => $judul,
            'status' => $status,
            'ringkasan' => $ringkasan,
            'catatan' => $catatan,
            'terkunci' => false,
        ];
    }
}
