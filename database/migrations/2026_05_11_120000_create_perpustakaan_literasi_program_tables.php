<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_materials')) {
            Schema::create('perpustakaan_literasi_materials', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 180);
                $table->string('slug', 220)->unique();
                $table->longText('reading_content')->nullable();
                $table->string('image_path')->nullable();
                $table->string('google_drive_url', 1000)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->dateTime('opens_at')->nullable()->index();
                $table->dateTime('closes_at')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('perpustakaan_literasi_questions')) {
            Schema::create('perpustakaan_literasi_questions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('material_id')->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('prompt');
                $table->string('image_path')->nullable();
                $table->string('google_drive_url', 1000)->nullable();
                $table->unsignedInteger('min_characters')->default(20);
                $table->unsignedInteger('max_characters')->default(1000);
                $table->boolean('is_required')->default(true);
                $table->timestamps();

                $table->index(['material_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('perpustakaan_literasi_responses')) {
            Schema::create('perpustakaan_literasi_responses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('material_id')->index();
                $table->unsignedBigInteger('data_siswa_id')->index();
                $table->string('student_name_snapshot', 180)->index();
                $table->string('student_class_snapshot', 100)->nullable()->index();
                $table->string('edit_code', 50)->unique();
                $table->timestamp('submitted_at')->nullable()->index();
                $table->timestamp('last_edited_at')->nullable();
                $table->string('ai_detection_status', 30)->default('not_checked')->index();
                $table->decimal('ai_score', 5, 2)->nullable();
                $table->json('ai_metadata')->nullable();
                $table->string('submitted_ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->unique(['material_id', 'data_siswa_id'], 'perpus_lit_material_student_unique');
                $table->index(['material_id', 'student_class_snapshot'], 'perpus_lit_resp_material_class');
            });
        }

        if (Schema::hasTable('perpustakaan_literasi_responses')
            && ! Schema::hasIndex('perpustakaan_literasi_responses', 'perpus_lit_resp_material_class')) {
            Schema::table('perpustakaan_literasi_responses', function (Blueprint $table): void {
                $table->index(['material_id', 'student_class_snapshot'], 'perpus_lit_resp_material_class');
            });
        }

        if (! Schema::hasTable('perpustakaan_literasi_answers')) {
            Schema::create('perpustakaan_literasi_answers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('response_id')->index();
                $table->unsignedBigInteger('question_id')->index();
                $table->longText('answer_text')->nullable();
                $table->unsignedInteger('character_count')->default(0);
                $table->timestamps();

                $table->unique(['response_id', 'question_id'], 'perpus_lit_response_question_unique');
            });
        }

        if (! Schema::hasTable('perpustakaan_literasi_similarity_matches')) {
            Schema::create('perpustakaan_literasi_similarity_matches', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('material_id');
                $table->unsignedBigInteger('question_id');
                $table->unsignedBigInteger('later_response_id');
                $table->unsignedBigInteger('matched_response_id');
                $table->unsignedBigInteger('later_answer_id');
                $table->unsignedBigInteger('matched_answer_id');
                $table->string('student_class_snapshot', 100)->nullable();
                $table->decimal('similarity_score', 5, 2);
                $table->timestamp('later_submitted_at')->nullable();
                $table->timestamp('matched_submitted_at')->nullable();
                $table->timestamps();

                $table->index('material_id', 'perpus_lit_sim_material_idx');
                $table->index('question_id', 'perpus_lit_sim_question_idx');
                $table->index('later_response_id', 'perpus_lit_sim_later_resp_idx');
                $table->index('matched_response_id', 'perpus_lit_sim_matched_resp_idx');
                $table->index('later_answer_id', 'perpus_lit_sim_later_answer_idx');
                $table->index('matched_answer_id', 'perpus_lit_sim_matched_answer_idx');
                $table->index('student_class_snapshot', 'perpus_lit_sim_class_idx');
                $table->index('similarity_score', 'perpus_lit_sim_score_idx');
                $table->unique(['later_answer_id', 'matched_answer_id'], 'perpus_lit_sim_pair_unique');
                $table->index(['material_id', 'student_class_snapshot'], 'perpus_lit_similarity_material_class');
            });
        }

        if (Schema::hasTable('perpustakaan_literasi_similarity_matches')) {
            $this->ensureSimilarityIndexes();
        }

        if (Schema::hasTable('perpustakaan_literasi_questions')
            && ! Schema::hasColumn('perpustakaan_literasi_questions', 'min_characters')) {
            Schema::table('perpustakaan_literasi_questions', function (Blueprint $table): void {
                $table->unsignedInteger('min_characters')->default(20)->after('google_drive_url');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('perpustakaan_literasi_similarity_matches');
        Schema::dropIfExists('perpustakaan_literasi_answers');
        Schema::dropIfExists('perpustakaan_literasi_responses');
        Schema::dropIfExists('perpustakaan_literasi_questions');
        Schema::dropIfExists('perpustakaan_literasi_materials');
    }

    protected function ensureSimilarityIndexes(): void
    {
        $indexes = [
            'perpus_lit_sim_material_idx' => ['index', ['material_id']],
            'perpus_lit_sim_question_idx' => ['index', ['question_id']],
            'perpus_lit_sim_later_resp_idx' => ['index', ['later_response_id']],
            'perpus_lit_sim_matched_resp_idx' => ['index', ['matched_response_id']],
            'perpus_lit_sim_later_answer_idx' => ['index', ['later_answer_id']],
            'perpus_lit_sim_matched_answer_idx' => ['index', ['matched_answer_id']],
            'perpus_lit_sim_class_idx' => ['index', ['student_class_snapshot']],
            'perpus_lit_sim_score_idx' => ['index', ['similarity_score']],
            'perpus_lit_sim_pair_unique' => ['unique', ['later_answer_id', 'matched_answer_id']],
            'perpus_lit_similarity_material_class' => ['index', ['material_id', 'student_class_snapshot']],
        ];

        foreach ($indexes as $name => [$type, $columns]) {
            if (Schema::hasIndex('perpustakaan_literasi_similarity_matches', $name)) {
                continue;
            }

            Schema::table('perpustakaan_literasi_similarity_matches', function (Blueprint $table) use ($name, $type, $columns): void {
                if ($type === 'unique') {
                    $table->unique($columns, $name);

                    return;
                }

                $table->index($columns, $name);
            });
        }
    }
};
