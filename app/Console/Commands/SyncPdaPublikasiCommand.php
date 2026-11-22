<?php

namespace App\Console\Commands;

use App\Services\PdaPublikasiSyncService;
use App\Services\PdaSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

#[Signature('pda:sync-publikasi {--pages=5} {--year=} {--import-dosen : Impor/perbarui master dosen TRK dan pengajar luar sebelum sinkronisasi} {--prodi=Teknologi Rekayasa Komputer : Program studi master dosen} {--homebase-only : Hanya dosen homebase tanpa pengajar luar} {--compact : Mode ringkas tanpa rincian per publikasi}')]
#[Description('Sinkronisasi publikasi ilmiah dari PDA Polines')]
class SyncPdaPublikasiCommand extends Command
{
    public function handle(PdaPublikasiSyncService $syncService, PdaSyncService $pdaSyncService): int
    {
        $pages = filter_var($this->option('pages'), FILTER_VALIDATE_INT);
        $yearOption = $this->option('year');
        $year = $yearOption !== null && $yearOption !== '' ? (string) $yearOption : null;
        $isCompact = (bool) $this->option('compact');

        if ($pages === false || $pages < 1) {
            $this->error('Opsi --pages harus berupa bilangan bulat minimal 1.');

            return self::INVALID;
        }

        if ($year !== null && ! preg_match('/^\d{4}$/', $year)) {
            $this->error('Opsi --year harus berupa tahun empat digit.');

            return self::INVALID;
        }

        if ($this->option('import-dosen')) {
            $prodi = (string) ($this->option('prodi') ?: 'Teknologi Rekayasa Komputer');
            $homebaseOnly = (bool) $this->option('homebase-only');
            $isTrk = str_contains(strtolower($prodi), 'rekayasa komputer');
            $includePengajar = $isTrk && ! $homebaseOnly;

            $this->info("Mengimpor master data dosen [{$prodi}]".($includePengajar ? ' beserta dosen luar pengajar TRK' : '').' dari PDA...');
            $dosenRes = $pdaSyncService->importDosenMasterList(
                prodi: $prodi,
                includePengajarLuar: $includePengajar
            );
            $this->info("✔ Master dosen: Total {$dosenRes['total_dataset']}, Ditambahkan {$dosenRes['created']}, Diperbarui {$dosenRes['updated']}.");
        }

        $byDosen = [];

        try {
            $result = $syncService->sync(
                maxPages: $pages,
                year: $year,
                onItem: function (array $item) use ($isCompact, &$byDosen) {
                    $nama = $item['dosen']->nama_dosen;
                    $statusDosen = $item['dosen']->status_dosen ?? 'DTPS';

                    $byDosen[$nama] ??= [
                        'nama' => $nama,
                        'status' => $statusDosen,
                        'total' => 0,
                    ];
                    $byDosen[$nama]['total']++;

                    if (! $isCompact) {
                        $badge = $item['status'] === 'created'
                            ? '<fg=green;options=bold>[BARU]</>'
                            : '<fg=cyan>[ADA]</>';
                        $tahun = $item['year'] ?? '-';
                        $typeName = $item['type']['name'] ?? '';
                        $this->line("   {$badge} [{$statusDosen}] <options=bold>{$nama}</> ({$tahun}) - {$typeName}");
                        $this->line("         <fg=gray>↳ \"{$item['title']}\"</>");
                    }
                }
            );
        } catch (ConnectionException|RuntimeException $exception) {
            $this->error('Sinkronisasi publikasi PDA gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Halaman diproses: {$result['pages']}");
        $this->info("Publikasi ditambahkan: {$result['created']}");
        $this->info("Publikasi sudah ada: {$result['already_exists']}");
        $this->info("Publikasi dilewati: {$result['skipped']}");

        return self::SUCCESS;
    }
}
