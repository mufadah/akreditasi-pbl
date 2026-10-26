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

        // Filter pencarian nama instansi/mitra, email, atau alamat
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_mitra', 'like', "%{$search}%")
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
        // Validasi input data mitra (mendukung nama_instansi maupun nama_mitra)
        $validated = $request->validate([
            'nama_instansi' => 'nullable|string|max:255',
            'nama_mitra' => 'nullable|string|max:255',
            'jenis_mitra' => 'nullable|string|max:100',
            'alamat' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telepon' => 'nullable|string|max:50',
        ]);

        $namaMitra = $validated['nama_mitra'] ?? ($validated['nama_instansi'] ?? null);
        if (! $namaMitra) {
            return response()->json([
                'message' => 'The nama_instansi field is required.',
                'errors' => [
                    'nama_instansi' => ['Nama instansi / nama mitra wajib diisi.'],
                ],
            ], 422);
        }

        // Simpan ke database sesuai struktur kolom schema mitra
        $mitra = Mitra::create([
            'nama_mitra' => $namaMitra,
            'jenis_mitra' => $validated['jenis_mitra'] ?? 'Instansi',
            'alamat' => $validated['alamat'] ?? null,
            'email' => $validated['email'] ?? null,
            'telepon' => $validated['telepon'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data mitra berhasil ditambahkan.',
            'data' => $mitra,
        ], 201);
    }

    // Ambil detail satu mitra beserta relasi kerja sama
    public function show(int $id): JsonResponse
    {
        $mitra = Mitra::with(['kerjaSama'])->find($id);

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
            'nama_instansi' => 'nullable|string|max:255',
            'nama_mitra' => 'nullable|string|max:255',
            'jenis_mitra' => 'nullable|string|max:100',
            'alamat' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telepon' => 'nullable|string|max:50',
        ]);

        $updateData = [];
        $namaMitra = $validated['nama_mitra'] ?? ($validated['nama_instansi'] ?? null);
        if ($namaMitra !== null) {
            $updateData['nama_mitra'] = $namaMitra;
        }

        foreach (['jenis_mitra', 'alamat', 'email', 'telepon'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        // Update data di database
        $mitra->update($updateData);

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

        // Cek apakah mitra masih dipakai di data kerja sama (Flowchart Fitur 6)
        if ($mitra->kerjaSama()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Mitra Masih Bekerja sama: Mitra tidak dapat dihapus karena masih terkait dengan data kerja sama.',
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
