<?php

namespace App\Support\DataSiswa;

use App\Models\DataSiswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DataSiswaProfileWorkbookImporter
{
    /**
     * @return array{
     *     summary: array{ready:int,review:int,not_found:int,skipped:int},
     *     rows: array<int, array<string, mixed>>
     * }
     */
    public function analyze(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($path);
        $worksheet = $this->resolveWorksheet($spreadsheet);
        $headings = $this->extractHeadings($worksheet);
        $allowedColumns = array_flip([
            'nama',
            'nipd',
            'nisn',
            'kepribadian',
            'gaya_belajar',
            'profiling',
            'mbti',
        ]);

        $students = DataSiswa::query()
            ->select(['id', 'nama', 'nisn', 'nipd'])
            ->orderBy('nama')
            ->get();

        $summary = [
            'ready' => 0,
            'review' => 0,
            'not_found' => 0,
            'skipped' => 0,
        ];

        $rows = [];
        $highestRow = $worksheet->getHighestDataRow();

        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $payload = [];

            foreach ($headings as $columnLetter => $heading) {
                if (! $heading || ! array_key_exists($heading, $allowedColumns)) {
                    continue;
                }

                $value = trim((string) $worksheet->getCell("{$columnLetter}{$rowIndex}")->getFormattedValue());

                if ($value !== '') {
                    $payload[$heading] = $value;
                }
            }

            $normalizedPayload = [
                'kepribadian' => $this->normalizeTestValue($payload['kepribadian'] ?? null),
                'gaya_belajar' => $this->normalizeTestValue($payload['gaya_belajar'] ?? null),
                'profiling' => $this->normalizeTestValue($payload['profiling'] ?? null),
                'mbti' => $this->normalizeTestValue($payload['mbti'] ?? null),
            ];

            $profilePayload = array_filter($normalizedPayload, fn ($value): bool => filled($value));
            $name = trim((string) ($payload['nama'] ?? ''));

            if ($name === '' || $profilePayload === []) {
                $summary['skipped']++;
                $rows[] = $this->buildPreviewRow($rowIndex, $payload, $normalizedPayload, 'skipped', 'Baris dilewati karena nama atau data tes siswa kosong.');

                continue;
            }

            $exactMatch = $this->findExactMatch($payload, $students);

            if ($exactMatch instanceof DataSiswa) {
                $summary['ready']++;
                $rows[] = $this->buildPreviewRow(
                    $rowIndex,
                    $payload,
                    $normalizedPayload,
                    'ready',
                    'Cocok langsung dengan data siswa di sistem.',
                    $exactMatch,
                    collect([$exactMatch])
                );

                continue;
            }

            $candidates = $this->findSimilarCandidates($name, $students);

            if ($candidates->isNotEmpty()) {
                $summary['review']++;
                $rows[] = $this->buildPreviewRow(
                    $rowIndex,
                    $payload,
                    $normalizedPayload,
                    'review',
                    'Nama tidak persis sama. Pilih siswa yang benar bila ini data yang dimaksud.',
                    null,
                    $candidates
                );

                continue;
            }

            $summary['not_found']++;
            $rows[] = $this->buildPreviewRow(
                $rowIndex,
                $payload,
                $normalizedPayload,
                'not_found',
                'Tidak ada siswa yang cocok di sistem. Periksa nama, NIPD, atau NISN.'
            );
        }

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{
     *     updated:int,
     *     unchanged:int,
     *     skipped:int,
     *     failed:int,
     *     details: array<int, string>
     * }
     */
    public function apply(array $rows): array
    {
        $result = [
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        DB::transaction(function () use ($rows, &$result): void {
            foreach ($rows as $row) {
                $name = trim((string) ($row['source_name'] ?? '-'));
                $shouldImport = (bool) ($row['confirm_import'] ?? false);
                $studentId = filled($row['selected_student_id'] ?? null) ? (int) $row['selected_student_id'] : null;

                if (! $shouldImport) {
                    $result['skipped']++;

                    if (filled($row['reason'] ?? null)) {
                        $result['details'][] = $name.': '.trim((string) $row['reason']);
                    }

                    continue;
                }

                if (! $studentId) {
                    $result['failed']++;
                    $result['details'][] = $name.': gagal diproses karena siswa tujuan belum dipilih.';

                    continue;
                }

                $student = DataSiswa::query()->find($studentId);

                if (! $student) {
                    $result['failed']++;
                    $result['details'][] = $name.': gagal diproses karena data siswa tujuan tidak ditemukan.';

                    continue;
                }

                $payload = array_filter([
                    'kepribadian' => $this->normalizeTestValue($row['kepribadian'] ?? null),
                    'gaya_belajar' => $this->normalizeTestValue($row['gaya_belajar'] ?? null),
                    'profiling' => $this->normalizeTestValue($row['profiling'] ?? null),
                    'mbti' => $this->normalizeTestValue($row['mbti'] ?? null),
                ], fn ($value): bool => filled($value));

                if ($payload === []) {
                    $result['skipped']++;
                    $result['details'][] = $name.': dilewati karena data tes siswa kosong.';

                    continue;
                }

                $student->fill($payload);

                if (! $student->isDirty()) {
                    $result['unchanged']++;

                    continue;
                }

                $student->save();
                $result['updated']++;
            }
        });

        return $result;
    }

    protected function resolveWorksheet(Spreadsheet $spreadsheet): Worksheet
    {
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            if (in_array(strtolower(trim($worksheet->getTitle())), ['template_data_tes_siswa', 'template_profil_sederhana'], true)) {
                return $worksheet;
            }
        }

        return $spreadsheet->getSheet(0);
    }

    /**
     * @return array<string, string|null>
     */
    protected function extractHeadings(Worksheet $worksheet): array
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        $headings = [];

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
            $rawHeading = (string) $worksheet->getCell("{$columnLetter}1")->getFormattedValue();
            $heading = $this->resolveHeadingAlias($this->normalizeHeading($rawHeading));

            $headings[$columnLetter] = $heading !== '' ? $heading : null;
        }

        return $headings;
    }

    protected function normalizeHeading(?string $heading): ?string
    {
        if ($heading === null) {
            return null;
        }

        $normalized = strtolower(trim($heading));
        $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized);
        $normalized = trim((string) $normalized, '_');

        return $normalized !== '' ? $normalized : null;
    }

    protected function resolveHeadingAlias(?string $heading): ?string
    {
        if ($heading === null) {
            return null;
        }

        return match ($heading) {
            'no', 'nomor', 'no_urut' => 'no',
            'gaya_belajar_siswa' => 'gaya_belajar',
            default => $heading,
        };
    }

    protected function normalizeTestValue(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? Str::upper($normalized) : null;
    }

    protected function normalizeName(?string $value): string
    {
        $normalized = Str::upper(trim((string) $value));
        $normalized = preg_replace('/[^A-Z0-9]+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    protected function findExactMatch(array $payload, Collection $students): ?DataSiswa
    {
        if (filled($payload['nipd'] ?? null)) {
            return DataSiswa::query()->where('nipd', $payload['nipd'])->first();
        }

        if (filled($payload['nisn'] ?? null)) {
            return DataSiswa::query()->where('nisn', $payload['nisn'])->first();
        }

        $normalizedName = $this->normalizeName($payload['nama'] ?? null);

        if ($normalizedName === '') {
            return null;
        }

        $matches = $students->filter(fn (DataSiswa $student): bool => $this->normalizeName($student->nama) === $normalizedName)->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    protected function findSimilarCandidates(string $name, Collection $students): Collection
    {
        $normalizedName = $this->normalizeName($name);

        if ($normalizedName === '') {
            return collect();
        }

        return $students
            ->map(function (DataSiswa $student) use ($normalizedName): array {
                $targetName = $this->normalizeName($student->nama);
                similar_text($normalizedName, $targetName, $percent);

                return [
                    'student' => $student,
                    'score' => $percent,
                ];
            })
            ->filter(fn (array $item): bool => $item['score'] >= 70)
            ->sortByDesc('score')
            ->take(5)
            ->map(fn (array $item): DataSiswa => $item['student'])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, ?string>  $normalizedPayload
     */
    protected function buildPreviewRow(
        int $rowIndex,
        array $payload,
        array $normalizedPayload,
        string $status,
        string $reason,
        ?DataSiswa $selectedStudent = null,
        ?Collection $candidates = null
    ): array {
        $candidateOptions = ($candidates ?? collect())
            ->map(fn (DataSiswa $student): array => [
                'id' => $student->id,
                'label' => $student->nama
                    .' | NIPD: '.($student->nipd ?: '-')
                    .' | NISN: '.($student->nisn ?: '-'),
            ])
            ->values()
            ->all();

        return [
            'row_number' => $rowIndex,
            'source_name' => trim((string) ($payload['nama'] ?? '')),
            'nipd' => trim((string) ($payload['nipd'] ?? '')),
            'nisn' => trim((string) ($payload['nisn'] ?? '')),
            'kepribadian' => $normalizedPayload['kepribadian'] ?? null,
            'gaya_belajar' => $normalizedPayload['gaya_belajar'] ?? null,
            'profiling' => $normalizedPayload['profiling'] ?? null,
            'mbti' => $normalizedPayload['mbti'] ?? null,
            'match_status' => $status,
            'match_status_label' => match ($status) {
                'ready' => 'Siap diimport',
                'review' => 'Perlu konfirmasi',
                'not_found' => 'Tidak ditemukan',
                default => 'Dilewati',
            },
            'reason' => $reason,
            'selected_student_id' => $selectedStudent?->id,
            'confirm_import' => $status === 'ready',
            'candidate_options_json' => json_encode($candidateOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
}
