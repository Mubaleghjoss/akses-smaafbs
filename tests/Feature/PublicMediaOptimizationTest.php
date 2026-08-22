<?php

namespace Tests\Feature;

use App\Support\Media\PublicImageOptimizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaOptimizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        Schema::dropIfExists('perpustakaan_literasi_materials');
        Schema::create('perpustakaan_literasi_materials', function (Blueprint $table): void {
            $table->id();
            $table->string('image_path')->nullable();
            $table->longText('reading_content')->nullable();
        });
    }

    public function test_material_image_is_resized_to_webp_with_thumbnail(): void
    {
        Storage::disk('public')->put(
            'literasi/materials/large-material.png',
            $this->png(1800, 1200),
        );

        $result = app(PublicImageOptimizer::class)
            ->optimize('literasi/materials/large-material.png', 'material');

        Storage::disk('public')->assertExists($result['path']);
        Storage::disk('public')->assertExists($result['thumbnail_path']);

        $display = getimagesize(Storage::disk('public')->path($result['path']));
        $thumbnail = getimagesize(Storage::disk('public')->path($result['thumbnail_path']));

        $this->assertSame('image/webp', $display['mime'] ?? null);
        $this->assertLessThanOrEqual(1280, $display[0] ?? PHP_INT_MAX);
        $this->assertLessThanOrEqual(1280, $display[1] ?? PHP_INT_MAX);
        $this->assertSame([640, 360], [$thumbnail[0] ?? null, $thumbnail[1] ?? null]);
    }

    public function test_command_applies_batch_with_private_backup_and_can_rollback(): void
    {
        $sourcePath = 'literasi/materials/rollback-source.png';
        $sourceBytes = $this->png(1000, 700);
        Storage::disk('public')->put($sourcePath, $sourceBytes);

        $id = DB::table('perpustakaan_literasi_materials')->insertGetId([
            'image_path' => $sourcePath,
            'reading_content' => null,
        ]);

        $this->artisan('media:optimize-public', ['--apply' => true, '--batch' => 1])
            ->assertSuccessful();

        $optimizedPath = (string) DB::table('perpustakaan_literasi_materials')
            ->where('id', $id)
            ->value('image_path');

        $this->assertStringEndsWith('-optimized.webp', $optimizedPath);
        Storage::disk('public')->assertExists($optimizedPath);

        $manifestPath = collect(Storage::disk('local')->allFiles('media-backups'))
            ->first(fn (string $path): bool => str_ends_with($path, '/manifest.json'));

        $this->assertNotNull($manifestPath);
        $manifest = json_decode((string) Storage::disk('local')->get($manifestPath), true);
        $manifestId = (string) ($manifest['id'] ?? '');

        $this->assertNotSame('', $manifestId);
        Storage::disk('local')->assertExists(
            'media-backups/'.$manifestId.'/files/'.$sourcePath,
        );

        $this->artisan('media:optimize-public', ['--rollback' => $manifestId])
            ->assertSuccessful();

        $this->assertSame(
            $sourcePath,
            DB::table('perpustakaan_literasi_materials')->where('id', $id)->value('image_path'),
        );
        Storage::disk('public')->assertExists($sourcePath);
        Storage::disk('public')->assertMissing($optimizedPath);
        $this->assertSame($sourceBytes, Storage::disk('public')->get($sourcePath));
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $background = imagecolorallocatealpha($image, 22, 163, 74, 0);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        ob_start();
        imagepng($image, null, 6);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
