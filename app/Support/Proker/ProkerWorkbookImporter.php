<?php

namespace App\Support\Proker;

use App\Models\Proker;
use App\Models\ProkerBidang;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class ProkerWorkbookImporter
{
    public function import(string $path, ?int $userId = null, string $sheetMode = 'first'): array
    {
        $spreadsheet = IOFactory::load($path);
        $worksheets = iterator_to_array($spreadsheet->getWorksheetIterator());

        if ($sheetMode === 'first') {
            $worksheets = array_slice($worksheets, 0, 1);
        } elseif ($sheetMode !== 'all' && str_starts_with($sheetMode, 'sheet:')) {
            $sheetName = trim(substr($sheetMode, 6));
            $worksheet = $spreadsheet->getSheetByName($sheetName);

            if (! $worksheet) {
                throw new RuntimeException("Sheet {$sheetName} tidak ditemukan di workbook.");
            }

            $worksheets = [$worksheet];
        }

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'sheets' => [],
        ];

        DB::transaction(function () use ($worksheets, $userId, &$result): void {
            foreach ($worksheets as $worksheet) {
                $rows = $this->isFlatTemplateSheet($worksheet)
                    ? $this->parseFlatTemplateSheet($worksheet)
                    : $this->parseMatrixSheet($worksheet);

                $result['sheets'][] = [
                    'sheet' => $worksheet->getTitle(),
                    'rows' => count($rows),
                ];

                foreach ($rows as $row) {
                    if (blank($row['nama'] ?? null)) {
                        $result['skipped']++;

                        continue;
                    }

                    $isCreated = $this->upsertProker($row, $userId);

                    if ($isCreated) {
                        $result['created']++;
                    } else {
                        $result['updated']++;
                    }
                }
            }
        });

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseMatrixSheet(Worksheet $worksheet): array
    {
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        $specialColumns = $this->detectSpecialColumns($worksheet, $highestColumnIndex);
        $monthColumns = $this->extractMonthColumns($worksheet, $specialColumns);
        $period = $this->extractPeriodData(
            $this->extractDisplayCellValue($worksheet->getCell('A1')),
            $worksheet->getTitle()
        );
        $periodBounds = $this->extractPeriodBounds($monthColumns);

        $rows = [];
        $currentPointDari = null;

        for ($rowIndex = 6; $rowIndex <= $highestRow; $rowIndex++) {
            $pointValue = $this->extractDisplayCellValue($worksheet->getCell("A{$rowIndex}"));

            if (filled($pointValue)) {
                $currentPointDari = $pointValue;
            }

            $nama = $this->extractDisplayCellValue($worksheet->getCell("C{$rowIndex}"));

            if (blank($nama)) {
                continue;
            }

            $waktuPelaksanaan = $specialColumns['waktu_pelaksanaan']
                ? $this->extractDisplayCellValue($worksheet->getCell($specialColumns['waktu_pelaksanaan'].$rowIndex))
                : null;

            $penanggungJawab = $specialColumns['penanggung_jawab']
                ? $this->extractDisplayCellValue($worksheet->getCell($specialColumns['penanggung_jawab'].$rowIndex))
                : null;

            if (blank($penanggungJawab) && filled($waktuPelaksanaan) && preg_match('/^PJ\s*:\s*(.+)$/i', $waktuPelaksanaan, $matches)) {
                $penanggungJawab = trim($matches[1]);
                $waktuPelaksanaan = null;
            }

            $schedulePayload = $this->extractScheduleFromMonths($worksheet, $rowIndex, $monthColumns);
            $keterangan = $this->combineNotes([
                $specialColumns['keterangan']
                    ? $this->extractDisplayCellValue($worksheet->getCell($specialColumns['keterangan'].$rowIndex))
                    : null,
                $schedulePayload['notes'] !== []
                    ? $this->combineNotes($schedulePayload['notes'])
                    : null,
            ]);

            $rows[] = $this->normalizeImportedRow([
                'periode_tahun' => $period['tahun'],
                'periode_label' => $period['label'],
                'point_dari' => $currentPointDari,
                'nomor_urut' => $this->extractNullableInteger(
                    $this->extractDisplayCellValue($worksheet->getCell("B{$rowIndex}"))
                ),
                'nama' => $nama,
                'penanggung_jawab' => $penanggungJawab,
                'jadwal_bulanan' => $schedulePayload['schedule'],
                'jadwal_ringkas' => $this->buildScheduleSummary($schedulePayload['schedule']),
                'waktu_pelaksanaan' => $waktuPelaksanaan,
                'rab_global' => $specialColumns['rab_global']
                    ? $this->extractDisplayCellValue($worksheet->getCell($specialColumns['rab_global'].$rowIndex))
                    : null,
                'keterangan' => $keterangan,
                'deskripsi' => null,
                'output_target' => null,
                'status' => null,
                'prioritas' => 'sedang',
            ], $periodBounds);
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseFlatTemplateSheet(Worksheet $worksheet): array
    {
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        $headings = [];

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $headings[$column] = $this->normalizeHeading(
                $this->extractDisplayCellValue($worksheet->getCell("{$column}1"))
            );
        }

        $periodBounds = $this->extractPeriodBoundsFromTemplateHeadings(array_values($headings));
        $rows = [];

        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $row = [];
            $jadwalBulanan = [];

            foreach ($headings as $column => $heading) {
                if (blank($heading)) {
                    continue;
                }

                $value = $this->extractDisplayCellValue($worksheet->getCell("{$column}{$rowIndex}"));

                if (str_starts_with($heading, 'jadwal_')) {
                    $label = $this->formatTemplateMonthLabel($heading);

                    if (filled($value) && filled($label)) {
                        $jadwalBulanan[$label] = $value;
                    }

                    continue;
                }

                $row[$heading] = $value;
            }

            $periodeLabel = $row['periode_label'] ?? null;
            $periodeTahun = $this->extractNullableInteger($row['periode_tahun'] ?? null);

            $rows[] = $this->normalizeImportedRow([
                'periode_tahun' => $periodeTahun ?? (int) now()->format('Y'),
                'periode_label' => filled($periodeLabel) ? $periodeLabel : ($periodeTahun ? (string) $periodeTahun : null),
                'point_dari' => $row['point_dari'] ?? null,
                'nomor_urut' => $this->extractNullableInteger($row['nomor_urut'] ?? null),
                'nama' => $row['nama_proker'] ?? $row['nama_kegiatan'] ?? null,
                'penanggung_jawab' => $row['penanggung_jawab'] ?? null,
                'jadwal_bulanan' => $jadwalBulanan,
                'jadwal_ringkas' => $this->buildScheduleSummary($jadwalBulanan),
                'waktu_pelaksanaan' => $row['waktu_pelaksanaan'] ?? null,
                'rab_global' => $row['rab_global'] ?? null,
                'keterangan' => $row['keterangan'] ?? null,
                'deskripsi' => $row['deskripsi'] ?? null,
                'output_target' => $row['output_target'] ?? null,
                'status' => $this->sanitizeStatus($row['status'] ?? null),
                'prioritas' => $this->sanitizePriority($row['prioritas'] ?? null),
            ], $periodBounds);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{start:?Carbon,end:?Carbon}  $periodBounds
     * @return array<string, mixed>
     */
    protected function normalizeImportedRow(array $row, array $periodBounds): array
    {
        $targetRange = $this->inferTargetRange(
            $row['jadwal_bulanan'] ?? [],
            $row['waktu_pelaksanaan'] ?? null,
            $periodBounds,
        );

        $status = filled($row['status'] ?? null)
            ? $this->sanitizeStatus($row['status'])
            : $this->inferStatusFromRange($targetRange['start'], $targetRange['end']);

        return [
            ...$row,
            'target_mulai' => $targetRange['start']?->toDateString(),
            'target_selesai' => $targetRange['end']?->toDateString(),
            'status' => $status,
            'progress_persen' => $status === 'selesai' ? 100 : 0,
        ];
    }

    protected function upsertProker(array $row, ?int $userId = null): bool
    {
        $bidangNama = filled($row['point_dari'] ?? null)
            ? trim((string) $row['point_dari'])
            : 'Umum';

        $bidang = ProkerBidang::query()->firstOrCreate(
            ['nama' => $bidangNama],
            [
                'penanggung_jawab' => $row['penanggung_jawab'] ?? null,
                'is_active' => true,
            ]
        );

        $query = Proker::query()
            ->where('bidang_id', $bidang->id)
            ->where('periode_tahun', (int) $row['periode_tahun'])
            ->where('nama', (string) $row['nama']);

        if (($row['nomor_urut'] ?? null) === null) {
            $query->whereNull('nomor_urut');
        } else {
            $query->where('nomor_urut', (int) $row['nomor_urut']);
        }

        $proker = $query->first();
        $isCreated = ! $proker;
        $hasMonitoringState = $proker && ($proker->updates()->exists() || filled($proker->last_monitored_at));

        $proker ??= new Proker;

        $proker->fill([
            'bidang_id' => $bidang->id,
            'periode_tahun' => (int) $row['periode_tahun'],
            'periode_label' => $row['periode_label'] ?? null,
            'point_dari' => $row['point_dari'] ?? null,
            'nomor_urut' => $row['nomor_urut'] ?? null,
            'nama' => $row['nama'],
            'penanggung_jawab' => $row['penanggung_jawab'] ?? null,
            'target_mulai' => $row['target_mulai'] ?? null,
            'target_selesai' => $row['target_selesai'] ?? null,
            'status' => $hasMonitoringState ? $proker->status : ($row['status'] ?? 'draft'),
            'prioritas' => $row['prioritas'] ?? 'sedang',
            'progress_persen' => $hasMonitoringState ? $proker->progress_persen : ($row['progress_persen'] ?? 0),
            'jadwal_bulanan' => $row['jadwal_bulanan'] ?? null,
            'jadwal_ringkas' => $row['jadwal_ringkas'] ?? null,
            'waktu_pelaksanaan' => $row['waktu_pelaksanaan'] ?? null,
            'rab_global' => $row['rab_global'] ?? null,
            'keterangan' => $row['keterangan'] ?? null,
            'deskripsi' => $row['deskripsi'] ?? null,
            'output_target' => $row['output_target'] ?? null,
            'created_by' => $proker->created_by ?? $userId,
        ]);

        $proker->save();

        return $isCreated;
    }

    protected function isFlatTemplateSheet(Worksheet $worksheet): bool
    {
        $headings = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $headings[] = $this->normalizeHeading(
                $this->extractDisplayCellValue($worksheet->getCell("{$column}1"))
            );
        }

        return in_array('nama_proker', $headings, true) || in_array('nama_kegiatan', $headings, true);
    }

    /**
     * @return array<string, string|null>
     */
    protected function detectSpecialColumns(Worksheet $worksheet, int $highestColumnIndex): array
    {
        $columns = [
            'waktu_pelaksanaan' => null,
            'penanggung_jawab' => null,
            'rab_global' => null,
            'keterangan' => null,
        ];

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $heading = strtoupper((string) $this->extractDisplayCellValue($worksheet->getCell("{$column}4")));

            if (str_contains($heading, 'JAM PELAKSANAAN')) {
                $columns['waktu_pelaksanaan'] = $column;
            }

            if ($heading === 'PJ') {
                $columns['penanggung_jawab'] = $column;
            }

            if (str_contains($heading, 'RAB')) {
                $columns['rab_global'] = $column;
            }

            if (str_contains($heading, 'KETERANGAN')) {
                $columns['keterangan'] = $column;
            }
        }

        return $columns;
    }

    /**
     * @param  array<string, string|null>  $specialColumns
     * @return array<string, string>
     */
    protected function extractMonthColumns(Worksheet $worksheet, array $specialColumns): array
    {
        $specialIndexes = array_values(array_filter(array_map(
            fn (?string $column): ?int => $column ? Coordinate::columnIndexFromString($column) : null,
            $specialColumns
        )));

        $lastMonthColumnIndex = $specialIndexes !== []
            ? (min($specialIndexes) - 1)
            : Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());

        $monthColumns = [];

        for ($columnIndex = Coordinate::columnIndexFromString('D'); $columnIndex <= $lastMonthColumnIndex; $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $label = $this->extractMonthHeadingValue($worksheet->getCell("{$column}5"));

            if (filled($label)) {
                $monthColumns[$column] = $label;
            }
        }

        return $monthColumns;
    }

    /**
     * @param  array<string, string>  $monthColumns
     * @return array{schedule: array<string, string>, notes: array<int, string>}
     */
    protected function extractScheduleFromMonths(Worksheet $worksheet, int $rowIndex, array $monthColumns): array
    {
        $schedule = [];
        $notes = [];

        foreach ($monthColumns as $column => $label) {
            $value = $this->extractScheduleCellValue($worksheet->getCell("{$column}{$rowIndex}"));

            if (blank($value)) {
                continue;
            }

            if ($this->looksLikeScheduleValue($value)) {
                $schedule[$label] = $value;

                continue;
            }

            $notes[] = "{$label}: {$value}";
        }

        return [
            'schedule' => $schedule,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, string>  $schedule
     */
    protected function buildScheduleSummary(array $schedule): ?string
    {
        if ($schedule === []) {
            return null;
        }

        return collect($schedule)
            ->map(fn (string $value, string $month): string => "{$month}: {$value}")
            ->implode(' | ');
    }

    /**
     * @return array{tahun:int, label:string}
     */
    protected function extractPeriodData(?string $rawLabel, string $fallbackSheetName): array
    {
        $source = filled($rawLabel) ? $rawLabel : $fallbackSheetName;
        $year = preg_match('/(20\d{2})/', (string) $source, $matches)
            ? (int) $matches[1]
            : (int) now()->format('Y');

        $label = preg_match('/(20\d{2}\s*-\s*20\d{2})/', (string) $source, $matches)
            ? preg_replace('/\s+/', '', $matches[1])
            : (string) $year;

        return [
            'tahun' => $year,
            'label' => $label,
        ];
    }

    /**
     * @param  array<string, string>  $monthColumns
     * @return array{start:?Carbon,end:?Carbon}
     */
    protected function extractPeriodBounds(array $monthColumns): array
    {
        $dates = collect($monthColumns)
            ->map(fn (string $label): ?Carbon => $this->parseMonthLabel($label))
            ->filter();

        if ($dates->isEmpty()) {
            return [
                'start' => null,
                'end' => null,
            ];
        }

        /** @var Carbon $start */
        $start = $dates->first()->copy()->startOfMonth();
        /** @var Carbon $end */
        $end = $dates->last()->copy()->endOfMonth();

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * @param  array<int, string|null>  $headings
     * @return array{start:?Carbon,end:?Carbon}
     */
    protected function extractPeriodBoundsFromTemplateHeadings(array $headings): array
    {
        $dates = collect($headings)
            ->filter(fn (?string $heading): bool => filled($heading) && str_starts_with($heading, 'jadwal_'))
            ->map(fn (string $heading): ?Carbon => $this->parseMonthLabel($this->formatTemplateMonthLabel($heading)))
            ->filter();

        if ($dates->isEmpty()) {
            return [
                'start' => null,
                'end' => null,
            ];
        }

        /** @var Carbon $start */
        $start = $dates->first()->copy()->startOfMonth();
        /** @var Carbon $end */
        $end = $dates->last()->copy()->endOfMonth();

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    protected function formatTemplateMonthLabel(string $heading): ?string
    {
        if (! preg_match('/^jadwal_([a-z]{3})_(20\d{2})$/', $heading, $matches)) {
            return null;
        }

        $month = ucfirst(strtolower($matches[1]));
        $yearSuffix = substr($matches[2], -2);

        return "{$month}-{$yearSuffix}";
    }

    protected function sanitizeStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'draft', 'berjalan', 'terkendala', 'selesai' => strtolower((string) $status),
            default => 'draft',
        };
    }

    protected function sanitizePriority(?string $priority): string
    {
        return match (strtolower((string) $priority)) {
            'rendah', 'sedang', 'tinggi' => strtolower((string) $priority),
            default => 'sedang',
        };
    }

    protected function extractNullableInteger(mixed $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        if (preg_match('/\d+/', (string) $value, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    protected function normalizeHeading(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return strtolower(trim((string) str($value)->snake()));
    }

    protected function normalizeCellValue(mixed $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }

    protected function extractDisplayCellValue(Cell $cell): ?string
    {
        return $this->normalizeCellValue($cell->getFormattedValue());
    }

    protected function extractMonthHeadingValue(Cell $cell): ?string
    {
        $rawValue = $cell->getValue();

        if (is_numeric($rawValue)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $rawValue))->format('M-y');
            } catch (Throwable) {
                // Ignore and fall back to formatted value.
            }
        }

        return $this->normalizeCellValue($cell->getFormattedValue());
    }

    protected function extractScheduleCellValue(Cell $cell): ?string
    {
        $rawValue = $cell->getCalculatedValue();

        if (is_numeric($rawValue)) {
            $numericValue = (float) $rawValue;

            if (floor($numericValue) === $numericValue) {
                return (string) (int) $numericValue;
            }

            return rtrim(rtrim(number_format($numericValue, 2, '.', ''), '0'), '.');
        }

        return $this->normalizeCellValue($cell->getFormattedValue());
    }

    protected function looksLikeScheduleValue(string $value): bool
    {
        return preg_match('/\d/', $value) === 1;
    }

    /**
     * @param  array<int, string|null>  $notes
     */
    protected function combineNotes(array $notes): ?string
    {
        $notes = array_values(array_filter($notes, fn (?string $note): bool => filled($note)));

        if ($notes === []) {
            return null;
        }

        return implode(' | ', $notes);
    }

    /**
     * @param  array<string, string>  $schedule
     * @param  array{start:?Carbon,end:?Carbon}  $periodBounds
     * @return array{start:?Carbon,end:?Carbon}
     */
    protected function inferTargetRange(array $schedule, ?string $waktuPelaksanaan, array $periodBounds): array
    {
        $starts = [];
        $ends = [];

        foreach ($schedule as $monthLabel => $value) {
            $range = $this->parseScheduleEntry($monthLabel, $value);

            if ($range['start']) {
                $starts[] = $range['start'];
            }

            if ($range['end']) {
                $ends[] = $range['end'];
            }
        }

        if ($starts !== [] && $ends !== []) {
            usort($starts, fn (Carbon $left, Carbon $right): int => $left->timestamp <=> $right->timestamp);
            usort($ends, fn (Carbon $left, Carbon $right): int => $left->timestamp <=> $right->timestamp);

            return [
                'start' => $starts[0]->copy()->startOfDay(),
                'end' => end($ends)->copy()->endOfDay(),
            ];
        }

        if (filled($waktuPelaksanaan) && $periodBounds['start'] && $periodBounds['end']) {
            return [
                'start' => $periodBounds['start']->copy()->startOfDay(),
                'end' => $periodBounds['end']->copy()->endOfDay(),
            ];
        }

        return [
            'start' => null,
            'end' => null,
        ];
    }

    /**
     * @return array{start:?Carbon,end:?Carbon}
     */
    protected function parseScheduleEntry(string $monthLabel, string $value): array
    {
        $anchor = $this->parseMonthLabel($monthLabel);

        if (! $anchor) {
            return [
                'start' => null,
                'end' => null,
            ];
        }

        $cleanValue = trim((string) preg_replace('/\s+/u', ' ', str_replace(['�', '�'], '-', $value)));

        if (preg_match('/^(\d{1,2})$/', $cleanValue, $matches)) {
            return [
                'start' => $anchor->copy()->day((int) $matches[1])->startOfDay(),
                'end' => $anchor->copy()->day((int) $matches[1])->endOfDay(),
            ];
        }

        if (preg_match('/^(\d{1,2})\s*-\s*(\d{1,2})$/', $cleanValue, $matches)) {
            return [
                'start' => $anchor->copy()->day((int) $matches[1])->startOfDay(),
                'end' => $anchor->copy()->day((int) $matches[2])->endOfDay(),
            ];
        }

        $fragments = $this->extractDateFragments($cleanValue);

        if ($fragments === []) {
            return [
                'start' => null,
                'end' => null,
            ];
        }

        $start = $this->buildDateFromFragment($fragments[0], $anchor);
        $end = $start;

        if (isset($fragments[1])) {
            $end = $this->buildDateFromFragment($fragments[1], $anchor, $start);
        }

        return [
            'start' => $start?->copy()->startOfDay(),
            'end' => $end?->copy()->endOfDay(),
        ];
    }

    protected function parseMonthLabel(?string $monthLabel): ?Carbon
    {
        if (blank($monthLabel)) {
            return null;
        }

        try {
            $parsed = DateTimeImmutable::createFromFormat('!M-y', (string) $monthLabel);

            if (! $parsed) {
                return null;
            }

            return Carbon::instance($parsed)->startOfMonth();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{day:int, month:?int}>
     */
    protected function extractDateFragments(string $value): array
    {
        preg_match_all('/(\d{1,2})\s*([[:alpha:]]+)?/u', strtolower($value), $matches, PREG_SET_ORDER);

        $fragments = [];

        foreach ($matches as $match) {
            $day = (int) ($match[1] ?? 0);

            if ($day < 1 || $day > 31) {
                continue;
            }

            $fragments[] = [
                'day' => $day,
                'month' => $this->normalizeMonthNumber($match[2] ?? null),
            ];
        }

        return $fragments;
    }

    /**
     * @param  array{day:int, month:?int}  $fragment
     */
    protected function buildDateFromFragment(array $fragment, Carbon $anchor, ?Carbon $previous = null): ?Carbon
    {
        $month = $fragment['month'] ?? $anchor->month;
        $year = $anchor->year;

        if ($previous) {
            $year = $previous->year;

            if ($month < $previous->month) {
                $year++;
            }
        } elseif ($fragment['month'] !== null && $month < $anchor->month) {
            $year++;
        }

        try {
            return Carbon::create($year, $month, $fragment['day']);
        } catch (Throwable) {
            return null;
        }
    }

    protected function normalizeMonthNumber(?string $month): ?int
    {
        if (blank($month)) {
            return null;
        }

        $month = strtolower(trim((string) preg_replace('/[^[:alpha:]]/u', '', $month)));

        $map = [
            'jan' => 1,
            'januari' => 1,
            'january' => 1,
            'feb' => 2,
            'februari' => 2,
            'february' => 2,
            'mar' => 3,
            'maret' => 3,
            'march' => 3,
            'apr' => 4,
            'april' => 4,
            'mei' => 5,
            'may' => 5,
            'jun' => 6,
            'juni' => 6,
            'june' => 6,
            'jul' => 7,
            'juli' => 7,
            'july' => 7,
            'agu' => 8,
            'agt' => 8,
            'ags' => 8,
            'agus' => 8,
            'agustus' => 8,
            'aug' => 8,
            'august' => 8,
            'sep' => 9,
            'sept' => 9,
            'september' => 9,
            'oct' => 10,
            'okt' => 10,
            'oktober' => 10,
            'october' => 10,
            'nov' => 11,
            'november' => 11,
            'des' => 12,
            'desember' => 12,
            'dec' => 12,
            'december' => 12,
        ];

        return $map[$month] ?? null;
    }

    protected function inferStatusFromRange(?Carbon $start, ?Carbon $end): string
    {
        $today = now()->startOfDay();

        if ($start && $today->lt($start->copy()->startOfDay())) {
            return 'draft';
        }

        if ($end && $today->gt($end->copy()->endOfDay())) {
            return 'selesai';
        }

        if ($start || $end) {
            return 'berjalan';
        }

        return 'draft';
    }
}
