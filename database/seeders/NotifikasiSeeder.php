<?php

namespace Database\Seeders;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class NotifikasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('notifikasi')) {
            return;
        }

        $admin = User::where('role', 'ADMINISTRATOR')->first();
        $dosen = User::where('role', 'DOSEN')->first();

        $adminId = $admin?->id ?? 1;
        $dosenId = $dosen?->id ?? 2;

        $notifikasiList = [
            [
                'user_id' => $adminId,
                'tipe' => 'KERJASAMA_EXPIRED',
                'judul' => 'Peringatan Masa Berlaku Kerja Sama',
                'pesan' => 'Kerja Sama MoA dengan PT Astra International Tbk telah berakhir pada 01 Januari 2025.',
                'link' => '/kerja-sama',
                'is_read' => true,
            ],
            [
                'user_id' => $adminId,
                'tipe' => 'EVIDENCE_BARU',
                'judul' => 'Pengajuan Evidence Baru Menunggu Validasi',
                'pesan' => 'Dosen telah mengunggah bukti dokumen MoU PT Telkom Indonesia untuk diverifikasi.',
                'link' => '/evidence',
                'is_read' => false,
            ],
            [
                'user_id' => $dosenId,
                'tipe' => 'EVIDENCE_VALID',
                'judul' => 'Evidence Berhasil Divalidasi',
                'pesan' => 'Evidence Laporan Akhir Riset IoT Tambak Udang telah diverifikasi dan disetujui oleh Asesor.',
                'link' => '/evidence',
                'is_read' => false,
            ],
            [
                'user_id' => $dosenId,
                'tipe' => 'SERTIFIKASI_REMINDER',
                'judul' => 'Pengingat Kedaluwarsa Sertifikasi Kompetensi',
                'pesan' => 'Sertifikasi Cisco CCNA Anda akan berakhir dalam kurun waktu 30 hari.',
                'link' => '/sertifikasi',
                'is_read' => false,
            ],
        ];

        foreach ($notifikasiList as $notif) {
            Notifikasi::firstOrCreate(
                [
                    'user_id' => $notif['user_id'],
                    'judul' => $notif['judul'],
                ],
                $notif
            );
        }
    }
}
