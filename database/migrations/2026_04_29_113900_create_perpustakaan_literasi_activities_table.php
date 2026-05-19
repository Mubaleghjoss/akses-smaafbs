<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_activities')) {
            return;
        }

        Schema::create('perpustakaan_literasi_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('activity_code', 40)->unique();
            $table->string('purpose', 30)->index();
            $table->unsignedBigInteger('participant_id')->nullable()->index();
            $table->string('participant_name', 150)->index();
            $table->string('participant_class', 100)->nullable()->index();
            $table->string('participant_role', 50)->nullable();
            $table->unsignedBigInteger('book_id')->nullable()->index();
            $table->string('book_title_snapshot', 500)->index();
            $table->string('book_author_snapshot', 255)->nullable();
            $table->string('subject_name', 150)->nullable()->index();
            $table->dateTime('activity_at')->nullable()->index();
            $table->string('result_status', 30)->default('pending')->index();
            $table->longText('result_text')->nullable();
            $table->dateTime('result_submitted_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perpustakaan_literasi_activities');
    }
};
