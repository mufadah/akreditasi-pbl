<?php

namespace App\Imports;

use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\Pendidikan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DosenImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * Format kolom Excel/CSV (heading):
     * id_jabatan (atau jabatan), id_pendidikan (atau pendidikan), nidn, nuptk, nama_dosen (atau nama), email, status_dosen, sertifikasi
     */
    public function model(array $row): Dosen
    {
        $nama = $row['nama_dosen'] ?? $row['nama'] ?? 'Tanpa Nama';
        $nidn = (string) ($row['nidn'] ?? '');
        $nuptk = isset($row['nuptk']) && $row['nuptk'] !== '' ? (string) $row['nuptk'] : null;

        // Resolve jabatan
        $idJabatan = $row['id_jabatan'] ?? null;
        if (! $idJabatan && isset($row['jabatan'])) {
            $jab = JabatanAkademik::where('nama_jabatan', $row['jabatan'])->first();
            $idJabatan = $jab ? $jab->id_jabatan : 1;
        }
        $idJabatan = $idJabatan ?: 1;

        // Resolve pendidikan
        $idPendidikan = $row['id_pendidikan'] ?? null;
        if (! $idPendidikan && isset($row['pendidikan'])) {
            $pend = Pendidikan::where('jenjang', $row['pendidikan'])->first();
            $idPendidikan = $pend ? $pend->id_pendidikan : 1;
        }
        $idPendidikan = $idPendidikan ?: 1;

        // Email fallback
        $email = $row['email'] ?? ($nidn ? $nidn.'@polines.ac.id' : uniqid('dosen_').'@polines.ac.id');

        // Status DTPS / DTT
        $statusDosen = $row['status_dosen'] ?? 'DTPS';
        if (strtoupper($statusDosen) === 'DTT') {
            $statusDosen = 'DTT';
        } elseif (strtoupper($statusDosen) === 'NONAKTIF') {
            $statusDosen = 'NONAKTIF';
        } else {
            $statusDosen = 'DTPS';
        }

        return new Dosen([
            'id_jabatan' => $idJabatan,
            'id_pendidikan' => $idPendidikan,
            'nidn' => $nidn,
            'nuptk' => $nuptk,
            'nama_dosen' => $nama,
            'email' => $email,
            'status_dosen' => $statusDosen,
            'sertifikasi' => $row['sertifikasi'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            '*.nidn' => 'required|distinct|unique:dosen,nidn',
            '*.nuptk' => 'nullable|string|max:50',
            '*.nama_dosen' => 'sometimes|string|max:255',
            '*.nama' => 'sometimes|string|max:255',
            '*.email' => 'nullable|email|distinct|unique:dosen,email',
            '*.status_dosen' => 'nullable|string|max:50',
            '*.sertifikasi' => 'nullable|string|max:255',
        ];
    }
}
