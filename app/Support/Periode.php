<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * A date range chosen on the dashboard, translated from a request into the
 * pair of dates every recap query filters on. One object is shared by the
 * admin dashboard, the guru/siswa dashboards, and both report screens, so the
 * numbers a figure shows and the period its label promises always agree.
 */
class Periode
{
    /** Preset windows offered by the filter, in menu order. */
    public const PRESETS = [
        'hari_ini',
        'minggu_ini',
        'minggu_lalu',
        'bulan_ini',
        'bulan_lalu',
        '30_hari',
        'tahun_ini',
        'custom',
    ];

    /** Widest custom range accepted, so a heatmap query cannot explode. */
    private const MAX_HARI = 366;

    private function __construct(
        public readonly Carbon $mulai,
        public readonly Carbon $selesai,
        public readonly string $preset,
    ) {
    }

    /**
     * Build the period from the request, defaulting to the current month.
     *
     * A custom range is validated (both dates present, end not before start),
     * so a malformed filter is rejected rather than silently misread.
     */
    public static function dari(Request $request): self
    {
        $data = $request->validate([
            'preset' => ['nullable', 'string', 'in:' . implode(',', self::PRESETS)],
            'mulai' => ['nullable', 'date'],
            'selesai' => ['nullable', 'date'],
        ]);

        $preset = $data['preset'] ?? 'bulan_ini';

        if ($preset !== 'custom') {
            return self::preset($preset);
        }

        $request->validate([
            'mulai' => ['required', 'date'],
            'selesai' => ['required', 'date', 'after_or_equal:mulai'],
        ]);

        $mulai = Carbon::parse($data['mulai'])->startOfDay();
        $selesai = Carbon::parse($data['selesai'])->startOfDay();

        // Clamp the span so a runaway range never reaches the heatmap query.
        if ($mulai->diffInDays($selesai) > self::MAX_HARI) {
            $selesai = $mulai->copy()->addDays(self::MAX_HARI);
        }

        return new self($mulai, $selesai, 'custom');
    }

    /**
     * Build a named preset. Windows that reach "today" stop there rather than
     * running into empty future dates.
     */
    public static function preset(string $preset): self
    {
        $hariIni = Carbon::today();

        [$mulai, $selesai] = match ($preset) {
            'hari_ini' => [$hariIni->copy(), $hariIni->copy()],
            'minggu_ini' => [$hariIni->copy()->startOfWeek(Carbon::MONDAY), $hariIni->copy()],
            'minggu_lalu' => [
                $hariIni->copy()->subWeek()->startOfWeek(Carbon::MONDAY),
                $hariIni->copy()->subWeek()->endOfWeek(Carbon::SUNDAY),
            ],
            'bulan_lalu' => [
                $hariIni->copy()->subMonthNoOverflow()->startOfMonth(),
                $hariIni->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            '30_hari' => [$hariIni->copy()->subDays(29), $hariIni->copy()],
            'tahun_ini' => [$hariIni->copy()->startOfYear(), $hariIni->copy()],
            default => [$hariIni->copy()->startOfMonth(), $hariIni->copy()],
        };

        $preset = in_array($preset, self::PRESETS, true) ? $preset : 'bulan_ini';

        return new self($mulai->startOfDay(), $selesai->startOfDay(), $preset);
    }

    /**
     * A trailing window of $n calendar days ending today — the default the
     * sparkline and fill-trend chart fall back to when no period is chosen.
     */
    public static function hariTerakhir(int $n): self
    {
        $selesai = Carbon::today();

        return new self($selesai->copy()->subDays(max(0, $n - 1))->startOfDay(), $selesai->copy()->startOfDay(), '30_hari');
    }

    public function mulaiString(): string
    {
        return $this->mulai->toDateString();
    }

    public function selesaiString(): string
    {
        return $this->selesai->toDateString();
    }

    public function jumlahHari(): int
    {
        return (int) $this->mulai->diffInDays($this->selesai) + 1;
    }

    public function isCustom(): bool
    {
        return $this->preset === 'custom';
    }

    /** True when the range straddles a month boundary, so labels need the month. */
    public function lintasBulan(): bool
    {
        return $this->mulai->format('Y-m') !== $this->selesai->format('Y-m');
    }

    /**
     * The school days (Monday–Saturday) inside the range, oldest first.
     *
     * @return array<int, Carbon>
     */
    public function hariSekolah(): array
    {
        return Ringkasan::hariSekolahAntara($this->mulai, $this->selesai);
    }

    /**
     * The equal-length window immediately before this one, for period-on-period
     * deltas.
     */
    public function sebelumnya(): self
    {
        $panjang = $this->jumlahHari();

        return new self(
            $this->mulai->copy()->subDays($panjang)->startOfDay(),
            $this->mulai->copy()->subDay()->startOfDay(),
            'custom',
        );
    }

    /** Human, translated label for the card meta line. */
    public function label(): string
    {
        return match ($this->preset) {
            'hari_ini' => 'Hari Ini',
            'minggu_ini' => 'Minggu Ini',
            'minggu_lalu' => 'Minggu Lalu',
            'bulan_lalu' => $this->mulai->translatedFormat('F Y'),
            '30_hari' => '30 Hari Terakhir',
            'tahun_ini' => 'Tahun ' . $this->mulai->year,
            'custom' => $this->mulai->translatedFormat('j M') . ' – ' . $this->selesai->translatedFormat('j M Y'),
            default => $this->mulai->translatedFormat('F Y'),
        };
    }
}
