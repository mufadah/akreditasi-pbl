<?php

namespace App\Imports;

use App\Models\Dosen;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DosenImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * Format kolom Excel (heading):
     * id_jabatan, id_pendidikan, nidn, nama_dosen, email, status_dosen, sertifikasi
     */
    public function model(array $row): Dosen
    {
        return new Dosen([
            'id_jabatan' => $row['id_jabatan'],
            'id_pendidikan' => $row['id_pendidikan'],
            'nidn' => (string) $row['nidn'],
            'nama_dosen' => $row['nama_dosen'],
            'email' => $row['email'],
            'status_dosen' => $row['status_dosen'] ?? 'DTPS',
            'sertifikasi' => $row['sertifikasi'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            '*.id_jabatan' => 'required|exists:jabatan_akademik,id_jabatan',
            '*.id_pendidikan' => 'required|exists:pendidikan,id_pendidikan',
            '*.nidn' => 'required|string|distinct|unique:dosen,nidn',
            '*.nama_dosen' => 'required|string|max:255',
            '*.email' => 'required|email|distinct|unique:dosen,email',
            '*.status_dosen' => 'nullable|string|max:50',
            '*.sertifikasi' => 'nullable|string|max:255',
        ];
    }
}
