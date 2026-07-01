<?php

namespace App\Support\ServerSync;

use RuntimeException;

class MariaDbDumpNormalizer
{
    /**
     * @return array{path: string, removed_lines: int, excluded_tables: array<int, string>}
     */
    public function normalizeForLocalClient(string $sourcePath): array
    {
        $source = fopen($sourcePath, 'rb');

        if ($source === false) {
            throw new RuntimeException("Tidak bisa membaca dump database: {$sourcePath}");
        }

        $targetPath = $sourcePath.'.local-compatible.sql';
        $target = fopen($targetPath, 'wb');

        if ($target === false) {
            fclose($source);

            throw new RuntimeException("Tidak bisa membuat dump kompatibel lokal: {$targetPath}");
        }

        $removedLines = 0;
        $excludedTables = [];
        $skippingTable = null;

        try {
            while (($line = fgets($source)) !== false) {
                $tableName = $this->tableStructureName($line);

                if ($skippingTable !== null) {
                    if ($tableName !== null && $tableName !== $skippingTable) {
                        $skippingTable = null;
                    } elseif (trim($line) === 'UNLOCK TABLES;') {
                        $skippingTable = null;

                        continue;
                    } else {
                        continue;
                    }
                }

                if ($tableName !== null && $this->shouldKeepLocalTable($tableName)) {
                    $skippingTable = $tableName;
                    $excludedTables[] = $tableName;

                    continue;
                }

                if ($this->isSandboxModeDirective($line)) {
                    $removedLines++;

                    continue;
                }

                if (fwrite($target, $line) !== strlen($line)) {
                    throw new RuntimeException("Gagal menulis dump kompatibel lokal: {$targetPath}");
                }
            }

            if (! feof($source)) {
                throw new RuntimeException("Gagal membaca seluruh dump database: {$sourcePath}");
            }
        } catch (\Throwable $exception) {
            fclose($source);
            fclose($target);
            @unlink($targetPath);

            throw $exception;
        }

        fclose($source);
        fclose($target);

        if ($removedLines === 0 && $excludedTables === []) {
            @unlink($targetPath);

            return [
                'path' => $sourcePath,
                'removed_lines' => 0,
                'excluded_tables' => [],
            ];
        }

        return [
            'path' => $targetPath,
            'removed_lines' => $removedLines,
            'excluded_tables' => array_values(array_unique($excludedTables)),
        ];
    }

    protected function isSandboxModeDirective(string $line): bool
    {
        return preg_match(
            '/^\/\*M!\d+\\\\- (?:enable|disable) the sandbox mode \*\/\s*$/i',
            $line,
        ) === 1;
    }

    protected function tableStructureName(string $line): ?string
    {
        if (preg_match('/^-- Table structure for table `([^`]+)`\s*$/', $line, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    protected function shouldKeepLocalTable(string $tableName): bool
    {
        return $tableName === 'sessions';
    }
}
