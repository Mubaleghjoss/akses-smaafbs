<?php

namespace App\Http\Controllers;

use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\PerpustakaanKategori;
use App\Models\PerpustakaanBuku;
use App\Models\PerpustakaanLiterasiActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $items = PerpustakaanBuku::query()
            ->with(['kategori:id,nama_kategori', 'lemari:id,nama_lemari,lokasi'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('judul_buku', 'like', '%'.$q.'%')
                        ->orWhere('penulis', 'like', '%'.$q.'%')
                        ->orWhere('penerbit', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        $categories = PerpustakaanKategori::query()
            ->where('status', 'aktif')
            ->orderBy('nama_kategori')
            ->limit(12)
            ->get(['id', 'nama_kategori']);

        return view('library.index', [
            'title' => 'Perpustakaan',
            'q' => $q,
            'items' => $items,
            'categories' => $categories,
        ]);
    }

    public function show(PerpustakaanBuku $book)
    {
        $book->loadMissing(['kategori:id,nama_kategori', 'lemari:id,nama_lemari,lokasi']);

        $bookUrl = null;
        if (($book->file_type ?? 'physical') === 'ebook' && ! empty($book->file_path)) {
            $path = (string) $book->file_path;
            if (str_starts_with($path, 'assets/')) {
                $path = preg_replace('#^assets/uploads/#', '', $path) ?: (string) $book->file_path;
            }
            $bookUrl = Storage::disk('public')->url($path);
        }

        return view('library.show', [
            'title' => $book->judul_buku,
            'book' => $book,
            'bookUrl' => $bookUrl,
        ]);
    }

    public function download(PerpustakaanBuku $book)
    {
        if (($book->file_type ?? 'physical') !== 'ebook' || empty($book->file_path)) {
            abort(404);
        }

        $path = (string) $book->file_path;
        if (str_starts_with($path, 'assets/')) {
            $path = preg_replace('#^assets/uploads/#', '', $path) ?: (string) $book->file_path;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }

        // increment download_count + log
        try {
            $book->increment('download_count');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            DB::table('perpustakaan_ebook_logs')->insert([
                'book_id' => $book->id,
                'user_name' => 'public',
                'user_class' => null,
                'access_type' => 'download',
                'ip_address' => request()->ip() ?? 'unknown',
                'user_agent' => substr((string) request()->userAgent(), 0, 2000),
                'access_time' => now(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return $disk->download($path);
    }

    public function activities(Request $request)
    {
        $filters = $this->activityFilters($request);
        $query = PerpustakaanLiterasiActivity::query()
            ->with('book:id,judul_buku,penulis')
            ->orderByDesc('activity_at')
            ->orderByDesc('created_at');

        $this->applyActivityFilters($query, $filters);

        $activities = $query->paginate(10)->withQueryString();

        $stats = [
            'total' => PerpustakaanLiterasiActivity::query()->count(),
            'literasi' => PerpustakaanLiterasiActivity::query()->where('purpose', PerpustakaanLiterasiActivity::PURPOSE_LITERASI)->count(),
            'tugas' => PerpustakaanLiterasiActivity::query()->where('purpose', PerpustakaanLiterasiActivity::PURPOSE_TUGAS)->count(),
            'active_users' => PerpustakaanLiterasiActivity::query()
                ->whereNotNull('participant_name')
                ->distinct()
                ->count('participant_name'),
        ];

        $classOptions = PerpustakaanLiterasiActivity::query()
            ->whereNotNull('participant_class')
            ->where('participant_class', '!=', '')
            ->distinct()
            ->orderBy('participant_class')
            ->limit(50)
            ->pluck('participant_class')
            ->all();

        $popularBooks = PerpustakaanLiterasiActivity::query()
            ->select('book_title_snapshot')
            ->selectRaw('count(*) as total')
            ->whereNotNull('book_title_snapshot')
            ->where('book_title_snapshot', '!=', '')
            ->groupBy('book_title_snapshot')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('library.activities', [
            'title' => 'Aktivitas Literasi',
            'filters' => $filters,
            'activities' => $activities,
            'stats' => $stats,
            'classOptions' => $classOptions,
            'popularBooks' => $popularBooks,
            'purposeOptions' => PerpustakaanLiterasiActivity::purposeOptions(),
            'resultStatusOptions' => PerpustakaanLiterasiActivity::resultStatusOptions(),
        ]);
    }

    public function createActivity()
    {
        $books = PerpustakaanBuku::query()
            ->orderBy('judul_buku')
            ->limit(150)
            ->get(['id', 'judul_buku', 'penulis']);

        return view('library.activity-form', [
            'title' => 'Form Aktivitas Literasi',
            'books' => $books,
            'students' => $this->studentParticipantOptions(),
            'teachers' => $this->teacherParticipantOptions(),
            'defaultActivityAt' => now()->format('Y-m-d\TH:i'),
            'purposeOptions' => PerpustakaanLiterasiActivity::purposeOptions(),
        ]);
    }

    public function storeActivity(Request $request)
    {
        $validated = $request->validate([
            'purpose' => ['required', 'string', 'in:'.implode(',', array_keys(PerpustakaanLiterasiActivity::purposeOptions()))],
            'participant_role' => ['required', 'string', 'in:siswa,guru'],
            'participant_id' => ['nullable', 'integer'],
            'participant_name' => ['nullable', 'string', 'max:150'],
            'participant_class' => ['nullable', 'string', 'max:100'],
            'book_id' => ['nullable', 'integer', 'exists:perpustakaan_buku,id'],
            'book_title' => ['nullable', 'string', 'max:500'],
            'book_author' => ['nullable', 'string', 'max:255'],
            'subject_name' => [
                $request->input('purpose') === PerpustakaanLiterasiActivity::PURPOSE_TUGAS ? 'required' : 'nullable',
                'string',
                'max:150',
            ],
            'activity_at' => ['nullable', 'date'],
        ]);

        $participant = filled($validated['participant_id'] ?? null)
            ? $this->resolveParticipant($validated['participant_role'], (int) $validated['participant_id'])
            : null;

        if (filled($validated['participant_id'] ?? null) && $participant === null) {
            return back()
                ->withErrors(['participant_id' => 'Data peserta tidak ditemukan pada data master.'])
                ->withInput();
        }

        $participantName = trim((string) ($participant['name'] ?? $validated['participant_name'] ?? ''));
        $participantClass = $validated['participant_role'] === 'siswa'
            ? trim((string) ($participant['class'] ?? $validated['participant_class'] ?? ''))
            : null;

        if ($participantName === '') {
            return back()
                ->withErrors(['participant_name' => 'Nama peserta wajib diisi atau pilih dari data master.'])
                ->withInput();
        }

        if ($validated['participant_role'] === 'siswa' && $participantClass === '') {
            return back()
                ->withErrors(['participant_class' => 'Kelas wajib diisi untuk aktivitas siswa.'])
                ->withInput();
        }

        $book = filled($validated['book_id'] ?? null)
            ? PerpustakaanBuku::query()->find((int) $validated['book_id'])
            : null;

        $bookTitle = trim((string) ($book?->judul_buku ?? $validated['book_title'] ?? ''));

        if ($bookTitle === '') {
            return back()
                ->withErrors(['book_title' => 'Judul buku wajib diisi atau pilih buku dari katalog.'])
                ->withInput();
        }

        $activity = PerpustakaanLiterasiActivity::query()->create([
            'activity_code' => $this->generateActivityCode(),
            'purpose' => $validated['purpose'],
            'participant_id' => $participant['id'] ?? null,
            'participant_name' => $participantName,
            'participant_class' => $participantClass !== '' ? $participantClass : null,
            'participant_role' => $validated['participant_role'],
            'book_id' => $book?->getKey(),
            'book_title_snapshot' => $bookTitle,
            'book_author_snapshot' => $book?->penulis ?: ($validated['book_author'] ?? null),
            'subject_name' => $validated['subject_name'] ?? null,
            'activity_at' => $validated['activity_at'] ?? now(),
            'result_status' => $validated['purpose'] === PerpustakaanLiterasiActivity::PURPOSE_LITERASI
                ? PerpustakaanLiterasiActivity::RESULT_PENDING
                : PerpustakaanLiterasiActivity::RESULT_NOT_REQUIRED,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return redirect()
            ->route('library.activities.result', ['code' => $this->activityShortCode($activity->activity_code)])
            ->with('success', 'Aktivitas tersimpan. Simpan kode singkat untuk mengisi atau mengedit hasil bacaan.');
    }

    public function result(Request $request)
    {
        $code = Str::upper(trim((string) $request->query('code', '')));
        $lookup = $code !== '' ? $this->resolveActivityByAccessCode($code) : ['activity' => null, 'error' => null];

        return view('library.result', [
            'title' => 'Input Hasil Literasi',
            'code' => $code,
            'activity' => $lookup['activity'],
            'lookupError' => $lookup['error'],
        ]);
    }

    public function lookupResult(Request $request)
    {
        $code = Str::upper(trim((string) $request->query('code', '')));
        $lookup = $this->resolveActivityByAccessCode($code);
        $activity = $lookup['activity'];

        if (! $activity) {
            return response()->json([
                'found' => false,
                'message' => $lookup['error'] ?: 'Kode literasi tidak ditemukan.',
            ], 404);
        }

        if ($activity->purpose !== PerpustakaanLiterasiActivity::PURPOSE_LITERASI) {
            return response()->json([
                'found' => false,
                'message' => 'Kode ini bukan aktivitas literasi yang membutuhkan hasil bacaan.',
            ], 422);
        }

        return response()->json([
            'found' => true,
            'short_code' => $this->activityShortCode($activity->activity_code),
            'activity_code' => $activity->activity_code,
            'participant_name' => $activity->participant_name,
            'participant_class' => $activity->participant_class,
            'book_title' => $activity->book_title_snapshot,
            'purpose_label' => PerpustakaanLiterasiActivity::purposeLabel($activity->purpose),
            'result_status_label' => PerpustakaanLiterasiActivity::resultStatusLabel($activity->result_status),
            'result_text' => $activity->result_text ?: '',
            'submitted_at' => $activity->result_submitted_at?->format('d M Y H:i'),
        ]);
    }

    public function storeResult(Request $request)
    {
        $validated = $request->validate([
            'activity_code' => ['required', 'string', 'max:40'],
            'result_text' => ['required', 'string', 'min:20', 'max:8000'],
        ]);

        $code = Str::upper(trim((string) $validated['activity_code']));

        $lookup = $this->resolveActivityByAccessCode($code);
        $activity = $lookup['activity'];

        if (! $activity) {
            return back()
                ->withErrors(['activity_code' => $lookup['error'] ?: 'Kode literasi tidak ditemukan.'])
                ->withInput();
        }

        if ($activity->purpose !== PerpustakaanLiterasiActivity::PURPOSE_LITERASI) {
            return back()
                ->withErrors(['activity_code' => 'Kode ini bukan aktivitas literasi yang membutuhkan hasil bacaan.'])
                ->withInput();
        }

        $activity->update([
            'result_text' => $validated['result_text'],
            'result_status' => PerpustakaanLiterasiActivity::RESULT_SUBMITTED,
            'result_submitted_at' => now(),
        ]);

        return redirect()
            ->route('library.activities.result', ['code' => $this->activityShortCode($activity->activity_code)])
            ->with('success', 'Hasil literasi berhasil disimpan.');
    }

    public function exportActivities(Request $request)
    {
        $filters = $this->activityFilters($request);
        $filename = 'aktivitas-literasi-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Tanggal', 'Peran', 'Tujuan', 'Nama', 'Kelas', 'Mata Pelajaran', 'Buku', 'Penulis', 'Status Hasil']);

            $query = PerpustakaanLiterasiActivity::query()
                ->orderByDesc('activity_at')
                ->orderByDesc('created_at');

            $this->applyActivityFilters($query, $filters);

            $query->chunk(200, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->activity_at?->format('d/m/Y H:i') ?? '-',
                        $row->participant_role ?: '-',
                        PerpustakaanLiterasiActivity::purposeLabel($row->purpose),
                        $row->participant_name,
                        $row->participant_class ?: '-',
                        $row->subject_name ?: '-',
                        $row->book_title_snapshot,
                        $row->book_author_snapshot ?: '-',
                        PerpustakaanLiterasiActivity::resultStatusLabel($row->result_status),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function activityFilters(Request $request): array
    {
        return [
            'purpose' => trim((string) $request->query('purpose', '')),
            'class' => trim((string) $request->query('class', '')),
            'date' => trim((string) $request->query('date', '')),
            'result_status' => trim((string) $request->query('result_status', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    protected function applyActivityFilters(Builder $query, array $filters): Builder
    {
        if (($filters['purpose'] ?? '') !== '' && array_key_exists($filters['purpose'], PerpustakaanLiterasiActivity::purposeOptions())) {
            $query->where('purpose', $filters['purpose']);
        }

        if (($filters['class'] ?? '') !== '') {
            $query->where('participant_class', $filters['class']);
        }

        if (($filters['date'] ?? '') !== '') {
            $query->whereDate('activity_at', $filters['date']);
        }

        if (($filters['result_status'] ?? '') !== '' && array_key_exists($filters['result_status'], PerpustakaanLiterasiActivity::resultStatusOptions())) {
            $query->where('result_status', $filters['result_status']);
        }

        if (($filters['q'] ?? '') !== '') {
            $q = $filters['q'];
            $query->where(function (Builder $inner) use ($q): void {
                $inner->where('activity_code', 'like', '%'.$q.'%')
                    ->orWhere('participant_name', 'like', '%'.$q.'%')
                    ->orWhere('book_title_snapshot', 'like', '%'.$q.'%')
                    ->orWhere('book_author_snapshot', 'like', '%'.$q.'%');
            });
        }

        return $query;
    }

    protected function resolveActivityByAccessCode(string $code): array
    {
        $normalized = Str::upper(trim($code));

        if ($normalized === '') {
            return ['activity' => null, 'error' => 'Kode literasi wajib diisi.'];
        }

        if (str_starts_with($normalized, 'LIT-')) {
            $activity = PerpustakaanLiterasiActivity::query()
                ->where('activity_code', $normalized)
                ->first();

            return [
                'activity' => $activity,
                'error' => $activity ? null : 'Kode literasi tidak ditemukan.',
            ];
        }

        if (! preg_match('/^[A-Z0-9]{6}$/', $normalized)) {
            return [
                'activity' => null,
                'error' => 'Masukkan 6 karakter kode singkat, contoh I8EVPG.',
            ];
        }

        $matches = PerpustakaanLiterasiActivity::query()
            ->where('activity_code', 'like', '%-'.$normalized)
            ->limit(2)
            ->get();

        if ($matches->count() === 1) {
            return ['activity' => $matches->first(), 'error' => null];
        }

        if ($matches->count() > 1) {
            return [
                'activity' => null,
                'error' => 'Kode singkat ini ditemukan lebih dari satu. Masukkan kode lengkap.',
            ];
        }

        return ['activity' => null, 'error' => 'Kode singkat tidak ditemukan.'];
    }

    protected function activityShortCode(?string $activityCode): string
    {
        $activityCode = trim((string) $activityCode);

        return $activityCode !== '' ? Str::afterLast($activityCode, '-') : '';
    }

    protected function studentParticipantOptions(): array
    {
        if (! Schema::hasTable('data_siswa') || ! Schema::hasColumn('data_siswa', 'nama')) {
            return [];
        }

        $columns = ['id', 'nama'];
        if (Schema::hasColumn('data_siswa', 'rombel_saat_ini')) {
            $columns[] = 'rombel_saat_ini';
        }
        if (Schema::hasColumn('data_siswa', 'status')) {
            $columns[] = 'status';
        }

        $query = DataSiswa::query()
            ->select($columns)
            ->whereNotNull('nama')
            ->where('nama', '!=', '');

        if (Schema::hasColumn('data_siswa', 'status')) {
            $query->orderByRaw("CASE WHEN status = 'aktif' THEN 0 ELSE 1 END");
        }

        return $query
            ->orderBy('nama')
            ->limit(750)
            ->get()
            ->map(function (DataSiswa $student): array {
                $name = trim((string) $student->nama);
                $class = trim((string) ($student->rombel_saat_ini ?? ''));
                $status = trim((string) ($student->status ?? ''));
                $label = $name;

                if ($class !== '') {
                    $label .= ' - '.$class;
                }

                if ($status !== '' && strtolower($status) !== 'aktif') {
                    $label .= ' ('.$status.')';
                }

                return [
                    'id' => (int) $student->id,
                    'name' => $name,
                    'class' => $class,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    protected function teacherParticipantOptions(): array
    {
        if (! Schema::hasTable('guru_tendik') || ! Schema::hasColumn('guru_tendik', 'nama')) {
            return [];
        }

        $columns = ['id', 'nama'];
        if (Schema::hasColumn('guru_tendik', 'jenis_ptk')) {
            $columns[] = 'jenis_ptk';
        }
        if (Schema::hasColumn('guru_tendik', 'status')) {
            $columns[] = 'status';
        }

        $query = GuruTendik::query()
            ->select($columns)
            ->whereNotNull('nama')
            ->where('nama', '!=', '');

        if (Schema::hasColumn('guru_tendik', 'status')) {
            $query->orderByRaw("CASE WHEN status = 'aktif' THEN 0 ELSE 1 END");
        }

        return $query
            ->orderBy('nama')
            ->limit(500)
            ->get()
            ->map(function (GuruTendik $teacher): array {
                $name = trim((string) $teacher->nama);
                $type = trim((string) ($teacher->jenis_ptk ?? ''));
                $status = trim((string) ($teacher->status ?? ''));
                $label = $name;

                if ($type !== '') {
                    $label .= ' - '.$type;
                }

                if ($status !== '' && strtolower($status) !== 'aktif') {
                    $label .= ' ('.$status.')';
                }

                return [
                    'id' => (int) $teacher->id,
                    'name' => $name,
                    'class' => '',
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveParticipant(string $role, int $id): ?array
    {
        if ($role === 'siswa' && Schema::hasTable('data_siswa')) {
            $columns = ['id', 'nama'];
            if (Schema::hasColumn('data_siswa', 'rombel_saat_ini')) {
                $columns[] = 'rombel_saat_ini';
            }

            $student = DataSiswa::query()
                ->select($columns)
                ->whereKey($id)
                ->first();

            if (! $student) {
                return null;
            }

            return [
                'id' => (int) $student->id,
                'name' => trim((string) $student->nama),
                'class' => trim((string) ($student->rombel_saat_ini ?? '')),
            ];
        }

        if ($role === 'guru' && Schema::hasTable('guru_tendik')) {
            $teacher = GuruTendik::query()
                ->select(['id', 'nama'])
                ->whereKey($id)
                ->first();

            if (! $teacher) {
                return null;
            }

            return [
                'id' => (int) $teacher->id,
                'name' => trim((string) $teacher->nama),
                'class' => '',
            ];
        }

        return null;
    }

    protected function generateActivityCode(): string
    {
        do {
            $shortCode = Str::upper(Str::random(6));
            $code = 'LIT-'.now()->format('ymd').'-'.$shortCode;
        } while (
            PerpustakaanLiterasiActivity::query()->where('activity_code', $code)->exists()
            || PerpustakaanLiterasiActivity::query()->where('activity_code', 'like', '%-'.$shortCode)->exists()
        );

        return $code;
    }
}
