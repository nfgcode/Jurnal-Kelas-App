<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jadwal bel
    |--------------------------------------------------------------------------
    |
    | A lesson period (jam pelajaran, JP) is 45 minutes — the standard length
    | for SMA/SMK in Indonesia. The wall-clock time of "JP 3" is not a fact
    | anyone should be typing into a form: it follows from the first bell, the
    | length of a period, and the breaks in between. So it is computed, and
    | these are the three inputs it is computed from.
    |
    | `istirahat` is keyed by the JP the break follows: `4 => 15` means a
    | 15-minute break after the fourth period. Change these and every schedule
    | re-derives its times the next time it is saved.
    |
    */

    'jp' => [
        'durasi_menit' => (int) env('SEKOLAH_JP_MENIT', 45),
        'bel_pertama' => env('SEKOLAH_BEL_PERTAMA', '07:00'),
        'maksimal' => (int) env('SEKOLAH_JP_MAKSIMAL', 12),

        'istirahat' => [
            4 => 15,
            8 => 30,
        ],
    ],

];
