<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_rapots') || Schema::hasColumn('boarding_rapots', 'administrasi_rapot_items')) {
            return;
        }

        Schema::table('boarding_rapots', function (Blueprint $table): void {
            $table->json('administrasi_rapot_items')->nullable()->after('rekap_payload');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('boarding_rapots') || ! Schema::hasColumn('boarding_rapots', 'administrasi_rapot_items')) {
            return;
        }

        Schema::table('boarding_rapots', function (Blueprint $table): void {
            $table->dropColumn('administrasi_rapot_items');
        });
    }
};
