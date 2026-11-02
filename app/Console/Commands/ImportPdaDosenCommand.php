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
    protected $signature = 'pda:import-dosen
                            {--prodi=Teknologi Rekayasa Komputer : Filter berdasarkan nama program studi}
                            {--homebase-only : Hanya impor dosen homebase tanpa dosen luar prodi/jurusan yang mengajar}
                            {--scan-pengajar : Pindai ulang riwayat pengajaran seluruh dosen secara live dari portal PDA}
                            {--all : Impor seluruh dosen dari semua program studi Polines}';

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
        $all = (bool) $this->option('all');
        $homebaseOnly = (bool) $this->option('homebase-only');
        $scanPengajar = (bool) $this->option('scan-pengajar');
        $prodi = $all ? null : (string) ($this->option('prodi') ?: 'Teknologi Rekayasa Komputer');

        $isTrk = $prodi !== null && str_contains(strtolower($prodi), 'rekayasa komputer');
        $includePengajar = $isTrk && ! $homebaseOnly;

        $targetText = $all ? 'seluruh program studi Polines' : "program studi [{$prodi}]";
        if ($includePengajar) {
            $targetText .= ' beserta dosen luar prodi/jurusan pengampu TRK';
        }

        $this->info("Memulai impor master data dosen untuk {$targetText} dari portal PDA Polines...");

        $result = $pdaSyncService->importDosenMasterList(
            prodi: $prodi,
            includePengajarLuar: $includePengajar,
            liveScan: $scanPengajar
        );

        if ($result['total_dataset'] === 0) {
            $this->warn("Data dosen PDA tidak ditemukan atau gagal diambil untuk {$targetText}.");

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
