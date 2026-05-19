<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prestasis') || Schema::hasColumn('prestasis', 'kategori')) {
            return;
        }

        Schema::table('prestasis', function (Blueprint $table): void {
            $table->string('kategori', 30)->nullable()->after('nama_lomba')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prestasis') || ! Schema::hasColumn('prestasis', 'kategori')) {
            return;
        }

        Schema::table('prestasis', function (Blueprint $table): void {
            $table->dropColumn('kategori');
        });
    }
};
