<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_similarity_matches')) {
            return;
        }

        Schema::table('perpustakaan_literasi_similarity_matches', function (Blueprint $table): void {
            if (! Schema::hasColumn('perpustakaan_literasi_similarity_matches', 'review_status')) {
                $table->string('review_status', 20)->default('suspected');
                $table->index('review_status', 'perpus_lit_sim_review_status_idx');
            }

            if (! Schema::hasColumn('perpustakaan_literasi_similarity_matches', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->index('perpus_lit_sim_reviewed_by_idx');
            }

            if (! Schema::hasColumn('perpustakaan_literasi_similarity_matches', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->index('perpus_lit_sim_reviewed_at_idx');
            }

            if (! Schema::hasColumn('perpustakaan_literasi_similarity_matches', 'review_note')) {
                $table->text('review_note')->nullable();
            }
        });

        DB::table('perpustakaan_literasi_similarity_matches')
            ->whereNull('review_status')
            ->update(['review_status' => 'suspected']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_similarity_matches')) {
            return;
        }

        Schema::table('perpustakaan_literasi_similarity_matches', function (Blueprint $table): void {
            if (Schema::hasIndex('perpustakaan_literasi_similarity_matches', 'perpus_lit_sim_review_status_idx')) {
                $table->dropIndex('perpus_lit_sim_review_status_idx');
            }

            if (Schema::hasIndex('perpustakaan_literasi_similarity_matches', 'perpus_lit_sim_reviewed_by_idx')) {
                $table->dropIndex('perpus_lit_sim_reviewed_by_idx');
            }

            if (Schema::hasIndex('perpustakaan_literasi_similarity_matches', 'perpus_lit_sim_reviewed_at_idx')) {
                $table->dropIndex('perpus_lit_sim_reviewed_at_idx');
            }

            $columns = collect(['review_status', 'reviewed_by', 'reviewed_at', 'review_note'])
                ->filter(fn (string $column): bool => Schema::hasColumn('perpustakaan_literasi_similarity_matches', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
