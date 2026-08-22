<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('belajar_id_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('role', 20)->default('siswa')->comment('siswa|guru');
            $table->string('nama');
            $table->string('status')->nullable()->comment('kelas untuk siswa; guru/tendik untuk guru');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('belajar_id_accounts');
    }
};
