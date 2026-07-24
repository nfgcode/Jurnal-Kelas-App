<?php

namespace App\Support;

use App\Models\Jurnal;
use App\Models\Presensi;
use Illuminate\Support\Facades\DB;

/**
 * The one roster-save path shared by the web form and the API: replace the
 * whole attendance set for a meeting, atomically, so a re-submitted form is
 * idempotent.
 */
class SimpanPresensi
{
    /**
     * On MySQL the whole replace runs inside sp_simpan_presensi (one
     * transaction, rolled back by its handler on any error); elsewhere a
     * Laravel transaction plus a lock on the journal row gives the same
     * all-or-nothing guarantee and serialises two people marking the same
     * roster at once. The unique index on (jurnal_id, siswa_id) is the final
     * guard against races either way.
     *
     * @param  array<int, array{siswa_id: int|string, status: string, keterangan?: ?string}>  $rows
     */
    public static function simpan(Jurnal $jurnal, array $rows): void
    {
        if (DbDriver::mysql()) {
            DB::statement('CALL sp_simpan_presensi(?, ?)', [
                $jurnal->id,
                json_encode(array_map(fn ($data) => [
                    'siswa_id' => (int) $data['siswa_id'],
                    'status' => $data['status'],
                    'keterangan' => $data['keterangan'] ?? null,
                ], $rows)),
            ]);

            return;
        }

        DB::transaction(function () use ($jurnal, $rows) {
            Jurnal::whereKey($jurnal->id)->lockForUpdate()->first();

            Presensi::where('jurnal_id', $jurnal->id)->delete();

            $now = now();
            Presensi::insert(array_map(fn ($data) => [
                'jurnal_id' => $jurnal->id,
                'siswa_id' => $data['siswa_id'],
                'status' => $data['status'],
                'keterangan' => $data['keterangan'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $rows));
        });
    }
}
