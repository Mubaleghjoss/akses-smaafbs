@php
    /** @var \App\Models\SarprasBospInventory $record */
    $year = $record->tahun_beli ?: now()->year;
    $settings = $stickerSettings ?? \App\Support\Sarpras\SarprasStickerSettings::defaults();
    $schoolText = strtoupper(trim((string) ($settings[\App\Support\Sarpras\SarprasStickerSettings::SCHOOL_TEXT] ?? 'SMA AFBS'))) ?: 'SMA AFBS';
    $programText = strtoupper(trim((string) ($settings[\App\Support\Sarpras\SarprasStickerSettings::PROGRAM_TEXT] ?? 'BOSP'))) ?: 'BOSP';
    $itemCode = filled($record->kode_barang) ? $record->kode_barang : 'ID-'.$record->getKey();
    $location = filled($record->tempat_stiker)
        ? $record->tempat_stiker
        : (filled($record->lokasi_barang) ? $record->lokasi_barang : 'Tempat belum diisi');
    $inventoryClass = mb_strlen((string) $itemCode) > 18 ? ' text-fit-xs' : (mb_strlen((string) $itemCode) > 12 ? ' text-fit-sm' : '');
    $locationClass = mb_strlen((string) $location) > 24 ? ' text-fit-xs' : (mb_strlen((string) $location) > 16 ? ' text-fit-sm' : '');
@endphp

<div class="sticker">
    <div class="sticker-frame">
        <div class="sticker-inner">
            <div class="logo-cell">
                <div class="logo-box">
                    @if(filled($logoSrc))
                        <img src="{{ $logoSrc }}" alt="Logo">
                    @else
                        <span class="logo-fallback">LOGO</span>
                    @endif
                </div>
            </div>
            <div class="text-cell">
                <div class="school"><span>{{ $schoolText }}</span></div>
                <div class="bosp-year"><span>{{ $programText }} TAHUN {{ $year }}</span></div>
            </div>
            <div class="qr-cell">
                <div class="program-line">[PROGRAM/YEAR: {{ $programText }} {{ $year }}]</div>
                <img class="qr-image" src="{{ $qrImage }}" alt="QR Code">
                <div class="inventory-line{{ $inventoryClass }}">[INVENTORY ID: {{ \Illuminate\Support\Str::limit((string) $itemCode, 24) }}]</div>
                <div class="location-line{{ $locationClass }}">[TEMPAT: {{ \Illuminate\Support\Str::limit((string) $location, 28) }}]</div>
            </div>
            <span class="logo-divider"></span>
            <span class="middle-divider"></span>
            <span class="qr-divider"></span>
        </div>
    </div>
</div>
