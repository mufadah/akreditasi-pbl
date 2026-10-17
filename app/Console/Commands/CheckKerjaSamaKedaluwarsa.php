<?php

namespace App\Console\Commands;

use App\Models\KerjaSama;
use App\Services\NotifikasiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckKerjaSamaKedaluwarsa extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kerjasama:check-kedaluwarsa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memeriksa masa berlaku kerja sama, memperbarui status kedaluwarsa, dan mengirim notifikasi in-app otomatis';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = now()->toDateString();
        $targetDate = now()->addDays(30)->toDateString();

        $this->info("Memulai pengecekan masa berlaku kerja sama per {$today}...");

        // 1. Perbarui dokumen kerja sama yang telah melewati tanggal selesai
        $expiredList = KerjaSama::where('status', 'Aktif')
            ->whereNotNull('tanggal_selesai')
            ->where('tanggal_selesai', '<', $today)
            ->get();

        $updatedCount = 0;
        foreach ($expiredList as $item) {
            $item->update(['status' => 'Kedaluwarsa']);
            $updatedCount++;

            NotifikasiService::kirimKeRole(
                ['ADMINISTRATOR', 'KAPRODI'],
                'KERJASAMA_KEDALUWARSA',
                'Dokumen Kerja Sama Telah Kedaluwarsa',
                "Dokumen '{$item->judul_kerja_sama}' telah kedaluwarsa pada tanggal {$item->tanggal_selesai}.",
                "/kerja-sama/{$item->getKey()}"
            );
        }

        $this->info("Berhasil memperbarui {$updatedCount} dokumen kerja sama ke status 'Kedaluwarsa'.");

        // 2. Beri notifikasi reminder untuk dokumen yang akan berakhir dalam 30 hari ke depan
        $expiringSoon = KerjaSama::where('status', 'Aktif')
            ->whereNotNull('tanggal_selesai')
            ->whereBetween('tanggal_selesai', [$today, $targetDate])
            ->get();

        $reminderCount = 0;
        foreach ($expiringSoon as $item) {
            $sisaHari = now()->diffInDays(Carbon::parse($item->tanggal_selesai));
            NotifikasiService::kirimKeRole(
                ['ADMINISTRATOR', 'KAPRODI'],
                'KERJASAMA_REMINDER',
                'Peringatan Masa Berlaku Kerja Sama',
                "Dokumen '{$item->judul_kerja_sama}' akan berakhir dalam {$sisaHari} hari ({$item->tanggal_selesai}).",
                "/kerja-sama/{$item->getKey()}"
            );
            $reminderCount++;
        }

        $this->info("Berhasil mengirim {$reminderCount} notifikasi reminder masa berlaku kerja sama.");

        return Command::SUCCESS;
    }
}
