<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $users = [
            [
                'name' => 'Administrator Sistem',
                'email' => 'administrator@example.com',
                'password' => Hash::make('password'),
                'role' => 'ADMINISTRATOR',
            ],
            [
                'name' => 'Ketua Program Studi (Kaprodi)',
                'email' => 'kaprodi@example.com',
                'password' => Hash::make('password'),
                'role' => 'KAPRODI',
            ],
            [
                'name' => 'Dosen K1 SSO',
                'email' => 'dosen@example.com',
                'password' => Hash::make('password'),
                'role' => 'DOSEN',
            ],
            [
                'name' => 'Dr. Ir. Ahmad Dahlan, M.T.',
                'email' => 'ahmad.dahlan@example.com',
                'password' => Hash::make('password'),
                'role' => 'DOSEN',
            ],
            [
                'name' => 'Dr. Eng. Siti Aminah, S.Kom., M.Cs.',
                'email' => 'siti.aminah@example.com',
                'password' => Hash::make('password'),
                'role' => 'DOSEN',
            ],
            [
                'name' => 'Asesor Akreditasi Eksternal',
                'email' => 'asesor@example.com',
                'password' => Hash::make('password'),
                'role' => 'ASESOR',
            ],
            [
                'name' => 'Bambang Sutejo, S.Kom. (Laboran)',
                'email' => 'tendik@example.com',
                'password' => Hash::make('password'),
                'role' => 'TENDIK',
            ],
            [
                'name' => 'Fajar Pratama (Mahasiswa)',
                'email' => 'mahasiswa@example.com',
                'password' => Hash::make('password'),
                'role' => 'MAHASISWA',
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
