<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BoardingRapotExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\ResolvesSchoolLetterhead;
use App\Models\BoardingRapot;
use App\Models\DataSiswa;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BoardingRapotDocumentController extends Controller
{
    use ResolvesSchoolLetterhead;

    public function preview(BoardingRapot $boardingRapot): View
    {
        $rapot = $this->resolveRecord($boardingRapot);
        $payload = $rapot->rekap_payload ?: $rapot->buildRekapPayload();

        return view('admin.boarding.rapot-print', [
            'rapot' => $rapot,
            'payload' => $payload,
            'letterhead' => $this->boardingRapotLetterhead(),
            'generatedAt' => now(),
            'printMode' => false,
        ]);
    }

    public function rekap(BoardingRapot $boardingRapot): View
    {
        $rapot = $this->resolveRecord($boardingRapot);
        $payload = $rapot->rekap_payload ?: $rapot->buildRekapPayload();

        return view('admin.boarding.rapot-preview', [
            'rapot' => $rapot,
            'payload' => $payload,
            'letterhead' => $this->boardingRapotLetterhead(),
            'generatedAt' => now(),
            'printMode' => false,
        ]);
    }

    public function print(BoardingRapot $boardingRapot): View
    {
        $rapot = $this->resolveRecord($boardingRapot);
        $payload = $rapot->rekap_payload ?: $rapot->buildRekapPayload();

        return view('admin.boarding.rapot-print', [
            'rapot' => $rapot,
            'payload' => $payload,
            'letterhead' => $this->boardingRapotLetterhead(),
            'generatedAt' => now(),
            'printMode' => true,
        ]);
    }

    public function export(BoardingRapot $boardingRapot): BinaryFileResponse
    {
        $rapot = $this->resolveRecord($boardingRapot);
        $payload = $rapot->rekap_payload ?: $rapot->buildRekapPayload();
        $filename = 'rapot-boarding-'.Str::slug((string) ($rapot->siswa?->nama ?: 'murid')).'-'.Str::slug((string) $rapot->periode_tahun).'-'.$rapot->semester.'.xlsx';

        return Excel::download(new BoardingRapotExport($rapot, $payload), $filename);
    }

    public function printAllReady(Request $request): View
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless(
            $user->hasFullAdminAccess() || $user->canViewModule('boarding_rapot'),
            Response::HTTP_FORBIDDEN,
        );

        $validated = $request->validate([
            'periode_tahun' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'rombel' => ['nullable', 'string', 'max:120'],
            'jenis_kelamin' => ['nullable', Rule::in(['all', 'L', 'P'])],
        ]);

        $baseQuery = $this->bulkRapotQuery(
            user: $user,
            periodeTahun: $validated['periode_tahun'] ?? null,
            semester: $validated['semester'] ?? null,
            rombel: $validated['rombel'] ?? null,
            jenisKelamin: $validated['jenis_kelamin'] ?? 'all',
        );

        $total = (clone $baseQuery)->count();
        $notReady = (clone $baseQuery)
            ->where('status_rapot', '!=', BoardingRapot::STATUS_READY_PRINT)
            ->count();

        abort_if($total === 0, Response::HTTP_NOT_FOUND, 'Tidak ada rapot pada filter ini.');
        abort_if($notReady > 0, Response::HTTP_CONFLICT, 'Semua rapot pada filter ini harus berstatus Siap Cetak sebelum print gabungan.');

        $rapots = (clone $baseQuery)
            ->where('status_rapot', BoardingRapot::STATUS_READY_PRINT)
            ->get()
            ->sortBy(fn (BoardingRapot $rapot): string => sprintf(
                '%s|%s|%s',
                (string) ($rapot->siswa?->rombel_saat_ini ?? ''),
                (string) ($rapot->siswa?->jk ?? ''),
                (string) ($rapot->siswa?->nama ?? ''),
            ))
            ->values();

        $printItems = $rapots
            ->map(function (BoardingRapot $rapot): array {
                $rapot->syncFromSources();
                $rapot->refresh();
                $rapot->loadMissing([
                    'siswa:id,nama,rombel_saat_ini,jk,status',
                    'pamongUser:id,name',
                ]);

                return [
                    'rapot' => $rapot,
                    'payload' => $rapot->rekap_payload ?: $rapot->buildRekapPayload(),
                ];
            })
            ->all();

        return view('admin.boarding.rapot-print', [
            'rapot' => $printItems[0]['rapot'],
            'payload' => $printItems[0]['payload'],
            'printItems' => $printItems,
            'letterhead' => $this->boardingRapotLetterhead(),
            'generatedAt' => now(),
            'printMode' => true,
            'bulkPrint' => true,
        ]);
    }

    protected function resolveRecord(BoardingRapot $boardingRapot): BoardingRapot
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless(
            $user->hasFullAdminAccess() || $user->canViewModule('boarding_rapot'),
            Response::HTTP_FORBIDDEN,
        );

        $columns = [
                'id',
                'siswa_id',
                'pamong_user_id',
                'periode_tahun',
                'semester',
                'nomor_dokumen',
                'predikat_boarding',
                'status_rapot',
                'tanggal_rapot',
                'generated_at',
                'rekap_payload',
                'ringkasan_pencapaian',
                'catatan_pamong',
                'rekomendasi_tindak_lanjut',
                'wali_pamong_nama',
                'kepala_boarding_nama',
                'mudir_asrama_nama',
                'tempat_cetak',
            ];

        foreach (['administrasi_rapot_items', 'kelas_boarding_override'] as $column) {
            if (SchemaFacade::hasColumn('boarding_rapots', $column)) {
                $columns[] = $column;
            }
        }

        $record = BoardingRapot::query()
            ->select($columns)
            ->forDocument($user)
            ->whereHas('siswa', fn ($query) => DataSiswa::applyVisibleScope($query, $user))
            ->whereKey($boardingRapot->getKey())
            ->firstOrFail();

        $record->syncFromSources();

        $record->refresh();
        $record->loadMissing([
            'siswa:id,nama,rombel_saat_ini,jk,status',
            'pamongUser:id,name',
        ]);

        return $record;
    }

    protected function bulkRapotQuery(User $user, ?string $periodeTahun, ?string $semester, ?string $rombel, ?string $jenisKelamin)
    {
        $columns = [
            'id',
            'siswa_id',
            'pamong_user_id',
            'periode_tahun',
            'semester',
            'nomor_dokumen',
            'predikat_boarding',
            'status_rapot',
            'tanggal_rapot',
            'generated_at',
            'rekap_payload',
            'ringkasan_pencapaian',
            'catatan_pamong',
            'rekomendasi_tindak_lanjut',
            'wali_pamong_nama',
            'kepala_boarding_nama',
            'mudir_asrama_nama',
            'tempat_cetak',
        ];

        foreach (['administrasi_rapot_items', 'kelas_boarding_override'] as $column) {
            if (SchemaFacade::hasColumn('boarding_rapots', $column)) {
                $columns[] = $column;
            }
        }

        return BoardingRapot::query()
            ->select($columns)
            ->forDocument($user)
            ->whereHas('siswa', function ($query) use ($user, $rombel, $jenisKelamin): void {
                DataSiswa::applyVisibleScope($query, $user);

                if (filled($rombel)) {
                    $query->where('rombel_saat_ini', $rombel);
                }

                if ($user->hasFullAdminAccess() && in_array($jenisKelamin, ['L', 'P'], true)) {
                    $query->where('jk', $jenisKelamin);
                }
            })
            ->when(filled($periodeTahun), fn ($query) => $query->where('periode_tahun', $periodeTahun))
            ->when(filled($semester), fn ($query) => $query->where('semester', $semester))
            ->with([
                'siswa:id,nama,rombel_saat_ini,jk,status',
                'pamongUser:id,name',
            ]);
    }

    protected function boardingRapotLetterhead(): array
    {
        $base = $this->schoolLetterhead();
        $settings = BoardingRapot::documentSettings();
        $logoPath = trim((string) ($settings[BoardingRapot::SETTING_LOGO_PATH] ?? ''));
        $rightLogoPath = trim((string) ($settings[BoardingRapot::SETTING_RIGHT_LOGO_PATH] ?? ''));
        $logoSource = $this->boardingRapotLogoSource($logoPath);
        $rightLogoSource = $this->boardingRapotLogoSource($rightLogoPath);
        $fallbackLogoSource = $this->boardingRapotLogoSource($base['logo_src'] ?? null);

        $fallbackContact = collect([$base['phone'] ?? null, $base['email'] ?? null])
            ->filter()
            ->implode(' | ');

        return [
            ...$base,
            'site_name' => $settings[BoardingRapot::SETTING_KOP_SITE_NAME] ?: $base['site_name'],
            'subtitle' => $settings[BoardingRapot::SETTING_KOP_SUBTITLE] ?: ($settings['boarding_label'] ?? 'Boarding School'),
            'address' => $settings[BoardingRapot::SETTING_KOP_ADDRESS] ?: $base['address'],
            'contact' => $settings[BoardingRapot::SETTING_KOP_CONTACT] ?: ($fallbackContact ?: null),
            'logo_src' => $logoSource ?: $fallbackLogoSource,
            'right_logo_src' => $rightLogoSource,
            'logo_size' => $this->numericLetterheadSetting($settings[BoardingRapot::SETTING_LOGO_SIZE] ?? null, 58, 34, 150),
            'site_name_font_size' => $this->numericLetterheadSetting($settings[BoardingRapot::SETTING_KOP_SITE_NAME_FONT_SIZE] ?? null, 18, 12, 50),
            'subtitle_font_size' => $this->numericLetterheadSetting($settings[BoardingRapot::SETTING_KOP_SUBTITLE_FONT_SIZE] ?? null, 13, 9, 22),
            'info_font_size' => $this->numericLetterheadSetting($settings[BoardingRapot::SETTING_KOP_INFO_FONT_SIZE] ?? null, 10.5, 8, 16),
            'signature_name_gap' => $this->numericLetterheadSetting($settings[BoardingRapot::SETTING_SIGNATURE_NAME_GAP] ?? null, 42, 18, 120),
        ];
    }

    protected function numericLetterheadSetting(mixed $value, float $default, float $min, float $max): float
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return min($max, max($min, (float) $value));
    }

    protected function boardingRapotLogoSource(?string $value): ?string
    {
        $asset = trim((string) $value);

        if (Str::startsWith($asset, ['[', '{'])) {
            $decoded = json_decode($asset, true);

            if (is_array($decoded)) {
                $asset = trim((string) collect($decoded)->flatten()->filter(fn (mixed $item): bool => filled($item))->first());
            }
        }

        if ($asset === '') {
            return null;
        }

        if (Str::startsWith($asset, 'data:') || preg_match('#^https?://#i', $asset) === 1) {
            return $asset;
        }

        if (Str::startsWith($asset, ['/storage/', 'storage/'])) {
            $relative = Str::startsWith($asset, '/storage/')
                ? Str::after($asset, '/storage/')
                : Str::after($asset, 'storage/');

            return $this->imageDataUriFromPath(Storage::disk('public')->path($relative));
        }

        if (! Str::startsWith($asset, ['/', '\\']) && Storage::disk('public')->exists($asset)) {
            return $this->imageDataUriFromPath(Storage::disk('public')->path($asset));
        }

        if (! Str::startsWith($asset, ['/', '\\']) && is_file(public_path($asset))) {
            return $this->imageDataUriFromPath(public_path($asset));
        }

        if (Str::startsWith($asset, '/') && is_file(public_path(ltrim($asset, '/')))) {
            return $this->imageDataUriFromPath(public_path(ltrim($asset, '/')));
        }

        return $this->imageDataUriFromPath($asset) ?: $this->printableAssetSource($asset);
    }

    protected function imageDataUriFromPath(?string $path): ?string
    {
        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
