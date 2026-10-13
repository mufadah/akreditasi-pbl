<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisLuaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JenisLuaranController extends Controller
{
    /**
     * Ambil semua data jenis luaran.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => JenisLuaran::all(),
        ]);
    }

    /**
     * Tambah data jenis luaran baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_jenis_luaran' => 'required|string|max:255',
            'indeks' => 'nullable|string|max:255',
        ]);

        $jenis = JenisLuaran::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis luaran berhasil ditambahkan.',
            'data' => $jenis,
        ], 201);
    }

    /**
     * Ambil detail satu jenis luaran.
     */
    public function show(int $id): JsonResponse
    {
        $jenis = JenisLuaran::find($id);

        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $jenis]);
    }

    /**
     * Update data jenis luaran.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $jenis = JenisLuaran::find($id);

        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'nama_jenis_luaran' => 'required|string|max:255',
            'indeks' => 'nullable|string|max:255',
        ]);

        $jenis->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis luaran berhasil diperbarui.',
            'data' => $jenis,
        ]);
    }

    /**
     * Hapus data jenis luaran.
     */
    public function destroy(int $id): JsonResponse
    {
        $jenis = JenisLuaran::find($id);

        if (! $jenis) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $jenis->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jenis luaran berhasil dihapus.',
        ]);
    }
}
