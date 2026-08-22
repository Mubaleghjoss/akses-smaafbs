<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webauthn_credentials', function (Blueprint $table): void {
            $table->longText('credential_public_key')->nullable()->after('public_key');
            $table->unsignedBigInteger('signature_counter')->nullable()->after('sign_count');
            $table->string('attestation_format', 50)->nullable()->after('signature_counter');
            $table->string('user_handle', 255)->nullable()->after('attestation_format');
            $table->boolean('user_verified')->nullable()->after('user_handle');
            $table->boolean('backup_eligible')->nullable()->after('user_verified');
            $table->boolean('backed_up')->nullable()->after('backup_eligible');
            $table->string('device_name', 100)->nullable()->after('label');
            $table->timestamp('verified_at')->nullable()->after('device_name');
        });
    }

    public function down(): void
    {
        Schema::table('webauthn_credentials', function (Blueprint $table): void {
            $table->dropColumn([
                'credential_public_key',
                'signature_counter',
                'attestation_format',
                'user_handle',
                'user_verified',
                'backup_eligible',
                'backed_up',
                'device_name',
                'verified_at',
            ]);
        });
    }
};
