<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penjadwalan otomatis reminder kedaluwarsa kerja sama setiap hari pukul 01:00 WIB
Schedule::command('kerjasama:check-kedaluwarsa')->dailyAt('01:00');

// Sinkronkan master dosen sekali setiap semester (Februari dan Agustus).
Schedule::command('pda:sync-dosen')->timezone('Asia/Jakarta')->yearlyOn(2, 1, '02:00');
Schedule::command('pda:sync-dosen')->timezone('Asia/Jakarta')->yearlyOn(8, 1, '02:00');
// Impor publikasi berkala pukul 03:00 WIB.
Schedule::command('pda:sync-publikasi --pages=5')->timezone('Asia/Jakarta')->twiceMonthly(1, 15, '03:00');

// 1. Impor Master Dosen TRK & Pengajar Luar TRK: 1 semester sekali (Februari & Agustus awal semester) pukul 02:00 WIB
Schedule::command('pda:import-dosen')->timezone('Asia/Jakarta')->cron('0 2 1 2,8 *');

// 2. Sinkronisasi Publikasi PDA: 3 bulan sekali (Januari, April, Juli, Oktober) pukul 03:00 WIB
Schedule::command('pda:sync-publikasi --pages=50')->timezone('Asia/Jakarta')->cron('0 3 1 1,4,7,10 *');

// 3. Cadangan: Satu perintah sinkronisasi terpadu pda:sync-all bisa dipanggil kapan saja atau triwulanan
Schedule::command('pda:sync-all --compact')->timezone('Asia/Jakarta')->cron('0 4 1 1,4,7,10 *');

// Snapshot statistik dashboard Tri Dharma setiap hari pukul 05:00 WIB (arsip harian untuk riwayat tren).
Schedule::command('statistik:snapshot')->timezone('Asia/Jakarta')->dailyAt('05:00')->withoutOverlapping();

// Early warning dosen: hitung indikator risiko dan kirim notifikasi ringkasan setiap pagi pukul 06:00 WIB.
Schedule::command('early-warning:check')->timezone('Asia/Jakarta')->dailyAt('06:00')->withoutOverlapping();