<?php

namespace App\Support\WifiAccount;

use App\Models\HotspotUser;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Import akun WiFi (jembatan) dari Excel kolom: USERNAME, PASSWORD, PROFIL, KELAS, ROLE.
 * - USERNAME & PASSWORD wajib.
 * - ROLE "guru" => role guru; selain itu siswa.
 * - PROFIL opsional (default "default"). KELAS opsional (kelas siswa / status guru).
 * - Upsert berdasar username. input_mode ditandai "otomatis" (hasil sinkron/import jembatan).
 */
class WifiAccountWorkbookImporter
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

                $username = $row['username'] ?? null;
                $password = $row['password'] ?? null;

                if (blank($username) || blank($password)) {
                    $result['skipped']++;

                    continue;
                }

                $role = strtolower((string) ($row['role'] ?? '')) === 'guru' ? 'guru' : 'siswa';
                $profile = $row['profile'] ?? 'default';
                $kelas = $row['kelas'] ?? null;
                $nama = $row['nama'] ?? null;

                $existing = HotspotUser::query()->where('username', $username)->first();
                $payload = [
                    'username' => $username,
                    'password' => $password,
                    'profile' => $profile !== '' ? $profile : 'default',
                    'role' => $role,
                    'nama' => $nama,
                    'kelas' => $kelas,
                    'input_mode' => 'otomatis',
                    'source' => 'router',
                ];

                if ($existing) {
                    $existing->fill($payload)->save();
                    $result['updated']++;
                } else {
                    HotspotUser::query()->create($payload);
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
                'user', 'nama_pengguna' => 'username',
                'kata_sandi', 'sandi', 'pass' => 'password',
                'profil', 'grup', 'group' => 'profile',
                'kelas_status', 'status' => 'kelas',
                'peran' => 'role',
                'nama_pemilik' => 'nama',
                default => $heading,
            };
            $headings[$columnLetter] = in_array($heading, ['username', 'password', 'profile', 'kelas', 'role', 'nama'], true)
                ? $heading
                : null;
        }

        return $headings;
    }
}
