<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hanguskan klaim kedaluwarsa (spec §2: 14 hari biasa / 30 hari hadiah besar).
Schedule::command('loyalty:expire-claims')->dailyAt('01:00');

// Poin lewat masa berlaku 12 bulan (spec poin-redeem §4).
Schedule::command('loyalty:expire-points')->dailyAt('01:05');
