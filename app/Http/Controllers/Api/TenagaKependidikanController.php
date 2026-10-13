<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenagaKependidikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenagaKependidikanController extends Controller
{
    // Ambil daftar tendik dengan filter pencarian dan relasi pendidikan
    public function index(Request $request): JsonResponse
    {
        // Query dengan relasi pendidikan
        $query = TenagaKependidikan::with('pendidikan');

        // Filter pencarian nama, NIP, atau jabatan
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_tendik', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('jabatan_tendik', 'like', "%{$search}%");
            });
        }

        // Paginasi data 10 per halaman
        $tendik = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $tendik,
        ]);
    }

    // Tambah data tendik baru
    public function store(Request $request): JsonResponse
    {
        // Validasi input data tendik
        $validated = $request->validate([
            'id_pendidikan' => 'required|exists:pendidikan,id_pendidikan',
            'nama_tendik' => 'required|string|max:255',
            'jabatan_tendik' => 'required|string|max:255',
            'nip' => 'required|string|max:50|unique:tenaga_kependidikan,nip',
        ]);

        // Simpan ke database
        $tendik = TenagaKependidikan::create($validated);

        // Kembalikan respon sukses beserta relasi pendidikan
        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil ditambahkan.',
            'data' => $tendik->load('pendidikan'),
        ], 201);
    }

    // Ambil detail satu tendik
    public function show(int $id): JsonResponse
    {
        $tendik = TenagaKependidikan::with('pendidikan')->find($id);

        // Cek data ada atau tidak
        if (! $tendik) {
            return response()->json(['success' => false, 'message' => 'Data tendik tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $tendik]);
    }

    // Update data tendik
    public function update(Request $request, int $id): JsonResponse
    {
        $tendik = TenagaKependidikan::find($id);

        // Cek data ada atau tidak
        if (! $tendik) {
            return response()->json(['success' => false, 'message' => 'Data tendik tidak ditemukan.'], 404);
        }

        // Validasi input data tendik untuk update
        $validated = $request->validate([
            'id_pendidikan' => 'sometimes|required|exists:pendidikan,id_pendidikan',
            'nama_tendik' => 'sometimes|required|string|max:255',
            'jabatan_tendik' => 'sometimes|required|string|max:255',
            'nip' => 'sometimes|required|string|max:50|unique:tenaga_kependidikan,nip,'.$id.',id_tendik',
        ]);

        // Update data di database
        $tendik->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil diperbarui.',
            'data' => $tendik->fresh('pendidikan'),
        ]);
    }

    // Hapus data tendik
    public function destroy(int $id): JsonResponse
    {
        $tendik = TenagaKependidikan::find($id);

        // Cek data ada atau tidak
        if (! $tendik) {
            return response()->json(['success' => false, 'message' => 'Data tendik tidak ditemukan.'], 404);
        }

        // Hapus dari database
        $tendik->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil dihapus.',
        ]);
    }
}
