<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessment_report_snapshots', 'delivery_mode')) {
            Schema::table('assessment_report_snapshots', function (Blueprint $table): void {
                $table->string('delivery_mode', 20)->default('stored')->after('generation_status')->index();
            });
        }

        if (! Schema::hasColumn('assessment_report_snapshots', 'snapshot_checksum')) {
            Schema::table('assessment_report_snapshots', function (Blueprint $table): void {
                $table->string('snapshot_checksum', 64)->nullable()->after('snapshot_data');
            });
        }

        if (! Schema::hasColumn('assessment_class_report_artifacts', 'cache_expires_at')) {
            Schema::table('assessment_class_report_artifacts', function (Blueprint $table): void {
                $table->dateTime('cache_expires_at')->nullable()->after('generated_at')->index();
            });
        }

        $canonicalize = function (mixed $value) use (&$canonicalize): mixed {
            if (! is_array($value)) {
                return $value;
            }

            if (array_is_list($value)) {
                return array_map($canonicalize, $value);
            }

            ksort($value, SORT_STRING);

            foreach ($value as $key => $item) {
                $value[$key] = $canonicalize($item);
            }

            return $value;
        };

        DB::table('assessment_report_snapshots')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($canonicalize): void {
                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->snapshot_data, true);
                    $encoded = json_encode(
                        $canonicalize(is_array($payload) ? $payload : []),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
                    );
                    $streamable = empty($row->pdf_path)
                        && in_array((string) $row->generation_status, ['not_scheduled', 'pending', 'processing', 'ready'], true);

                    DB::table('assessment_report_snapshots')
                        ->where('id', $row->id)
                        ->update([
                            'snapshot_checksum' => hash('sha256', (string) $encoded),
                            'delivery_mode' => $streamable ? 'stream' : 'stored',
                            'generation_status' => $streamable ? 'ready' : $row->generation_status,
                        ]);
                }
            });

        if (Schema::hasTable('assessment_report_generation_runs')) {
            DB::table('assessment_report_generation_runs')
                ->orderBy('id')
                ->chunkById(100, function ($runs): void {
                    foreach ($runs as $run) {
                        $readySnapshots = DB::table('assessment_report_snapshots')
                            ->where('assessment_report_generation_run_id', $run->id)
                            ->whereIn('generation_status', ['ready', 'completed'])
                            ->count();

                        DB::table('assessment_report_generation_runs')
                            ->where('id', $run->id)
                            ->update(['completed_students' => $readySnapshots]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('assessment_class_report_artifacts', function (Blueprint $table): void {
            $table->dropIndex(['cache_expires_at']);
            $table->dropColumn('cache_expires_at');
        });

        Schema::table('assessment_report_snapshots', function (Blueprint $table): void {
            $table->dropIndex(['delivery_mode']);
            $table->dropColumn(['delivery_mode', 'snapshot_checksum']);
        });
    }
};
