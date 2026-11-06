<?php

namespace App\Console\Commands;

use App\Services\PdaPublikasiSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

#[Signature('pda:sync-publikasi {--pages=5} {--year=}')]
#[Description('Sinkronisasi publikasi ilmiah dari PDA Polines')]
class SyncPdaPublikasiCommand extends Command
{
    public function handle(PdaPublikasiSyncService $syncService): int
    {
        $pages = filter_var($this->option('pages'), FILTER_VALIDATE_INT);
        $yearOption = $this->option('year');
        $year = $yearOption !== null && $yearOption !== '' ? (string) $yearOption : null;

        if ($pages === false || $pages < 1) {
            $this->error('Opsi --pages harus berupa bilangan bulat minimal 1.');

            return self::INVALID;
        }

        if ($year !== null && ! preg_match('/^\d{4}$/', $year)) {
            $this->error('Opsi --year harus berupa tahun empat digit.');

            return self::INVALID;
        }

        try {
            $result = $syncService->sync($pages, $year);
        } catch (ConnectionException|RuntimeException $exception) {
            $this->error('Sinkronisasi publikasi PDA gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Halaman diproses: {$result['pages']}");
        $this->info("Publikasi ditambahkan: {$result['created']}");
        $this->info("Publikasi dilewati: {$result['skipped']}");

        return self::SUCCESS;
    }
}
