<?php

namespace App\Support;

use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\PresensiHarian;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one save path for a meeting's attendance, shared by the web form and the
 * API.
 *
 * Two things happen on every save, in one transaction:
 *
 *  1. The meeting's own roster is replaced ({@see Presensi}) — the record the
 *     guru actually marked, per lesson and therefore per subject.
 *  2. The class's day-level record is rebuilt from every lesson of that day
 *     ({@see PresensiHarian}), so the recaps, exports, dashboards
 *     and stored functions that count school days keep reading one row per
 *     student per day and never have to know lessons exist.
 *
 * (2) is a projection, not a second source of truth: it is derived from (1)
 * every time and never written by hand. That is what lets attendance differ
 * between subjects without the monthly recap counting the same student six
 * times for a six-lesson day.
 */
class SimpanPresensiJurnal
{
    /**
     * How the day's status is chosen when a student was marked differently in
     * different lessons: the most consequential mark of the day wins.
     *
     * A student who skipped one of six lessons was not simply "hadir" that day,
     * and the school's own attendance book is the place that has to say so. The
     * per-lesson rows keep the full picture for anyone who needs it.
     */
    private const BOBOT = ['hadir' => 0, 'sakit' => 1, 'izin' => 2, 'alpa' => 3];

    /**
     * Replace one meeting's roster, then re-derive the class's day.
     *
     * Idempotent: re-submitting the form overwrites the meeting rather than
     * adding a second roster beside it, which the unique index
     * (jurnal_id, siswa_nis) also enforces underneath.
     *
     * @param  array<int, array{siswa_nis: int|string, status: string, keterangan?: ?string}>  $rows
     */
    public static function simpan(Jurnal $jurnal, array $rows, ?User $pengisi = null): void
    {
        $bersih = array_map(fn ($data) => [
            'siswa_nis' => (int) $data['siswa_nis'],
            'status' => $data['status'],
            'keterangan' => $data['keterangan'] ?? null,
        ], $rows);

        DB::transaction(function () use ($jurnal, $bersih, $pengisi) {
            Presensi::where('jurnal_id', $jurnal->id)->delete();

            $now = now();
            Presensi::insert(array_map(fn ($data) => $data + [
                'jurnal_id' => $jurnal->id,
                'diisi_oleh_id' => $pengisi?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_values($bersih)));
        });

        self::segarkanHarian($jurnal, $pengisi);
    }

    /**
     * Rebuild the class's daily record for the journal's date from every lesson
     * that date holds.
     *
     * Called after a save, and after a journal is deleted — a removed meeting
     * must stop contributing its marks to the day.
     */
    public static function segarkanHarian(Jurnal $jurnal, ?User $pengisi = null): void
    {
        $kelas = $jurnal->jadwal?->kelas;

        if (! $kelas instanceof Kelas) {
            return;
        }

        $tanggal = $jurnal->tanggal->toDateString();
        $harian = self::gabungkanHari($kelas->id, $tanggal);

        if ($harian === []) {
            // The day's last roster was just removed. Clearing the projection
            // keeps it honest — leaving the old rows would report attendance for
            // lessons that no longer have any.
            PresensiHarian::where('kelas_id', $kelas->id)
                ->whereDate('tanggal', $tanggal)
                ->delete();

            return;
        }

        SimpanPresensiHarian::simpan($kelas, $tanggal, $harian, $pengisi);
    }

    /**
     * Every mark recorded for a class on a date, folded to one row per student.
     *
     * @return array<int, array{siswa_nis: int, status: string, keterangan: ?string}>
     */
    private static function gabungkanHari(int $kelasId, string $tanggal): array
    {
        $baris = Presensi::query()
            ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->where('jadwal.kelas_id', $kelasId)
            ->whereDate('jurnal.tanggal', $tanggal)
            ->select(['presensi.siswa_nis', 'presensi.status', 'presensi.keterangan'])
            ->get();

        $hasil = [];

        foreach ($baris as $row) {
            $nis = (int) $row->siswa_nis;
            $bobot = self::BOBOT[$row->status] ?? 0;

            // The heaviest mark of the day wins, and carries its own note with
            // it — a "sakit" note must never end up attached to an "alpa".
            if (isset($hasil[$nis]) && $hasil[$nis]['bobot'] >= $bobot) {
                continue;
            }

            $hasil[$nis] = [
                'bobot' => $bobot,
                'siswa_nis' => $nis,
                'status' => $row->status,
                'keterangan' => $row->keterangan,
            ];
        }

        return array_values(array_map(
            fn ($row) => ['siswa_nis' => $row['siswa_nis'], 'status' => $row['status'], 'keterangan' => $row['keterangan']],
            $hasil
        ));
    }
}
