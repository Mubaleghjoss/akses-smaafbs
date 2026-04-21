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

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'uses_default_password')) {
                $table->boolean('uses_default_password')->default(false)->after('module_access_levels')->index();
            }

            if (! Schema::hasColumn('users', 'default_password_reset_at')) {
                $table->timestamp('default_password_reset_at')->nullable()->after('uses_default_password');
            }

            if (! Schema::hasColumn('users', 'default_password_changed_at')) {
                $table->timestamp('default_password_changed_at')->nullable()->after('default_password_reset_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['uses_default_password', 'default_password_reset_at', 'default_password_changed_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
