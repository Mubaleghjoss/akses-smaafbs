<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $hasAngkatanScope = Schema::hasColumn('users', 'boarding_angkatan_scope');

        if ($hasAngkatanScope) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('boarding_angkatan_scope', 30)->nullable()->after('password');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'boarding_angkatan_scope')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('boarding_angkatan_scope');
        });
    }
};
