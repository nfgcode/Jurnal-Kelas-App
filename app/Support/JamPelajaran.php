<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The bell schedule: what time each numbered lesson period actually runs.
 *
 * The timetable form used to ask for the period number *and* the clock time,
 * which meant the two could disagree — a slot labelled "JP 1-2" starting at
 * 13:00 was accepted without complaint, and nothing said which of the two the
 * journal deadline should trust. Now the clock time is derived from the period
 * number, so there is only one answer and the school changes it in one place
 * (config/sekolah.php).
 */
class JamPelajaran
{
    /**
     * Minutes from the first bell to the start of the given period.
     *
     * Periods before this one contribute their full length, and every break
     * that falls in between contributes its own — which is what makes JP 5
     * start after the morning break rather than 45 minutes after JP 4.
     */
    private static function menitSebelum(int $jamKe): int
    {
        $durasi = self::durasi();
        $menit = ($jamKe - 1) * $durasi;

        foreach (self::istirahat() as $setelahJp => $lama) {
            if ($jamKe > $setelahJp) {
                $menit += $lama;
            }
        }

        return $menit;
    }

    /** Clock time this period begins, as "H:i". */
    public static function mulai(int $jamKe): string
    {
        return self::belPertama()->addMinutes(self::menitSebelum($jamKe))->format('H:i');
    }

    /** Clock time this period ends, as "H:i". */
    public static function selesai(int $jamKe): string
    {
        return self::belPertama()
            ->addMinutes(self::menitSebelum($jamKe) + self::durasi())
            ->format('H:i');
    }

    /**
     * The span of a lesson running from one period to another, as the schedule
     * stores it: ['jam_mulai' => '07:00', 'jam_selesai' => '08:30'].
     *
     * @return array{jam_mulai: string, jam_selesai: string}
     */
    public static function rentang(int $dari, int $sampai): array
    {
        $sampai = max($dari, $sampai);

        return [
            'jam_mulai' => self::mulai($dari),
            'jam_selesai' => self::selesai($sampai),
        ];
    }

    /** Human label for a span, e.g. "07:00 - 08:30". */
    public static function label(int $dari, int $sampai): string
    {
        $rentang = self::rentang($dari, $sampai);

        return "{$rentang['jam_mulai']} - {$rentang['jam_selesai']}";
    }

    /**
     * Every period with its clock time, for the reference table the schedule
     * form shows so an admin can see what "JP 5" means before choosing it.
     *
     * @return list<array{jam_ke: int, mulai: string, selesai: string, istirahat: int|null}>
     */
    public static function daftar(): array
    {
        $istirahat = self::istirahat();

        return array_map(fn (int $jp) => [
            'jam_ke' => $jp,
            'mulai' => self::mulai($jp),
            'selesai' => self::selesai($jp),
            'istirahat' => $istirahat[$jp] ?? null,
        ], range(1, self::maksimal()));
    }

    public static function durasi(): int
    {
        return (int) config('sekolah.jp.durasi_menit', 45);
    }

    public static function maksimal(): int
    {
        return (int) config('sekolah.jp.maksimal', 12);
    }

    /**
     * Break lengths keyed by the period they follow, smallest key first so the
     * running total in {@see menitSebelum()} adds them in bell order.
     *
     * @return array<int, int>
     */
    public static function istirahat(): array
    {
        $istirahat = (array) config('sekolah.jp.istirahat', []);
        ksort($istirahat);

        return $istirahat;
    }

    private static function belPertama(): Carbon
    {
        return Carbon::createFromFormat('H:i', (string) config('sekolah.jp.bel_pertama', '07:00'));
    }
}
