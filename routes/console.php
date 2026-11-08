<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penjadwalan otomatis reminder kedaluwarsa kerja sama setiap hari pukul 01:00 WIB
Schedule::command('kerjasama:check-kedaluwarsa')->dailyAt('01:00');

// Impor master dosen berlangsung lebih dulu, lalu publikasi disinkronkan pukul 03:00 WIB.
Schedule::command('pda:import-dosen')->timezone('Asia/Jakarta')->twiceMonthly(1, 15, '02:00');
Schedule::command('pda:sync-publikasi --pages=5')->timezone('Asia/Jakarta')->twiceMonthly(1, 15, '03:00');
