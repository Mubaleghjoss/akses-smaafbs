<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('profil_sekolahs')) {
            return;
        }

        Schema::create('profil_sekolahs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->string('title', 160)->default('Identitas Sekolah');
            $table->text('alamat')->nullable();
            $table->string('kontak_telepon', 60)->nullable();
            $table->string('kontak_email', 120)->nullable();
            $table->string('maps_url', 2048)->nullable();
            $table->string('youtube_url', 2048)->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->string('facebook_url', 2048)->nullable();
            $table->string('tiktok_url', 2048)->nullable();
            $table->json('fasilitas')->nullable();
            $table->json('jadwal_kbm')->nullable();
            $table->json('menu_makan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profil_sekolahs');
    }
};
