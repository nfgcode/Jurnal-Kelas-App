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
 * It no longer estimates a roster. Student attendance is a single daily roll call
 * filed by the ketua kelas, and a day either had one or it did not — inventing one
 * from a neighbouring lesson would fabricate the very record the daily rule exists
 * to make trustworthy. A day with no roster reads as "belum diisi", which is true.
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
                // materi is NOT NULL; a clear placeholder also tells a guru who
                // later opens it that this was a system fill to be completed. The
                // "Otomatis" status chip comes from the peran, not this text.
                'materi' => 'Diisi otomatis oleh sistem — mohon lengkapi bila perlu.',
                'kehadiran_guru_status' => 'tidak_hadir',
                'kehadiran_guru_ada_tugas' => false,
                'guru_id' => $jadwal->guru_id,
                'diisi_oleh_id' => null,
                'diisi_oleh_peran' => Jurnal::PERAN_SISTEM,
            ]);
        });
    }
}
