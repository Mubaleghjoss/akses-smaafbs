<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_access_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->string('source', 50)->nullable();
            $table->json('template_keys');
            $table->json('before_levels');
            $table->json('after_levels');
            $table->json('changed_prefixes')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['target_user_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_access_change_logs');
    }
};
