<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('struktur_organisasis', function (Blueprint $table): void {
            $table->foreignId('homepage_parent_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('struktur_organisasis')
                ->nullOnDelete();
            $table->unsignedInteger('homepage_row')
                ->nullable()
                ->after('urutan');
            $table->unsignedInteger('homepage_order')
                ->nullable()
                ->after('homepage_row');
        });
    }

    public function down(): void
    {
        Schema::table('struktur_organisasis', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('homepage_parent_id');
            $table->dropColumn(['homepage_row', 'homepage_order']);
        });
    }
};
