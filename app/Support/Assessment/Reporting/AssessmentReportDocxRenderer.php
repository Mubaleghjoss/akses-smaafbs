<?php

namespace App\Support\Assessment\Reporting;

use App\Models\Assessment\ReportSnapshot;
use RuntimeException;
use ZipArchive;

final class AssessmentReportDocxRenderer
{
    /** Create a compact, standards-compliant Word report without a new dependency. */
    public function render(ReportSnapshot $snapshot): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi ZIP PHP belum aktif.');
        }

        $data = is_array($snapshot->snapshot_data) ? $snapshot->snapshot_data : [];
        $period = (array) data_get($data, 'period', []);
        $student = (array) data_get($data, 'student', []);
        $homeroom = (array) data_get($data, 'homeroom', []);
        $settings = (array) data_get($data, 'template.settings', []);
        $year = preg_replace('/^\s*Tahun\s+Pelajaran\s+/iu', '', (string) ($period['academic_year'] ?? '-')) ?: '-';
        $semester = preg_replace('/^\s*Semester\s+/iu', '', (string) ($period['semester'] ?? '-')) ?: '-';
        $type = $period['type'] ?? 'ASTS';
        $type = $type instanceof \BackedEnum ? $type->value : (string) $type;
        $title = trim((string) ($settings['report_title'] ?? '')) ?: 'LAPORAN HASIL ASESMEN SUMATIF TENGAH SEMESTER ('.strtoupper($type).')';
        $subjects = is_array($data['subjects'] ?? null) ? $data['subjects'] : [];
        $signatures = is_array($data['signatures'] ?? null) ? $data['signatures'] : [];
        $homeroomSignature = collect($signatures)->first(fn ($signature): bool => str_contains(strtolower((string) data_get($signature, 'label')), 'wali kelas')) ?: [];

        $body = $this->paragraph($title, true, 'center', 28)
            .$this->paragraph('Tahun Pelajaran '.$year, false, 'center', 22)
            .$this->table([
                ['Nama Siswa', ':', $student['name'] ?? '-', 'Kelas', ':', $student['class_name'] ?? '-'],
                ['NIS/NISN', ':', trim((string) ($student['nis'] ?? '-')).' / '.trim((string) ($student['nisn'] ?? '-')), 'Semester', ':', $semester],
            ])
            .$this->paragraph('Nilai Mata Pelajaran', true, 'left', 22)
            .$this->table(array_merge([['No.', 'Mata Pelajaran', 'Nilai', 'Predikat']], array_map(
                fn ($subject, $i): array => [(string) ($i + 1), (string) data_get($subject, 'name', '-'), (string) (data_get($subject, 'final_score') ?? '-'), (string) (data_get($subject, 'predicate') ?? '-')],
                $subjects,
                array_keys($subjects),
            )), true)
            .$this->paragraph('Ketidakhadiran', true, 'left', 22)
            .$this->table([
                ['Sakit', $this->attendance($homeroom['sick_days'] ?? 0)],
                ['Izin', $this->attendance($homeroom['permission_days'] ?? 0)],
                ['Alpa', $this->attendance($homeroom['absent_days'] ?? 0)],
            ])
            .$this->paragraph('Ekstrakurikuler', true, 'left', 22)
            .$this->table($this->extracurricularRows($homeroom['extracurricular_data'] ?? $homeroom['extracurricular'] ?? []), true)
            .$this->paragraph((string) ($homeroomSignature['place_date'] ?? 'Tangerang, ....................'), false, 'right')
            .$this->paragraph((string) ($homeroomSignature['label'] ?? 'Wali Kelas'), false, 'right')
            .$this->paragraph("\n\n".(string) ($homeroomSignature['name'] ?? '................................................'), false, 'right');

        $path = tempnam(sys_get_temp_dir(), 'assessment-docx-');
        if ($path === false) {
            throw new RuntimeException('Tidak bisa membuat dokumen Word sementara.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Tidak bisa membuat dokumen Word.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/></w:sectPr></w:body></w:document>');
        $zip->close();
        $contents = file_get_contents($path);
        @unlink($path);
        if ($contents === false) {
            throw new RuntimeException('Dokumen Word gagal dibaca.');
        }

        return $contents;
    }

    private function attendance(mixed $days): string { return (int) $days === 0 ? '-' : ((int) $days).' hari'; }
    private function extracurricularRows(mixed $items): array
    {
        $rows = [['No.', 'Nama Ekstrakurikuler', 'Predikat']];
        foreach (is_array($items) ? $items : [] as $index => $item) {
            if (filled(data_get($item, 'name'))) $rows[] = [(string) ($index + 1), (string) data_get($item, 'name'), (string) data_get($item, 'grade', data_get($item, 'description', '-'))];
        }
        return count($rows) === 1 ? [...$rows, ['-', '-', '-']] : $rows;
    }
    private function paragraph(string $text, bool $bold = false, string $align = 'left', int $size = 20): string
    {
        return '<w:p><w:pPr><w:jc w:val="'.$align.'"/></w:pPr><w:r>'.($bold ? '<w:rPr><w:b/></w:rPr>' : '').'<w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r></w:p>';
    }
    private function table(array $rows, bool $header = false): string
    {
        $xml = '<w:tbl><w:tblPr><w:tblBorders><w:top w:val="single"/><w:left w:val="single"/><w:bottom w:val="single"/><w:right w:val="single"/><w:insideH w:val="single"/><w:insideV w:val="single"/></w:tblBorders></w:tblPr>';
        foreach ($rows as $rowIndex => $row) { $xml .= '<w:tr>'; foreach ($row as $cell) $xml .= '<w:tc><w:p><w:r>'.($header && $rowIndex === 0 ? '<w:rPr><w:b/></w:rPr>' : '').'<w:t xml:space="preserve">'.$this->escape((string) $cell).'</w:t></w:r></w:p></w:tc>'; $xml .= '</w:tr>'; }
        return $xml.'</w:tbl>';
    }
    private function escape(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
}
