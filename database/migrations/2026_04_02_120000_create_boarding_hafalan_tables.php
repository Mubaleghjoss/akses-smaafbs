<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_hafalan_points')) {
            Schema::create('boarding_hafalan_points', function (Blueprint $table): void {
                $table->id();
                $table->string('materi_key', 40);
                $table->string('jenis', 20)->nullable();
                $table->string('nama_point', 191);
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();

                $table->index(['materi_key', 'urutan', 'id'], 'boarding_hafalan_points_materi_urutan_id_index');
                $table->index('is_active', 'boarding_hafalan_points_is_active_index');
            });
        }

        if (! Schema::hasTable('boarding_hafalan_assessments')) {
            Schema::create('boarding_hafalan_assessments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('boarding_pencapaian_id')
                    ->constrained('boarding_pencapaians')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->foreignId('boarding_hafalan_point_id')
                    ->constrained('boarding_hafalan_points')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
                $table->date('assessed_at');
                $table->unsignedTinyInteger('score');
                $table->unsignedBigInteger('reviewer_user_id')->nullable()->index();
                $table->string('reviewer_name', 100)->nullable();
                $table->timestamps();

                $table->unique(
                    ['boarding_pencapaian_id', 'boarding_hafalan_point_id'],
                    'boarding_hafalan_assessments_pencapaian_point_unique'
                );
            });
        }

        // Seed default points idempotently (only when table exists and is empty).
        if (Schema::hasTable('boarding_hafalan_points') && DB::table('boarding_hafalan_points')->count() === 0) {
            $now = now();

            $points = [];
            $push = function (string $materiKey, ?string $jenis, string $namaPoint) use (&$points, $now): void {
                $points[] = [
                    'materi_key' => $materiKey,
                    'jenis' => $jenis,
                    'nama_point' => $namaPoint,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            };

            // Appendix A (90 items) — urutan will be reindexed per materi below.
            // pegon_bacaan — surat (10)
            $push('pegon_bacaan', 'surat', 'Al-Fill');
            $push('pegon_bacaan', 'surat', 'Al-Quraisy');
            $push('pegon_bacaan', 'surat', 'Al-Ma\'un');
            $push('pegon_bacaan', 'surat', 'Al-Kautsar');
            $push('pegon_bacaan', 'surat', 'Al-Kafirun');
            $push('pegon_bacaan', 'surat', 'An-Nashr');
            $push('pegon_bacaan', 'surat', 'Al-Lahab');
            $push('pegon_bacaan', 'surat', 'Al-Ikhlas');
            $push('pegon_bacaan', 'surat', 'Al-Falaq');
            $push('pegon_bacaan', 'surat', 'An-Nas');

            // pegon_bacaan — doa (14)
            $push('pegon_bacaan', 'doa', 'Asmaul Husna');
            $push('pegon_bacaan', 'doa', 'Pagi Sore');
            $push('pegon_bacaan', 'doa', 'Raja Istigfar');
            $push('pegon_bacaan', 'doa', 'Sesudah Berpakaian');
            $push('pegon_bacaan', 'doa', 'Sapujagad');
            $push('pegon_bacaan', 'doa', 'Minta Ilham yang Baik');
            $push('pegon_bacaan', 'doa', 'Ketetapan Iman');
            $push('pegon_bacaan', 'doa', 'Minta Surga');
            $push('pegon_bacaan', 'doa', 'Akan & Bangun Tidur');
            $push('pegon_bacaan', 'doa', 'Masuk & Keluar WC');
            $push('pegon_bacaan', 'doa', 'Akan & Setelah Wudhu');
            $push('pegon_bacaan', 'doa', 'Setelah Mendengar Adzan');
            $push('pegon_bacaan', 'doa', 'Masuk & Keluar Masjid');
            $push('pegon_bacaan', 'doa', 'Akan & Sesudah Makan');

            // lambatan — surat (8)
            $push('lambatan', 'surat', 'Az-Zalzalah');
            $push('lambatan', 'surat', 'Al-\'Adiyat');
            $push('lambatan', 'surat', 'Al-Qari\'ah');
            $push('lambatan', 'surat', 'At-Takatsur');
            $push('lambatan', 'surat', 'Al-\'Ashr');
            $push('lambatan', 'surat', 'Al-Humazah');
            $push('lambatan', 'surat', 'As-Sof (ayat 10-13)');
            $push('lambatan', 'surat', 'Al-Hasr (ayat 22-24)');

            // lambatan — doa (7)
            $push('lambatan', 'doa', 'Berlindung dari Siksa Kubur');
            $push('lambatan', 'doa', 'Berlindung dari Sifat Munafiq');
            $push('lambatan', 'doa', 'Berlindung dari Syirik');
            $push('lambatan', 'doa', 'Pengayoman');
            $push('lambatan', 'doa', 'Kerukunan');
            $push('lambatan', 'doa', 'Urutan Doa Sepertiga Malam');
            $push('lambatan', 'doa', 'Kumpulan Doa Nabi');

            // lambatan — dalil (9)
            $push('lambatan', 'dalil', 'Dalil 5 bab — Mengaji');
            $push('lambatan', 'dalil', 'Dalil 5 bab — Mengamal');
            $push('lambatan', 'dalil', 'Dalil 5 bab — Membela');
            $push('lambatan', 'dalil', 'Dalil 5 bab — Sambung Jamaah');
            $push('lambatan', 'dalil', 'Dalil 5 bab — Toa');
            $push('lambatan', 'dalil', 'Dalil 4 tali keimanan — Bersyukur');
            $push('lambatan', 'dalil', 'Dalil 4 tali keimanan — Mengagungkan');
            $push('lambatan', 'dalil', 'Dalil 4 tali keimanan — Mempersungguh');
            $push('lambatan', 'dalil', 'Dalil 4 tali keimanan — Berdoa');

            // cepatan — surat (10)
            $push('cepatan', 'surat', 'Ad-Dhuha');
            $push('cepatan', 'surat', 'Al-Insyirah');
            $push('cepatan', 'surat', 'At-Tin');
            $push('cepatan', 'surat', 'Al-\'Alaq');
            $push('cepatan', 'surat', 'Al-Qodar');
            $push('cepatan', 'surat', 'Al-Bayyinah');
            $push('cepatan', 'surat', 'Al-Baqoroh (ayat 1-5)');
            $push('cepatan', 'surat', 'Al-Baqoroh (ayat 255-257)');
            $push('cepatan', 'surat', 'Al-Baqoroh (ayat 284-286)');
            $push('cepatan', 'surat', 'Al-Kahfi (ayat 1-10)');

            // cepatan — doa (4)
            $push('cepatan', 'doa', 'Selesai Membaca Al-Qur\'an');
            $push('cepatan', 'doa', 'Maskumambang');
            $push('cepatan', 'doa', 'Minta Haji');
            $push('cepatan', 'doa', 'Syarat & Doa Asad');

            // cepatan — dalil (9)
            $push('cepatan', 'dalil', 'Dalil 6 thobiat luhur — Rukun');
            $push('cepatan', 'dalil', 'Dalil 6 thobiat luhur — Kompak');
            $push('cepatan', 'dalil', 'Dalil 6 thobiat luhur — Kerjasama yang Baik');
            $push('cepatan', 'dalil', 'Dalil 6 thobiat luhur — Jujur');
            $push('cepatan', 'dalil', 'Dalil 6 thobiat luhur — Amanah');
            $push('cepatan', 'dalil', 'Dalil 6 thobiat luhur — Mujhi Muzhid');
            $push('cepatan', 'dalil', 'Dalil 3 sukses generus — Akhlaqul Karimah');
            $push('cepatan', 'dalil', 'Dalil 3 sukses generus — Alim Faqih');
            $push('cepatan', 'dalil', 'Dalil 3 sukses generus — Mandiri');

            // seleksi_saringan — surat (14)
            $push('seleksi_saringan', 'surat', 'An-Naba\'');
            $push('seleksi_saringan', 'surat', 'An-Nazi\'at');
            $push('seleksi_saringan', 'surat', "'Abasa");
            $push('seleksi_saringan', 'surat', 'At-Takwir');
            $push('seleksi_saringan', 'surat', 'Al-Infithar');
            $push('seleksi_saringan', 'surat', 'Al-Muthaffifin');
            $push('seleksi_saringan', 'surat', 'Al-Insyiqaq');
            $push('seleksi_saringan', 'surat', 'Al-Buruj');
            $push('seleksi_saringan', 'surat', 'At-Thariq');
            $push('seleksi_saringan', 'surat', 'Al-A\'la');
            $push('seleksi_saringan', 'surat', 'Al-Ghasyiyah');
            $push('seleksi_saringan', 'surat', 'Al-Fajr');
            $push('seleksi_saringan', 'surat', 'As-Syams');
            $push('seleksi_saringan', 'surat', 'Al-Lail');

            // seleksi_saringan — doa (5)
            $push('seleksi_saringan', 'doa', 'Sholat Dhuha');
            $push('seleksi_saringan', 'doa', 'Sholat Istiqoroh');
            $push('seleksi_saringan', 'doa', 'Sholat Hajat');
            $push('seleksi_saringan', 'doa', 'Sholat Jenazah');
            $push('seleksi_saringan', 'doa', 'PR 13 dan keutamaannya');

            $materiCounters = [];
            foreach ($points as &$point) {
                $materiKey = $point['materi_key'];
                $materiCounters[$materiKey] = ($materiCounters[$materiKey] ?? 0) + 1;
                $point['urutan'] = $materiCounters[$materiKey];
                $point['is_active'] = true;
                $point['created_by'] = null;
                $point['updated_by'] = null;
            }
            unset($point);

            DB::table('boarding_hafalan_points')->insert($points);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_hafalan_assessments');
        Schema::dropIfExists('boarding_hafalan_points');
    }
};
