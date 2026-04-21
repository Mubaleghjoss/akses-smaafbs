<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('profil_sekolahs')) {
            return;
        }

        Schema::table('profil_sekolahs', function (Blueprint $table): void {
            if (! Schema::hasColumn('profil_sekolahs', 'nama_sekolah')) {
                $table->string('nama_sekolah', 180)->nullable()->after('title');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'provinsi')) {
                $table->string('provinsi', 120)->nullable()->after('nama_sekolah');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'desa_kelurahan')) {
                $table->string('desa_kelurahan', 120)->nullable()->after('provinsi');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'kecamatan')) {
                $table->string('kecamatan', 120)->nullable()->after('desa_kelurahan');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'kode_pos')) {
                $table->string('kode_pos', 20)->nullable()->after('alamat');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'website_url')) {
                $table->string('website_url', 2048)->nullable()->after('kontak_email');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'status_sekolah')) {
                $table->string('status_sekolah', 120)->nullable()->after('website_url');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'kelompok_sekolah')) {
                $table->string('kelompok_sekolah', 120)->nullable()->after('status_sekolah');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'terakreditasi')) {
                $table->string('terakreditasi', 120)->nullable()->after('kelompok_sekolah');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'tanggal_identitas')) {
                $table->date('tanggal_identitas')->nullable()->after('terakreditasi');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'tahun_berdiri')) {
                $table->string('tahun_berdiri', 20)->nullable()->after('tanggal_identitas');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'kbm')) {
                $table->string('kbm', 120)->nullable()->after('tahun_berdiri');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'bangunan_sekolah')) {
                $table->string('bangunan_sekolah', 160)->nullable()->after('kbm');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'luas_bangunan')) {
                $table->string('luas_bangunan', 120)->nullable()->after('bangunan_sekolah');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'organisasi_penyelenggara')) {
                $table->string('organisasi_penyelenggara', 180)->nullable()->after('luas_bangunan');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'identitas_tambahan')) {
                $table->json('identitas_tambahan')->nullable()->after('organisasi_penyelenggara');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('profil_sekolahs')) {
            return;
        }

        Schema::table('profil_sekolahs', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('profil_sekolahs', 'nama_sekolah') ? 'nama_sekolah' : null,
                Schema::hasColumn('profil_sekolahs', 'provinsi') ? 'provinsi' : null,
                Schema::hasColumn('profil_sekolahs', 'desa_kelurahan') ? 'desa_kelurahan' : null,
                Schema::hasColumn('profil_sekolahs', 'kecamatan') ? 'kecamatan' : null,
                Schema::hasColumn('profil_sekolahs', 'kode_pos') ? 'kode_pos' : null,
                Schema::hasColumn('profil_sekolahs', 'website_url') ? 'website_url' : null,
                Schema::hasColumn('profil_sekolahs', 'status_sekolah') ? 'status_sekolah' : null,
                Schema::hasColumn('profil_sekolahs', 'kelompok_sekolah') ? 'kelompok_sekolah' : null,
                Schema::hasColumn('profil_sekolahs', 'terakreditasi') ? 'terakreditasi' : null,
                Schema::hasColumn('profil_sekolahs', 'tanggal_identitas') ? 'tanggal_identitas' : null,
                Schema::hasColumn('profil_sekolahs', 'tahun_berdiri') ? 'tahun_berdiri' : null,
                Schema::hasColumn('profil_sekolahs', 'kbm') ? 'kbm' : null,
                Schema::hasColumn('profil_sekolahs', 'bangunan_sekolah') ? 'bangunan_sekolah' : null,
                Schema::hasColumn('profil_sekolahs', 'luas_bangunan') ? 'luas_bangunan' : null,
                Schema::hasColumn('profil_sekolahs', 'organisasi_penyelenggara') ? 'organisasi_penyelenggara' : null,
                Schema::hasColumn('profil_sekolahs', 'identitas_tambahan') ? 'identitas_tambahan' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
