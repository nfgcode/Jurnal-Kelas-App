<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * sp_simpan_presensi (MySQL-only): replace a journal's whole attendance set in
 * one transaction. The roster arrives as a JSON array; JSON_TABLE expands it
 * into rows. An EXIT HANDLER rolls the transaction back on any error, so a
 * partial roster is never persisted. The API attendance-save calls this on
 * MySQL and uses a Laravel transaction as the fallback elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_simpan_presensi');
        DB::unprepared("
            CREATE PROCEDURE sp_simpan_presensi(IN p_jurnal_id INT, IN p_data JSON)
            BEGIN
                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    RESIGNAL;
                END;

                START TRANSACTION;
                    DELETE FROM presensi WHERE jurnal_id = p_jurnal_id;

                    INSERT INTO presensi (jurnal_id, siswa_id, status, keterangan, created_at, updated_at)
                    SELECT p_jurnal_id, jt.siswa_id, jt.status, jt.keterangan, NOW(), NOW()
                    FROM JSON_TABLE(p_data, '$[*]' COLUMNS (
                        siswa_id   INT          PATH '$.siswa_id',
                        status     VARCHAR(10)  PATH '$.status',
                        keterangan TEXT         PATH '$.keterangan'
                    )) AS jt;
                COMMIT;
            END
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_simpan_presensi');
    }
};
