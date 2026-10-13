<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $charset = DB::connection()->getConfig('charset') ?: 'utf8mb4';
        $collation = DB::connection()->getConfig('collation') ?: 'utf8mb4_unicode_ci';
        $text = " CHARACTER SET {$charset} COLLATE {$collation}";

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_tambah_jurusan');
        DB::unprepared("CREATE PROCEDURE sp_tambah_jurusan(
            IN p_kode TEXT{$text}, IN p_nama TEXT{$text}, IN p_bidang TEXT{$text},
            IN p_deskripsi TEXT{$text}, IN p_aktif INT
        )
        BEGIN
            DECLARE EXIT HANDLER FOR 1062
            BEGIN
                ROLLBACK;
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'KODE_JURUSAN_SUDAH_ADA';
            END;
            DECLARE EXIT HANDLER FOR SQLEXCEPTION
            BEGIN
                ROLLBACK;
                RESIGNAL;
            END;

            START TRANSACTION;
                SET p_kode = UPPER(p_kode);
                SET p_nama = TRIM(p_nama);
                IF p_kode IS NULL OR p_kode = '' OR CHAR_LENGTH(p_kode) > 20
                    OR REGEXP_LIKE(p_kode, '[[:space:]]') THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'KODE_JURUSAN_TIDAK_VALID';
                END IF;
                IF p_nama IS NULL OR p_nama = '' OR CHAR_LENGTH(p_nama) > 255 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NAMA_JURUSAN_TIDAK_VALID';
                END IF;
                IF CHAR_LENGTH(p_bidang) > 255 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'BIDANG_JURUSAN_TIDAK_VALID';
                END IF;
                IF p_aktif IS NULL OR p_aktif NOT IN (0, 1) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AKTIF_JURUSAN_TIDAK_VALID';
                END IF;
                IF EXISTS (SELECT 1 FROM jurusan WHERE kode = p_kode) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'KODE_JURUSAN_SUDAH_ADA';
                END IF;
                INSERT INTO jurusan (kode, nama, bidang, deskripsi, aktif, created_at, updated_at)
                VALUES (p_kode, p_nama, NULLIF(TRIM(p_bidang), ''), NULLIF(p_deskripsi, ''), p_aktif, NOW(), NOW());
            COMMIT;
        END");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP PROCEDURE IF EXISTS sp_tambah_jurusan');
        }
    }
};
