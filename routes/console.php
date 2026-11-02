<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penjadwalan otomatis reminder kedaluwarsa kerja sama setiap hari pukul 01:00 WIB
Schedule::command('kerjasama:check-kedaluwarsa')->dailyAt('01:00');

// Penjadwalan otomatis sinkronisasi Tridharma dari PDA Polines setiap 2 minggu sekali (tanggal 1 dan 15 pukul 02:00 WIB)
Schedule::command('pda:sync --all')->twiceMonthly(1, 15, '02:00');
