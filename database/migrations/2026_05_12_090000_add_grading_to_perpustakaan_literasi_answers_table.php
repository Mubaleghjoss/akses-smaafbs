<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_answers')) {
            return;
        }

        Schema::table('perpustakaan_literasi_answers', function (Blueprint $table): void {
            if (! Schema::hasColumn('perpustakaan_literasi_answers', 'is_correct')) {
                $table->boolean('is_correct')->nullable()->after('character_count');
            }

            if (! Schema::hasColumn('perpustakaan_literasi_answers', 'graded_by')) {
                $table->unsignedBigInteger('graded_by')->nullable()->after('is_correct');
            }

            if (! Schema::hasColumn('perpustakaan_literasi_answers', 'graded_at')) {
                $table->timestamp('graded_at')->nullable()->after('graded_by');
            }

            if (! Schema::hasColumn('perpustakaan_literasi_answers', 'grading_note')) {
                $table->text('grading_note')->nullable()->after('graded_at');
            }
        });

        $this->ensureIndexes();
    }

    public function down(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_answers')) {
            return;
        }

        Schema::table('perpustakaan_literasi_answers', function (Blueprint $table): void {
            foreach ([
                'perpus_lit_ans_correct_idx',
                'perpus_lit_ans_graded_by_idx',
                'perpus_lit_ans_graded_at_idx',
                'perpus_lit_ans_question_correct_idx',
            ] as $indexName) {
                if (Schema::hasIndex('perpustakaan_literasi_answers', $indexName)) {
                    $table->dropIndex($indexName);
                }
            }

            foreach (['grading_note', 'graded_at', 'graded_by', 'is_correct'] as $column) {
                if (Schema::hasColumn('perpustakaan_literasi_answers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    protected function ensureIndexes(): void
    {
        $indexes = [
            'perpus_lit_ans_correct_idx' => ['is_correct'],
            'perpus_lit_ans_graded_by_idx' => ['graded_by'],
            'perpus_lit_ans_graded_at_idx' => ['graded_at'],
            'perpus_lit_ans_question_correct_idx' => ['question_id', 'is_correct'],
        ];

        foreach ($indexes as $name => $columns) {
            if (Schema::hasIndex('perpustakaan_literasi_answers', $name)) {
                continue;
            }

            Schema::table('perpustakaan_literasi_answers', function (Blueprint $table) use ($columns, $name): void {
                $table->index($columns, $name);
            });
        }
    }
};
