<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenagaKependidikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenagaKependidikanController extends Controller
{
    // GET /api/tendik
    public function index(Request $request): JsonResponse
    {
        $query = TenagaKependidikan::with('pendidikan');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_tendik', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('jabatan_tendik', 'like', "%{$search}%");
            });
        }

        $tendik = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $tendik,
        ]);
    }

    // POST /api/tendik
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_pendidikan' => 'required|exists:pendidikan,id_pendidikan',
            'nama_tendik' => 'required|string|max:255',
            'jabatan_tendik' => 'required|string|max:255',
            'nip' => 'required|string|max:50|unique:tenaga_kependidikan,nip',
        ]);

        $tendik = TenagaKependidikan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil ditambahkan.',
            'data' => $tendik->load('pendidikan'),
        ], 201);
    }

    // GET /api/tendik/{id}
    public function show(int $id): JsonResponse
    {
        $tendik = TenagaKependidikan::with('pendidikan')->find($id);

        if (! $tendik) {
            return response()->json(['success' => false, 'message' => 'Data tendik tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $tendik]);
    }

    // PUT /api/tendik/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $tendik = TenagaKependidikan::find($id);

        if (! $tendik) {
            return response()->json(['success' => false, 'message' => 'Data tendik tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'id_pendidikan' => 'sometimes|required|exists:pendidikan,id_pendidikan',
            'nama_tendik' => 'sometimes|required|string|max:255',
            'jabatan_tendik' => 'sometimes|required|string|max:255',
            'nip' => 'sometimes|required|string|max:50|unique:tenaga_kependidikan,nip,'.$id.',id_tendik',
        ]);

        $tendik->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil diperbarui.',
            'data' => $tendik->fresh('pendidikan'),
        ]);
    }

    // DELETE /api/tendik/{id}
    public function destroy(int $id): JsonResponse
    {
        $tendik = TenagaKependidikan::find($id);

        if (! $tendik) {
            return response()->json(['success' => false, 'message' => 'Data tendik tidak ditemukan.'], 404);
        }

        $tendik->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil dihapus.',
        ]);
    }
}
