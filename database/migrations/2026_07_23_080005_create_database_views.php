<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reporting views (MySQL-only). Endpoints that read them fall back to an
 * equivalent query builder on SQLite.
 *
 *  - v_jurnal_lengkap:       one flattened row per journal (schedule, class,
 *                            subject, teacher joined in) for read-heavy listing.
 *  - v_rekap_presensi_kelas: attendance totals per class (hadir/sakit/izin/alpa).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('CREATE OR REPLACE VIEW v_jurnal_lengkap AS
            SELECT
                j.id,
                j.tanggal,
                j.materi,
                j.tugas,
                j.catatan,
                j.kehadiran_guru_status,
                j.guru_id,
                g.name              AS guru_nama,
                jd.id               AS jadwal_id,
                jd.hari,
                jd.jam_ke_mulai,
                jd.jam_ke_selesai,
                k.id                AS kelas_id,
                k.nama_kelas,
                mp.id               AS mata_pelajaran_id,
                mp.nama             AS mata_pelajaran
            FROM jurnal j
            JOIN jadwal jd          ON j.jadwal_id = jd.id
            JOIN kelas k            ON jd.kelas_id = k.id
            JOIN mata_pelajaran mp  ON jd.mata_pelajaran_id = mp.id
            JOIN users g            ON j.guru_id = g.id');

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

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS v_jurnal_lengkap');
        DB::statement('DROP VIEW IF EXISTS v_rekap_presensi_kelas');
    }
};
