<?php

namespace App\Imports;

use App\Models\Pendidikan;
use App\Models\TenagaKependidikan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TendikDataImport implements ToCollection, WithHeadingRow
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
                'nama_tendik' => ['required', 'string', 'max:255'],
                'jabatan_tendik' => ['required', 'string', 'max:255'],
                'jenjang' => ['required', 'string', 'max:255'],
                'universitas' => ['required', 'string', 'max:255'],
                'program_studi' => ['required', 'string', 'max:255'],
                'tahun_lulus' => ['required', 'integer', 'digits:4'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = 'Sheet Tenaga Kependidikan, baris '.($index + 2).': '.$message;
                }

                continue;
            }

            $valid[] = $validator->validated();
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => $errors]);
        }

        foreach ($valid as $data) {
            $pendidikan = Pendidikan::firstOrCreate([
                'jenjang' => $data['jenjang'],
                'universitas' => $data['universitas'],
                'program_studi' => $data['program_studi'],
                'tahun_lulus' => $data['tahun_lulus'],
            ]);

            TenagaKependidikan::withTrashed()->updateOrCreate(
                ['nip' => $data['nip']],
                [
                    'id_pendidikan' => $pendidikan->id_pendidikan,
                    'nama_tendik' => $data['nama_tendik'],
                    'jabatan_tendik' => $data['jabatan_tendik'],
                ]
            );
        }
    }
}
