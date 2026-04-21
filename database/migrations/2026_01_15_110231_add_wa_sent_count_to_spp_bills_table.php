<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('spp_bills', function (Blueprint $table) {
            $table->unsignedInteger('wa_sent_count')->default(0)->after('wa_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spp_bills', function (Blueprint $table) {
            $table->dropColumn('wa_sent_count');
        });
    }
};
