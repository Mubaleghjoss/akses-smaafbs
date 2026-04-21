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
        Schema::table('spp_payment_attachments', function (Blueprint $table) {
            $table->unsignedInteger('amount')->nullable()->after('bill_id');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('amount');
            $table->dateTime('verified_at')->nullable()->after('status');
            $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
            $table->string('verification_notes', 255)->nullable()->after('verified_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spp_payment_attachments', function (Blueprint $table) {
            $table->dropColumn([
                'amount',
                'status',
                'verified_at',
                'verified_by',
                'verification_notes',
            ]);
        });
    }
};
