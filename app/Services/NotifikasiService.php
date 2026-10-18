<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotifikasiService
{
    /**
     * Kirim notifikasi in-app ke pengguna tertentu.
     */
    public static function kirim(
        ?int $userId,
        string $tipe,
        string $judul,
        string $pesan,
        ?string $link = null
    ): ?Notifikasi {
        try {
            return Notifikasi::create([
                'user_id' => $userId,
                'tipe' => $tipe,
                'judul' => $judul,
                'pesan' => $pesan,
                'link' => $link,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Gagal mengirim notifikasi in-app: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Kirim notifikasi in-app massal ke seluruh pengguna dengan role tertentu.
     */
    public static function kirimKeRole(
        string|array $roles,
        string $tipe,
        string $judul,
        string $pesan,
        ?string $link = null
    ): int {
        try {
            $roles = is_array($roles) ? $roles : [$roles];
            $users = User::whereIn('role', $roles)->get();

            $count = 0;
            foreach ($users as $user) {
                self::kirim($user->id, $tipe, $judul, $pesan, $link);
                $count++;
            }

            // Jika tidak ada user dengan role tersebut, buat notifikasi global (user_id = null)
            if ($count === 0) {
                self::kirim(null, $tipe, $judul, $pesan, $link);
                $count = 1;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::warning("Gagal mengirim notifikasi ke role: {$e->getMessage()}");
            return 0;
        }
    }
}
