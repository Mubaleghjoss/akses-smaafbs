<?php

namespace App\Support\ServerSync;

use RuntimeException;

class StorageDirectorySwapper
{
    public function replace(string $stagingPath, string $localPath, string $previousPath): void
    {
        if (! is_dir($stagingPath)) {
            throw new RuntimeException("Folder staging tidak ditemukan: {$stagingPath}");
        }

        $this->ensureDirectoryExists(dirname($localPath));
        $this->ensureDirectoryExists(dirname($previousPath));
        $this->deleteDirectory($previousPath);

        $hadLocalDirectory = is_dir($localPath);

        if ($hadLocalDirectory && ! @rename($localPath, $previousPath)) {
            throw new RuntimeException("Folder lokal tidak dapat dipindahkan sebelum aktivasi: {$localPath}");
        }

        try {
            if (! @rename($stagingPath, $localPath)) {
                throw new RuntimeException("Folder staging tidak dapat diaktifkan: {$stagingPath}");
            }
        } catch (\Throwable $exception) {
            if ($hadLocalDirectory && ! file_exists($localPath) && is_dir($previousPath)) {
                @rename($previousPath, $localPath);
            }

            throw $exception;
        }

        $this->deleteDirectory($previousPath);
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new RuntimeException("Folder tidak dapat dibuat: {$path}");
        }
    }

    protected function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            throw new RuntimeException("Folder tidak dapat dibaca: {$path}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path.DIRECTORY_SEPARATOR.$item;

            if (is_dir($itemPath) && ! is_link($itemPath)) {
                $this->deleteDirectory($itemPath);
            } elseif (! @unlink($itemPath)) {
                throw new RuntimeException("File lama tidak dapat dihapus: {$itemPath}");
            }
        }

        if (! @rmdir($path)) {
            throw new RuntimeException("Folder lama tidak dapat dihapus: {$path}");
        }
    }
}
