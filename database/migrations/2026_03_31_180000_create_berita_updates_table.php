<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('berita_updates')) {
            return;
        }

        $beritaIdColumnType = Schema::hasTable('berita')
            ? Schema::getColumnType('berita', 'id')
            : 'integer';

        Schema::create('berita_updates', function (Blueprint $table): void {
            $table->id();

            if ($beritaIdColumnType === 'bigint') {
                $table->unsignedBigInteger('berita_id')->index();
            } else {
                $table->integer('berita_id')->index();
            }

            $table->string('phase', 20);
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->dateTime('tanggal_update')->nullable();
            $table->text('update_text')->nullable();
            $table->json('documentation_media')->nullable();
            $table->string('live_url', 2048)->nullable();
            $table->timestamps();

            $table->foreign('berita_id')->references('id')->on('berita')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berita_updates');
    }
};
