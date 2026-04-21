<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_challenges', function (Blueprint $table): void {
            $table->id();
            $table->string('challenge_id', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('ceremony', 32);
            $table->string('challenge_hash', 64)->nullable();
            $table->timestamp('challenge_expires_at');
            $table->boolean('browser_supported')->default(true);
            $table->json('context')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->string('failure_reason', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ceremony']);
            $table->index(['challenge_expires_at', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_challenges');
    }
};
