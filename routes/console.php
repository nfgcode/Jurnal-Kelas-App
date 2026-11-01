<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Just after midnight, once the day has turned, backfill any meeting from the
// past days that still has no journal — marked guru-absent, its roster estimated
// from another lesson that class had the same day, spread across waves so the
// database is never hit all at once. See App\Console\Commands\IsiJurnalOtomatis.
Schedule::command('jurnal:isi-otomatis')->dailyAt('00:30')->withoutOverlapping();

// Preparation only: activation, promotion, graduation remain explicit admin actions.
Schedule::command('tahun-ajaran:siapkan-berikutnya')->dailyAt('00:15')->withoutOverlapping();

// Otomatisasi Cadangan Database Bulanan & Pembersihan Retensi (Fase 7)
// Dijalankan setiap tanggal 1 jam 01:00 dini hari
Schedule::command('cadangan:buat-bulanan')->monthlyOn(1, '01:00')->withoutOverlapping();
Schedule::command('cadangan:bersihkan-retensi')->monthlyOn(1, '02:00')->withoutOverlapping();
