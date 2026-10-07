<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisPublikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JenisPublikasiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => JenisPublikasi::all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_jenis_publikasi' => 'required|string|max:255|unique:jenis_publikasi,nama_jenis_publikasi',
            'indeks' => 'nullable|string|max:100',
        ]);

        $publikasi = JenisPublikasi::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis publikasi berhasil ditambahkan.',
            'data' => $publikasi,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $publikasi = JenisPublikasi::find($id);
        if (! $publikasi) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $publikasi]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $publikasi = JenisPublikasi::find($id);
        if (! $publikasi) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'nama_jenis_publikasi' => 'required|string|max:255|unique:jenis_publikasi,nama_jenis_publikasi,'.$id.',id_jenis_publikasi',
            'indeks' => 'nullable|string|max:100',
        ]);

        $publikasi->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenis publikasi berhasil diperbarui.',
            'data' => $publikasi,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $publikasi = JenisPublikasi::find($id);
        if (! $publikasi) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $publikasi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jenis publikasi berhasil dihapus.',
        ]);
    }
}
