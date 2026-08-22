<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_questions')) {
            Schema::table('perpustakaan_literasi_questions', function (Blueprint $table): void {
                if (! Schema::hasColumn('perpustakaan_literasi_questions', 'question_type')) {
                    $table->string('question_type', 30)->default('essay')->index()->after('sort_order');
                }
                if (! Schema::hasColumn('perpustakaan_literasi_questions', 'configuration')) {
                    $table->json('configuration')->nullable()->after('google_drive_url');
                }
                if (! Schema::hasColumn('perpustakaan_literasi_questions', 'speech_input_enabled')) {
                    $table->boolean('speech_input_enabled')->default(false)->after('configuration');
                }
            });
        }

        if (Schema::hasTable('perpustakaan_literasi_answers')) {
            Schema::table('perpustakaan_literasi_answers', function (Blueprint $table): void {
                if (! Schema::hasColumn('perpustakaan_literasi_answers', 'answer_payload')) {
                    $table->json('answer_payload')->nullable()->after('answer_text');
                }
                if (! Schema::hasColumn('perpustakaan_literasi_answers', 'score_earned')) {
                    $table->unsignedInteger('score_earned')->nullable()->after('character_count');
                }
                if (! Schema::hasColumn('perpustakaan_literasi_answers', 'score_possible')) {
                    $table->unsignedInteger('score_possible')->default(1)->after('score_earned');
                }
                if (! Schema::hasColumn('perpustakaan_literasi_answers', 'grading_source')) {
                    $table->string('grading_source', 30)->nullable()->index()->after('score_possible');
                }
            });

            DB::table('perpustakaan_literasi_answers')
                ->whereNull('score_possible')
                ->update(['score_possible' => 1]);

            if (Schema::hasColumn('perpustakaan_literasi_answers', 'is_correct')) {
                DB::table('perpustakaan_literasi_answers')
                    ->where('is_correct', true)
                    ->update([
                        'score_earned' => 1,
                        'score_possible' => 1,
                        'grading_source' => 'legacy',
                    ]);

                DB::table('perpustakaan_literasi_answers')
                    ->where('is_correct', false)
                    ->update([
                        'score_earned' => 0,
                        'score_possible' => 1,
                        'grading_source' => 'legacy',
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_answers')) {
            Schema::table('perpustakaan_literasi_answers', function (Blueprint $table): void {
                foreach (['grading_source', 'score_possible', 'score_earned', 'answer_payload'] as $column) {
                    if (Schema::hasColumn('perpustakaan_literasi_answers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('perpustakaan_literasi_questions')) {
            Schema::table('perpustakaan_literasi_questions', function (Blueprint $table): void {
                foreach (['speech_input_enabled', 'configuration', 'question_type'] as $column) {
                    if (Schema::hasColumn('perpustakaan_literasi_questions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
