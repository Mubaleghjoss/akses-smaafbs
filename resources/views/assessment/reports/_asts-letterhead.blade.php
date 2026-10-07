@php
    $letterheadFoundation = trim((string) data_get($school, 'foundation_name')) ?: 'YAYASAN DAR AL FURQON AL HAKIM';
    $letterheadSchoolName = trim((string) data_get($school, 'name')) ?: 'SMA AL FURQON BOARDING SCHOOL';
    $letterheadAddress = trim((string) data_get($school, 'address')) ?: 'Jl. Untung Suropati 1 No.8 RT/RW 003/003, Kel. Cimone Jaya, Kec. Karawaci, Kota Tangerang, Banten';
    $letterheadContact = trim((string) data_get($school, 'contact')) ?: 'No Wa +6285178494207 | email : smaafbs@gmail.com | website: smaafbs.sch.id';
@endphp
<table class="letterhead letterhead--asts"><tr>
    <td class="letterhead__logo">@if ($logoIsSafe)<img src="{{ $logo }}" alt="Logo sekolah">@endif</td>
    <td class="letterhead__school"><p class="letterhead__foundation">{{ $letterheadFoundation }}</p><p class="letterhead__school-name">{{ $letterheadSchoolName }}</p>
        <p class="letterhead__school-info">{{ $letterheadAddress }}</p>
        <p class="letterhead__school-info">{{ $letterheadContact }}</p>
    </td><td class="letterhead__logo"></td>
</tr></table>
<hr class="letterhead-rule letterhead-rule--asts">
