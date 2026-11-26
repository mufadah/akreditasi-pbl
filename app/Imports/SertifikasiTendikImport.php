<?php

namespace App\Imports;

use App\Models\Sertifikasi;
use App\Models\TenagaKependidikan;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class SertifikasiTendikImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        $errors = [];
        $valid = [];

        foreach ($rows as $index => $row) {
            $data = $row->toArray();

            if (count(array_filter($data, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $validator = Validator::make($data, [
                'nip' => ['required', 'string', 'max:50'],
                'jenis_sertifikasi' => ['required', 'string', 'max:255'],
                'bidang_studi' => ['nullable', 'string', 'max:255'],
                'nomor_registrasi_pendidik' => ['nullable', 'string', 'max:100'],
                'nomor_sk' => ['nullable', 'string', 'max:100'],
                'nomor_sertifikat' => ['nullable', 'string', 'max:100'],
                'lembaga_penerbit' => ['nullable', 'string', 'max:255'],
                'tahun_sertifikasi' => ['required'],
                'tanggal_kedaluwarsa' => ['nullable'],
                'status_sertifikasi' => ['nullable', 'string', 'max:50'],
                'url_sertifikasi' => ['nullable', 'string', 'max:2048'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = 'Sheet Sertifikasi, baris '.($index + 2).': '.$message;
                }

                continue;
            }

            $data = $validator->validated();

            $tendik = TenagaKependidikan::withTrashed()
                ->where('nip', $data['nip'])
                ->first();

            if (! $tendik) {
                $errors[] = 'Sheet Sertifikasi, baris '.($index + 2).': NIP '.$data['nip'].' tidak ditemukan.';

                continue;
            }

            try {
                $tahun = $this->normalizeDate($data['tahun_sertifikasi']);

                $kedaluwarsa = ! empty($data['tanggal_kedaluwarsa'])
                    ? $this->normalizeDate($data['tanggal_kedaluwarsa'])
                    : null;
            } catch (\InvalidArgumentException $exception) {
                $errors[] = 'Sheet Sertifikasi, baris '.($index + 2).': '.$exception->getMessage();

                continue;
            }

            $valid[] = [$tendik, $data, $tahun, $kedaluwarsa];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => $errors,
            ]);
        }

        foreach ($valid as [$tendik, $data, $tahun, $kedaluwarsa]) {
            $identity = ! empty($data['nomor_sertifikat'])
                ? [
                    'id_tendik' => $tendik->id_tendik,
                    'nomor_sertifikat' => $data['nomor_sertifikat'],
                ]
                : [
                    'id_tendik' => $tendik->id_tendik,
                    'jenis_sertifikasi' => $data['jenis_sertifikasi'],
                    'tahun_sertifikasi' => $tahun,
                ];

            Sertifikasi::updateOrCreate($identity, [
                'id_dosen' => null,
                'jenis_sertifikasi' => $data['jenis_sertifikasi'],
                'bidang_studi' => $data['bidang_studi'] ?? null,
                'nomor_registrasi_pendidik' => $data['nomor_registrasi_pendidik'] ?? null,
                'nomor_sk' => $data['nomor_sk'] ?? null,
                'nomor_sertifikat' => $data['nomor_sertifikat'] ?? null,
                'lembaga_penerbit' => $data['lembaga_penerbit'] ?? null,
                'tahun_sertifikasi' => $tahun,
                'tanggal_kedaluwarsa' => $kedaluwarsa,
                'status_sertifikasi' => $data['status_sertifikasi'] ?? 'Aktif',
                'url_sertifikasi' => $data['url_sertifikasi'] ?? '',
            ]);
        }
    }

    private function normalizeDate(mixed $value): string
    {
        if ($value === null || trim((string) $value) === '') {
            throw new \InvalidArgumentException('Tanggal sertifikasi wajib diisi.');
        }

        if (is_numeric($value) && (float) $value > 10000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $exception) {
                throw new \InvalidArgumentException('Format tanggal Excel tidak valid.');
            }
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{4}$/', $value)) {
            $year = (int) $value;

            if ($year < 1900 || $year > 2100) {
                throw new \InvalidArgumentException('Tahun sertifikasi harus antara 1900 sampai 2100.');
            }

            return $value.'-01-01';
        }

        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $dateErrors = DateTimeImmutable::getLastErrors();

            if (
                $date !== false
                && (
                    $dateErrors === false
                    || (
                        $dateErrors['warning_count'] === 0
                        && $dateErrors['error_count'] === 0
                    )
                )
            ) {
                return $date->format('Y-m-d');
            }
        }

        throw new \InvalidArgumentException(
            'Format tanggal tidak valid. Gunakan YYYY, YYYY-MM-DD, DD/MM/YYYY, atau DD-MM-YYYY.'
        );
    }
}
