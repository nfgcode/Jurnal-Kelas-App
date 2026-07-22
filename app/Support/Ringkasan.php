<?php

namespace App\Support;

use App\Models\Jurnal;
use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * The handful of derived figures the dashboard and recap screens keep asking
 * for: daily counts behind a sparkline, an attendance rollup, and the journal
 * completeness grid.
 */
class Ringkasan
{
    /** Weekday names as stored in the jadwal table, Monday first. */
    public const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /**
     * Daily counts for the last $days days, zero-filled so the sparkline keeps
     * a stable width even on quiet days.
     *
     * @return array<string, int>
     */
    public static function harian(Builder $query, int $days = 14, string $column = 'tanggal'): array
    {
        $mulai = Carbon::today()->subDays($days - 1);

        $tercatat = $query
            ->selectRaw("DATE({$column}) as hari, COUNT(*) as total")
            ->whereDate($column, '>=', $mulai)
            ->groupBy('hari')
            ->pluck('total', 'hari');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $tanggal = $mulai->copy()->addDays($i);
            $series[$tanggal->format('j')] = (int) ($tercatat[$tanggal->toDateString()] ?? 0);
        }

        return $series;
    }

    /**
     * Attendance totals keyed hadir/sakit/izin/alpa, always all four present.
     *
     * @return array<string, int>
     */
    public static function presensi(?Builder $query = null): array
    {
        $rows = ($query ?? Presensi::query())
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
     * Teacher-attendance totals, expressed in the merged report vocabulary.
     *
     * @return array{hadir: int, ada_tugas: int, tanpa_tugas: int, total: int}
     */
    public static function kehadiranGuru(?Builder $query = null): array
    {
        $rows = ($query ?? Jurnal::query())
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
     * @param  Collection<int, \App\Models\Kelas>  $kelasList
     * @return array<string, array<string, int>>
     */
    public static function heatmapJurnal(Collection $kelasList, int $days = 20): array
    {
        if ($kelasList->isEmpty()) {
            return [];
        }

        $tanggalList = self::hariSekolah($days);
        $kelasIds = $kelasList->pluck('id');

        // Scheduled meetings per class per weekday.
        $terjadwal = \App\Models\Jadwal::query()
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

                $cells[$tanggal->format('j')] = $target === 0
                    ? 0
                    : min(4, (int) ceil($aktual / $target * 4));
            }

            $rows[$kelas->nama_kelas] = $cells;
        }

        return $rows;
    }

    /**
     * Journal completeness as a percentage, grouped by a jadwal column.
     *
     * Completeness compares journals actually written against the meetings the
     * timetable scheduled over the window — a class that met twenty times and
     * logged eighteen journals sits at 90%.
     *
     * @return array<int, float>  keyed by the grouping column's value
     */
    public static function kelengkapan(string $kolom = 'kelas_id', int $days = 20): array
    {
        $tanggalList = self::hariSekolah($days);

        // How many times each weekday fell inside the window.
        $bobot = [];
        foreach ($tanggalList as $tanggal) {
            $hari = self::HARI[$tanggal->dayOfWeekIso - 1] ?? null;

            if ($hari !== null) {
                $bobot[$hari] = ($bobot[$hari] ?? 0) + 1;
            }
        }

        $target = [];
        $terjadwal = \App\Models\Jadwal::query()
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
