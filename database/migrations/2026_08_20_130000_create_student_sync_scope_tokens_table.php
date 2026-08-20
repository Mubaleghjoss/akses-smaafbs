<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_sync_scope_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token_hash', 64)->unique();
            $table->longText('encrypted_student_ids');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_sync_scope_tokens');
    }
};
