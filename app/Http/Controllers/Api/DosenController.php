<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\DosenImport;
use App\Models\Dosen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class DosenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Dosen::with(['jabatanAkademik', 'pendidikan', 'bidangKeahlian']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_dosen', 'like', "%{$search}%")
                    ->orWhere('nidn', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status_dosen', $status);
        }

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $dosen = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $dosen,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_jabatan' => 'required|exists:jabatan_akademik,id_jabatan',
            'id_pendidikan' => 'required|exists:pendidikan,id_pendidikan',
            'nidn' => 'required|string|unique:dosen,nidn|max:30',
            'nama_dosen' => 'required|string|max:255',
            'email' => 'required|email|unique:dosen,email|max:255',
            'status_dosen' => 'nullable|string|max:50',
            'sertifikasi' => 'nullable|string|max:255',
            'bidang_keahlian_ids' => 'nullable|array',
            'bidang_keahlian_ids.*' => 'exists:bidang_keahlian,id_bidang_keahlian',
        ]);

        $dosen = Dosen::create($validated);

        if (! empty($validated['bidang_keahlian_ids'])) {
            $dosen->bidangKeahlian()->sync($validated['bidang_keahlian_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil ditambahkan.',
            'data' => $dosen->load(['jabatanAkademik', 'pendidikan', 'bidangKeahlian']),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $dosen = Dosen::with(['jabatanAkademik', 'pendidikan', 'bidangKeahlian', 'penelitian', 'pkm'])->find($id);

        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $dosen]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $dosen = Dosen::find($id);

        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'id_jabatan' => 'sometimes|required|exists:jabatan_akademik,id_jabatan',
            'id_pendidikan' => 'sometimes|required|exists:pendidikan,id_pendidikan',
            'nidn' => 'sometimes|required|string|max:30|unique:dosen,nidn,'.$id.',id_dosen',
            'nama_dosen' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:dosen,email,'.$id.',id_dosen',
            'status_dosen' => 'nullable|string|max:50',
            'sertifikasi' => 'nullable|string|max:255',
            'bidang_keahlian_ids' => 'nullable|array',
            'bidang_keahlian_ids.*' => 'exists:bidang_keahlian,id_bidang_keahlian',
        ]);

        $dosen->update($validated);

        if ($request->has('bidang_keahlian_ids')) {
            $dosen->bidangKeahlian()->sync($validated['bidang_keahlian_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil diperbarui.',
            'data' => $dosen->fresh(['jabatanAkademik', 'pendidikan', 'bidangKeahlian']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $dosen = Dosen::find($id);

        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        $dosen->update(['status_dosen' => 'NONAKTIF']);
        $dosen->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil dinonaktifkan (soft deleted).',
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $dosen = Dosen::onlyTrashed()->find($id);

        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Dosen terhapus tidak ditemukan.'], 404);
        }

        $dosen->restore();
        $dosen->update(['status_dosen' => 'DTPS']);

        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil diaktifkan kembali.',
            'data' => $dosen,
        ]);
    }

    public function importExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120', // Maksimal 5MB
        ]);

        try {
            Excel::import(new DosenImport, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Data dosen berhasil diimpor dari file Excel.',
            ]);
        } catch (ValidationException $e) {
            $failures = $e->failures();
            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            return response()->json([
                'success' => false,
                'message' => 'Terdapat kesalahan validasi pada data Excel.',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor file: '.$e->getMessage(),
            ], 500);
        }
    }
}
