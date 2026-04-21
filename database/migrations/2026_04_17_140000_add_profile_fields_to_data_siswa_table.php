<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_siswa')) {
            return;
        }

        Schema::table('data_siswa', function (Blueprint $table): void {
            if (! Schema::hasColumn('data_siswa', 'kepribadian')) {
                $table->string('kepribadian', 100)->nullable()->after('nama');
            }

            if (! Schema::hasColumn('data_siswa', 'gaya_belajar')) {
                $table->string('gaya_belajar', 100)->nullable()->after('kepribadian');
            }

            if (! Schema::hasColumn('data_siswa', 'profiling')) {
                $table->string('profiling', 150)->nullable()->after('gaya_belajar');
            }

            if (! Schema::hasColumn('data_siswa', 'mbti')) {
                $table->string('mbti', 20)->nullable()->after('profiling');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('data_siswa')) {
            return;
        }

        Schema::table('data_siswa', function (Blueprint $table): void {
            $columnsToDrop = array_values(array_filter([
                Schema::hasColumn('data_siswa', 'kepribadian') ? 'kepribadian' : null,
                Schema::hasColumn('data_siswa', 'gaya_belajar') ? 'gaya_belajar' : null,
                Schema::hasColumn('data_siswa', 'profiling') ? 'profiling' : null,
                Schema::hasColumn('data_siswa', 'mbti') ? 'mbti' : null,
            ]));

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
