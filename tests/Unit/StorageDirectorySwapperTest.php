<?php

namespace Tests\Unit;

use App\Support\ServerSync\StorageDirectorySwapper;
use PHPUnit\Framework\TestCase;

class StorageDirectorySwapperTest extends TestCase
{
    public function test_it_replaces_the_live_directory_after_staging_is_complete(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'server-sync-swap-'.bin2hex(random_bytes(5));
        $live = $root.DIRECTORY_SEPARATOR.'live';
        $staging = $root.DIRECTORY_SEPARATOR.'staging';
        $previous = $root.DIRECTORY_SEPARATOR.'previous';

        mkdir($live, 0777, true);
        mkdir($staging, 0777, true);
        file_put_contents($live.DIRECTORY_SEPARATOR.'logo.png', 'old-logo');
        file_put_contents($staging.DIRECTORY_SEPARATOR.'logo.png', 'new-logo');
        file_put_contents($staging.DIRECTORY_SEPARATOR.'favicon.png', 'new-favicon');

        try {
            (new StorageDirectorySwapper())->replace($staging, $live, $previous);

            $this->assertSame('new-logo', file_get_contents($live.DIRECTORY_SEPARATOR.'logo.png'));
            $this->assertSame('new-favicon', file_get_contents($live.DIRECTORY_SEPARATOR.'favicon.png'));
            $this->assertDirectoryDoesNotExist($staging);
            $this->assertDirectoryDoesNotExist($previous);
        } finally {
            $this->deleteDirectory($root);
        }
    }

    public function test_it_does_not_touch_the_live_directory_when_staging_is_missing(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'server-sync-swap-'.bin2hex(random_bytes(5));
        $live = $root.DIRECTORY_SEPARATOR.'live';

        mkdir($live, 0777, true);
        file_put_contents($live.DIRECTORY_SEPARATOR.'logo.png', 'still-live');

        try {
            $this->expectExceptionMessage('Folder staging tidak ditemukan');

            (new StorageDirectorySwapper())->replace(
                $root.DIRECTORY_SEPARATOR.'missing',
                $live,
                $root.DIRECTORY_SEPARATOR.'previous',
            );
        } finally {
            $this->assertSame('still-live', file_get_contents($live.DIRECTORY_SEPARATOR.'logo.png'));
            $this->deleteDirectory($root);
        }
    }

    protected function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path.DIRECTORY_SEPARATOR.$item;

            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
            } else {
                @unlink($itemPath);
            }
        }

        @rmdir($path);
    }
}
