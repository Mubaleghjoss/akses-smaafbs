<?php

namespace Tests\Unit;

use App\Support\ServerSync\MariaDbDumpNormalizer;
use PHPUnit\Framework\TestCase;

class MariaDbDumpNormalizerTest extends TestCase
{
    public function test_it_removes_new_mariadb_sandbox_directive_without_changing_sql(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'server-sync-normalizer-'.bin2hex(random_bytes(5));
        mkdir($directory, 0777, true);

        $sourcePath = $directory.DIRECTORY_SEPARATOR.'server.sql';
        $sql = "/*M!999999\\- enable the sandbox mode */ \n"
            ."-- MariaDB dump\n"
            ."CREATE TABLE `example` (`id` bigint unsigned NOT NULL);\n"
            ."INSERT INTO `example` VALUES (1);\n";
        file_put_contents($sourcePath, $sql);

        try {
            $result = (new MariaDbDumpNormalizer())->normalizeForLocalClient($sourcePath);

            $this->assertSame(1, $result['removed_lines']);
            $this->assertSame([], $result['excluded_tables']);
            $this->assertNotSame($sourcePath, $result['path']);
            $this->assertSame(
                "-- MariaDB dump\n"
                ."CREATE TABLE `example` (`id` bigint unsigned NOT NULL);\n"
                ."INSERT INTO `example` VALUES (1);\n",
                file_get_contents($result['path']),
            );
            $this->assertSame($sql, file_get_contents($sourcePath));
        } finally {
            foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($directory);
        }
    }

    public function test_it_keeps_an_already_compatible_dump_unchanged(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'server-sync-normalizer-'.bin2hex(random_bytes(5));
        mkdir($directory, 0777, true);

        $sourcePath = $directory.DIRECTORY_SEPARATOR.'server.sql';
        $sql = "-- MariaDB dump\nSELECT 1;\n";
        file_put_contents($sourcePath, $sql);

        try {
            $result = (new MariaDbDumpNormalizer())->normalizeForLocalClient($sourcePath);

            $this->assertSame([
                'path' => $sourcePath,
                'removed_lines' => 0,
                'excluded_tables' => [],
            ], $result);
            $this->assertFileDoesNotExist($sourcePath.'.local-compatible.sql');
            $this->assertSame($sql, file_get_contents($sourcePath));
        } finally {
            @unlink($sourcePath);
            @rmdir($directory);
        }
    }

    public function test_it_keeps_local_sessions_table_out_of_the_server_dump(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'server-sync-normalizer-'.bin2hex(random_bytes(5));
        mkdir($directory, 0777, true);

        $sourcePath = $directory.DIRECTORY_SEPARATOR.'server.sql';
        $sql = "-- Table structure for table `roles`\n"
            ."DROP TABLE IF EXISTS `roles`;\n"
            ."CREATE TABLE `roles` (`id` bigint);\n"
            ."-- Dumping data for table `roles`\n"
            ."LOCK TABLES `roles` WRITE;\n"
            ."INSERT INTO `roles` VALUES (1);\n"
            ."UNLOCK TABLES;\n"
            ."-- Table structure for table `sessions`\n"
            ."DROP TABLE IF EXISTS `sessions`;\n"
            ."CREATE TABLE `sessions` (`id` varchar(255));\n"
            ."-- Dumping data for table `sessions`\n"
            ."LOCK TABLES `sessions` WRITE;\n"
            ."INSERT INTO `sessions` VALUES ('server-session');\n"
            ."UNLOCK TABLES;\n"
            ."-- Table structure for table `users`\n"
            ."DROP TABLE IF EXISTS `users`;\n"
            ."CREATE TABLE `users` (`id` bigint);\n";
        file_put_contents($sourcePath, $sql);

        try {
            $result = (new MariaDbDumpNormalizer())->normalizeForLocalClient($sourcePath);
            $normalized = file_get_contents($result['path']);

            $this->assertSame(['sessions'], $result['excluded_tables']);
            $this->assertStringNotContainsString('`sessions`', $normalized);
            $this->assertStringContainsString('`roles`', $normalized);
            $this->assertStringContainsString('`users`', $normalized);
        } finally {
            foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($directory);
        }
    }

    public function test_it_keeps_literacy_and_spmb_sync_tables_from_the_server_dump(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'server-sync-normalizer-'.bin2hex(random_bytes(5));
        mkdir($directory, 0777, true);

        $sourcePath = $directory.DIRECTORY_SEPARATOR.'server.sql';
        $sql = "-- Table structure for table `data_siswa`\n"
            ."DROP TABLE IF EXISTS `data_siswa`;\n"
            ."CREATE TABLE `data_siswa` (`id` bigint, `spmb_nomor_pendaftaran` varchar(255));\n"
            ."INSERT INTO `data_siswa` VALUES (1, 'SPMB-001');\n"
            ."-- Table structure for table `spmb_sync_runs`\n"
            ."DROP TABLE IF EXISTS `spmb_sync_runs`;\n"
            ."CREATE TABLE `spmb_sync_runs` (`id` bigint);\n"
            ."INSERT INTO `spmb_sync_runs` VALUES (1);\n"
            ."-- Table structure for table `perpustakaan_literasi_materials`\n"
            ."DROP TABLE IF EXISTS `perpustakaan_literasi_materials`;\n"
            ."CREATE TABLE `perpustakaan_literasi_materials` (`id` bigint, `program_category` varchar(60));\n"
            ."INSERT INTO `perpustakaan_literasi_materials` VALUES (1, 'numeracy_excellence');\n";
        file_put_contents($sourcePath, $sql);

        try {
            $result = (new MariaDbDumpNormalizer())->normalizeForLocalClient($sourcePath);
            $normalized = file_get_contents($result['path']);

            $this->assertSame([], $result['excluded_tables']);
            $this->assertStringContainsString('`data_siswa`', $normalized);
            $this->assertStringContainsString('`spmb_sync_runs`', $normalized);
            $this->assertStringContainsString('`perpustakaan_literasi_materials`', $normalized);
            $this->assertStringContainsString('SPMB-001', $normalized);
            $this->assertStringContainsString('numeracy_excellence', $normalized);
        } finally {
            foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($directory);
        }
    }
}
