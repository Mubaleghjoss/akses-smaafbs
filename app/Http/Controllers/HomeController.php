<?php

namespace App\Http\Controllers;

use App\Models\Berita;
use App\Models\CalendarEvent;
use App\Models\DataSiswa;
use App\Models\PerpustakaanBuku;
use App\Models\Prestasi;
use App\Models\ProfilSekolah;
use App\Models\ProkerBidang;
use App\Models\Rombel;
use App\Models\StrukturOrganisasi;
use App\Models\VisiMisi;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    protected ?bool $dataSiswaSearchColumnsAvailable = null;

    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $hasDataSiswaTable = Schema::hasTable('data_siswa');
        $hasDataSiswaStatusColumn = $hasDataSiswaTable && Schema::hasColumn('data_siswa', 'status');
        $hasDataSiswaGenderColumn = $hasDataSiswaTable && Schema::hasColumn('data_siswa', 'jk');
        $hasDataSiswaRombelColumn = $hasDataSiswaTable && Schema::hasColumn('data_siswa', 'rombel_saat_ini');
        $activeRombelNames = null;

        try {
            if ($hasDataSiswaRombelColumn
                && Schema::hasTable('rombels')
                && Schema::hasColumn('rombels', 'is_active')) {
                $activeRombelNames = Rombel::query()
                    ->where('is_active', true)
                    ->orderBy('nama')
                    ->pluck('nama')
                    ->filter(fn ($name): bool => filled($name))
                    ->values();
            }
        } catch (\Throwable $e) {
            // Keep compatibility with installations that do not have the rombel master yet.
        }

        $guruTendikTable = Schema::hasTable('guru_tendik')
            ? 'guru_tendik'
            : (Schema::hasTable('guru_tendiks') ? 'guru_tendiks' : null);
        $hasGuruTendikGenderColumn = $guruTendikTable !== null && Schema::hasColumn($guruTendikTable, 'jk');
        $hasGuruTendikJenisColumn = $guruTendikTable !== null && Schema::hasColumn($guruTendikTable, 'jenis_ptk');

        $prokerTable = Schema::hasTable('prokers')
            ? 'prokers'
            : (Schema::hasTable('proker') ? 'proker' : null);
        $hasProkerStatusColumn = $prokerTable !== null && Schema::hasColumn($prokerTable, 'status');

        $prokerBidangTable = Schema::hasTable('proker_bidangs')
            ? 'proker_bidangs'
            : (Schema::hasTable('proker_bidang') ? 'proker_bidang' : null);

        $booksCount = 0;
        $achievementsCount = 0;
        try {
            $booksCount = PerpustakaanBuku::query()->count();
        } catch (\Throwable $e) {
            // ignore missing optional module table
        }

        try {
            if (Schema::hasTable('prestasis')) {
                $achievementsCount = Prestasi::query()->count();
            }
        } catch (\Throwable $e) {
            // ignore missing optional module table
        }

        $activeStudentsCount = 0;
        if ($hasDataSiswaStatusColumn) {
            $activeStudentsQuery = DataSiswa::query()->where('status', 'aktif');

            if ($activeRombelNames instanceof Collection) {
                $activeStudentsQuery->whereIn('rombel_saat_ini', $activeRombelNames);
            }

            $activeStudentsCount = $activeStudentsQuery->count();
        }

        $stats = [
            'students' => $hasDataSiswaTable ? DataSiswa::query()->count() : 0,
            'active_students' => $activeStudentsCount,
            'alumni_students' => $hasDataSiswaStatusColumn ? DataSiswa::query()->where('status', 'alumni')->count() : 0,
            'achievements' => $achievementsCount,
            'news' => Berita::query()->where('status', 'aktif')->count(),
            'books' => $booksCount,
            'student_male' => 0,
            'student_female' => 0,
            'guru_tendik_male' => 0,
            'guru_tendik_female' => 0,
            'student_active_male' => 0,
            'student_active_female' => 0,
            'guru_count' => 0,
            'guru_male' => 0,
            'guru_female' => 0,
            'tendik_count' => 0,
            'tendik_male' => 0,
            'tendik_female' => 0,
            'pamong_count' => 0,
            'pamong_male' => 0,
            'pamong_female' => 0,
            'rombel_count' => 0,
            'proker_count' => 0,
            'rombel_items' => [],
            'proker_bidang_count' => 0,
            'proker_status_draft' => 0,
            'proker_status_berjalan' => 0,
            'proker_status_selesai' => 0,
        ];

        try {
            if ($hasDataSiswaGenderColumn) {
                $studentGenderCounts = DataSiswa::query()
                    ->selectRaw('jk, count(*) as total')
                    ->whereIn('jk', ['L', 'P'])
                    ->groupBy('jk')
                    ->pluck('total', 'jk');

                $stats['student_male'] = (int) ($studentGenderCounts['L'] ?? 0);
                $stats['student_female'] = (int) ($studentGenderCounts['P'] ?? 0);

                $activeStudentGenderQuery = DataSiswa::query()->whereIn('jk', ['L', 'P']);
                if ($hasDataSiswaStatusColumn) {
                    $activeStudentGenderQuery->where('status', 'aktif');
                }
                if ($activeRombelNames instanceof Collection) {
                    $activeStudentGenderQuery->whereIn('rombel_saat_ini', $activeRombelNames);
                }

                $activeStudentGenderCounts = $activeStudentGenderQuery
                    ->selectRaw('jk, count(*) as total')
                    ->groupBy('jk')
                    ->pluck('total', 'jk');

                $stats['student_active_male'] = (int) ($activeStudentGenderCounts['L'] ?? 0);
                $stats['student_active_female'] = (int) ($activeStudentGenderCounts['P'] ?? 0);
            }
        } catch (\Throwable $e) {
            // ignore optional/legacy gender column issues
        }

        try {
            if ($hasGuruTendikJenisColumn) {
                $jenisPtkCounts = DB::table($guruTendikTable)
                    ->selectRaw('jenis_ptk, count(*) as total')
                    ->whereIn('jenis_ptk', ['Guru', 'Tendik', 'Pamong'])
                    ->groupBy('jenis_ptk')
                    ->pluck('total', 'jenis_ptk');

                $stats['guru_count'] = (int) ($jenisPtkCounts['Guru'] ?? 0);
                $stats['tendik_count'] = (int) ($jenisPtkCounts['Tendik'] ?? 0);
                $stats['pamong_count'] = (int) ($jenisPtkCounts['Pamong'] ?? 0);
            }

            if ($hasGuruTendikGenderColumn) {
                $guruTendikGenderCounts = DB::table($guruTendikTable)
                    ->selectRaw('jk, count(*) as total')
                    ->whereIn('jk', ['L', 'P'])
                    ->groupBy('jk')
                    ->pluck('total', 'jk');

                $stats['guru_tendik_male'] = (int) ($guruTendikGenderCounts['L'] ?? 0);
                $stats['guru_tendik_female'] = (int) ($guruTendikGenderCounts['P'] ?? 0);

                if ($hasGuruTendikJenisColumn) {
                    $guruTendikRows = DB::table($guruTendikTable)
                        ->select(['jenis_ptk', 'jk'])
                        ->selectRaw('count(*) as total')
                        ->whereIn('jk', ['L', 'P'])
                        ->groupBy('jenis_ptk', 'jk')
                        ->get();

                    foreach ($guruTendikRows as $row) {
                        $jenis = strtolower(trim((string) $row->jenis_ptk));
                        $jk = strtoupper((string) $row->jk);
                        $total = (int) ($row->total ?? 0);

                        if ($jenis === 'guru') {
                            if ($jk === 'L') {
                                $stats['guru_male'] = $total;
                            }

                            if ($jk === 'P') {
                                $stats['guru_female'] = $total;
                            }
                        }

                        if ($jenis === 'tendik') {
                            if ($jk === 'L') {
                                $stats['tendik_male'] = $total;
                            }

                            if ($jk === 'P') {
                                $stats['tendik_female'] = $total;
                            }
                        }

                        if ($jenis === 'pamong') {
                            if ($jk === 'L') {
                                $stats['pamong_male'] = $total;
                            }

                            if ($jk === 'P') {
                                $stats['pamong_female'] = $total;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore optional/legacy guru tendik table issues
        }

        try {
            if ($hasDataSiswaRombelColumn) {
                $rombelQuery = DataSiswa::query()
                    ->whereNotNull('rombel_saat_ini')
                    ->where('rombel_saat_ini', '!=', '');

                if ($hasDataSiswaStatusColumn) {
                    $rombelQuery->where('status', 'aktif');
                }
                if ($activeRombelNames instanceof Collection) {
                    $rombelQuery->whereIn('rombel_saat_ini', $activeRombelNames);
                }

                $rombelStudentCounts = $rombelQuery
                    ->select('rombel_saat_ini')
                    ->selectRaw('count(*) as total_students')
                    ->groupBy('rombel_saat_ini')
                    ->orderBy('rombel_saat_ini')
                    ->pluck('total_students', 'rombel_saat_ini');

                if ($activeRombelNames instanceof Collection) {
                    $stats['rombel_count'] = $activeRombelNames->count();
                    $stats['rombel_items'] = $activeRombelNames
                        ->map(fn ($name): array => [
                            'name' => (string) $name,
                            'students' => (int) ($rombelStudentCounts[$name] ?? 0),
                        ])
                        ->all();
                } else {
                    $stats['rombel_count'] = $rombelStudentCounts->count();
                    $stats['rombel_items'] = $rombelStudentCounts
                        ->map(fn ($total, $name): array => [
                            'name' => (string) $name,
                            'students' => (int) $total,
                        ])
                        ->values()
                        ->all();
                }
            }
        } catch (\Throwable $e) {
            // ignore optional/legacy rombel source issues
        }

        try {
            if ($prokerTable !== null) {
                $stats['proker_count'] = DB::table($prokerTable)->count();

                if ($hasProkerStatusColumn) {
                    $prokerStatusCounts = DB::table($prokerTable)
                        ->selectRaw('status, count(*) as total')
                        ->whereIn('status', ['draft', 'berjalan', 'selesai'])
                        ->groupBy('status')
                        ->pluck('total', 'status');

                    $stats['proker_status_draft'] = (int) ($prokerStatusCounts['draft'] ?? 0);
                    $stats['proker_status_berjalan'] = (int) ($prokerStatusCounts['berjalan'] ?? 0);
                    $stats['proker_status_selesai'] = (int) ($prokerStatusCounts['selesai'] ?? 0);
                }
            }

            if ($prokerBidangTable !== null) {
                $stats['proker_bidang_count'] = $prokerBidangTable === ProkerBidang::query()->getModel()->getTable()
                    ? ProkerBidang::query()->count()
                    : DB::table($prokerBidangTable)->count();
            }
        } catch (\Throwable $e) {
            // ignore optional/legacy proker table issues
        }

        $strukturOrganisasi = collect();
        $komiteOrganisasi = collect();
        $komitePeriods = collect();
        $visiMisi = null;
        $profilSekolah = null;
        $prestasiSiswa = collect();

        try {
            if (Schema::hasTable('struktur_organisasis')) {
                [$strukturOrganisasi, $komiteOrganisasi, $komitePeriods] = $this->loadHomepageProfileBranches();
            }
        } catch (\Throwable $e) {
            // ignore missing optional structure table
        }

        try {
            if (Schema::hasTable('visi_misis')) {
                $visiMisi = VisiMisi::query()->first();
            }
        } catch (\Throwable $e) {
            // ignore missing optional visi misi table
        }

        try {
            if (Schema::hasTable('profil_sekolahs')) {
                $profilSekolah = ProfilSekolah::query()->first();
            }
        } catch (\Throwable $e) {
            // ignore missing optional profil sekolah table
        }

        try {
            if (Schema::hasTable('prestasis') && Schema::hasTable('data_siswa')) {
                $prestasiStudentColumns = ['id', 'nama'];

                if ($hasDataSiswaRombelColumn) {
                    $prestasiStudentColumns[] = 'rombel_saat_ini';
                }

                $prestasiSiswa = Prestasi::query()
                    ->with(['siswa' => fn ($query) => $query->select($prestasiStudentColumns)])
                    ->whereHas('siswa')
                    ->orderByDesc('tanggal_prestasi')
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get();
            }
        } catch (\Throwable $e) {
            // ignore missing optional prestasi table
        }

        $trackerNews = collect();
        if (Berita::hasAnyTrackerColumn()) {
            $trackerNews = Berita::query()
                ->where('status', 'aktif')
                ->where(function ($query): void {
                    if (Berita::trackerPhaseColumnAvailable()) {
                        $query->orWhereNotNull('tracker_phase');
                    }

                    if (Berita::trackerProgressPercentColumnAvailable()) {
                        $query->orWhereNotNull('tracker_progress_percent');
                    }

                    if (Berita::trackerUpdateTextColumnAvailable()) {
                        $query->orWhereNotNull('tracker_update_text');
                    }

                    if (Berita::trackerLiveUrlColumnAvailable()) {
                        $query->orWhereNotNull('tracker_live_url');
                    }

                    if (Berita::trackerDocumentationMediaColumnAvailable()) {
                        $query->orWhereNotNull('tracker_documentation_media');
                    }

                    if (Berita::updatesTableAvailable()) {
                        $query->orWhereHas('updates');
                    }
                })
                ->when(Berita::updatesTableAvailable(), fn ($query) => $query->with('latestUpdate'))
                ->orderByDesc('tanggal_berita')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();
        }

        $studentResults = $this->searchStudents($q);

        $externalEvents = collect();
        try {
            $externalEvents = CalendarEvent::query()
                ->where('visibility', 'external')
                ->orderBy('start')
                ->limit(8)
                ->get();
        } catch (\Throwable $e) {
            // ignore
        }

        return view('home', [
            'title' => 'Beranda',
            'q' => $q,
            'stats' => $stats,
            'strukturOrganisasi' => $strukturOrganisasi,
            'komiteOrganisasi' => $komiteOrganisasi,
            'komitePeriods' => $komitePeriods,
            'visiMisi' => $visiMisi,
            'profilSekolah' => $profilSekolah,
            'prestasiSiswa' => $prestasiSiswa,
            'studentResults' => $studentResults,
            'trackerNews' => $trackerNews,
            'externalEvents' => $externalEvents,
        ]);
    }

    public function studentSearch(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $studentResults = $this->searchStudents($q);

        return response()->json([
            'query' => $q,
            'results' => $this->formatStudentSearchResults($studentResults),
        ]);
    }

    protected function searchStudents(string $q)
    {
        $q = trim($q);

        if ($q === '' || mb_strlen($q) < 2 || ! $this->hasDataSiswaSearchColumns()) {
            return collect();
        }

        $prefixQuery = $q.'%';
        $containsQuery = '%'.$q.'%';

        return DataSiswa::query()
            ->select([
                'id',
                'nama',
                'nisn',
                'tanggal_lahir',
                'status',
                'kepribadian',
                'gaya_belajar',
                'profiling',
                'mbti',
            ])
            ->whereIn('status', ['aktif', 'alumni'])
            ->where(function ($inner) use ($q): void {
                $inner->where('nama', 'like', '%'.$q.'%')
                    ->orWhere('nisn', 'like', '%'.$q.'%');
            })
            ->orderByRaw(
                'case when nisn = ? then 0 when nisn like ? then 1 when nama like ? then 2 when nama like ? then 3 else 4 end',
                [$q, $prefixQuery, $prefixQuery, $containsQuery]
            )
            ->orderBy('nama')
            ->limit(12)
            ->get();
    }

    /**
     * @param  Collection<int, DataSiswa>  $students
     * @return array<int, array{id:int, nama:string, nisn:string, tanggal_lahir_label:string, status:string, status_label:string, status_short:string, profile_url:string, test_data:array<string,string>}>
     */
    protected function formatStudentSearchResults(Collection $students): array
    {
        return $students
            ->map(function (DataSiswa $student): array {
                $status = strtolower((string) $student->status);
                $testData = [
                    'Kepribadian' => filled($student->kepribadian) ? Str::upper((string) $student->kepribadian) : '',
                    'Gaya Belajar' => filled($student->gaya_belajar) ? Str::upper((string) $student->gaya_belajar) : '',
                    'Profiling' => filled($student->profiling) ? Str::upper((string) $student->profiling) : '',
                    'MBTI' => filled($student->mbti) ? Str::upper((string) $student->mbti) : '',
                ];

                return [
                    'id' => (int) $student->getKey(),
                    'nama' => (string) $student->nama,
                    'nisn' => (string) ($student->nisn ?? ''),
                    'tanggal_lahir_label' => $student->tanggal_lahir?->format('d/m/Y') ?: '-',
                    'status' => $status,
                    'status_label' => DataSiswa::statusLabel($student->status),
                    'status_short' => $status === 'alumni' ? 'Alumni' : 'Aktif',
                    'profile_url' => route('students.show', ['student' => $student->getKey()]),
                    'test_data' => $testData,
                ];
            })
            ->values()
            ->all();
    }

    protected function hasDataSiswaSearchColumns(): bool
    {
        return $this->dataSiswaSearchColumnsAvailable ??= Schema::hasTable('data_siswa')
            && Schema::hasColumn('data_siswa', 'nama')
            && Schema::hasColumn('data_siswa', 'nisn')
            && Schema::hasColumn('data_siswa', 'tanggal_lahir')
            && Schema::hasColumn('data_siswa', 'status');
    }

    /**
     * @return array{0: Collection<int, StrukturOrganisasi>, 1: Collection<int, StrukturOrganisasi>, 2: Collection<int, array{year:int|null, label:string|null, count:int, nodes:Collection<int, StrukturOrganisasi>}>}
     */
    protected function loadHomepageProfileBranches(): array
    {
        if (StrukturOrganisasi::categoryColumnAvailable()) {
            $komitePeriods = $this->loadCommitteeHomepagePeriods();

            return [
                StrukturOrganisasi::homepageTree(StrukturOrganisasi::CATEGORY_SCHOOL),
                collect(data_get($komitePeriods->first(), 'nodes', collect())),
                $komitePeriods,
            ];
        }

        [$strukturOrganisasi, $komiteOrganisasi] = $this->splitHomepageProfileBranches(
            StrukturOrganisasi::homepageTree()
        );

        $komitePeriods = $komiteOrganisasi->isNotEmpty()
            ? collect([[
                'year' => null,
                'label' => 'Struktur Komite',
                'count' => $this->countStructureNodes($komiteOrganisasi),
                'nodes' => $komiteOrganisasi,
            ]])
            : collect();

        return [$strukturOrganisasi, $komiteOrganisasi, $komitePeriods];
    }

    /**
     * @return Collection<int, array{year:int|null, label:string|null, count:int, nodes:Collection<int, StrukturOrganisasi>}>
     */
    protected function loadCommitteeHomepagePeriods(): Collection
    {
        $periods = StrukturOrganisasi::committeePeriods()
            ->map(function (array $period): array {
                $nodes = StrukturOrganisasi::homepageTree(
                    StrukturOrganisasi::CATEGORY_COMMITTEE,
                    $period['year'] ?? null,
                );

                return [
                    'year' => $period['year'] ?? null,
                    'label' => $period['label'] ?? null,
                    'count' => $this->countStructureNodes($nodes),
                    'nodes' => $nodes,
                ];
            })
            ->filter(fn (array $period): bool => collect($period['nodes'])->isNotEmpty())
            ->values();

        if ($periods->isNotEmpty()) {
            return $periods;
        }

        $legacyNodes = StrukturOrganisasi::homepageTree(StrukturOrganisasi::CATEGORY_COMMITTEE);

        if ($legacyNodes->isEmpty()) {
            return collect();
        }

        return collect([[
            'year' => null,
            'label' => 'Struktur Komite',
            'count' => $this->countStructureNodes($legacyNodes),
            'nodes' => $legacyNodes,
        ]]);
    }

    protected function countStructureNodes(Collection $nodes): int
    {
        return $nodes->sum(function (StrukturOrganisasi $node): int {
            return 1 + $this->countStructureNodes(collect($node->children ?? []));
        });
    }

    /**
     * @return array{0: Collection<int, StrukturOrganisasi>, 1: Collection<int, StrukturOrganisasi>}
     */
    protected function splitHomepageProfileBranches(Collection $nodes): array
    {
        if (StrukturOrganisasi::categoryColumnAvailable()) {
            return [
                StrukturOrganisasi::homepageTree(StrukturOrganisasi::CATEGORY_SCHOOL),
                StrukturOrganisasi::homepageTree(StrukturOrganisasi::CATEGORY_COMMITTEE),
            ];
        }

        $komiteOrganisasi = $nodes
            ->filter(fn (StrukturOrganisasi $node): bool => $this->isKomiteHomepageBranch($node))
            ->values();

        $strukturOrganisasi = $nodes
            ->reject(fn (StrukturOrganisasi $node): bool => $this->isKomiteHomepageBranch($node))
            ->values();

        return [$strukturOrganisasi, $komiteOrganisasi];
    }

    protected function isKomiteHomepageBranch(StrukturOrganisasi $node): bool
    {
        return $this->containsKomiteKeyword($node->jabatan)
            || $this->containsKomiteKeyword($node->nama);
    }

    protected function containsKomiteKeyword(?string $value): bool
    {
        $normalized = Str::lower(trim((string) $value));

        return $normalized !== '' && Str::contains($normalized, 'komite');
    }
}
