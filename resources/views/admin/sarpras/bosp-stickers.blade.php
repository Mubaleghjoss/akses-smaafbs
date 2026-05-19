<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Stiker Sarpras BOSP</title>
    @php
        $settings = $stickerSettings ?? \App\Support\Sarpras\SarprasStickerSettings::defaults();
        $widthMm = (float) ($settings[\App\Support\Sarpras\SarprasStickerSettings::WIDTH_MM] ?? 96);
        $heightMm = (float) ($settings[\App\Support\Sarpras\SarprasStickerSettings::HEIGHT_MM] ?? 25.5);
        $rows = $single ? collect([$records->values()]) : $records->values()->chunk(2);
    @endphp
    <style>
        @page {
            @if($single)
                size: {{ $widthMm }}mm {{ $heightMm }}mm;
                margin: 0;
            @else
                size: A4 portrait;
                margin: 7mm 5mm;
            @endif
        }
        * { box-sizing: border-box; }
        html,
        body {
            margin: 0;
            padding: 0;
            background: #fff;
            font-size: 0;
            line-height: 0;
        }
        @if($single)
            html,
            body,
            .single-wrap {
                width: {{ $widthMm }}mm;
                height: {{ $heightMm }}mm;
                overflow: hidden;
            }
        @endif
        .single-wrap {
            page-break-after: avoid;
            page-break-inside: avoid;
        }
        .sticker-image {
            display: block;
            width: {{ $widthMm }}mm;
            height: {{ $heightMm }}mm;
            margin: 0;
            padding: 0;
            border: 0;
        }
        .sheet-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2mm 2.5mm;
        }
        .sheet-table td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }
    </style>
</head>
<body>
@if($single)
    <div class="single-wrap">
        <img class="sticker-image" src="{{ $stickerImageFor($records->first()) }}" alt="Stiker Sarpras BOSP">
    </div>
@else
    <table class="sheet-table">
        @foreach($rows as $row)
            <tr>
                @foreach($row as $record)
                    <td>
                        <img class="sticker-image" src="{{ $stickerImageFor($record) }}" alt="Stiker Sarpras BOSP">
                    </td>
                @endforeach
                @if($row->count() === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
@endif
</body>
</html>
