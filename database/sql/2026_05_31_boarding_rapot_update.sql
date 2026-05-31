-- Manual SQL handoff for boarding target, materi boarding/MT, and rapot updates.
-- Use this only when the server cannot run `php artisan migrate --force`.
-- Assumption: the existing boarding base tables already exist on the server.

SET @schema_name := DATABASE();
SET @migration_batch := COALESCE((SELECT MAX(batch) + 1 FROM migrations), 1);

-- 1) Schema updates.
SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'boarding_hafalan_points'
      AND column_name = 'materi_scope'
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

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'boarding_makna_progresses'
      AND column_name = 'total_pages'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_makna_progresses ADD COLUMN total_pages SMALLINT UNSIGNED NULL AFTER remaining_pages',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'boarding_pencapaians'
      AND column_name = 'materi_rapot_scope'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_pencapaians ADD COLUMN materi_rapot_scope VARCHAR(20) NOT NULL DEFAULT ''boarding'' AFTER status_pencapaian, ADD INDEX boarding_pencapaians_materi_rapot_scope_index (materi_rapot_scope)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'boarding_rapots'
      AND column_name = 'administrasi_rapot_items'
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
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'boarding_rapots'
      AND column_name = 'kelas_boarding_override'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE boarding_rapots ADD COLUMN kelas_boarding_override VARCHAR(80) NULL AFTER predikat_boarding',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Data normalization for materi groups.
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

UPDATE boarding_hafalan_points
SET nama_point = 'Doa Sholat Dhuha', updated_at = NOW()
WHERE materi_key = 'materi_tambahan_hafalan' AND jenis = 'doa' AND nama_point = 'Sholat Dhuha';

UPDATE boarding_hafalan_points
SET nama_point = 'Doa Sholat Istiqoroh', updated_at = NOW()
WHERE materi_key = 'materi_tambahan_hafalan' AND jenis = 'doa' AND nama_point = 'Sholat Istiqoroh';

UPDATE boarding_hafalan_points
SET nama_point = 'Doa Sholat Hajat', updated_at = NOW()
WHERE materi_key = 'materi_tambahan_hafalan' AND jenis = 'doa' AND nama_point = 'Sholat Hajat';

UPDATE boarding_hafalan_points
SET nama_point = 'Doa Sholat Jenazah', updated_at = NOW()
WHERE materi_key = 'materi_tambahan_hafalan' AND jenis = 'doa' AND nama_point = 'Sholat Jenazah';

UPDATE boarding_hafalan_points
SET nama_point = 'Doa PR 13 dan keutamaannya', updated_at = NOW()
WHERE materi_key = 'materi_tambahan_hafalan' AND jenis = 'doa' AND nama_point = 'PR 13 dan keutamaannya';

UPDATE boarding_makna_progresses
SET target_name = REPLACE(target_name, 'Makna Al-Quran Juz ', 'Makna Qur''an Juz ')
WHERE target_key LIKE 'quran_juz_%'
  AND target_name LIKE 'Makna Al-Quran Juz %';

-- 3) Seed/update materi master rows.
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
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 1',1001),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 2',1002),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 3',1003),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 4',1004),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 5',1005),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 6',1006),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 7',1007),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 8',1008),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 9',1009),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 10',1010),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 11',1011),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 12',1012),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 13',1013),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 14',1014),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 15',1015),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 16',1016),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 17',1017),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 18',1018),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 19',1019),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 20',1020),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 21',1021),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 22',1022),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 23',1023),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 24',1024),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 25',1025),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 26',1026),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 27',1027),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 28',1028),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 29',1029),
('boarding','materi_tambahan_makna_quran','makna_quran','Makna Al-Qur''an Juz 30',1030),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Sholah',1100),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Nawafil',1101),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Da''wat',1102),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Adab',1103),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Jannah Wannar',1104),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Janaiz',1105),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Adillah',1106),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Shoum',1107),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Ahkam',1108),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Manasik Waljihad',1109),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Jihad',1110),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Haji',1111),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Manasikil Haji',1112),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Imaroh',1113),
('boarding','materi_tambahan_makna_hadits','makna_hadits','Kanzil Umal',1114),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Faroid',1115),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Khotbah',1116),
('boarding','materi_tambahan_makna_hadits','makna_hadits','Materi Tata Krama',1117),
('boarding','materi_tambahan_makna_hadits','makna_hadits','Materi Bacaan',1118),
('boarding','materi_tambahan_makna_hadits','makna_hadits','Materi Pegon',1119),
('boarding','materi_tambahan_makna_hadits','makna_hadits','Materi Lambatan',1120),
('boarding','materi_tambahan_makna_hadits','makna_hadits','Materi Cepatan',1121),
('boarding','materi_tambahan_makna_hadits','makna_hadits','Materi Saringan',1122),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Nikah',1123),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Talaq',1124),
('boarding','materi_tambahan_makna_hadits','makna_hadits','K. Zakat',1125),
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

-- 4) Mark Laravel migrations as applied when this SQL is used instead of artisan migrate.
INSERT INTO migrations (migration, batch)
SELECT '2026_05_30_210000_expand_boarding_materi_master', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_30_210000_expand_boarding_materi_master');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_30_213000_separate_boarding_materi_tambahan_groups', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_30_213000_separate_boarding_materi_tambahan_groups');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_30_214000_consolidate_boarding_materi_tambahan_class', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_30_214000_consolidate_boarding_materi_tambahan_class');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_30_215000_split_boarding_materi_tambahan_by_class', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_30_215000_split_boarding_materi_tambahan_by_class');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_30_216000_add_scope_and_mt_materi_boarding_points', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_30_216000_add_scope_and_mt_materi_boarding_points');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_30_217000_create_boarding_mt_progresses_table', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_30_217000_create_boarding_mt_progresses_table');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_30_218000_expand_boarding_makna_and_materi_boarding', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_30_218000_expand_boarding_makna_and_materi_boarding');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_30_219000_rename_boarding_makna_quran_targets', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_30_219000_rename_boarding_makna_quran_targets');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_31_080000_add_materi_rapot_scope_to_boarding_pencapaians', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_31_080000_add_materi_rapot_scope_to_boarding_pencapaians');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_31_090000_add_boarding_pengetesan_makna_material_point', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_31_090000_add_boarding_pengetesan_makna_material_point');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_31_100000_add_administrasi_items_to_boarding_rapots', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_31_100000_add_administrasi_items_to_boarding_rapots');

INSERT INTO migrations (migration, batch)
SELECT '2026_05_31_101000_add_kelas_boarding_override_to_boarding_rapots', @migration_batch
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_05_31_101000_add_kelas_boarding_override_to_boarding_rapots');
