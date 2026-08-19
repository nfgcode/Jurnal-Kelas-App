<?php

namespace App\Support;

use App\Models\PresensiHarian;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The three shapes every daily-attendance screen and export asks the database
 * for, written once so the table on screen and the workbook a teacher downloads
 * can never disagree:
 *
 *  - {@see perKelasHari()}  one row per class-day  (the recap table)
 *  - {@see perSiswa()}      one row per student    (the monthly recap)
 *  - {@see matriksHarian()} student × date grid    (the monthly detail sheet)
 *
 * All three are grouped aggregates over presensi_harian rather than hydrated
 * models: a month of a whole school is tens of thousands of rows, and none of
 * these screens needs an object per student per day.
 */
class RekapPresensi
{
    /**
     * One row per class-day: the class, the date, and the H/S/I/A split.
     *
     * @param  array<int>|null  $kelasIds  null means every class (admin)
     */
    public static function perKelasHari(?array $kelasIds, string $mulai, string $selesai): Builder
    {
        return DB::table('presensi_harian')
            ->join('kelas', 'presensi_harian.kelas_id', '=', 'kelas.id')
            ->selectRaw(
                'presensi_harian.kelas_id, kelas.nama_kelas, kelas.tingkat, kelas.jurusan, presensi_harian.tanggal, '
                .'COUNT(*) as total_siswa, '
                .self::hitung('hadir').', '
                .self::hitung('sakit').', '
                .self::hitung('izin').', '
                .self::hitung('alpa')
            )
            ->whereBetween('presensi_harian.tanggal', [$mulai, $selesai])
            ->when($kelasIds !== null, fn ($q) => $q->whereIn('presensi_harian.kelas_id', $kelasIds))
            ->groupBy(
                'presensi_harian.kelas_id',
                'kelas.nama_kelas',
                'kelas.tingkat',
                'kelas.jurusan',
                'presensi_harian.tanggal'
            );
    }

    /**
     * One row per student over the window: their name, class, and how many days
     * they were hadir/sakit/izin/alpa. The recap a monthly export is built from.
     *
     * @param  array<int>|null  $kelasIds
     */
    public static function perSiswa(?array $kelasIds, string $mulai, string $selesai): Builder
    {
        return DB::table('presensi_harian')
            ->join('users', 'presensi_harian.siswa_id', '=', 'users.id')
            ->join('kelas', 'presensi_harian.kelas_id', '=', 'kelas.id')
            ->selectRaw(
                'users.id as siswa_id, users.name as nama, users.nis, kelas.nama_kelas, '
                .'COUNT(*) as total_hari, '
                .self::hitung('hadir').', '
                .self::hitung('sakit').', '
                .self::hitung('izin').', '
                .self::hitung('alpa')
            )
            ->whereBetween('presensi_harian.tanggal', [$mulai, $selesai])
            ->when($kelasIds !== null, fn ($q) => $q->whereIn('presensi_harian.kelas_id', $kelasIds))
            ->groupBy('users.id', 'users.name', 'users.nis', 'kelas.nama_kelas')
            ->orderBy('kelas.nama_kelas')
            ->orderBy('users.name');
    }

    /**
     * The student × date grid behind the monthly detail sheet: each student's
     * status on each day they have a record for.
     *
     * @param  array<int>|null  $kelasIds
     * @return array<int, array<string, string>> siswa_id => [Y-m-d => status]
     */
    public static function matriksHarian(?array $kelasIds, string $mulai, string $selesai): array
    {
        $rows = PresensiHarian::query()
            ->select('siswa_id', 'tanggal', 'status')
            ->dalamPeriode($mulai, $selesai)
            ->when($kelasIds !== null, fn ($q) => $q->whereIn('kelas_id', $kelasIds))
            ->get();

        $matriks = [];

        foreach ($rows as $row) {
            $matriks[$row->siswa_id][$row->tanggal->toDateString()] = $row->status;
        }

        return $matriks;
    }

    /**
     * The single letter a status shows as in the monthly grid — H/S/I/A, the
     * notation Indonesian attendance books already use.
     */
    public static function huruf(?string $status): string
    {
        return match ($status) {
            'hadir' => 'H',
            'sakit' => 'S',
            'izin' => 'I',
            'alpa' => 'A',
            default => '',
        };
    }

    /**
     * A `SUM(status = 'x') AS x` count that both MySQL and SQLite accept. Written
     * as CASE rather than the shorter boolean sum because only MySQL treats a
     * comparison as 1/0 in every context.
     */
    private static function hitung(string $status): string
    {
        return "SUM(CASE WHEN presensi_harian.status = '{$status}' THEN 1 ELSE 0 END) as {$status}";
    }
}
