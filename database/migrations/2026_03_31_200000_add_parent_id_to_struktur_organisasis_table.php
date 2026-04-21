<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('struktur_organisasis')) {
            return;
        }

        Schema::table('struktur_organisasis', function (Blueprint $table): void {
            if (! Schema::hasColumn('struktur_organisasis', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('foto');
                $table->index(['parent_id', 'urutan', 'id'], 'struktur_parent_urutan_index');
                $table->foreign('parent_id', 'struktur_parent_fk')
                    ->references('id')
                    ->on('struktur_organisasis')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('struktur_organisasis') || ! Schema::hasColumn('struktur_organisasis', 'parent_id')) {
            return;
        }

        Schema::table('struktur_organisasis', function (Blueprint $table): void {
            $table->dropForeign('struktur_parent_fk');
            $table->dropIndex('struktur_parent_urutan_index');
            $table->dropColumn('parent_id');
        });
    }
};
