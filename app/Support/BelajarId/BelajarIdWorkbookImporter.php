<?php

namespace App\Support\BelajarId;

use App\Models\BelajarIdAccount;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Import akun Belajar.id dari Excel 4 kolom: NAMA, STATUS, EMAIL, PASSWORD.
 * STATUS "guru"/"tendik" -> role guru; selain itu dianggap kelas -> role siswa.
 * Upsert berdasar email (unik).
 */
class BelajarIdWorkbookImporter
{
    /**
     * @return array{created:int,updated:int,skipped:int,siswa:int,guru:int}
     */
    public function import(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getSheet(0);

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'siswa' => 0, 'guru' => 0];

        $headings = $this->extractHeadings($worksheet);

        DB::transaction(function () use ($worksheet, $headings, &$result): void {
            $highestRow = $worksheet->getHighestDataRow();

            for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
                $row = [];
                foreach ($headings as $columnLetter => $heading) {
                    if ($heading === null) {
                        continue;
                    }
                    $value = trim((string) $worksheet->getCell("{$columnLetter}{$rowIndex}")->getFormattedValue());
                    if ($value !== '') {
                        $row[$heading] = $value;
                    }
                }

                $nama = $row['nama'] ?? null;
                $email = strtolower((string) ($row['email'] ?? ''));
                $password = $row['password'] ?? null;
                $status = $row['status'] ?? null;

                // Wajib: nama, email valid, password.
                if (blank($nama) || blank($password) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $result['skipped']++;

                    continue;
                }

                $role = BelajarIdAccount::roleFromStatus($status);

                $existing = BelajarIdAccount::query()->where('email', $email)->first();
                $payload = [
                    'role' => $role,
                    'nama' => $nama,
                    'status' => $status,
                    'email' => $email,
                    'password' => $password,
                ];

                if ($existing) {
                    $existing->fill($payload)->save();
                    $result['updated']++;
                } else {
                    BelajarIdAccount::query()->create($payload);
                    $result['created']++;
                }

                $result[$role]++;
            }
        });

        return $result;
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
            $rawHeading = $worksheet->getCell("{$columnLetter}1")->getFormattedValue();
            $heading = strtolower(trim((string) preg_replace('/\s+/', '_', $rawHeading)));
            $heading = match ($heading) {
                'kelas' => 'status',        // toleransi header lama
                'e-mail', 'email_belajar_id', 'email_belajarid' => 'email',
                'kata_sandi', 'sandi' => 'password',
                default => $heading,
            };
            $headings[$columnLetter] = in_array($heading, ['nama', 'status', 'email', 'password'], true) ? $heading : null;
        }

        return $headings;
    }
}
