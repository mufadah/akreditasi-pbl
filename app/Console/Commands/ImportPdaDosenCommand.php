<?php

namespace App\Console\Commands;

use App\Services\PdaSyncService;
use Illuminate\Console\Command;

class ImportPdaDosenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pda:import-dosen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Impor atau perbarui daftar master dosen Politeknik Negeri Semarang dari portal PDA';

    /**
     * Execute the console command.
     */
    public function handle(PdaSyncService $pdaSyncService): int
    {
        $this->info('Memulai impor master data dosen dari dataset PDA Polines...');

        $result = $pdaSyncService->importDosenMasterList();

        if ($result['total_dataset'] === 0) {
            $this->warn('Dataset dosen PDA tidak ditemukan di database/data/dosen_polines.json');

            return self::FAILURE;
        }

        $this->info("✔ Berhasil memproses {$result['total_dataset']} dosen dari PDA Polines:");
        $this->line("  - Ditambahkan : {$result['created']}");
        $this->line("  - Diperbarui   : {$result['updated']}");
        if ($result['skipped'] > 0) {
            $this->line("  - Dilewati    : {$result['skipped']}");
        }

        return self::SUCCESS;
    }
}
