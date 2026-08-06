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
 * meeting that ended without one, and estimate its roster from another lesson the
 * same class had that day. Dispatched in staggered waves by {@see IsiJurnalOtomatis}
 * so a large backlog never lands on the database all at once.
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
            $jurnal = Jurnal::create([
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

            $this->salinRoster($jurnal, $jadwal);
        });
    }

    /**
     * Copy the roster from another meeting the same class had the same day, if any
     * — the estimate this feature rests on. Left empty when there is no source
     * (never guess a whole class absent).
     */
    private function salinRoster(Jurnal $jurnal, Jadwal $jadwal): void
    {
        $sumber = Jurnal::query()
            ->where('jadwal_id', '!=', $jadwal->id)
            ->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $jadwal->kelas_id))
            ->whereDate('tanggal', $jurnal->tanggal)
            ->whereHas('presensis')
            ->with('presensis:id,jurnal_id,siswa_id,status,keterangan')
            ->first();

        if (! $sumber) {
            return;
        }

        $now = now();
        $rows = $sumber->presensis->map(fn ($p) => [
            'jurnal_id' => $jurnal->id,
            'siswa_id' => $p->siswa_id,
            'status' => $p->status,
            'keterangan' => $p->keterangan,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            DB::table('presensi')->insert($rows);
        }
    }
}
