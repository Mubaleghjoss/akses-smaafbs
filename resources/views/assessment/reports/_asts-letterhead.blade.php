@php
    $letterheadFoundation = trim((string) data_get($templateSettings, 'foundation_name')) ?: trim((string) data_get($school, 'foundation_name')) ?: 'YAYASAN DAR AL FURQON AL HAKIM';
    $letterheadSchoolName = trim((string) data_get($templateSettings, 'school_name')) ?: trim((string) data_get($school, 'name')) ?: 'SMA AL FURQON BOARDING SCHOOL';
    $letterheadAddress = trim((string) data_get($templateSettings, 'school_address')) ?: trim((string) data_get($school, 'address')) ?: 'Jl. Untung Suropati 1 No.8 RT/RW 003/003, Kel. Cimone Jaya, Kec. Karawaci, Kota Tangerang, Banten';
    $letterheadContact = trim((string) data_get($templateSettings, 'school_contact')) ?: trim((string) data_get($school, 'contact')) ?: 'No Wa +6285178494207 | email : smaafbs@gmail.com | website: smaafbs.sch.id';
    $kopAlignment = data_get($templateSettings, 'report_layout.kop_alignment', 'center') === 'left' ? 'left' : 'center';
    $showLogo = (bool) data_get($templateSettings, 'report_layout.show_logo', true);
    $logoSize = min(60, max(32, (float) data_get($templateSettings, 'report_layout.logo_size', 48)));
@endphp
<table class="letterhead letterhead--asts letterhead--{{ $kopAlignment }}" style="--letterhead-logo-size: {{ $logoSize }}px;"><tr>
    <td class="letterhead__logo">@if ($showLogo && $logoIsSafe)<img src="{{ $logo }}" alt="Logo sekolah">@endif</td>
    <td class="letterhead__school"><p class="letterhead__foundation">{{ $letterheadFoundation }}</p><p class="letterhead__school-name">{{ $letterheadSchoolName }}</p>
        <p class="letterhead__school-info">{{ $letterheadAddress }}</p>
        <p class="letterhead__school-info">{{ $letterheadContact }}</p>
    </td><td class="letterhead__logo"></td>
</tr></table>
<hr class="letterhead-rule letterhead-rule--asts">
