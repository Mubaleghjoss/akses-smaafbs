<table class="letterhead letterhead--asts"><tr>
    <td class="letterhead__logo">@if ($logoIsSafe)<img src="{{ $logo }}" alt="Logo sekolah">@endif</td>
    <td class="letterhead__school"><p class="letterhead__foundation">YAYASAN DAR AL FURQON AL HAKIM</p><p class="letterhead__school-name">SMA AL FURQON BOARDING SCHOOL</p>
        @if (filled(data_get($school, 'address')))<p class="letterhead__school-info">{{ data_get($school, 'address') }}</p>@endif
        @if (filled(data_get($school, 'contact')))<p class="letterhead__school-info">{{ data_get($school, 'contact') }}</p>@endif
    </td><td class="letterhead__logo"></td>
</tr></table>
<hr class="letterhead-rule letterhead-rule--asts">
