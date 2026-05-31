<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_pencapaians') || Schema::hasColumn('boarding_pencapaians', 'materi_rapot_scope')) {
            return;
        }

        Schema::table('boarding_pencapaians', function (Blueprint $table): void {
            $table->string('materi_rapot_scope', 20)
                ->default('boarding')
                ->after('status_pencapaian')
                ->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('boarding_pencapaians') || ! Schema::hasColumn('boarding_pencapaians', 'materi_rapot_scope')) {
            return;
        }

        Schema::table('boarding_pencapaians', function (Blueprint $table): void {
            $table->dropIndex('boarding_pencapaians_materi_rapot_scope_index');
            $table->dropColumn('materi_rapot_scope');
        });
    }
};
