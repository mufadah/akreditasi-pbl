<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BidangKeahlian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BidangKeahlianController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => BidangKeahlian::all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_bidang' => 'required|string|max:100|unique:bidang_keahlian,nama_bidang',
        ]);

        $keahlian = BidangKeahlian::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bidang keahlian berhasil ditambahkan.',
            'data' => $keahlian,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $keahlian = BidangKeahlian::find($id);
        if (! $keahlian) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $keahlian]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $keahlian = BidangKeahlian::find($id);
        if (! $keahlian) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'nama_bidang' => 'required|string|max:100|unique:bidang_keahlian,nama_bidang,'.$id.',id_bidang_keahlian',
        ]);

        $keahlian->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bidang keahlian berhasil diperbarui.',
            'data' => $keahlian,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $keahlian = BidangKeahlian::find($id);
        if (! $keahlian) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $keahlian->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bidang keahlian berhasil dihapus.',
        ]);
    }
}
