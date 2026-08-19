<?php

namespace App\Support;

use App\Models\Kelas;
use App\Models\PresensiHarian;
use App\Models\PresensiHarianLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one save path for a class's daily attendance, shared by the web form and
 * the API: replace the whole roster for (kelas, tanggal) atomically, so a
 * re-submitted form is idempotent and never leaves half a class marked.
 */
class SimpanPresensiHarian
{
    /**
     * On MySQL the replace runs inside sp_simpan_presensi_harian (one
     * transaction, rolled back by its handler on any error); elsewhere a Laravel
     * transaction gives the same all-or-nothing guarantee. The unique index
     * (kelas_id, tanggal, siswa_id) is the final guard against two people saving
     * the same day at once, either way.
     *
     * One presensi_harian_log row records who saved it and how many students —
     * flagged `koreksi` when the day already had a roster, which is the
     * distinction the admin audit screen is actually there to show.
     *
     * @param  array<int, array{siswa_id: int|string, status: string, keterangan?: ?string}>  $rows
     */
    public static function simpan(Kelas $kelas, string $tanggal, array $rows, ?User $pengisi = null): void
    {
        // Read before the replace: afterwards the day always has a roster, so
        // asking then would label every save a correction.
        $koreksi = PresensiHarian::sudahDiisi($kelas->id, $tanggal);

        $bersih = array_map(fn ($data) => [
            'siswa_id' => (int) $data['siswa_id'],
            'status' => $data['status'],
            'keterangan' => $data['keterangan'] ?? null,
        ], $rows);

        if (DbDriver::mysql()) {
            DB::statement('CALL sp_simpan_presensi_harian(?, ?, ?, ?)', [
                $kelas->id,
                $tanggal,
                $pengisi?->id,
                json_encode(array_values($bersih)),
            ]);
        } else {
            DB::transaction(function () use ($kelas, $tanggal, $bersih, $pengisi) {
                PresensiHarian::where('kelas_id', $kelas->id)
                    ->whereDate('tanggal', $tanggal)
                    ->delete();

                $now = now();
                PresensiHarian::insert(array_map(fn ($data) => $data + [
                    'kelas_id' => $kelas->id,
                    'tanggal' => $tanggal,
                    'diisi_oleh_id' => $pengisi?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $bersih));
            });
        }

        PresensiHarianLog::create([
            'kelas_id' => $kelas->id,
            'tanggal' => $tanggal,
            'diedit_oleh_id' => $pengisi?->id,
            'jumlah_siswa' => count($bersih),
            'koreksi' => $koreksi,
            'created_at' => now(),
        ]);
    }
}
