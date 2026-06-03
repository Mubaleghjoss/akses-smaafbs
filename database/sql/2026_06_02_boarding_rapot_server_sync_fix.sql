-- Server repair SQL for Boarding Rapot sync.
-- Use after FTP upload when the server cannot run php artisan migrate --force.
-- Safe to re-run. It prepares missing boarding material tables/default rows,
-- creates missing rapot rows for the current period, and clears old payloads
-- so Laravel rebuilds rapot data from pencapaian when preview/print is opened.

SET @schema_name := DATABASE();
SET @rapot_periode_tahun := IF(
    MONTH(CURDATE()) >= 7,
    CONCAT(YEAR(CURDATE()), '/', YEAR(CURDATE()) + 1),
    CONCAT(YEAR(CURDATE()) - 1, '/', YEAR(CURDATE()))
);
SET @rapot_semester := IF(MONTH(CURDATE()) >= 7, 'ganjil', 'genap');
SET @rapot_tanggal := CURDATE();

CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @migration_batch := COALESCE((SELECT MAX(batch) + 1 FROM migrations), 1);

SET @missing_required_tables := (
    SELECT GROUP_CONCAT(required_tables.table_name ORDER BY required_tables.table_name SEPARATOR ', ')
    FROM (
        SELECT 'boarding_pencapaians' AS table_name
        UNION ALL SELECT 'boarding_rapots'
    ) AS required_tables
    LEFT JOIN information_schema.tables AS existing_tables
      ON existing_tables.table_schema = @schema_name
     AND existing_tables.table_name = required_tables.table_name
    WHERE existing_tables.table_name IS NULL
);

SET @sql := IF(
    @missing_required_tables IS NULL,
    'SELECT 1',
    CONCAT('SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''Missing required base table(s): ', @missing_required_tables, '''')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Core columns needed by the current code.
SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_pencapaians' AND column_name = 'pamong_user_id'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_pencapaians ADD COLUMN pamong_user_id BIGINT UNSIGNED NULL AFTER siswa_id, ADD INDEX boarding_pencapaian_pamong_user_index (pamong_user_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_pencapaians' AND column_name = 'target_jumlah_surat'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_pencapaians ADD COLUMN target_jumlah_surat INT UNSIGNED NOT NULL DEFAULT 0 AFTER jumlah_hadits_dihafal',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_pencapaians' AND column_name = 'target_jumlah_doa'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_pencapaians ADD COLUMN target_jumlah_doa INT UNSIGNED NOT NULL DEFAULT 0 AFTER target_jumlah_surat',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_pencapaians' AND column_name = 'target_jumlah_hadits'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_pencapaians ADD COLUMN target_jumlah_hadits INT UNSIGNED NOT NULL DEFAULT 0 AFTER target_jumlah_doa',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_pencapaians' AND column_name = 'materi_rapot_scope'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_pencapaians ADD COLUMN materi_rapot_scope VARCHAR(20) NOT NULL DEFAULT ''boarding'' AFTER status_pencapaian, ADD INDEX boarding_pencapaians_materi_rapot_scope_index (materi_rapot_scope)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE boarding_pencapaians
SET materi_rapot_scope = 'boarding'
WHERE materi_rapot_scope IS NULL OR materi_rapot_scope = '';

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_rapots' AND column_name = 'pamong_user_id'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN pamong_user_id BIGINT UNSIGNED NULL AFTER siswa_id, ADD INDEX boarding_rapot_pamong_user_index (pamong_user_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_rapots' AND column_name = 'nomor_dokumen'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN nomor_dokumen VARCHAR(50) NULL AFTER status_rapot',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_rapots' AND column_name = 'predikat_boarding'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN predikat_boarding VARCHAR(50) NULL AFTER nomor_dokumen',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_rapots' AND column_name = 'generated_at'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN generated_at DATETIME NULL AFTER rekomendasi_tindak_lanjut',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_rapots' AND column_name = 'rekap_payload'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN rekap_payload JSON NULL AFTER generated_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_rapots' AND column_name = 'wali_pamong_nama'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN wali_pamong_nama VARCHAR(100) NULL AFTER rekap_payload',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_rapots' AND column_name = 'kepala_boarding_nama'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN kepala_boarding_nama VARCHAR(100) NULL AFTER wali_pamong_nama',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_rapots' AND column_name = 'mudir_asrama_nama'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN mudir_asrama_nama VARCHAR(100) NULL AFTER kepala_boarding_nama',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_rapots' AND column_name = 'tempat_cetak'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN tempat_cetak VARCHAR(100) NULL AFTER mudir_asrama_nama',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_rapots' AND column_name = 'administrasi_rapot_items'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN administrasi_rapot_items JSON NULL AFTER rekap_payload',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_rapots' AND column_name = 'kelas_boarding_override'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN kelas_boarding_override VARCHAR(80) NULL AFTER predikat_boarding',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tables used by the rapot payload.
CREATE TABLE IF NOT EXISTS boarding_hafalan_points (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    materi_scope VARCHAR(20) NOT NULL DEFAULT 'boarding',
    materi_key VARCHAR(40) NOT NULL,
    jenis VARCHAR(30) NULL,
    nama_point VARCHAR(191) NOT NULL,
    urutan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY boarding_hafalan_points_materi_scope_index (materi_scope),
    KEY boarding_hafalan_points_materi_urutan_id_index (materi_key, urutan, id),
    KEY boarding_hafalan_points_is_active_index (is_active),
    KEY boarding_hafalan_points_created_by_index (created_by),
    KEY boarding_hafalan_points_updated_by_index (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_hafalan_points' AND column_name = 'materi_scope'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_hafalan_points ADD COLUMN materi_scope VARCHAR(20) NOT NULL DEFAULT ''boarding'' AFTER id, ADD INDEX boarding_hafalan_points_materi_scope_index (materi_scope)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE boarding_hafalan_points
SET materi_scope = 'boarding'
WHERE materi_scope IS NULL OR materi_scope = '';

CREATE TABLE IF NOT EXISTS boarding_hafalan_assessments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    boarding_pencapaian_id BIGINT UNSIGNED NOT NULL,
    boarding_hafalan_point_id BIGINT UNSIGNED NOT NULL,
    assessed_at DATE NOT NULL,
    score TINYINT UNSIGNED NOT NULL,
    reviewer_user_id BIGINT UNSIGNED NULL,
    reviewer_name VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY boarding_hafalan_assessments_pencapaian_point_unique (boarding_pencapaian_id, boarding_hafalan_point_id),
    KEY boarding_hafalan_assessments_reviewer_user_id_index (reviewer_user_id),
    KEY boarding_hafalan_assessments_point_index (boarding_hafalan_point_id),
    CONSTRAINT boarding_hafalan_assessments_boarding_pencapaian_id_foreign
        FOREIGN KEY (boarding_pencapaian_id) REFERENCES boarding_pencapaians (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT boarding_hafalan_assessments_boarding_hafalan_point_id_foreign
        FOREIGN KEY (boarding_hafalan_point_id) REFERENCES boarding_hafalan_points (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boarding_makna_progresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    boarding_pencapaian_id BIGINT UNSIGNED NOT NULL,
    target_key VARCHAR(100) NOT NULL,
    target_group VARCHAR(40) NOT NULL,
    target_name VARCHAR(191) NOT NULL,
    urutan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'belum_diisi',
    remaining_pages SMALLINT UNSIGNED NULL,
    total_pages SMALLINT UNSIGNED NULL,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY boarding_makna_progresses_pencapaian_target_unique (boarding_pencapaian_id, target_key),
    KEY boarding_makna_progresses_group_order_index (boarding_pencapaian_id, target_group, urutan),
    KEY boarding_makna_progresses_status_index (status),
    KEY boarding_makna_progresses_updated_by_user_id_index (updated_by_user_id),
    CONSTRAINT boarding_makna_progresses_boarding_pencapaian_id_foreign
        FOREIGN KEY (boarding_pencapaian_id) REFERENCES boarding_pencapaians (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_makna_progresses' AND column_name = 'total_pages'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_makna_progresses ADD COLUMN total_pages SMALLINT UNSIGNED NULL AFTER remaining_pages',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS boarding_bacaan_assessments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    boarding_pencapaian_id BIGINT UNSIGNED NOT NULL,
    assessed_at DATE NOT NULL,
    kelas_bacaan VARCHAR(10) NULL,
    pp_grade VARCHAR(1) NOT NULL,
    kl_grade VARCHAR(1) NOT NULL,
    tj_grade VARCHAR(1) NOT NULL,
    mj_grade VARCHAR(1) NOT NULL,
    reviewer_user_id BIGINT UNSIGNED NULL,
    reviewer_name VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY boarding_bacaan_assessments_reviewer_user_id_index (reviewer_user_id),
    KEY boarding_bacaan_assessments_pencapaian_date_index (boarding_pencapaian_id, assessed_at, id),
    CONSTRAINT boarding_bacaan_assessments_boarding_pencapaian_id_foreign
        FOREIGN KEY (boarding_pencapaian_id) REFERENCES boarding_pencapaians (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = @schema_name AND table_name = 'boarding_bacaan_assessments' AND column_name = 'kelas_bacaan'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_bacaan_assessments ADD COLUMN kelas_bacaan VARCHAR(10) NULL AFTER assessed_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS boarding_materi_progresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    boarding_pencapaian_id BIGINT UNSIGNED NOT NULL,
    target_key VARCHAR(80) NOT NULL,
    target_group VARCHAR(40) NOT NULL,
    target_name VARCHAR(120) NOT NULL,
    grade VARCHAR(20) NULL,
    notes TEXT NULL,
    urutan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY boarding_materi_progresses_pencapaian_target_unique (boarding_pencapaian_id, target_key),
    KEY boarding_materi_progresses_group_order_index (boarding_pencapaian_id, target_group, urutan),
    KEY boarding_materi_progresses_updated_by_user_id_index (updated_by_user_id),
    CONSTRAINT boarding_materi_progresses_boarding_pencapaian_id_foreign
        FOREIGN KEY (boarding_pencapaian_id) REFERENCES boarding_pencapaians (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boarding_mt_progresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    boarding_pencapaian_id BIGINT UNSIGNED NOT NULL,
    target_key VARCHAR(120) NOT NULL,
    target_group VARCHAR(50) NOT NULL,
    target_name VARCHAR(191) NOT NULL,
    input_type VARCHAR(20) NOT NULL,
    grade_scale VARCHAR(30) NULL,
    progress_value SMALLINT UNSIGNED NULL,
    target_total SMALLINT UNSIGNED NULL,
    unit_label VARCHAR(40) NULL,
    grade VARCHAR(20) NULL,
    notes TEXT NULL,
    urutan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY boarding_mt_progresses_pencapaian_target_unique (boarding_pencapaian_id, target_key),
    KEY boarding_mt_progresses_group_order_index (boarding_pencapaian_id, target_group, urutan),
    KEY boarding_mt_progresses_grade_index (grade),
    KEY boarding_mt_progresses_updated_by_user_id_index (updated_by_user_id),
    CONSTRAINT boarding_mt_progresses_boarding_pencapaian_id_foreign
        FOREIGN KEY (boarding_pencapaian_id) REFERENCES boarding_pencapaians (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Normalize legacy material keys before seeding missing master rows.
UPDATE boarding_hafalan_points
SET materi_key = 'materi_tambahan_hafalan', updated_at = NOW()
WHERE materi_key IN ('seleksi_saringan', 'materi_tambahan')
  AND jenis IN ('surat', 'doa', 'dalil');

UPDATE boarding_hafalan_points
SET materi_key = 'materi_tambahan_makna_quran', updated_at = NOW()
WHERE materi_key = 'materi_tambahan'
  AND jenis = 'makna_quran';

UPDATE boarding_hafalan_points
SET materi_key = 'materi_tambahan_makna_hadits', updated_at = NOW()
WHERE materi_key = 'materi_tambahan'
  AND jenis = 'makna_hadits';

DROP TEMPORARY TABLE IF EXISTS tmp_boarding_hafalan_seed;
CREATE TEMPORARY TABLE tmp_boarding_hafalan_seed (
    materi_scope VARCHAR(20) NOT NULL,
    materi_key VARCHAR(40) NOT NULL,
    jenis VARCHAR(30) NOT NULL,
    nama_point VARCHAR(191) NOT NULL,
    urutan SMALLINT UNSIGNED NOT NULL
) ENGINE=MEMORY;

INSERT INTO tmp_boarding_hafalan_seed (materi_scope, materi_key, jenis, nama_point, urutan) VALUES
('boarding','materi_quran_bacaan','bacaan_quran','Bacaan Qur''an',1),
('boarding','materi_pengetesan_makna','pengetesan_makna','Pengetesan Makna',1),
('boarding','pegon_bacaan','surat','Al-Fill',1),
('boarding','pegon_bacaan','surat','Al-Quraisy',2),
('boarding','pegon_bacaan','surat','Al-Ma''un',3),
('boarding','pegon_bacaan','surat','Al-Kautsar',4),
('boarding','pegon_bacaan','surat','Al-Kafirun',5),
('boarding','pegon_bacaan','surat','An-Nashr',6),
('boarding','pegon_bacaan','surat','Al-Lahab',7),
('boarding','pegon_bacaan','surat','Al-Ikhlas',8),
('boarding','pegon_bacaan','surat','Al-Falaq',9),
('boarding','pegon_bacaan','surat','An-Nas',10),
('boarding','pegon_bacaan','doa','Asmaul Husna',11),
('boarding','pegon_bacaan','doa','Pagi Sore',12),
('boarding','pegon_bacaan','doa','Raja Istigfar',13),
('boarding','pegon_bacaan','doa','Sesudah Berpakaian',14),
('boarding','pegon_bacaan','doa','Sapujagad',15),
('boarding','pegon_bacaan','doa','Minta Ilham yang Baik',16),
('boarding','pegon_bacaan','doa','Ketetapan Iman',17),
('boarding','pegon_bacaan','doa','Minta Surga',18),
('boarding','pegon_bacaan','doa','Akan & Bangun Tidur',19),
('boarding','pegon_bacaan','doa','Masuk & Keluar WC',20),
('boarding','pegon_bacaan','doa','Akan & Setelah Wudhu',21),
('boarding','pegon_bacaan','doa','Setelah Mendengar Adzan',22),
('boarding','pegon_bacaan','doa','Masuk & Keluar Masjid',23),
('boarding','pegon_bacaan','doa','Akan & Sesudah Makan',24),
('boarding','lambatan','surat','Az-Zalzalah',1),
('boarding','lambatan','surat','Al-''Adiyat',2),
('boarding','lambatan','surat','Al-Qari''ah',3),
('boarding','lambatan','surat','At-Takatsur',4),
('boarding','lambatan','surat','Al-''Ashr',5),
('boarding','lambatan','surat','Al-Humazah',6),
('boarding','lambatan','surat','As-Sof (ayat 10-13)',7),
('boarding','lambatan','surat','Al-Hasr (ayat 22-24)',8),
('boarding','lambatan','doa','Berlindung dari Siksa Kubur',9),
('boarding','lambatan','doa','Berlindung dari Sifat Munafiq',10),
('boarding','lambatan','doa','Berlindung dari Syirik',11),
('boarding','lambatan','doa','Pengayoman',12),
('boarding','lambatan','doa','Kerukunan',13),
('boarding','lambatan','doa','Urutan Doa Sepertiga Malam',14),
('boarding','lambatan','doa','Kumpulan Doa Nabi',15),
('boarding','lambatan','dalil','Dalil 5 bab - Mengaji',16),
('boarding','lambatan','dalil','Dalil 5 bab - Mengamal',17),
('boarding','lambatan','dalil','Dalil 5 bab - Membela',18),
('boarding','lambatan','dalil','Dalil 5 bab - Sambung Jamaah',19),
('boarding','lambatan','dalil','Dalil 5 bab - Toa',20),
('boarding','lambatan','dalil','Dalil 4 tali keimanan - Bersyukur',21),
('boarding','lambatan','dalil','Dalil 4 tali keimanan - Mengagungkan',22),
('boarding','lambatan','dalil','Dalil 4 tali keimanan - Mempersungguh',23),
('boarding','lambatan','dalil','Dalil 4 tali keimanan - Berdoa',24),
('boarding','cepatan','surat','Ad-Dhuha',1),
('boarding','cepatan','surat','Al-Insyirah',2),
('boarding','cepatan','surat','At-Tin',3),
('boarding','cepatan','surat','Al-''Alaq',4),
('boarding','cepatan','surat','Al-Qodar',5),
('boarding','cepatan','surat','Al-Bayyinah',6),
('boarding','cepatan','surat','Al-Baqoroh (ayat 1-5)',7),
('boarding','cepatan','surat','Al-Baqoroh (ayat 255-257)',8),
('boarding','cepatan','surat','Al-Baqoroh (ayat 284-286)',9),
('boarding','cepatan','surat','Al-Kahfi (ayat 1-10)',10),
('boarding','cepatan','doa','Selesai Membaca Al-Qur''an',11),
('boarding','cepatan','doa','Maskumambang',12),
('boarding','cepatan','doa','Minta Haji',13),
('boarding','cepatan','doa','Syarat & Doa Asad',14),
('boarding','cepatan','dalil','Dalil 6 thobiat luhur - Rukun',15),
('boarding','cepatan','dalil','Dalil 6 thobiat luhur - Kompak',16),
('boarding','cepatan','dalil','Dalil 6 thobiat luhur - Kerjasama yang Baik',17),
('boarding','cepatan','dalil','Dalil 6 thobiat luhur - Jujur',18),
('boarding','cepatan','dalil','Dalil 6 thobiat luhur - Amanah',19),
('boarding','cepatan','dalil','Dalil 6 thobiat luhur - Mujhi Muzhid',20),
('boarding','cepatan','dalil','Dalil 3 sukses generus - Akhlaqul Karimah',21),
('boarding','cepatan','dalil','Dalil 3 sukses generus - Alim Faqih',22),
('boarding','cepatan','dalil','Dalil 3 sukses generus - Mandiri',23),
('boarding','materi_tambahan_hafalan','surat','An-Naba''',1),
('boarding','materi_tambahan_hafalan','surat','An-Nazi''at',2),
('boarding','materi_tambahan_hafalan','surat','''Abasa',3),
('boarding','materi_tambahan_hafalan','surat','At-Takwir',4),
('boarding','materi_tambahan_hafalan','surat','Al-Infithar',5),
('boarding','materi_tambahan_hafalan','surat','Al-Muthaffifin',6),
('boarding','materi_tambahan_hafalan','surat','Al-Insyiqaq',7),
('boarding','materi_tambahan_hafalan','surat','Al-Buruj',8),
('boarding','materi_tambahan_hafalan','surat','At-Thariq',9),
('boarding','materi_tambahan_hafalan','surat','Al-A''la',10),
('boarding','materi_tambahan_hafalan','surat','Al-Ghasyiyah',11),
('boarding','materi_tambahan_hafalan','surat','Al-Fajr',12),
('boarding','materi_tambahan_hafalan','surat','As-Syams',13),
('boarding','materi_tambahan_hafalan','surat','Al-Lail',14),
('boarding','materi_tambahan_hafalan','doa','Doa Sholat Dhuha',15),
('boarding','materi_tambahan_hafalan','doa','Doa Sholat Istiqoroh',16),
('boarding','materi_tambahan_hafalan','doa','Doa Sholat Hajat',17),
('boarding','materi_tambahan_hafalan','doa','Doa Sholat Jenazah',18),
('boarding','materi_tambahan_hafalan','doa','Doa PR 13 dan keutamaannya',19),
('mt','mt_makna_hadits','mt_makna_hadits','Muslim Jilid 1',1),
('mt','mt_makna_hadits','mt_makna_hadits','Muslim Jilid 2',2),
('mt','mt_makna_hadits','mt_makna_hadits','Muslim Jilid 3',3),
('mt','mt_makna_hadits','mt_makna_hadits','Muslim Jilid 4',4),
('mt','mt_tambahan','mt_praktek','Tugas Praktek',10),
('mt','mt_hafalan','mt_hafalan','Hafalan Surat Quran Juz 1',20),
('mt','mt_hafalan','mt_hafalan','Hafalan Dalil 29 Karakter Luhur',21),
('mt','mt_catatan_saran','mt_catatan_saran','Kedisiplinan',30),
('mt','mt_catatan_saran','mt_catatan_saran','Ketertiban',31),
('mt','mt_catatan_saran','mt_catatan_saran','Akhlak',32),
('mt','mt_catatan_saran','mt_catatan_saran','Kesemangatan',33);

UPDATE boarding_hafalan_points p
JOIN tmp_boarding_hafalan_seed s
  ON p.materi_scope = s.materi_scope
 AND p.materi_key = s.materi_key
 AND p.jenis = s.jenis
 AND p.nama_point = s.nama_point
SET p.urutan = s.urutan,
    p.is_active = 1,
    p.updated_at = NOW();

INSERT INTO boarding_hafalan_points
    (materi_scope, materi_key, jenis, nama_point, urutan, is_active, created_at, updated_at)
SELECT s.materi_scope, s.materi_key, s.jenis, s.nama_point, s.urutan, 1, NOW(), NOW()
FROM tmp_boarding_hafalan_seed s
WHERE NOT EXISTS (
    SELECT 1
    FROM boarding_hafalan_points p
    WHERE p.materi_scope = s.materi_scope
      AND p.materi_key = s.materi_key
      AND p.jenis = s.jenis
      AND p.nama_point = s.nama_point
);

DROP TEMPORARY TABLE IF EXISTS tmp_boarding_hafalan_seed;

-- Default progress rows for every pencapaian, so rapot totals are not 0/0.
DROP TEMPORARY TABLE IF EXISTS tmp_boarding_makna_targets;
CREATE TEMPORARY TABLE tmp_boarding_makna_targets (
    target_key VARCHAR(100) NOT NULL,
    target_group VARCHAR(40) NOT NULL,
    target_name VARCHAR(191) NOT NULL,
    urutan SMALLINT UNSIGNED NOT NULL
) ENGINE=MEMORY;

INSERT INTO tmp_boarding_makna_targets (target_key, target_group, target_name, urutan) VALUES
('quran_juz_1','quran','Makna Qur''an Juz 1',1),
('quran_juz_2','quran','Makna Qur''an Juz 2',2),
('quran_juz_3','quran','Makna Qur''an Juz 3',3),
('quran_juz_4','quran','Makna Qur''an Juz 4',4),
('quran_juz_5','quran','Makna Qur''an Juz 5',5),
('quran_juz_6','quran','Makna Qur''an Juz 6',6),
('quran_juz_7','quran','Makna Qur''an Juz 7',7),
('quran_juz_8','quran','Makna Qur''an Juz 8',8),
('quran_juz_9','quran','Makna Qur''an Juz 9',9),
('quran_juz_10','quran','Makna Qur''an Juz 10',10),
('quran_juz_11','quran','Makna Qur''an Juz 11',11),
('quran_juz_12','quran','Makna Qur''an Juz 12',12),
('quran_juz_13','quran','Makna Qur''an Juz 13',13),
('quran_juz_14','quran','Makna Qur''an Juz 14',14),
('quran_juz_15','quran','Makna Qur''an Juz 15',15),
('quran_juz_16','quran','Makna Qur''an Juz 16',16),
('quran_juz_17','quran','Makna Qur''an Juz 17',17),
('quran_juz_18','quran','Makna Qur''an Juz 18',18),
('quran_juz_19','quran','Makna Qur''an Juz 19',19),
('quran_juz_20','quran','Makna Qur''an Juz 20',20),
('quran_juz_21','quran','Makna Qur''an Juz 21',21),
('quran_juz_22','quran','Makna Qur''an Juz 22',22),
('quran_juz_23','quran','Makna Qur''an Juz 23',23),
('quran_juz_24','quran','Makna Qur''an Juz 24',24),
('quran_juz_25','quran','Makna Qur''an Juz 25',25),
('quran_juz_26','quran','Makna Qur''an Juz 26',26),
('quran_juz_27','quran','Makna Qur''an Juz 27',27),
('quran_juz_28','quran','Makna Qur''an Juz 28',28),
('quran_juz_29','quran','Makna Qur''an Juz 29',29),
('quran_juz_30','quran','Makna Qur''an Juz 30',30),
('hadits_materi_1','hadits_materi','K. Sholah',100),
('hadits_materi_2','hadits_materi','K. Nawafil',101),
('hadits_materi_3','hadits_materi','K. Da''wat',102),
('hadits_materi_4','hadits_materi','K. Adab',103),
('hadits_materi_5','hadits_materi','K. Jannah Wannar',104),
('hadits_materi_6','hadits_materi','K. Janaiz',105),
('hadits_materi_7','hadits_materi','K. Adillah',106),
('hadits_materi_8','hadits_materi','K. Shoum',107),
('hadits_materi_9','hadits_materi','K. Ahkam',108),
('hadits_materi_10','hadits_materi','K. Manasik Waljihad',109),
('hadits_materi_11','hadits_materi','K. Jihad',110),
('hadits_materi_12','hadits_materi','K. Haji',111),
('hadits_materi_13','hadits_materi','K. Manasikil Haji',112),
('hadits_materi_14','hadits_materi','K. Imaroh',113),
('hadits_materi_15','hadits_materi','Kanzil Umal',114),
('hadits_materi_16','hadits_materi','K. Faroid',115),
('hadits_materi_17','hadits_materi','K. Khotbah',116),
('hadits_materi_18','hadits_materi','Materi Tata Krama',117),
('hadits_materi_19','hadits_materi','Materi Bacaan',118),
('hadits_materi_20','hadits_materi','Materi Pegon',119),
('hadits_materi_21','hadits_materi','Materi Lambatan',120),
('hadits_materi_22','hadits_materi','Materi Cepatan',121),
('hadits_materi_23','hadits_materi','Materi Saringan',122),
('hadits_materi_24','hadits_materi','K. Nikah',123),
('hadits_materi_25','hadits_materi','K. Talaq',124),
('hadits_materi_26','hadits_materi','K. Zakat',125);

INSERT INTO boarding_makna_progresses
    (boarding_pencapaian_id, target_key, target_group, target_name, urutan, status, created_at, updated_at)
SELECT p.id, t.target_key, t.target_group, t.target_name, t.urutan, 'belum_diisi', NOW(), NOW()
FROM boarding_pencapaians p
CROSS JOIN tmp_boarding_makna_targets t
WHERE NOT EXISTS (
    SELECT 1
    FROM boarding_makna_progresses mp
    WHERE mp.boarding_pencapaian_id = p.id
      AND mp.target_key = t.target_key
);

DROP TEMPORARY TABLE IF EXISTS tmp_boarding_makna_targets;

DROP TEMPORARY TABLE IF EXISTS tmp_boarding_materi_targets;
CREATE TEMPORARY TABLE tmp_boarding_materi_targets (
    target_key VARCHAR(80) NOT NULL,
    target_group VARCHAR(40) NOT NULL,
    target_name VARCHAR(120) NOT NULL,
    urutan SMALLINT UNSIGNED NOT NULL
) ENGINE=MEMORY;

INSERT INTO tmp_boarding_materi_targets (target_key, target_group, target_name, urutan) VALUES
('pengetesan_makna','pengetesan_makna','Pengetesan Makna',1),
('kedisiplinan','catatan_saran','Kedisiplinan',10),
('ketertiban','catatan_saran','Ketertiban',11),
('akhlak','catatan_saran','Akhlak',12),
('kesemangatan','catatan_saran','Kesemangatan',13);

INSERT INTO boarding_materi_progresses
    (boarding_pencapaian_id, target_key, target_group, target_name, urutan, created_at, updated_at)
SELECT p.id, t.target_key, t.target_group, t.target_name, t.urutan, NOW(), NOW()
FROM boarding_pencapaians p
CROSS JOIN tmp_boarding_materi_targets t
WHERE NOT EXISTS (
    SELECT 1
    FROM boarding_materi_progresses mp
    WHERE mp.boarding_pencapaian_id = p.id
      AND mp.target_key = t.target_key
);

DROP TEMPORARY TABLE IF EXISTS tmp_boarding_materi_targets;

DROP TEMPORARY TABLE IF EXISTS tmp_boarding_mt_targets;
CREATE TEMPORARY TABLE tmp_boarding_mt_targets (
    target_key VARCHAR(120) NOT NULL,
    target_group VARCHAR(50) NOT NULL,
    target_name VARCHAR(191) NOT NULL,
    input_type VARCHAR(20) NOT NULL,
    grade_scale VARCHAR(30) NULL,
    target_total SMALLINT UNSIGNED NULL,
    unit_label VARCHAR(40) NULL,
    urutan SMALLINT UNSIGNED NOT NULL
) ENGINE=MEMORY;

INSERT INTO tmp_boarding_mt_targets (target_key, target_group, target_name, input_type, grade_scale, target_total, unit_label, urutan) VALUES
('muslim_jilid_1','makna_hadits','Muslim Jilid 1','progress',NULL,NULL,'lembar',1),
('muslim_jilid_2','makna_hadits','Muslim Jilid 2','progress',NULL,NULL,'lembar',2),
('muslim_jilid_3','makna_hadits','Muslim Jilid 3','progress',NULL,NULL,'lembar',3),
('muslim_jilid_4','makna_hadits','Muslim Jilid 4','progress',NULL,NULL,'lembar',4),
('tugas_praktek','materi_tambahan','Tugas Praktek','grade','baik_cukup',NULL,NULL,10),
('hafalan_surat_quran_juz_1','hafalan','Hafalan Surat Quran Juz 1','progress',NULL,NULL,'halaman',20),
('hafalan_dalil_29_karakter_luhur','hafalan','Hafalan Dalil 29 Karakter Luhur','progress',NULL,29,'dalil',21),
('kedisiplinan','catatan_saran','Kedisiplinan','grade','baik_cukup_kurang',NULL,NULL,30),
('ketertiban','catatan_saran','Ketertiban','grade','baik_cukup_kurang',NULL,NULL,31),
('akhlak','catatan_saran','Akhlak','grade','baik_cukup_kurang',NULL,NULL,32),
('kesemangatan','catatan_saran','Kesemangatan','grade','baik_cukup_kurang',NULL,NULL,33);

INSERT INTO boarding_mt_progresses
    (boarding_pencapaian_id, target_key, target_group, target_name, input_type, grade_scale, target_total, unit_label, urutan, created_at, updated_at)
SELECT p.id, t.target_key, t.target_group, t.target_name, t.input_type, t.grade_scale, t.target_total, t.unit_label, t.urutan, NOW(), NOW()
FROM boarding_pencapaians p
CROSS JOIN tmp_boarding_mt_targets t
WHERE NOT EXISTS (
    SELECT 1
    FROM boarding_mt_progresses mp
    WHERE mp.boarding_pencapaian_id = p.id
      AND mp.target_key = t.target_key
);

DROP TEMPORARY TABLE IF EXISTS tmp_boarding_mt_targets;

-- Create missing rapot rows for the current period. Edit variables at the top if needed.
INSERT INTO boarding_rapots
    (siswa_id, pamong_user_id, periode_tahun, semester, tanggal_rapot, status_rapot, created_at, updated_at)
SELECT p.siswa_id, p.pamong_user_id, @rapot_periode_tahun, @rapot_semester, @rapot_tanggal, 'draft', NOW(), NOW()
FROM boarding_pencapaians p
WHERE NOT EXISTS (
    SELECT 1
    FROM boarding_rapots r
    WHERE r.siswa_id = p.siswa_id
      AND r.periode_tahun = @rapot_periode_tahun
      AND r.semester = @rapot_semester
);

-- Important: old non-null JSON keeps preview using stale dummy data.
-- Clearing it makes the uploaded Laravel code rebuild payload from pencapaian.
UPDATE boarding_rapots
SET rekap_payload = NULL,
    generated_at = NULL,
    updated_at = NOW();

-- Mark related migrations as applied when this SQL is imported manually.
INSERT INTO migrations (migration, batch)
SELECT '2026_04_02_120000_create_boarding_hafalan_tables', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_04_02_120000_create_boarding_hafalan_tables');

INSERT INTO migrations (migration, batch)
SELECT '2026_04_03_220000_create_boarding_makna_and_bacaan_tables', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_04_03_220000_create_boarding_makna_and_bacaan_tables');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_30_217000_create_boarding_mt_progresses_table', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_30_217000_create_boarding_mt_progresses_table');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_30_218000_expand_boarding_makna_and_materi_boarding', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_30_218000_expand_boarding_makna_and_materi_boarding');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_31_080000_add_materi_rapot_scope_to_boarding_pencapaians', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_31_080000_add_materi_rapot_scope_to_boarding_pencapaians');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_31_100000_add_administrasi_items_to_boarding_rapots', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_31_100000_add_administrasi_items_to_boarding_rapots');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_31_101000_add_kelas_boarding_override_to_boarding_rapots', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_31_101000_add_kelas_boarding_override_to_boarding_rapots');

INSERT INTO migrations (migration, batch)
SELECT '2026_06_03_080000_add_kelas_bacaan_to_boarding_bacaan_assessments', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_06_03_080000_add_kelas_bacaan_to_boarding_bacaan_assessments');

-- Quick verification after import:
SELECT
    (SELECT COUNT(*) FROM boarding_hafalan_points WHERE materi_key IN ('pegon_bacaan','lambatan','cepatan','materi_tambahan_hafalan') AND is_active = 1) AS active_hafalan_master_points,
    (SELECT COUNT(*) FROM boarding_makna_progresses) AS makna_progress_rows,
    (SELECT COUNT(*) FROM boarding_materi_progresses) AS materi_boarding_progress_rows,
    (SELECT COUNT(*) FROM boarding_mt_progresses) AS mt_progress_rows,
    (SELECT COUNT(*) FROM boarding_rapots WHERE rekap_payload IS NULL) AS rapots_waiting_rebuild;
