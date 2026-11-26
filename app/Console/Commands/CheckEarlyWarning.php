<?php

namespace App\Console\Commands;

use App\Models\Notifikasi;
use App\Services\EarlyWarningService;
use App\Services\NotifikasiService;
use Illuminate\Console\Command;

class CheckEarlyWarning extends Command
{
    protected $signature = 'early-warning:check';

    protected $description = 'Menghitung indikator early warning dosen dan mengirim notifikasi ringkasan ke Administrator dan Kaprodi';

    public function handle(EarlyWarningService $service)
    {
        $hasil = $service->hitung();
        $terkirim = 0;

        foreach ($hasil['indikator'] as $indikator) {
            if ($indikator['jumlah_peringatan'] === 0) {
                continue;
            }

            $tipe = str_replace('-', '_', $indikator['kode']);

            if (Notifikasi::where('tipe', $tipe)->whereDate('created_at', today())->exists()) {
                continue;
            }

            NotifikasiService::kirimKeRole(
                ['ADMINISTRATOR', 'KAPRODI'],
                $tipe,
                'Peringatan Dini: '.$indikator['nama'],
                "{$indikator['jumlah_peringatan']} peringatan terdeteksi per {$hasil['tanggal_hitung']}.",
                '/early-warning'
            );

            $terkirim++;
            $this->info("Notifikasi {$tipe} dikirim ({$indikator['jumlah_peringatan']} peringatan).");
        }

        $this->info("Selesai: {$terkirim} notifikasi terkirim.");

        return Command::SUCCESS;
    }
}