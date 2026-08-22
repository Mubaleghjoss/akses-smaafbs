<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password')->default('');
            $table->string('profile')->default('default');
            $table->integer('durasi')->default(0);
            $table->text('note')->nullable();
            $table->boolean('disabled')->default(false);
            $table->string('source')->default('router')->comment('router|local|both');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_users');
    }
};