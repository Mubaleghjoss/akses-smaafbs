<?php

namespace Tests\Feature\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait BootstrapsStudentAndTeacherTables
{
    protected function bootstrapStudentAndTeacherTables(): void
    {
        $this->createRombelsTable();
        $this->createDataSiswaTable();
        $this->createGuruTables();
    }

    protected function createRombelsTable(): void
    {
        if (Schema::hasTable('rombels')) {
            return;
        }

        Schema::create('rombels', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 50)->unique();
            $table->string('angkatan', 20)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    protected function createDataSiswaTable(): void
    {
        if (! Schema::hasTable('data_siswa')) {
            Schema::create('data_siswa', function (Blueprint $table): void {
                $table->id();
                $table->string('nama');
                $table->string('kepribadian')->nullable();
                $table->string('gaya_belajar')->nullable();
                $table->string('profiling')->nullable();
                $table->string('mbti')->nullable();
                $table->string('rombel_saat_ini')->nullable();
                $table->string('jk', 2)->nullable();
                $table->string('status')->nullable();
                $table->string('kategori_non_aktif')->nullable();
                $table->text('alasan_non_aktif')->nullable();
                $table->timestamps();
            });

            return;
        }

        $missingProfileColumns = collect(['kepribadian', 'gaya_belajar', 'profiling', 'mbti'])
            ->reject(fn (string $column): bool => Schema::hasColumn('data_siswa', $column))
            ->values();

        if ($missingProfileColumns->isNotEmpty()) {
            Schema::table('data_siswa', function (Blueprint $table) use ($missingProfileColumns): void {
                foreach ($missingProfileColumns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
    }

    protected function createGuruTables(): void
    {
        if (! Schema::hasTable('guru_tendik')) {
            Schema::create('guru_tendik', function (Blueprint $table): void {
                $table->id();
                $table->string('nama');
                $table->string('nip')->nullable();
                $table->string('nuptk')->nullable();
                $table->string('nik')->nullable();
                $table->string('jenis_ptk')->nullable();
                $table->string('jk')->nullable();
                $table->string('tempat_lahir')->nullable();
                $table->date('tanggal_lahir')->nullable();
                $table->string('status')->nullable();
                $table->string('foto_profil')->nullable();
                $table->text('bio_singkat')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('guru_tendik', function (Blueprint $table): void {
                if (! Schema::hasColumn('guru_tendik', 'foto_profil')) {
                    $table->string('foto_profil')->nullable();
                }

                if (! Schema::hasColumn('guru_tendik', 'bio_singkat')) {
                    $table->text('bio_singkat')->nullable();
                }
            });
        }

        if (! Schema::hasTable('guru_tendik_tugas_tambahans')) {
            Schema::create('guru_tendik_tugas_tambahans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('guru_tendik_id');
                $table->string('tugas_tambahan');
                $table->string('no_sk');
                $table->date('tmt');
                $table->date('tst')->nullable();
                $table->string('sk_file_path')->nullable();
                $table->unsignedBigInteger('berkas_guru_id')->nullable();
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('guru_tendik_tugas_tambahans', function (Blueprint $table): void {
                if (! Schema::hasColumn('guru_tendik_tugas_tambahans', 'sk_file_path')) {
                    $table->string('sk_file_path')->nullable();
                }

                if (! Schema::hasColumn('guru_tendik_tugas_tambahans', 'berkas_guru_id')) {
                    $table->unsignedBigInteger('berkas_guru_id')->nullable();
                }
            });
        }

        if (! Schema::hasTable('jenis_berkas')) {
            Schema::create('jenis_berkas', function (Blueprint $table): void {
                $table->id();
                $table->string('nama_berkas');
                $table->string('google_drive_folder_name')->nullable();
                $table->text('deskripsi')->nullable();
                $table->string('wajib')->nullable();
                $table->integer('urutan')->default(0);
                $table->string('status')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('jenis_berkas', function (Blueprint $table): void {
                if (! Schema::hasColumn('jenis_berkas', 'google_drive_folder_name')) {
                    $table->string('google_drive_folder_name')->nullable();
                }
            });
        }

        if (! Schema::hasTable('berkas_guru')) {
            Schema::create('berkas_guru', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('guru_id');
                $table->unsignedBigInteger('jenis_berkas_id');
                $table->string('file_path')->nullable();
                $table->string('keterangan')->nullable();
                $table->boolean('has_deleted')->default(false);
                $table->timestamp('uploaded_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
            });
        }
    }
}
