<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_questions')) {
            return;
        }

        Schema::table('perpustakaan_literasi_questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('perpustakaan_literasi_questions', 'plagiarism_detection_enabled')) {
                $table->boolean('plagiarism_detection_enabled')
                    ->default(true)
                    ->after('is_required');
                $table->index('plagiarism_detection_enabled', 'perpus_lit_q_plagiarism_enabled_idx');
            }

            if (! Schema::hasColumn('perpustakaan_literasi_questions', 'answer_key')) {
                $table->text('answer_key')
                    ->nullable()
                    ->after('plagiarism_detection_enabled');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_questions')) {
            return;
        }

        Schema::table('perpustakaan_literasi_questions', function (Blueprint $table): void {
            if (Schema::hasIndex('perpustakaan_literasi_questions', 'perpus_lit_q_plagiarism_enabled_idx')) {
                $table->dropIndex('perpus_lit_q_plagiarism_enabled_idx');
            }

            $columns = collect(['answer_key', 'plagiarism_detection_enabled'])
                ->filter(fn (string $column): bool => Schema::hasColumn('perpustakaan_literasi_questions', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
