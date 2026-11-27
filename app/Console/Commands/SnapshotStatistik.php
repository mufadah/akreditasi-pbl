<?php

namespace App\Console\Commands;

use App\Models\StatistikSnapshot;
use App\Services\LaporanService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class SnapshotStatistik extends Command
{
    protected $signature = 'statistik:snapshot {--tahun= : Tahun penilaian (bawaan: tahun berjalan)}';

    protected $description = 'Menyimpan ringkasan statistik dashboard Tri Dharma hari ini ke tabel statistik_snapshot';

    public function handle(LaporanService $laporan)
    {
        $tahun = (int) ($this->option('tahun') ?: today()->year);

        if ($tahun < 1900 || $tahun > 2100) {
            $this->error('Tahun tidak valid. Gunakan empat digit antara 1900 dan 2100.');

            return Command::FAILURE;
        }

        $data = $laporan->dashboard($tahun);

        StatistikSnapshot::updateOrCreate(
            ['tanggal' => today()->toDateString(), 'tahun' => $tahun],
            ['data' => [
                'ringkasan' => $data['ringkasan'],
                'produktivitas' => Arr::except($data['produktivitas'], ['daftar_dosen']),
                'rasio' => $data['rasio'],
                'tren' => $data['tren'],
                'early_warning' => $data['early_warning']['ringkasan'],
            ]]
        );

        $this->info("Snapshot statistik tahun {$tahun} tersimpan.");

        return Command::SUCCESS;
    }
}