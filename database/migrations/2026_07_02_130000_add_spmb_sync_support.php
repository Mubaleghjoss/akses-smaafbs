<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('data_siswa')) {
            Schema::table('data_siswa', function (Blueprint $table): void {
                if (! Schema::hasColumn('data_siswa', 'spmb_nomor_pendaftaran')) {
                    $table->string('spmb_nomor_pendaftaran', 50)
                        ->nullable()
                        ->unique('data_siswa_spmb_nomor_unique')
                        ->after('nisn');
                }

                if (! Schema::hasColumn('data_siswa', 'spmb_source_updated_at')) {
                    $table->timestamp('spmb_source_updated_at')->nullable()->after('spmb_nomor_pendaftaran');
                }

                if (! Schema::hasColumn('data_siswa', 'spmb_synced_at')) {
                    $table->timestamp('spmb_synced_at')->nullable()->after('spmb_source_updated_at');
                }

                if (! Schema::hasColumn('data_siswa', 'spmb_checksum')) {
                    $table->string('spmb_checksum', 64)->nullable()->after('spmb_synced_at');
                }
            });
        }

        if (! Schema::hasTable('spmb_sync_runs')) {
            Schema::create('spmb_sync_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->enum('status', ['berjalan', 'berhasil', 'gagal']);
                $table->unsignedInteger('fetched_count')->default(0);
                $table->unsignedInteger('created_count')->default(0);
                $table->unsignedInteger('updated_count')->default(0);
                $table->unsignedInteger('unchanged_count')->default(0);
                $table->unsignedInteger('conflict_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->text('message')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spmb_sync_runs');

        if (! Schema::hasTable('data_siswa')) {
            return;
        }

        Schema::table('data_siswa', function (Blueprint $table): void {
            if (Schema::hasColumn('data_siswa', 'spmb_nomor_pendaftaran')) {
                $table->dropUnique('data_siswa_spmb_nomor_unique');
            }

            $columns = collect([
                'spmb_nomor_pendaftaran',
                'spmb_source_updated_at',
                'spmb_synced_at',
                'spmb_checksum',
            ])->filter(fn (string $column): bool => Schema::hasColumn('data_siswa', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
