<?php

namespace App\Console\Commands;

use App\Jobs\IsiJurnalGelombang;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Support\Ringkasan;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Nightly: find meetings that ended without a journal and hand them to
 * {@see IsiJurnalGelombang} in staggered waves, so a backlog is filled without a
 * spike on the database. Past days only — a lesson still today or in the future
 * may yet be filled by its own teacher.
 */
class IsiJurnalOtomatis extends Command
{
    protected $signature = 'jurnal:isi-otomatis
        {--lookback=14 : Berapa hari ke belakang yang dicari}
        {--ukuran-gelombang=200 : Pertemuan per gelombang}
        {--jeda=2 : Jeda antar gelombang (menit)}
        {--sekarang : Proses langsung tanpa antrean (uji / jalan manual)}';

    protected $description = 'Isi otomatis jurnal pertemuan lampau yang kosong (guru tidak hadir · tanpa tugas) + salin presensinya, per gelombang.';

    public function handle(): int
    {
        $lookback = max(1, (int) $this->option('lookback'));
        $ukuran = max(1, (int) $this->option('ukuran-gelombang'));
        $jeda = max(0, (int) $this->option('jeda'));

        $mulai = Carbon::today()->subDays($lookback);
        $selesai = Carbon::today()->subDay(); // yesterday: only fully-past days

        $pertemuan = $this->pertemuanKosong($mulai, $selesai);

        if ($pertemuan === []) {
            $this->info('Tidak ada jurnal kosong untuk diisi.');

            return self::SUCCESS;
        }

        $gelombang = array_chunk($pertemuan, $ukuran);

        foreach ($gelombang as $i => $batch) {
            if ($this->option('sekarang')) {
                (new IsiJurnalGelombang($batch))->handle();
            } else {
                IsiJurnalGelombang::dispatch($batch)->delay(now()->addMinutes($i * $jeda));
            }
        }

        $this->info(sprintf(
            '%d pertemuan kosong %s dalam %d gelombang (%d/gelombang, jeda %d menit).',
            count($pertemuan),
            $this->option('sekarang') ? 'diproses' : 'dijadwalkan',
            count($gelombang),
            $ukuran,
            $jeda,
        ));

        return self::SUCCESS;
    }

    /**
     * Every (jadwal, tanggal) in range that falls on a school day and has no
     * journal yet — the gaps the backfill fills.
     *
     * @return array<int, array{jadwal_id: int, tanggal: string}>
     */
    private function pertemuanKosong(Carbon $mulai, Carbon $selesai): array
    {
        $jadwalPerHari = Jadwal::get(['id', 'hari'])->groupBy('hari');

        // The (jadwal, date) pairs already journaled in the window, as a fast set.
        $terisi = Jurnal::whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->get(['jadwal_id', 'tanggal'])
            ->mapWithKeys(fn ($j) => [$j->jadwal_id.'|'.$j->tanggal->toDateString() => true]);

        $kosong = [];

        for ($d = $mulai->copy(); $d->lte($selesai); $d->addDay()) {
            $hari = Ringkasan::HARI[$d->dayOfWeekIso - 1] ?? null;

            if ($hari === null) {
                continue;
            }

            foreach ($jadwalPerHari[$hari] ?? [] as $jadwal) {
                if (! isset($terisi[$jadwal->id.'|'.$d->toDateString()])) {
                    $kosong[] = ['jadwal_id' => $jadwal->id, 'tanggal' => $d->toDateString()];
                }
            }
        }

        return $kosong;
    }
}
