<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penjadwalan otomatis reminder kedaluwarsa kerja sama setiap hari pukul 01:00 WIB
\Illuminate\Support\Facades\Schedule::command('kerjasama:check-kedaluwarsa')->dailyAt('01:00');
