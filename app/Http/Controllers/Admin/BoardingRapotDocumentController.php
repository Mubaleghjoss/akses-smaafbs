<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BoardingRapotExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\ResolvesSchoolLetterhead;
use App\Models\BoardingRapot;
use App\Models\DataSiswa;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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

    public function editManual(BoardingRapot $boardingRapot): View
    {
        config(['livewire.inject_assets' => false]);

        $rapot = $this->resolveEditableRecord($boardingRapot);
        $pamongOptions = User::boardingPamongQuery()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        if ($rapot->pamong_user_id && ! array_key_exists($rapot->pamong_user_id, $pamongOptions)) {
            $pamongOptions[$rapot->pamong_user_id] = $rapot->pamongUser?->name ?? 'Pamong #'.$rapot->pamong_user_id;
        }

        return view('admin.boarding.rapot-manual-edit', [
            'rapot' => $rapot,
            'payload' => $rapot->rekap_payload ?: $rapot->buildRekapPayload(),
            'statusOptions' => BoardingRapot::statusOptions(),
            'semesterOptions' => [
                'ganjil' => 'Ganjil',
                'genap' => 'Genap',
            ],
            'boardingClassOptions' => BoardingRapot::boardingClassOptions(),
            'pamongOptions' => $pamongOptions,
            'administrasiRows' => $this->manualAdministrasiRows($rapot),
            'kelasOverrideColumnAvailable' => SchemaFacade::hasColumn('boarding_rapots', 'kelas_boarding_override'),
            'administrasiColumnAvailable' => SchemaFacade::hasColumn('boarding_rapots', 'administrasi_rapot_items'),
        ]);
    }

    public function updateManual(Request $request, BoardingRapot $boardingRapot): RedirectResponse
    {
        $rapot = $this->resolveEditableRecord($boardingRapot);

        $validated = $request->validate([
            'pamong_user_id' => ['required', 'integer', 'exists:users,id'],
            'periode_tahun' => ['required', 'string', 'max:20'],
            'semester' => ['required', Rule::in(array_keys([
                'ganjil' => 'Ganjil',
                'genap' => 'Genap',
            ]))],
            'tanggal_rapot' => ['nullable', 'date'],
            'status_rapot' => ['required', Rule::in(array_keys(BoardingRapot::statusOptions()))],
            'nomor_dokumen' => ['nullable', 'string', 'max:50'],
            'kelas_boarding_override' => SchemaFacade::hasColumn('boarding_rapots', 'kelas_boarding_override')
                ? ['required', Rule::in(array_keys(BoardingRapot::boardingClassOptions()))]
                : ['nullable'],
            'wali_pamong_nama' => ['nullable', 'string', 'max:100'],
            'kepala_boarding_nama' => ['nullable', 'string', 'max:100'],
            'mudir_asrama_nama' => ['nullable', 'string', 'max:100'],
            'tempat_cetak' => ['nullable', 'string', 'max:100'],
            'ringkasan_pencapaian' => ['nullable', 'string', 'max:5000'],
            'catatan_pamong' => ['nullable', 'string', 'max:5000'],
            'rekomendasi_tindak_lanjut' => ['nullable', 'string', 'max:5000'],
            'administrasi_questions' => ['nullable', 'array'],
            'administrasi_questions.*' => ['nullable', 'string', 'max:120'],
            'administrasi_answers' => ['nullable', 'array'],
            'administrasi_answers.*' => ['nullable', 'string', 'max:500'],
        ]);

        $duplicateExists = BoardingRapot::query()
            ->where('siswa_id', $rapot->siswa_id)
            ->where('periode_tahun', $validated['periode_tahun'])
            ->where('semester', $validated['semester'])
            ->where('id', '!=', $rapot->getKey())
            ->exists();

        if ($duplicateExists) {
            return back()
                ->withInput()
                ->withErrors([
                    'periode_tahun' => 'Rapot untuk murid, periode, dan semester ini sudah ada.',
                ]);
        }

        $data = collect($validated)
            ->only([
                'pamong_user_id',
                'periode_tahun',
                'semester',
                'tanggal_rapot',
                'status_rapot',
                'nomor_dokumen',
                'wali_pamong_nama',
                'kepala_boarding_nama',
                'mudir_asrama_nama',
                'tempat_cetak',
                'ringkasan_pencapaian',
                'catatan_pamong',
                'rekomendasi_tindak_lanjut',
            ])
            ->all();

        if (SchemaFacade::hasColumn('boarding_rapots', 'kelas_boarding_override')) {
            $data['kelas_boarding_override'] = BoardingRapot::normalizeBoardingClassKey($validated['kelas_boarding_override'] ?? null);
        }

        if (SchemaFacade::hasColumn('boarding_rapots', 'administrasi_rapot_items')) {
            $data['administrasi_rapot_items'] = $this->normalizeManualAdministrasiItems($request);
        }

        $rapot->fill($data);
        $rapot->save();
        $rapot->syncFromSources();

        return redirect()
            ->route('admin.boarding-rapots.manual-edit', $rapot)
            ->with('status', 'Rapot boarding berhasil disimpan manual tanpa Livewire.');
    }

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

    protected function resolveEditableRecord(BoardingRapot $boardingRapot): BoardingRapot
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless(
            $user->hasFullAdminAccess() || $user->canManageModule('boarding_rapot'),
            Response::HTTP_FORBIDDEN,
        );

        return $this->resolveRecord($boardingRapot);
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    protected function manualAdministrasiRows(BoardingRapot $rapot): array
    {
        $rows = BoardingRapot::normalizeAdministrasiRapotItems($rapot->administrasi_rapot_items ?? []);

        while (count($rows) < 6) {
            $rows[] = ['question' => '', 'answer' => ''];
        }

        return $rows;
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    protected function normalizeManualAdministrasiItems(Request $request): array
    {
        $questions = $request->input('administrasi_questions', []);
        $answers = $request->input('administrasi_answers', []);

        if (! is_array($questions)) {
            $questions = [];
        }

        if (! is_array($answers)) {
            $answers = [];
        }

        $rows = collect($questions)
            ->map(fn (mixed $question, int|string $index): array => [
                'question' => $question,
                'answer' => $answers[$index] ?? null,
            ])
            ->all();

        return BoardingRapot::normalizeAdministrasiRapotItems($rows);
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
