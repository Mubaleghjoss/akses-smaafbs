<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveis', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->string('audience_type', 20);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('opens_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('survei_questions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('survei_id');
            $table->unsignedInteger('urutan')->default(0);
            $table->string('question_type', 30);
            $table->text('prompt');
            $table->boolean('is_required')->default(true);
            $table->json('options')->nullable();
            $table->timestamps();

            $table->index(['survei_id', 'urutan']);
        });

        Schema::create('survei_targets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('survei_id');
            $table->string('audience_type', 20);
            $table->unsignedBigInteger('data_siswa_id')->nullable();
            $table->unsignedBigInteger('guru_tendik_id')->nullable();
            $table->string('recipient_name_snapshot', 180);
            $table->string('recipient_context_snapshot', 180)->nullable();
            $table->string('whatsapp_number', 32)->nullable();
            $table->string('access_token', 80)->unique();
            $table->string('submission_status', 20)->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['survei_id', 'submission_status']);
            $table->index(['survei_id', 'data_siswa_id']);
            $table->index(['survei_id', 'guru_tendik_id']);
        });

        Schema::create('survei_submissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('survei_id');
            $table->unsignedBigInteger('survei_target_id')->unique();
            $table->json('answers');
            $table->string('submitted_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['survei_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survei_submissions');
        Schema::dropIfExists('survei_targets');
        Schema::dropIfExists('survei_questions');
        Schema::dropIfExists('surveis');
    }
};
