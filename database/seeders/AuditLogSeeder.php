<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AuditLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $admin = User::where('role', 'ADMINISTRATOR')->first();
        $adminId = $admin?->id ?? 1;

        $dosen = User::where('role', 'DOSEN')->first();
        $dosenId = $dosen?->id ?? 2;

        $logs = [
            [
                'user_id' => $dosenId,
                'role' => 'DOSEN',
                'action' => 'CREATE',
                'entity_type' => 'App\Models\Kegiatan',
                'entity_id' => '1',
                'old_values' => null,
                'new_values' => [
                    'judul_kegiatan' => 'Rancang Bangun Sistem Monitoring Kualitas Air Tambak Udang Berbasis IoT',
                    'tahun_usulan' => 2024,
                    'status_kegiatan' => 'Usulan',
                ],
                'ip_address' => '192.168.1.105',
                'created_at' => now()->subDays(30),
            ],
            [
                'user_id' => $adminId,
                'role' => 'ADMINISTRATOR',
                'action' => 'VALIDATE',
                'entity_type' => 'App\Models\Evidence',
                'entity_id' => '1',
                'old_values' => [
                    'status_validasi' => 'Menunggu',
                ],
                'new_values' => [
                    'status_validasi' => 'Valid',
                    'catatan_validasi' => 'Dokumen lengkap, bertanda tangan basah dan terdapat stempel mitra.',
                ],
                'ip_address' => '192.168.1.1',
                'created_at' => now()->subDays(15),
            ],
            [
                'user_id' => $adminId,
                'role' => 'ADMINISTRATOR',
                'action' => 'CONFIG_METRIK',
                'entity_type' => 'App\Models\KonfigurasiMetrik',
                'entity_id' => '1',
                'old_values' => [
                    'ambang_target' => 10.00,
                ],
                'new_values' => [
                    'ambang_target' => 12.00,
                ],
                'ip_address' => '192.168.1.1',
                'created_at' => now()->subDays(7),
            ],
            [
                'user_id' => $dosenId,
                'role' => 'DOSEN',
                'action' => 'UPDATE',
                'entity_type' => 'App\Models\Sertifikasi',
                'entity_id' => '1',
                'old_values' => [
                    'status_sertifikasi' => 'Proses Pengajuan',
                ],
                'new_values' => [
                    'status_sertifikasi' => 'Aktif',
                    'nomor_sertifikat' => 'SERDOS-2022-882190',
                ],
                'ip_address' => '192.168.1.105',
                'created_at' => now()->subDays(3),
            ],
        ];

        foreach ($logs as $log) {
            AuditLog::firstOrCreate(
                [
                    'action' => $log['action'],
                    'entity_type' => $log['entity_type'],
                    'entity_id' => $log['entity_id'],
                    'created_at' => $log['created_at'],
                ],
                $log
            );
        }
    }
}
