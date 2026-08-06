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
