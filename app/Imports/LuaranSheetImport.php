<?php

namespace App\Imports;

use App\Models\Dosen;
use App\Models\JenisLuaran;
use App\Models\Luaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class LuaranSheetImport implements Import, ToCollection, WithHeadingRow
{
    private array $fields;

    public function __construct(
        private readonly string $kategori
    ) {
        $this->fields = match ($kategori) {
            'Buku' => [
                'judul_buku',
                'isbn',
                'penerbit_buku',
                'tahun_terbit',
                'jenis_buku',
                'bidang_ilmu',
                'halaman_buku',
                'tanggal_terbit',
                'tautan_buku',
            ],
            'Jurnal' => [
                'nama_jurnal',
                'issn',
                'volume',
                'tanggal_terbit',
                'doi',
                'nomor_edisi',
                'halaman_jurnal',
                'indeksasi',
                'tautan_jurnal',
            ],
            'Prosiding' => [
                'nama_seminar',
                'nama_prosiding',
                'isbn_prosiding',
                'penyelenggara',
                'tanggal_seminar',
                'lokasi_seminar',
                'halaman_prosiding',
                'doi_prosiding',
                'tautan_seminar',
            ],
            'HKI' => [
                'jenis_hki',
                'judul_hak_cipta',
                'nomor_pendaftaran_hki',
                'nomor_sertifikat_hki',
                'tanggal_pendaftaran_hki',
                'tanggal_terbit_sertifikat_hki',
                'pemegang_hki',
                'status_hki',
                'tautan_hki',
            ],
            'Produk' => [
                'nama_produk',
                'jenis_produk',
                'status_pengembangan',
                'tingkat_kesiapanterapan',
                'tanggal_pembuatan',
                'deskripsi_produk',
                'tautan_produk',
            ],
            default => throw new \InvalidArgumentException(
                'Kategori luaran tidak valid.'
            ),
        };
    }

    public function collection(Collection $rows): void
    {
        $dataValid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $data = $row->toArray();

            if (count(array_filter(
                $data,
                fn ($value) => $value !== null && $value !== ''
            )) === 0) {
                continue;
            }

            if (isset($data['nidn'])) {
                $data['nidn'] = trim((string) $data['nidn']);
            }

            if (isset($data['nama_jenis_luaran'])) {
                $data['nama_jenis_luaran'] = trim(
                    (string) $data['nama_jenis_luaran']
                );
            }

            if (isset($data['indeks'])) {
                $data['indeks'] = trim((string) $data['indeks']);
            }

            $this->normalizeDates($data);

            if (isset($data['id_kegiatan']) && is_numeric($data['id_kegiatan'])) {
                $data['id_kegiatan'] = (int) $data['id_kegiatan'];
            }

            if (isset($data['tahun_terbit']) && is_numeric($data['tahun_terbit'])) {
                $data['tahun_terbit'] = (int) $data['tahun_terbit'];
            }

            $rules = [
                'nidn' => ['required', 'string', 'max:30'],
                'id_kegiatan' => [
                    'nullable',
                    'integer',
                    'exists:kegiatan,id_kegiatan',
                ],
                'nama_jenis_luaran' => ['required', 'string'],
                'indeks' => ['nullable', 'string'],
            ];

            $rules = array_merge($rules, match ($this->kategori) {
                'Buku' => [
                    'judul_buku' => ['required', 'string', 'max:255'],
                    'isbn' => ['nullable', 'string', 'max:255'],
                    'penerbit_buku' => ['nullable', 'string', 'max:255'],
                    'tahun_terbit' => ['required', 'integer', 'digits:4'],
                    'jenis_buku' => ['nullable', 'string', 'max:255'],
                    'bidang_ilmu' => ['nullable', 'string', 'max:255'],
                    'halaman_buku' => ['nullable', 'integer', 'min:1'],
                    'tanggal_terbit' => ['nullable', 'date'],
                    'tautan_buku' => ['nullable', 'url', 'max:2048'],
                ],
                'Jurnal' => [
                    'nama_jurnal' => ['required', 'string', 'max:255'],
                    'issn' => ['nullable', 'string', 'max:255'],
                    'volume' => ['nullable', 'string', 'max:255'],
                    'tanggal_terbit' => ['nullable', 'date'],
                    'doi' => ['nullable', 'string', 'max:255'],
                    'nomor_edisi' => ['nullable', 'string', 'max:255'],
                    'halaman_jurnal' => ['nullable', 'integer', 'min:1'],
                    'indeksasi' => ['nullable', 'string', 'max:255'],
                    'tautan_jurnal' => ['nullable', 'url', 'max:2048'],
                ],
                'Prosiding' => [
                    'nama_seminar' => ['required', 'string', 'max:255'],
                    'nama_prosiding' => ['nullable', 'string', 'max:255'],
                    'isbn_prosiding' => ['nullable', 'string', 'max:255'],
                    'penyelenggara' => ['nullable', 'string', 'max:255'],
                    'tanggal_seminar' => ['nullable', 'date'],
                    'lokasi_seminar' => ['nullable', 'string', 'max:255'],
                    'halaman_prosiding' => ['nullable', 'integer', 'min:1'],
                    'doi_prosiding' => ['nullable', 'string', 'max:255'],
                    'tautan_seminar' => ['nullable', 'url', 'max:2048'],
                ],
                'HKI' => [
                    'jenis_hki' => ['nullable', 'string', 'max:255'],
                    'judul_hak_cipta' => ['required', 'string', 'max:255'],
                    'nomor_pendaftaran_hki' => ['nullable', 'string', 'max:255'],
                    'nomor_sertifikat_hki' => ['nullable', 'string', 'max:255'],
                    'tanggal_pendaftaran_hki' => ['nullable', 'date'],
                    'tanggal_terbit_sertifikat_hki' => ['nullable', 'date'],
                    'pemegang_hki' => ['nullable', 'string', 'max:255'],
                    'status_hki' => ['nullable', 'string', 'max:255'],
                    'tautan_hki' => ['nullable', 'url', 'max:2048'],
                ],
                'Produk' => [
                    'nama_produk' => ['required', 'string', 'max:255'],
                    'jenis_produk' => ['nullable', 'string', 'max:255'],
                    'status_pengembangan' => ['nullable', 'string', 'max:255'],
                    'tingkat_kesiapanterapan' => ['nullable', 'string', 'max:255'],
                    'tanggal_pembuatan' => ['nullable', 'date'],
                    'deskripsi_produk' => ['nullable', 'string'],
                    'tautan_produk' => ['nullable', 'url', 'max:2048'],
                ],
            });

            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = 'Baris '.($index + 2).': '.$message;
                }

                continue;
            }

            $dosen = Dosen::where('nidn', $data['nidn'])->first();

            if (! $dosen) {
                $errors[] = 'Baris '.($index + 2).': Dosen dengan NIDN '.$data['nidn'].' tidak ditemukan atau sudah dihapus.';

                continue;
            }

            $jenisLuaran = $this->resolveJenisLuaran($data);

            if (! $jenisLuaran) {
                $errors[] = 'Baris '.($index + 2).': Jenis luaran tidak ditemukan atau tidak dapat ditentukan secara unik. Periksa nama_jenis_luaran dan indeks.';

                continue;
            }

            $record = [
                'id_dosen' => $dosen->id_dosen,
                'id_kegiatan' => $data['id_kegiatan'] ?? null,
                'id_jenis_luaran' => $jenisLuaran->id_jenis_luaran,
            ];

            foreach ($this->fields as $field) {
                $record[$field] = $data[$field] ?? null;
            }

            $dataValid[] = $record;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => $errors,
            ]);
        }

        foreach ($dataValid as $data) {
            Luaran::create($data);
        }
    }

    private function resolveJenisLuaran(array $data): ?JenisLuaran
    {
        $query = JenisLuaran::query();

        $query->where(function ($query): void {
            match ($this->kategori) {
                'Buku' => $query->where('nama_jenis_luaran', 'like', '%Buku%'),
                'Jurnal' => $query->where('nama_jenis_luaran', 'like', '%Jurnal%'),
                'Prosiding' => $query->where('nama_jenis_luaran', 'like', '%Prosiding%'),
                'HKI' => $query->where('nama_jenis_luaran', 'like', '%HKI%')
                    ->orWhere('nama_jenis_luaran', 'like', '%Hak Cipta%'),
                'Produk' => $query->where('nama_jenis_luaran', 'like', '%Produk%'),
            };
        });

        $query->where('nama_jenis_luaran', $data['nama_jenis_luaran']);

        if (! empty($data['indeks'])) {
            $query->where('indeks', $data['indeks']);
        }

        $jenis = $query->get();

        return $jenis->count() === 1 ? $jenis->first() : null;
    }

    private function normalizeDates(array &$data): void
    {
        $dateFields = [
            'tanggal_terbit',
            'tanggal_seminar',
            'tanggal_pendaftaran_hki',
            'tanggal_terbit_sertifikat_hki',
            'tanggal_pembuatan',
        ];

        foreach ($dateFields as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                continue;
            }

            try {
                if ($data[$field] instanceof \DateTimeInterface) {
                    $data[$field] = $data[$field]->format('Y-m-d');
                } elseif (is_numeric($data[$field])) {
                    $data[$field] = ExcelDate::excelToDateTimeObject(
                        (float) $data[$field]
                    )->format('Y-m-d');
                } else {
                    $timestamp = strtotime((string) $data[$field]);

                    $data[$field] = $timestamp === false
                        ? 'invalid-date'
                        : date('Y-m-d', $timestamp);
                }
            } catch (Throwable) {
                $data[$field] = 'invalid-date';
            }
        }
    }
}
