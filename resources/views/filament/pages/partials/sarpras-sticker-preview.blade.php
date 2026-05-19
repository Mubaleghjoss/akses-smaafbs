@php
    use App\Support\Sarpras\SarprasBospStickerImage;
    use App\Support\Sarpras\SarprasStickerSettings;
    use chillerlan\QRCode\QRCode;
    use chillerlan\QRCode\QROptions;

    $settings = array_merge(SarprasStickerSettings::defaults(), $settings ?? []);
    $layout = SarprasBospStickerImage::layout($settings, 2025);
    $widthMm = $layout['widthMm'];
    $heightMm = $layout['heightMm'];
    $programText = strtoupper(trim((string) ($settings[SarprasStickerSettings::PROGRAM_TEXT] ?? 'BOSP'))) ?: 'BOSP';
    $logoSrc = SarprasStickerSettings::imageDataUri((string) ($settings[SarprasStickerSettings::LOGO_PATH] ?? ''))
        ?? SarprasStickerSettings::imageDataUri(SarprasStickerSettings::resolvedLogoPath());
    $qrImage = (new QRCode(new QROptions([
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'outputBase64' => true,
        'quietzoneSize' => 2,
        'scale' => 5,
    ])))->render(url('/s/b/3'));
    $previewImage = SarprasBospStickerImage::renderDataUriFromData([
        'year' => '2025',
        'inventory_id' => 'BOSP-PRI-001',
        'location' => 'Rak 2 Lab Komputer',
    ], $settings, $logoSrc, $qrImage);
@endphp

<x-filament::section>
    <x-slot name="heading">Preview Stiker</x-slot>
    <x-slot name="description">
        Preview ini adalah gambar final yang sama dengan gambar yang ditempel ke PDF saat download. Teks contoh memakai {{ $programText }} 2025.
    </x-slot>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-950">
        <img
            src="{{ $previewImage }}"
            alt="Preview Stiker Sarpras"
            style="display: block; width: {{ $widthMm }}mm; height: {{ $heightMm }}mm; max-width: none;"
        >
    </div>
</x-filament::section>
