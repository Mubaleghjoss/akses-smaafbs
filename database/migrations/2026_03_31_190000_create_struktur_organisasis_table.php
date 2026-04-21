<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('struktur_organisasis')) {
            return;
        }

        Schema::create('struktur_organisasis', function (Blueprint $table): void {
            $table->id();
            $table->string('jabatan', 150);
            $table->string('nama', 150);
            $table->string('foto')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['urutan', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('struktur_organisasis');
    }
};
