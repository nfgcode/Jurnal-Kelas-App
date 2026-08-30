<?php

namespace App\Jobs;

use App\Console\Commands\IsiJurnalOtomatis;
use App\Models\Jadwal;
use App\Models\Jurnal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * One wave of the nightly journal backfill: create a system journal for each
 * meeting that ended without one. Dispatched in staggered waves by
 * {@see IsiJurnalOtomatis} so a large backlog never lands on the database at once.
 *
 * It never estimates a roster. Attendance is what the lesson's teacher marked,
 * and a meeting either has it or it does not — inventing one from a neighbouring
 * lesson would fabricate the very record the roster exists to make trustworthy.
 * A meeting with no roster reads as "belum ditandai", which is true.
 */
class IsiJurnalGelombang implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{jadwal_id: int, tanggal: string}>  $pertemuan
     */
    public function __construct(public array $pertemuan) {}

    public function handle(): void
    {
        foreach ($this->pertemuan as $item) {
            $this->prosesSatu((int) $item['jadwal_id'], $item['tanggal']);
        }
    }

    private function prosesSatu(int $jadwalId, string $tanggal): void
    {
        // Idempotent, and safe against a teacher having filled it since the wave
        // was queued: only backfill a meeting that still has no journal at all.
        if (Jurnal::where('jadwal_id', $jadwalId)->whereDate('tanggal', $tanggal)->exists()) {
            return;
        }

        $jadwal = Jadwal::find($jadwalId);

        if (! $jadwal) {
            return;
        }

        DB::transaction(function () use ($jadwal, $tanggal) {
            Jurnal::create([
                'jadwal_id' => $jadwal->id,
                'tanggal' => $tanggal,
                'materi' => Jurnal::MATERI_PLACEHOLDER,
                'kehadiran_guru_status' => 'tidak_hadir',
                'kehadiran_guru_ada_tugas' => false,
                'diisi_oleh_id' => null,
                'diisi_oleh_peran' => Jurnal::PERAN_SISTEM,
            ]);
        });
    }
}
