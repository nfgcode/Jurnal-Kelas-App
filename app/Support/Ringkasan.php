<?php

namespace App\Support;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The handful of derived figures the dashboard and recap screens keep asking
 * for: daily counts behind a sparkline, an attendance rollup, and the journal
 * completeness grid.
 *
 * Every aggregate accepts an optional {@see Periode}. When one is given the
 * query is confined to that range; when it is omitted the figure spans all
 * time (or, for the school-day windows, the trailing default) exactly as it
 * did before periods existed.
 */
class Ringkasan
{
    /** Weekday names as stored in the jadwal table, Monday first. */
    public const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /** School days a windowless completeness/heatmap query falls back to. */
    private const HARI_DEFAULT = 20;

    /** Above this many days the fill chart aggregates weekly, not daily. */
    private const AMBANG_MINGGUAN = 31;

    /**
     * Daily fill counts across the period, zero-filled so the chart keeps a
     * stable shape on quiet days. Each row carries a machine key (Y-m-d, the
     * date a bar drills into), a short display label, and the count.
     *
     * Keying by the full date rather than day-of-month matters once a range
     * spans two months: otherwise the 5th of June and the 5th of July would
     * collapse onto the same bucket and silently overwrite each other.
     *
     * @return array<int, array{key: string, label: string, value: int}>
     */
    public static function harian(Builder $query, ?Periode $periode = null, string $column = 'tanggal'): array
    {
        $periode ??= Periode::hariTerakhir(14);
        $mulai = $periode->mulai->copy()->startOfDay();
        $selesai = $periode->selesai->copy()->startOfDay();

        $tercatat = $query
            ->selectRaw("DATE({$column}) as hari, COUNT(*) as total")
            ->whereBetween($column, [$mulai->toDateString(), $selesai->toDateString()])
            ->groupBy('hari')
            ->pluck('total', 'hari');

        // A long range would render as hundreds of hair-thin bars, so past a
        // month's worth of days the series is rolled up per ISO week instead.
        if ($periode->jumlahHari() > self::AMBANG_MINGGUAN) {
            return self::mingguan($tercatat, $mulai, $selesai);
        }

        $lintasBulan = $periode->lintasBulan();
        $series = [];

        for ($tanggal = $mulai->copy(); $tanggal->lte($selesai); $tanggal->addDay()) {
            $series[] = [
                'key' => $tanggal->toDateString(),
                'label' => $tanggal->translatedFormat($lintasBulan ? 'j/n' : 'j'),
                'value' => (int) ($tercatat[$tanggal->toDateString()] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Weekly rollup of a daily count map, one row per ISO week the range
     * touches. The key is the week's Monday, which the drill-down reads back.
     *
     * @param  Collection<string, int>  $tercatat  counts keyed Y-m-d
     * @return array<int, array{key: string, label: string, value: int}>
     */
    private static function mingguan(Collection $tercatat, Carbon $mulai, Carbon $selesai): array
    {
        $series = [];
        $awalPekan = $mulai->copy()->startOfWeek(Carbon::MONDAY);

        while ($awalPekan->lte($selesai)) {
            $akhirPekan = $awalPekan->copy()->endOfWeek(Carbon::SUNDAY);
            $total = 0;

            for ($hari = $awalPekan->copy(); $hari->lte($akhirPekan) && $hari->lte($selesai); $hari->addDay()) {
                if ($hari->lt($mulai)) {
                    continue;
                }

                $total += (int) ($tercatat[$hari->toDateString()] ?? 0);
            }

            $series[] = [
                'key' => $awalPekan->toDateString(),
                'label' => $awalPekan->translatedFormat('j/n'),
                'value' => $total,
            ];

            $awalPekan->addWeek();
        }

        return $series;
    }

    /**
     * Attendance totals keyed hadir/sakit/izin/alpa, always all four present.
     *
     * @return array<string, int>
     */
    public static function presensi(?Builder $query = null, ?Periode $periode = null): array
    {
        $query ??= Presensi::query();

        if ($periode) {
            $query->whereHas('jurnal', fn ($jurnal) => $jurnal->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()]));
        }

        $rows = $query
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'hadir' => (int) ($rows['hadir'] ?? 0),
            'sakit' => (int) ($rows['sakit'] ?? 0),
            'izin' => (int) ($rows['izin'] ?? 0),
            'alpa' => (int) ($rows['alpa'] ?? 0),
        ];
    }

    /**
     * A student's attendance percentage — the fn_persentase_kehadiran_siswa
     * stored function on MySQL, the equivalent aggregate elsewhere.
     */
    public static function persentaseKehadiranSiswa(int $siswaId): float
    {
        if (DbDriver::mysql()) {
            return (float) DB::selectOne('SELECT fn_persentase_kehadiran_siswa(?) AS p', [$siswaId])->p;
        }

        $r = Presensi::where('siswa_id', $siswaId)
            ->selectRaw("COUNT(*) t, SUM(status = 'hadir') h")
            ->first();

        return $r->t ? round($r->h / $r->t * 100, 2) : 0.0;
    }

    /**
     * A class's attendance percentage across every meeting — the
     * fn_persentase_kehadiran_kelas stored function on MySQL, the equivalent
     * aggregate elsewhere.
     */
    public static function persentaseKehadiranKelas(int $kelasId): float
    {
        if (DbDriver::mysql()) {
            return (float) DB::selectOne('SELECT fn_persentase_kehadiran_kelas(?) AS p', [$kelasId])->p;
        }

        $r = Presensi::query()
            ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->where('jadwal.kelas_id', $kelasId)
            ->selectRaw("COUNT(*) t, SUM(presensi.status = 'hadir') h")
            ->first();

        return $r->t ? round($r->h / $r->t * 100, 2) : 0.0;
    }

    /**
     * Teacher-attendance totals, expressed in the merged report vocabulary.
     *
     * @return array{hadir: int, ada_tugas: int, tanpa_tugas: int, total: int}
     */
    public static function kehadiranGuru(?Builder $query = null, ?Periode $periode = null): array
    {
        $query ??= Jurnal::query();

        if ($periode) {
            $query->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()]);
        }

        $rows = $query
            ->selectRaw('kehadiran_guru_status, kehadiran_guru_ada_tugas, COUNT(*) as total')
            ->groupBy('kehadiran_guru_status', 'kehadiran_guru_ada_tugas')
            ->get();

        $hasil = ['hadir' => 0, 'ada_tugas' => 0, 'tanpa_tugas' => 0, 'total' => 0];

        foreach ($rows as $row) {
            $bucket = match (true) {
                $row->kehadiran_guru_status === 'hadir' => 'hadir',
                (bool) $row->kehadiran_guru_ada_tugas => 'ada_tugas',
                default => 'tanpa_tugas',
            };

            $hasil[$bucket] += (int) $row->total;
            $hasil['total'] += (int) $row->total;
        }

        return $hasil;
    }

    /**
     * Journal completeness per class per day, as the 0–4 shades the heatmap
     * renders. The level is the share of that day's scheduled meetings that
     * actually have a journal.
     *
     * @param  Collection<int, Kelas>  $kelasList
     * @return array<string, array<string, int>>
     */
    public static function heatmapJurnal(Collection $kelasList, ?Periode $periode = null): array
    {
        if ($kelasList->isEmpty()) {
            return [];
        }

        $tanggalList = $periode ? $periode->hariSekolah() : self::hariSekolah(self::HARI_DEFAULT);

        if ($tanggalList === []) {
            return [];
        }

        $lintasBulan = $tanggalList[0]->format('Y-m') !== end($tanggalList)->format('Y-m');
        $kelasIds = $kelasList->pluck('id');

        // Scheduled meetings per class per weekday.
        $terjadwal = Jadwal::query()
            ->selectRaw('kelas_id, hari, COUNT(*) as total')
            ->whereIn('kelas_id', $kelasIds)
            ->groupBy('kelas_id', 'hari')
            ->get()
            ->groupBy('kelas_id')
            ->map(fn ($rows) => $rows->pluck('total', 'hari'));

        // Journals actually written per class per date.
        $terisi = Jurnal::query()
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->selectRaw('jadwal.kelas_id, jurnal.tanggal, COUNT(*) as total')
            ->whereIn('jadwal.kelas_id', $kelasIds)
            ->whereIn('jurnal.tanggal', array_map(fn ($t) => $t->toDateString(), $tanggalList))
            ->groupBy('jadwal.kelas_id', 'jurnal.tanggal')
            ->get()
            ->groupBy('kelas_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [
                Carbon::parse($r->tanggal)->toDateString() => (int) $r->total,
            ]));

        $rows = [];

        foreach ($kelasList as $kelas) {
            $cells = [];

            foreach ($tanggalList as $tanggal) {
                $hari = self::HARI[$tanggal->dayOfWeekIso - 1] ?? null;
                $target = (int) ($terjadwal[$kelas->id][$hari] ?? 0);
                $aktual = (int) ($terisi[$kelas->id][$tanggal->toDateString()] ?? 0);

                $cells[self::labelTanggal($tanggal, $lintasBulan)] = $target === 0
                    ? 0
                    : min(4, (int) ceil($aktual / $target * 4));
            }

            $rows[$kelas->nama_kelas] = $cells;
        }

        return $rows;
    }

    /**
     * Attendance totals per class over the period, keyed by kelas id then by
     * status — the per-class comparison the recap chart draws.
     *
     * @return array<int, Collection<string, int>>
     */
    public static function presensiPerKelas(?Periode $periode = null): array
    {
        $query = Presensi::query()
            ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->selectRaw('jadwal.kelas_id, presensi.status, COUNT(*) as total');

        if ($periode) {
            $query->whereBetween('jurnal.tanggal', [$periode->mulaiString(), $periode->selesaiString()]);
        }

        return $query
            ->groupBy('jadwal.kelas_id', 'presensi.status')
            ->get()
            ->groupBy('kelas_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status'))
            ->all();
    }

    /**
     * Journal completeness as a percentage, grouped by a jadwal column.
     *
     * Completeness compares journals actually written against the meetings the
     * timetable scheduled over the window — a class that met twenty times and
     * logged eighteen journals sits at 90%.
     *
     * @return array<int, float> keyed by the grouping column's value
     */
    public static function kelengkapan(string $kolom = 'kelas_id', ?Periode $periode = null): array
    {
        $tanggalList = $periode ? $periode->hariSekolah() : self::hariSekolah(self::HARI_DEFAULT);

        if ($tanggalList === []) {
            return [];
        }

        $bobot = self::bobotHari($tanggalList);

        $target = [];
        $terjadwal = Jadwal::query()
            ->selectRaw("{$kolom} as kunci, hari, COUNT(*) as total")
            ->groupBy($kolom, 'hari')
            ->get();

        foreach ($terjadwal as $row) {
            $target[$row->kunci] = ($target[$row->kunci] ?? 0) + $row->total * ($bobot[$row->hari] ?? 0);
        }

        $aktual = Jurnal::query()
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->selectRaw("jadwal.{$kolom} as kunci, COUNT(*) as total")
            ->whereBetween('jurnal.tanggal', [
                $tanggalList[0]->toDateString(),
                end($tanggalList)->toDateString(),
            ])
            ->groupBy("jadwal.{$kolom}")
            ->pluck('total', 'kunci');

        $hasil = [];

        foreach ($target as $kunci => $jumlahTarget) {
            $hasil[$kunci] = self::persen((int) ($aktual[$kunci] ?? 0), $jumlahTarget);
        }

        return $hasil;
    }

    /**
     * Journal completeness for a single class, as a percentage — the same
     * measure as {@see kelengkapan()} but scoped to one class so callers that
     * only need one figure don't compute the whole school. Used by the student
     * dashboard, the journal recap, and the wali-kelas screens.
     */
    public static function kelengkapanKelas(int $kelasId, ?Periode $periode = null): float
    {
        $tanggalList = $periode ? $periode->hariSekolah() : self::hariSekolah(self::HARI_DEFAULT);

        if ($tanggalList === []) {
            return 0.0;
        }

        $bobot = self::bobotHari($tanggalList);

        $target = 0;
        $terjadwal = Jadwal::query()
            ->where('kelas_id', $kelasId)
            ->selectRaw('hari, COUNT(*) as total')
            ->groupBy('hari')
            ->pluck('total', 'hari');

        foreach ($terjadwal as $hari => $total) {
            $target += (int) $total * ($bobot[$hari] ?? 0);
        }

        $aktual = Jurnal::query()
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->where('jadwal.kelas_id', $kelasId)
            ->whereBetween('jurnal.tanggal', [
                $tanggalList[0]->toDateString(),
                end($tanggalList)->toDateString(),
            ])
            ->count();

        return self::persen($aktual, $target);
    }

    /**
     * Total meetings the timetable scheduled over the window — the honest
     * denominator behind "belum diisi", counted directly from the jadwal
     * rather than reconstructed from a rounded completeness percentage.
     */
    public static function terjadwal(?Periode $periode = null): int
    {
        $tanggalList = $periode ? $periode->hariSekolah() : self::hariSekolah(self::HARI_DEFAULT);

        if ($tanggalList === []) {
            return 0;
        }

        $bobot = self::bobotHari($tanggalList);

        $perHari = Jadwal::query()
            ->selectRaw('hari, COUNT(*) as total')
            ->groupBy('hari')
            ->pluck('total', 'hari');

        $total = 0;

        foreach ($perHari as $hari => $jumlah) {
            $total += (int) $jumlah * ($bobot[$hari] ?? 0);
        }

        return $total;
    }

    /**
     * How many times each weekday falls inside a list of dates.
     *
     * @param  array<int, Carbon>  $tanggalList
     * @return array<string, int>
     */
    private static function bobotHari(array $tanggalList): array
    {
        $bobot = [];

        foreach ($tanggalList as $tanggal) {
            $hari = self::HARI[$tanggal->dayOfWeekIso - 1] ?? null;

            if ($hari !== null) {
                $bobot[$hari] = ($bobot[$hari] ?? 0) + 1;
            }
        }

        return $bobot;
    }

    /**
     * The last $days school days (Monday–Saturday), oldest first.
     *
     * @return array<int, Carbon>
     */
    public static function hariSekolah(int $days): array
    {
        $tanggal = Carbon::today();
        $hasil = [];

        while (count($hasil) < $days) {
            if ($tanggal->dayOfWeekIso <= 6) {
                $hasil[] = $tanggal->copy();
            }

            $tanggal = $tanggal->subDay();
        }

        return array_reverse($hasil);
    }

    /**
     * The school days (Monday–Saturday) between two dates inclusive, oldest
     * first — the window a {@see Periode} filters on.
     *
     * @return array<int, Carbon>
     */
    public static function hariSekolahAntara(Carbon $mulai, Carbon $selesai): array
    {
        $hasil = [];

        for ($tanggal = $mulai->copy()->startOfDay(); $tanggal->lte($selesai); $tanggal->addDay()) {
            if ($tanggal->dayOfWeekIso <= 6) {
                $hasil[] = $tanggal->copy();
            }
        }

        return $hasil;
    }

    /**
     * The short column label a date carries in the heatmap — day-of-month, or
     * day/month once a range straddles two months so the columns stay unique.
     * Shared with the dashboard so a heatmap cell's drill-down can map its
     * column back to a real date.
     */
    public static function labelTanggal(Carbon $tanggal, bool $lintasBulan): string
    {
        return $tanggal->translatedFormat($lintasBulan ? 'j/n' : 'j');
    }

    /**
     * Today's weekday as the jadwal table spells it.
     */
    public static function hariIni(): string
    {
        return self::HARI[Carbon::today()->dayOfWeekIso - 1] ?? 'Senin';
    }

    /**
     * Percentage of $part within $whole, guarding the empty case.
     */
    public static function persen(int|float $part, int|float $whole, int $presisi = 0): float
    {
        return $whole > 0 ? round($part / $whole * 100, $presisi) : 0;
    }
}
