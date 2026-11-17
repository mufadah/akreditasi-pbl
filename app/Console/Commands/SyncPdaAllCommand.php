<?php

namespace App\Console\Commands;

use App\Models\Luaran;
use App\Services\PdaPublikasiSyncService;
use App\Services\PdaSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

#[Signature('pda:sync-all {--prodi=Teknologi Rekayasa Komputer : Nama program studi} {--compact : Mode ringkas tanpa log rincian per publikasi}')]
#[Description('Satu perintah otomatis: impor master dosen TRK & pengajar luar, lalu sinkronisasi seluruh publikasi PDA')]
class SyncPdaAllCommand extends Command
{
    public function handle(PdaSyncService $dosenService, PdaPublikasiSyncService $publikasiService): int
    {
        $this->info('========================================================');
        $this->info('  SINKRONISASI OTOMATIS PDA POLINES (DOSEN & PUBLIKASI)  ');
        $this->info('========================================================');

        $prodi = (string) ($this->option('prodi') ?: 'Teknologi Rekayasa Komputer');
        $isCompact = (bool) $this->option('compact');

        // 1. Impor & Sinkronisasi Dosen TRK + Pengajar Luar TRK (Siklus Semesteran)
        $this->newLine();
        $this->line("1. Mengimpor master dosen prodi [{$prodi}] dan pengajar luar...");
        try {
            $dosenRes = $dosenService->importDosenMasterList(
                prodi: $prodi,
                includePengajarLuar: true
            );
            $this->info("   ✔ Dosen diproses  : {$dosenRes['total_dataset']}");
            $this->info("   ✔ Dosen ditambah  : {$dosenRes['created']}");
            $this->info("   ✔ Dosen diperbarui : {$dosenRes['updated']}");
        } catch (\Throwable $e) {
            $this->warn("   ⚠ Gagal sinkron master dosen (menggunakan data database): {$e->getMessage()}");
        }

        // 2. Sinkronisasi Publikasi PDA (Siklus 3 Bulanan / Berkala)
        $this->newLine();
        $this->line('2. Menyinkronkan publikasi ilmiah PDA Polines...');
        if (! $isCompact) {
            $this->comment('   [LOG RINCIAN TEMUAN PUBLIKASI AKTIF]');
        }

        $byDosen = [];

        try {
            $pubRes = $publikasiService->sync(
                maxPages: 50,
                onItem: function (array $item) use ($isCompact, &$byDosen) {
                    $nama = $item['dosen']->nama_dosen;
                    $statusDosen = $item['dosen']->status_dosen ?? 'DTPS';

                    $byDosen[$nama] ??= [
                        'nama' => $nama,
                        'status' => $statusDosen,
                        'total' => 0,
                        'scopus' => 0,
                        'sinta' => 0,
                        'prosiding' => 0,
                        'buku' => 0,
                    ];
                    $byDosen[$nama]['total']++;

                    $kind = $item['type']['kind'] ?? '';
                    $typeName = $item['type']['name'] ?? '';
                    if (str_contains(strtolower($typeName), 'internasional bereputasi')) {
                        $byDosen[$nama]['scopus']++;
                    } elseif (str_contains(strtolower($typeName), 'nasional terakreditasi')) {
                        $byDosen[$nama]['sinta']++;
                    } elseif ($kind === 'proceedings') {
                        $byDosen[$nama]['prosiding']++;
                    } elseif ($kind === 'book') {
                        $byDosen[$nama]['buku']++;
                    }

                    if (! $isCompact) {
                        $badge = $item['status'] === 'created'
                            ? '<fg=green;options=bold>[BARU]</>'
                            : '<fg=cyan>[ADA]</>';
                        $tahun = $item['year'] ?? '-';
                        $this->line("   {$badge} [{$statusDosen}] <options=bold>{$nama}</> ({$tahun}) - {$typeName}");
                        $this->line("         <fg=gray>↳ \"{$item['title']}\"</>");
                    }
                }
            );

            $this->newLine();
            $totalLuaran = Luaran::count();
            $this->info("   ✔ Halaman diproses        : {$pubRes['pages']}");
            $this->info("   ✔ Publikasi baru ditambah : {$pubRes['created']}");
            $this->info("   ✔ Sudah ada di database   : {$pubRes['already_exists']} (up-to-date)");
            $this->info("   ✔ Dilewati (prodi lain)   : {$pubRes['skipped']}");
            $this->info("   ✔ Total luaran tersimpan  : {$totalLuaran}");

            if (! $isCompact && ! empty($byDosen)) {
                $this->newLine();
                $this->info('--- REKAPITULASI TEMUAN PUBLIKASI PER DOSEN (LAM INFOKOM) ---');
                uasort($byDosen, fn ($a, $b) => $b['total'] <=> $a['total']);
                $this->table(
                    ['Nama Dosen', 'Status', 'Total', 'Jurnal Scopus', 'Jurnal SINTA', 'Prosiding', 'Buku'],
                    array_values($byDosen)
                );
            }
        } catch (ConnectionException|RuntimeException $e) {
            $this->error("   ✘ Gagal sinkronisasi publikasi: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✔ Seluruh proses sinkronisasi PDA selesai dengan sukses.');

        return self::SUCCESS;
    }
}
