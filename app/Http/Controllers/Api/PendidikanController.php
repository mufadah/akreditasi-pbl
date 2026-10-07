<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pendidikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendidikanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Pendidikan::all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jenjang' => 'required|string|max:50|unique:pendidikan,jenjang',
        ]);

        $pendidikan = Pendidikan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil ditambahkan.',
            'data' => $pendidikan,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $pendidikan = Pendidikan::find($id);
        if (! $pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $pendidikan]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $pendidikan = Pendidikan::find($id);
        if (! $pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'jenjang' => 'required|string|max:50|unique:pendidikan,jenjang,'.$id.',id_pendidikan',
        ]);

        $pendidikan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil diperbarui.',
            'data' => $pendidikan,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $pendidikan = Pendidikan::find($id);
        if (! $pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $pendidikan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil dihapus.',
        ]);
    }
}
