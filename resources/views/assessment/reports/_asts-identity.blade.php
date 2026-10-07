<table class="identity identity--asts{{ ! empty($summary) ? ' asts-summary-identity' : '' }}">
    <colgroup>
        <col class="identity__col--asts-left-label">
        <col class="identity__col--asts-left-separator">
        <col class="identity__col--asts-primary-value">
        <col class="identity__col--asts-spacer">
        <col class="identity__col--asts-right-label">
        <col class="identity__col--asts-right-separator">
        <col class="identity__col--asts-secondary-value">
    </colgroup>
    <tr>
        <td class="identity__label identity__label--asts-primary">Nama Siswa</td>
        <td class="identity__separator identity__separator--asts identity__separator--asts-left">:</td>
        <td class="identity__value--asts identity__value--asts-primary identity__value--asts-nowrap">{{ data_get($student, 'name', '-') }}</td>
        <td class="identity__spacer" aria-hidden="true"></td>
        <td class="identity__label identity__label--asts-secondary">Kelas</td>
        <td class="identity__separator identity__separator--asts identity__separator--asts-right">:</td>
        <td class="identity__value--asts identity__value--asts-secondary identity__value--asts-nowrap">{{ $className ?: '-' }}</td>
    </tr>
    <tr>
        <td class="identity__label identity__label--asts-primary">NIS / NISN</td>
        <td class="identity__separator identity__separator--asts identity__separator--asts-left">:</td>
        <td class="identity__value--asts identity__value--asts-primary identity__value--asts-nowrap">{{ $studentNis }} / {{ $studentNisn }}</td>
        <td class="identity__spacer" aria-hidden="true"></td>
        <td class="identity__label identity__label--asts-secondary">Semester</td>
        <td class="identity__separator identity__separator--asts identity__separator--asts-right">:</td>
        <td class="identity__value--asts identity__value--asts-secondary identity__value--asts-nowrap">{{ data_get($period, 'semester', '-') }}</td>
    </tr>
</table>
