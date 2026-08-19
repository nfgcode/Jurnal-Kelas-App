<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carry the existing per-meeting attendance over into the daily table, so the
 * dashboards and reports that now read `presensi_harian` still show the school's
 * history instead of starting from an empty chart.
 *
 * A class-day may hold several old rosters (one per lesson). Only one can
 * survive, and the earliest lesson of the day is the honest choice: it is the
 * roll call taken closest to the start of school, before anyone had left. Rows
 * are streamed in that order and inserted with "ignore duplicates", so the
 * unique index (kelas, tanggal, siswa) keeps the first one it sees and silently
 * drops the later lessons' repeats.
 */
return new class extends Migration
{
    /** Rows per INSERT — small enough for MySQL's max_allowed_packet. */
    private const BATCH = 500;

    public function up(): void
    {
        $sumber = DB::table('presensi')
            ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->select([
                'jadwal.kelas_id',
                'jurnal.tanggal',
                'presensi.siswa_id',
                'presensi.status',
                'presensi.keterangan',
                'presensi.created_at',
                'presensi.updated_at',
            ])
            // The order is the rule: earliest lesson of the day wins the day.
            ->orderBy('jadwal.kelas_id')
            ->orderBy('jurnal.tanggal')
            ->orderBy('presensi.siswa_id')
            ->orderBy('jadwal.jam_ke_mulai')
            ->orderBy('presensi.id');

        $batch = [];

        foreach ($sumber->cursor() as $row) {
            $batch[] = [
                'kelas_id' => $row->kelas_id,
                // MySQL stores a DATE; SQLite keeps "Y-m-d H:i:s". Normalising
                // here is what makes the unique index collapse a class's day.
                'tanggal' => substr((string) $row->tanggal, 0, 10),
                'siswa_id' => $row->siswa_id,
                'status' => $row->status,
                'keterangan' => $row->keterangan,
                // Nobody alive filed these; they are carried over, not authored.
                'diisi_oleh_id' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];

            if (count($batch) >= self::BATCH) {
                DB::table('presensi_harian')->insertOrIgnore($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('presensi_harian')->insertOrIgnore($batch);
        }
    }

    /**
     * The source rows were never deleted, so undoing this is just emptying the
     * derived table.
     */
    public function down(): void
    {
        DB::table('presensi_harian')->delete();
    }
};
