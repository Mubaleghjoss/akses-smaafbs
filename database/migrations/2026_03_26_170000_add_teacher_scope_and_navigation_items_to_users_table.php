<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'guru_tendik_id')) {
                $table->unsignedBigInteger('guru_tendik_id')->nullable()->after('boarding_rombel_scope')->index();
            }

            if (! Schema::hasColumn('users', 'guru_mapel_label')) {
                $table->string('guru_mapel_label', 150)->nullable()->after('guru_tendik_id');
            }

            if (! Schema::hasColumn('users', 'guru_walas_scope')) {
                $table->json('guru_walas_scope')->nullable()->after('guru_mapel_label');
            }

            if (! Schema::hasColumn('users', 'allowed_navigation_items')) {
                $table->json('allowed_navigation_items')->nullable()->after('allowed_navigation_groups');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['guru_tendik_id', 'guru_mapel_label', 'guru_walas_scope', 'allowed_navigation_items'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
