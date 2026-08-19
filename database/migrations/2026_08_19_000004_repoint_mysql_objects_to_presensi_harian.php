<?php

use App\Support\Ringkasan;
use App\Support\SimpanPresensiHarian;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The MySQL-side objects still described attendance as a per-journal fact.
 * Now that a roster is a class-day, they are redefined against presensi_harian
 * — otherwise the stored functions and the recap view would keep answering from
 * a table the application no longer writes to, and the API would disagree with
 * every screen.
 *
 * Also adds sp_simpan_presensi_harian, the daily counterpart of
 * sp_simpan_presensi: replace a class-day's whole roster in one transaction.
 * Everything here is MySQL-only; SQLite callers use the portable fallbacks in
 * {@see Ringkasan} and {@see SimpanPresensiHarian}.
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
                    FROM presensi_harian WHERE siswa_id = p_siswa_id;
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
                SELECT COUNT(*), SUM(status = 'hadir') INTO v_total, v_hadir
                    FROM presensi_harian WHERE kelas_id = p_kelas_id;
                IF v_total IS NULL OR v_total = 0 THEN
                    RETURN 0;
                END IF;
                RETURN ROUND(v_hadir / v_total * 100, 2);
            END
        ");

        DB::statement("CREATE OR REPLACE VIEW v_rekap_presensi_kelas AS
            SELECT
                k.id            AS kelas_id,
                k.nama_kelas,
                SUM(ph.status = 'hadir') AS hadir,
                SUM(ph.status = 'sakit') AS sakit,
                SUM(ph.status = 'izin')  AS izin,
                SUM(ph.status = 'alpa')  AS alpa,
                COUNT(*)                 AS total
            FROM presensi_harian ph
            JOIN kelas k ON ph.kelas_id = k.id
            GROUP BY k.id, k.nama_kelas");

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_simpan_presensi_harian');
        DB::unprepared("
            CREATE PROCEDURE sp_simpan_presensi_harian(
                IN p_kelas_id INT, IN p_tanggal DATE, IN p_pengisi INT, IN p_data JSON
            )
            BEGIN
                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    RESIGNAL;
                END;

                START TRANSACTION;
                    DELETE FROM presensi_harian
                        WHERE kelas_id = p_kelas_id AND tanggal = p_tanggal;

                    INSERT INTO presensi_harian
                        (kelas_id, tanggal, siswa_id, status, keterangan, diisi_oleh_id, created_at, updated_at)
                    SELECT p_kelas_id, p_tanggal, jt.siswa_id, jt.status, jt.keterangan, p_pengisi, NOW(), NOW()
                    FROM JSON_TABLE(p_data, '$[*]' COLUMNS (
                        siswa_id   INT          PATH '$.siswa_id',
                        status     VARCHAR(10)  PATH '$.status',
                        keterangan TEXT         PATH '$.keterangan'
                    )) AS jt;
                COMMIT;
            END
        ");
    }

    /**
     * Roll the two functions and the view back to their per-journal definitions
     * and drop the new procedure, so a rollback leaves a coherent database
     * rather than objects pointing at a table the previous migration removes.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_simpan_presensi_harian');

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

        DB::statement("CREATE OR REPLACE VIEW v_rekap_presensi_kelas AS
            SELECT
                k.id            AS kelas_id,
                k.nama_kelas,
                SUM(p.status = 'hadir') AS hadir,
                SUM(p.status = 'sakit') AS sakit,
                SUM(p.status = 'izin')  AS izin,
                SUM(p.status = 'alpa')  AS alpa,
                COUNT(*)                AS total
            FROM presensi p
            JOIN jurnal j   ON p.jurnal_id = j.id
            JOIN jadwal jd  ON j.jadwal_id = jd.id
            JOIN kelas k    ON jd.kelas_id = k.id
            GROUP BY k.id, k.nama_kelas");
    }
};
