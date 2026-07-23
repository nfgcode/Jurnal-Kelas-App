<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stored functions returning an attendance percentage (MySQL-only). Declared
 * READS SQL DATA so they create cleanly with binary logging enabled. Endpoints
 * that call them fall back to an equivalent aggregate on SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP FUNCTION IF EXISTS fn_persentase_kehadiran_siswa');
        DB::unprepared("
            CREATE FUNCTION fn_persentase_kehadiran_siswa(p_siswa_id INT)
                RETURNS DECIMAL(5,2)
                READS SQL DATA
            BEGIN
                DECLARE v_total INT;
                DECLARE v_hadir INT;
                SELECT COUNT(*), SUM(status = 'hadir') INTO v_total, v_hadir
                    FROM presensi WHERE siswa_id = p_siswa_id;
                IF v_total IS NULL OR v_total = 0 THEN
                    RETURN 0;
                END IF;
                RETURN ROUND(v_hadir / v_total * 100, 2);
            END
        ");

        DB::unprepared('DROP FUNCTION IF EXISTS fn_persentase_kehadiran_kelas');
        DB::unprepared("
            CREATE FUNCTION fn_persentase_kehadiran_kelas(p_kelas_id INT)
                RETURNS DECIMAL(5,2)
                READS SQL DATA
            BEGIN
                DECLARE v_total INT;
                DECLARE v_hadir INT;
                SELECT COUNT(*), SUM(p.status = 'hadir') INTO v_total, v_hadir
                    FROM presensi p
                    JOIN jurnal j  ON p.jurnal_id = j.id
                    JOIN jadwal jd ON j.jadwal_id = jd.id
                    WHERE jd.kelas_id = p_kelas_id;
                IF v_total IS NULL OR v_total = 0 THEN
                    RETURN 0;
                END IF;
                RETURN ROUND(v_hadir / v_total * 100, 2);
            END
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP FUNCTION IF EXISTS fn_persentase_kehadiran_siswa');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_persentase_kehadiran_kelas');
    }
};
