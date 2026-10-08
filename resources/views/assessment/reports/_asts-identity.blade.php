@php
    $identityStyle = (string) data_get($templateSettings, 'report_layout.identity_style', 'two_column_compact');
    $identityStyle = in_array($identityStyle, ['two_column_compact', 'one_column_full', 'two_column_wide_left'], true) ? $identityStyle : 'two_column_compact';
    $identityFontSize = min(12, max(9, (float) data_get($templateSettings, 'report_layout.identity_font_size', 9)));
    $identityTableSpacing = min(24, max(0, (float) data_get($templateSettings, 'report_layout.identity_table_spacing', 3)));
    $identityLabels = [
        'student_name' => trim((string) data_get($templateSettings, 'report_layout.labels.student_name', 'Nama Siswa')) ?: 'Nama Siswa',
        'student_number' => trim((string) data_get($templateSettings, 'report_layout.labels.student_number', 'NIS/NISN')) ?: 'NIS/NISN',
        'class' => trim((string) data_get($templateSettings, 'report_layout.labels.class', 'Kelas')) ?: 'Kelas',
        'semester' => trim((string) data_get($templateSettings, 'report_layout.labels.semester', 'Semester')) ?: 'Semester',
    ];
@endphp
<table class="identity identity--asts identity--asts-{{ $identityStyle }}{{ ! empty($summary) ? ' asts-summary-identity' : '' }}" style="--identity-font-size: {{ $identityFontSize }}pt; --identity-table-spacing: {{ $identityTableSpacing }}pt;">
    @if ($identityStyle === 'one_column_full')
        <tr><td class="identity__label">{{ $identityLabels['student_name'] }}</td><td class="identity__separator">:</td><td>{{ data_get($student, 'name', '-') }}</td></tr>
        <tr><td class="identity__label">{{ $identityLabels['student_number'] }}</td><td class="identity__separator">:</td><td>{{ $studentNis }} / {{ $studentNisn }}</td></tr>
        <tr><td class="identity__label">{{ $identityLabels['class'] }}</td><td class="identity__separator">:</td><td>{{ $className ?: '-' }}</td></tr>
        <tr><td class="identity__label">{{ $identityLabels['semester'] }}</td><td class="identity__separator">:</td><td>{{ data_get($period, 'semester', '-') }}</td></tr>
    @else
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
            <td class="identity__label identity__label--asts-primary">{{ $identityLabels['student_name'] }}</td>
            <td class="identity__separator identity__separator--asts identity__separator--asts-left">:</td>
            <td class="identity__value--asts identity__value--asts-primary identity__value--asts-nowrap">{{ data_get($student, 'name', '-') }}</td>
            <td class="identity__spacer" aria-hidden="true"></td>
            <td class="identity__label identity__label--asts-secondary">{{ $identityLabels['class'] }}</td>
            <td class="identity__separator identity__separator--asts identity__separator--asts-right">:</td>
            <td class="identity__value--asts identity__value--asts-secondary identity__value--asts-nowrap">{{ $className ?: '-' }}</td>
        </tr>
        <tr>
            <td class="identity__label identity__label--asts-primary">{{ $identityLabels['student_number'] }}</td>
            <td class="identity__separator identity__separator--asts identity__separator--asts-left">:</td>
            <td class="identity__value--asts identity__value--asts-primary identity__value--asts-nowrap">{{ $studentNis }} / {{ $studentNisn }}</td>
            <td class="identity__spacer" aria-hidden="true"></td>
            <td class="identity__label identity__label--asts-secondary">{{ $identityLabels['semester'] }}</td>
            <td class="identity__separator identity__separator--asts identity__separator--asts-right">:</td>
            <td class="identity__value--asts identity__value--asts-secondary identity__value--asts-nowrap">{{ data_get($period, 'semester', '-') }}</td>
        </tr>
    @endif
</table>
