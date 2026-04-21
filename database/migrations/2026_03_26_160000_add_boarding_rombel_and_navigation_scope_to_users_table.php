<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $hasRombelScope = Schema::hasColumn('users', 'boarding_rombel_scope');
        $hasNavigationGroups = Schema::hasColumn('users', 'allowed_navigation_groups');

        if (! $hasRombelScope || ! $hasNavigationGroups) {
            Schema::table('users', function (Blueprint $table) use ($hasRombelScope, $hasNavigationGroups): void {
                if (! $hasRombelScope) {
                    $table->json('boarding_rombel_scope')->nullable()->after('boarding_angkatan_scope');
                }

                if (! $hasNavigationGroups) {
                    $table->json('allowed_navigation_groups')->nullable()->after('boarding_rombel_scope');
                }
            });
        }

        if (! Schema::hasTable('data_siswa') || ! Schema::hasColumn('users', 'boarding_angkatan_scope') || ! Schema::hasColumn('users', 'boarding_rombel_scope')) {
            return;
        }

        $users = DB::table('users')
            ->select('id', 'boarding_angkatan_scope', 'boarding_rombel_scope')
            ->whereNotNull('boarding_angkatan_scope')
            ->get();

        foreach ($users as $user) {
            if (! blank($user->boarding_rombel_scope)) {
                continue;
            }

            $angkatanScope = trim((string) $user->boarding_angkatan_scope);

            if ($angkatanScope === '') {
                continue;
            }

            $rombels = DB::table('data_siswa')
                ->whereNotNull('rombel_saat_ini')
                ->where('rombel_saat_ini', 'like', '%'.$angkatanScope.'%')
                ->distinct()
                ->orderBy('rombel_saat_ini')
                ->pluck('rombel_saat_ini')
                ->filter(fn ($rombel) => filled($rombel))
                ->values()
                ->all();

            if ($rombels === []) {
                continue;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'boarding_rombel_scope' => json_encode($rombels, JSON_UNESCAPED_UNICODE),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['allowed_navigation_groups', 'boarding_rombel_scope'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
