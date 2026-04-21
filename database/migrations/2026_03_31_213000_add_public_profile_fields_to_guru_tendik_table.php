<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guru_tendik')) {
            return;
        }

        Schema::table('guru_tendik', function (Blueprint $table): void {
            if (! Schema::hasColumn('guru_tendik', 'foto_profil')) {
                $table->string('foto_profil')->nullable()->after('status');
            }

            if (! Schema::hasColumn('guru_tendik', 'bio_singkat')) {
                $table->text('bio_singkat')->nullable()->after('foto_profil');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guru_tendik')) {
            return;
        }

        Schema::table('guru_tendik', function (Blueprint $table): void {
            if (Schema::hasColumn('guru_tendik', 'bio_singkat')) {
                $table->dropColumn('bio_singkat');
            }

            if (Schema::hasColumn('guru_tendik', 'foto_profil')) {
                $table->dropColumn('foto_profil');
            }
        });
    }
};
