<?php

namespace App\Imports;

use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KegiatanImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        private readonly string $jenisKegiatan
    ) {}

    public function collection(Collection $rows): void
    {
        $jenis = JenisKegiatan::where(
            'nama_jenis_kegiatan',
            $this->jenisKegiatan
        )->first();

        if (! $jenis) {
            throw ValidationException::withMessages([
                'jenis_kegiatan' => [
                    "Jenis kegiatan {$this->jenisKegiatan} tidak ditemukan.",
                ],
            ]);
        }

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

            $validator = Validator::make($data, [
                'jenis_kegiatan' => [
                    'nullable',
                    'string',
                    Rule::in([$this->jenisKegiatan]),
                ],
                'judul_kegiatan' => ['required', 'string', 'max:255'],
                'lokasi_kegiatan' => ['nullable', 'string', 'max:255'],
                'tahun_usulan' => ['nullable', 'integer', 'digits:4'],
                'tahun_kegiatan' => ['nullable', 'integer', 'digits:4'],
                'tahun_pelaksanaan' => ['nullable', 'integer', 'digits:4'],
                'lama_kegiatan' => ['nullable', 'string', 'max:100'],
                'status_kegiatan' => ['nullable', 'string', 'max:50'],
                'in_kind' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = 'Baris '.($index + 2).': '.$message;
                }

                continue;
            }

            $dataValid[] = [
                'id_jenis_kegiatan' => $jenis->id_jenis_kegiatan,
                'judul_kegiatan' => $data['judul_kegiatan'],
                'lokasi_kegiatan' => $data['lokasi_kegiatan'] ?? null,
                'tahun_usulan' => $data['tahun_usulan'] ?? null,
                'tahun_kegiatan' => $data['tahun_kegiatan'] ?? null,
                'tahun_pelaksanaan' => $data['tahun_pelaksanaan'] ?? null,
                'lama_kegiatan' => $data['lama_kegiatan'] ?? null,
                'status_kegiatan' => $data['status_kegiatan'] ?? 'Usulan',
                'in_kind' => $data['in_kind'] ?? null,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => $errors,
            ]);
        }

        DB::transaction(function () use ($dataValid): void {
            foreach ($dataValid as $data) {
                Kegiatan::create($data);
            }
        });
    }
}
