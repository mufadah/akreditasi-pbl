<?php

namespace App\Console\Commands;

use App\Services\PdaDosenMasterSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

#[Signature('pda:sync-dosen')]
#[Description('Sinkronisasi master dosen TRK, Teknik Informatika, dan pengampu MKU dari PDA')]
class SyncPdaDosenMasterCommand extends Command
{
    public function handle(PdaDosenMasterSyncService $syncService): int
    {
        $this->info('Mengambil direktori dan profil publik dosen PDA...');

        try {
            $result = $syncService->sync();
        } catch (ConnectionException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Sinkronisasi semester PDA selesai.');
        $this->line('Semester pengajaran terbaru: '.($result['semester'] ?? 'tidak tersedia'));
        $this->line("D4 Teknologi Rekayasa Komputer (DTPS): {$result['d4_trk']}");
        $this->line("D3 Teknik Informatika (DTPS): {$result['d3_ti']}");
        $this->line("Pengampu MKU lintas prodi (DTT): {$result['mku']}");
        $this->line("Ditambahkan: {$result['created']} | Diperbarui: {$result['updated']} | Dilewati: {$result['skipped']}");

        if ($result['jabatan_distribution'] !== []) {
            $this->newLine();
            $this->info('Sebaran jabatan fungsional:');
            foreach ($result['jabatan_distribution'] as $jabatan => $jumlah) {
                $this->line("  - {$jabatan}: {$jumlah}");
            }
        }

        if ($result['serdos_available'] === 0) {
            $this->warn('Informasi Serdos tidak tersedia di data publik PDA; nilai yang sudah tersimpan tidak diubah.');
        }

        if ($result['semester'] === null) {
            $this->warn('Semester tidak dapat ditentukan dari profil PDA; hanya dosen homebase yang disinkronkan.');
        }

        return self::SUCCESS;
    }
}
