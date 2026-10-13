<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mitra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MitraController extends Controller
{
    // Ambil daftar mitra dengan filter pencarian
    public function index(Request $request): JsonResponse
    {
        $query = Mitra::query();

        // Filter pencarian nama instansi, email, atau alamat
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_instansi', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%");
            });
        }

        // Paginasi data 10 per halaman
        $mitra = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $mitra,
        ]);
    }

    // Tambah data mitra baru
    public function store(Request $request): JsonResponse
    {
        // Validasi input data mitra
        $validated = $request->validate([
            'nama_instansi' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telepon' => 'nullable|string|max:50',
        ]);

        // Simpan ke database
        $mitra = Mitra::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil ditambahkan.',
            'data' => $mitra,
        ], 201);
    }

    // Ambil detail satu mitra beserta relasi PKM dan kerja sama
    public function show(int $id): JsonResponse
    {
        $mitra = Mitra::with(['pkm', 'kerjaSama'])->find($id);

        // Cek data ada atau tidak
        if (! $mitra) {
            return response()->json([
                'success' => false,
                'message' => 'Data mitra tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $mitra,
        ]);
    }

    // Update data mitra
    public function update(Request $request, int $id): JsonResponse
    {
        $mitra = Mitra::find($id);

        // Cek data ada atau tidak
        if (! $mitra) {
            return response()->json([
                'success' => false,
                'message' => 'Data mitra tidak ditemukan.',
            ], 404);
        }

        // Validasi input data mitra untuk update
        $validated = $request->validate([
            'nama_instansi' => 'sometimes|required|string|max:255',
            'alamat' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telepon' => 'nullable|string|max:50',
        ]);

        // Update data di database
        $mitra->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil diperbarui.',
            'data' => $mitra,
        ]);
    }

    // Hapus data mitra
    public function destroy(int $id): JsonResponse
    {
        $mitra = Mitra::find($id);

        // Cek data ada atau tidak
        if (! $mitra) {
            return response()->json([
                'success' => false,
                'message' => 'Data mitra tidak ditemukan.',
            ], 404);
        }

        // Cek apakah mitra masih dipakai di data PKM atau kerja sama
        if ($mitra->pkm()->exists() || $mitra->kerjaSama()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Mitra tidak dapat dihapus karena masih terkait dengan data PKM atau kerja sama.',
            ], 422);
        }

        // Hapus dari database
        $mitra->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil dihapus.',
        ]);
    }
}
