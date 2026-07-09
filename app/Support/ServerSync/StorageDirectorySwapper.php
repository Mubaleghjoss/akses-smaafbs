<?php

namespace App\Support\ServerSync;

use RuntimeException;

class StorageDirectorySwapper
{
    public const MODE_SWAPPED = 'swapped';

    public const MODE_MIRRORED = 'mirrored';

    public function replace(string $stagingPath, string $localPath, string $previousPath): string
    {
        if (! is_dir($stagingPath)) {
            throw new RuntimeException("Folder staging tidak ditemukan: {$stagingPath}");
        }

        $this->ensureDirectoryExists(dirname($localPath));
        $this->ensureDirectoryExists(dirname($previousPath));
        $this->deleteDirectory($previousPath);

        $hadLocalDirectory = is_dir($localPath);

        if ($hadLocalDirectory && ! $this->moveDirectory($localPath, $previousPath)) {
            $this->copyDirectoryContents($stagingPath, $localPath);
            $this->deleteDirectory($stagingPath);

            return self::MODE_MIRRORED;
        }

        try {
            if (! $this->moveDirectory($stagingPath, $localPath)) {
                throw new RuntimeException("Folder staging tidak dapat diaktifkan: {$stagingPath}");
            }
        } catch (\Throwable $exception) {
            if ($hadLocalDirectory && ! file_exists($localPath) && is_dir($previousPath)) {
                $this->moveDirectory($previousPath, $localPath);
            }

            throw $exception;
        }

        $this->deleteDirectory($previousPath);

        return self::MODE_SWAPPED;
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

    protected function copyDirectoryContents(string $sourcePath, string $targetPath): void
    {
        if (! is_dir($sourcePath)) {
            throw new RuntimeException("Folder sumber tidak ditemukan: {$sourcePath}");
        }

        $this->ensureDirectoryExists($targetPath);

        $items = scandir($sourcePath);

        if ($items === false) {
            throw new RuntimeException("Folder sumber tidak dapat dibaca: {$sourcePath}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourceItem = $sourcePath.DIRECTORY_SEPARATOR.$item;
            $targetItem = $targetPath.DIRECTORY_SEPARATOR.$item;

            if (is_dir($sourceItem) && ! is_link($sourceItem)) {
                $this->copyDirectoryContents($sourceItem, $targetItem);

                continue;
            }

            if (! @copy($sourceItem, $targetItem)) {
                throw new RuntimeException("File staging tidak dapat disalin ke storage aktif: {$targetItem}");
            }
        }
    }

    protected function moveDirectory(string $sourcePath, string $targetPath): bool
    {
        return @rename($sourcePath, $targetPath);
    }
}
