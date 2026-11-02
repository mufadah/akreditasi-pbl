<?php

namespace App\Console\Commands;

use App\Models\Dosen;
use App\Services\PdaSyncService;
use Illuminate\Console\Command;

class SyncPdaDosenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pda:sync
                            {--dosen= : ID Dosen tertentu untuk disinkronkan}
                            {--all : Sinkronkan seluruh dosen yang terdaftar}
                            {--import-dosen : Impor/perbarui master data dosen dari PDA terlebih dahulu}
                            {--limit= : Batasi jumlah dosen jika menggunakan --all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi otomatis portofolio Tridharma dosen dari portal Polines Dalam Angka (PDA)';

    /**
     * Execute the console command.
     */
    public function handle(PdaSyncService $pdaSyncService): int
    {
        if ($this->option('import-dosen')) {
            $this->info('Mengimpor master data dosen dari PDA Polines terlebih dahulu...');
            $importRes = $pdaSyncService->importDosenMasterList();
            $this->info("✔ Master dosen: Ditambahkan {$importRes['created']}, Diperbarui {$importRes['updated']}.");
        }

        $dosenId = $this->option('dosen');
        $isAll = $this->option('all');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if (! $dosenId && ! $isAll) {
            if ($this->option('import-dosen')) {
                return self::SUCCESS;
            }

            $this->error('Tentukan opsi --dosen=<id> atau --all untuk sinkronisasi seluruh dosen.');

            return self::FAILURE;
        }

        $this->info('Memulai sinkronisasi data Tridharma dengan portal PDA Polines...');

        if ($dosenId) {
            $this->line("Menyinkronkan data dosen ID: {$dosenId}...");
            $result = $pdaSyncService->syncDosen($dosenId);

            if ($result['success']) {
                $this->info("✔ {$result['message']}");

                return self::SUCCESS;
            }

            $this->error("✘ {$result['message']}");
            foreach ($result['errors'] as $err) {
                $this->warn("  - {$err}");
            }

            return self::FAILURE;
        }

        // Sinkronisasi massal seluruh dosen
        $query = Dosen::query();
        if ($limit !== null && $limit > 0) {
            $query->take($limit);
        }

        $dosenList = $query->get();
        $total = $dosenList->count();

        if ($total === 0) {
            $this->warn('Tidak ada data dosen yang terdaftar di database.');

            return self::SUCCESS;
        }

        $this->info("Menemukan {$total} dosen untuk disinkronkan.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $successCount = 0;
        foreach ($dosenList as $dosen) {
            $res = $pdaSyncService->syncDosen($dosen->id_dosen);
            $this->newLine();
            if ($res['success']) {
                $successCount++;
                $this->info("  ✔ [{$dosen->nama_dosen}] {$res['message']}");
            } else {
                $this->warn("  ✘ [{$dosen->nama_dosen}] {$res['message']}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Sinkronisasi berkala selesai! Berhasil menyinkronkan {$successCount}/{$total} dosen.");

        return self::SUCCESS;
    }
}
