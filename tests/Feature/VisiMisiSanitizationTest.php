<?php

namespace Tests\Feature;

use App\Models\VisiMisi;
use App\Support\Content\VisiMisiSanitizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisiMisiSanitizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureVisiMisiTable();
    }

    public function test_title_and_content_are_sanitized_when_stored(): void
    {
        $record = VisiMisi::query()->create([
            'title' => "\n  <h1>  Visi  Besar  </h1>\n",
            'content' => '<p>Konten aman.</p><script>alert("x")</script><a href="javascript:alert(1)">klik</a>',
        ]);

        $record->refresh();

        $this->assertSame('Visi Besar', $record->title);
        $this->assertStringContainsString('<p>Konten aman.</p>', $record->content);
        $this->assertStringNotContainsString('<script', $record->content);
        $this->assertStringNotContainsString('javascript:', $record->content);
    }

    public function test_rendered_content_uses_the_same_sanitization_policy(): void
    {
        $record = new VisiMisi;
        $record->content = '<p>Ringkasan misi.</p><iframe src="https://evil.example.com"></iframe>';

        $this->assertSame(
            VisiMisiSanitizer::sanitizeContent($record->content),
            $record->rendered_content
        );
        $this->assertStringNotContainsString('<iframe', $record->rendered_content);
    }

    protected function ensureVisiMisiTable(): void
    {
        if (Schema::hasTable('visi_misis')) {
            return;
        }

        Schema::create('visi_misis', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->string('title', 160);
            $table->longText('content');
            $table->timestamps();
        });
    }
}
