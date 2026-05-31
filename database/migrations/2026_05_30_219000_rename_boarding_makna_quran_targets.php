<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_makna_progresses')) {
            return;
        }

        foreach (range(1, 30) as $juz) {
            DB::table('boarding_makna_progresses')
                ->where('target_key', 'quran_juz_'.$juz)
                ->update(['target_name' => "Makna Qur'an Juz ".$juz]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('boarding_makna_progresses')) {
            return;
        }

        foreach (range(1, 30) as $juz) {
            DB::table('boarding_makna_progresses')
                ->where('target_key', 'quran_juz_'.$juz)
                ->update(['target_name' => 'Makna Al-Quran Juz '.$juz]);
        }
    }
};
