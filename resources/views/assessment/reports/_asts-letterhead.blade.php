@php
    $letterheadAddress = trim((string) data_get($school, 'address')) ?: 'JL. UNTUNG SUROPATI 1 NO. 8 RT/RW 003/003, CIMONE JAYA, KARAWACI, KOTA TANGERANG, BANTEN.';
    $letterheadContact = trim((string) data_get($school, 'contact')) ?: '+6285178494207';
@endphp
<table class="letterhead letterhead--asts"><tr>
    <td class="letterhead__logo">@if ($logoIsSafe)<img src="{{ $logo }}" alt="Logo sekolah">@endif</td>
    <td class="letterhead__school"><p class="letterhead__foundation">YAYASAN DAR AL FURQON AL HAKIM</p><p class="letterhead__school-name">{{ data_get($school, 'name', 'SMA AL FURQON BOARDING SCHOOL') }}</p>
        <p class="letterhead__school-info">{{ $letterheadAddress }}</p>
        <p class="letterhead__school-info">{{ $letterheadContact }}</p>
    </td><td class="letterhead__logo"></td>
</tr></table>
<hr class="letterhead-rule letterhead-rule--asts">
