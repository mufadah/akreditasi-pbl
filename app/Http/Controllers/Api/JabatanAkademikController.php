<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JabatanAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JabatanAkademikController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => JabatanAkademik::all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:100|unique:jabatan_akademik,nama_jabatan',
        ]);

        $jabatan = JabatanAkademik::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jabatan akademik berhasil ditambahkan.',
            'data' => $jabatan,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $jabatan = JabatanAkademik::find($id);
        if (! $jabatan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $jabatan]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $jabatan = JabatanAkademik::find($id);
        if (! $jabatan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:100|unique:jabatan_akademik,nama_jabatan,'.$id.',id_jabatan',
        ]);

        $jabatan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jabatan akademik berhasil diperbarui.',
            'data' => $jabatan,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $jabatan = JabatanAkademik::find($id);
        if (! $jabatan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $jabatan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jabatan akademik berhasil dihapus.',
        ]);
    }
}
