<?php

namespace App\Imports;

use App\Models\Dosen;
use App\Models\JenisKerjaSama;
use App\Models\KerjaSama;
use App\Models\Mitra;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class KerjaSamaImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        $validData = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $data = $row->toArray();

            if (count(array_filter(
                $data,
                fn ($value) => $value !== null && $value !== ''
            )) === 0) {
                continue;
            }

            $validator = Validator::make($data, [
                'nama_mitra' => ['required', 'string'],
                'nama_jenis_kerjasama' => ['required', 'string'],
                'nidn' => ['required', 'string'],
                'judul_kerja_sama' => ['required', 'string', 'max:255'],
                'tingkat' => ['required', 'string', 'max:100'],
                'bentuk_kegiatan' => ['required', 'string'],
                'nomor_dokumen' => ['required', 'string', 'max:255'],
                'jenis_dokumen' => ['required', 'string', 'max:255'],
                'tanggal_mulai' => ['required'],
                'tanggal_selesai' => ['nullable'],
                'status' => ['nullable', 'string', 'max:255'],
                'bukti_dokumen' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = 'Baris '.($index + 2).': '.$message;
                }

                continue;
            }

            $mitra = Mitra::query()
                ->where('nama_mitra', trim($data['nama_mitra']))
                ->first();

            $jenis = JenisKerjaSama::query()
                ->where(
                    'nama_jenis_kerjasama',
                    trim($data['nama_jenis_kerjasama'])
                )
                ->first();

            $dosen = Dosen::query()
                ->where('nidn', trim((string) $data['nidn']))
                ->first();

            if (! $mitra) {
                $errors[] = 'Baris '.($index + 2).': Nama mitra tidak ditemukan.';

                continue;
            }

            if (! $jenis) {
                $errors[] = 'Baris '.($index + 2).': Jenis kerja sama tidak ditemukan.';

                continue;
            }

            if (! $dosen) {
                $errors[] = 'Baris '.($index + 2).': NIDN dosen tidak ditemukan.';

                continue;
            }

            try {
                $tanggalMulai = $this->normalizeDate($data['tanggal_mulai']);
                $tanggalSelesai = $this->normalizeDate(
                    $data['tanggal_selesai'] ?? null
                );
            } catch (\Throwable $exception) {
                $errors[] = 'Baris '.($index + 2).': Format tanggal tidak valid.';

                continue;
            }

            if (! $tanggalMulai) {
                $errors[] = 'Baris '.($index + 2).': Tanggal mulai wajib diisi.';

                continue;
            }

            $validData[] = [
                'id_mitra' => $mitra->id_mitra,
                'id_jenis_kerjasama' => $jenis->id_jenis_kerjasama,
                'id_dosen' => $dosen->id_dosen,
                'judul_kerja_sama' => $data['judul_kerja_sama'],
                'tingkat' => $data['tingkat'],
                'bentuk_kegiatan' => $data['bentuk_kegiatan'],
                'nomor_dokumen' => $data['nomor_dokumen'],
                'jenis_dokumen' => $data['jenis_dokumen'],
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'status' => $data['status'] ?: 'Aktif',
                'bukti_dokumen' => $data['bukti_dokumen'] ?? null,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => $errors,
            ]);
        }

        DB::transaction(function () use ($validData): void {
            foreach ($validData as $data) {
                KerjaSama::create($data);
            }
        });
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 10000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)
                    ->format('Y-m-d');
            } catch (\Throwable $exception) {
                throw new \InvalidArgumentException(
                    'Format tanggal Excel tidak valid.'
                );
            }
        }

        $value = trim((string) $value);

        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if (
                $date !== false
                && (
                    $errors === false
                    || (
                        $errors['warning_count'] === 0
                        && $errors['error_count'] === 0
                    )
                )
            ) {
                return $date->format('Y-m-d');
            }
        }

        throw new \InvalidArgumentException(
            'Format tanggal tidak valid.'
        );
    }
}
