<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_arsip_mt_histories')) {
            Schema::create('boarding_arsip_mt_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('boarding_arsip_mt_id')->index();
                $table->string('status_lama', 40)->nullable();
                $table->string('status_baru', 40);
                $table->string('judul_ringkas', 255)->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 100)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_arsip_mt_histories');
    }
};
