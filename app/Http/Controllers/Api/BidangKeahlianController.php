<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BidangKeahlian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BidangKeahlianController extends Controller
{
    /**
     * Tampilkan Daftar Bidang Keahlian (Flowchart: Buka Menu Atribut Dosen -> Pilih Tipe Bidang Keahlian -> Tampilkan Daftar)
     */
    public function index(Request $request): JsonResponse
    {
        $query = BidangKeahlian::query();

        // Hitung keterkaitan ke data dosen
        $query->withCount('dosen');

        if ($search = $request->query('search')) {
            $query->where('nama_bidang', 'like', "%{$search}%");
        }

        $keahlian = $request->has('per_page')
            ? $query->paginate($request->integer('per_page', 10))
            : $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar bidang keahlian berhasil diambil.',
            'data' => $keahlian,
        ]);
    }

    /**
     * Tambah Bidang Keahlian (Flowchart: Input kode dan nama -> Simpan ke Database -> Tampilkan Pesan Berhasil)
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();

        // Mapping fleksibel nama -> nama_bidang
        if ($request->filled('nama') && ! $request->filled('nama_bidang')) {
            $input['nama_bidang'] = $request->input('nama');
        }

        $validator = Validator::make($input, [
            'nama_bidang' => 'required|string|max:100|unique:bidang_keahlian,nama_bidang',
        ], [
            'nama_bidang.required' => 'Nama bidang keahlian wajib diisi.',
            'nama_bidang.unique' => 'Nama bidang keahlian sudah ada dalam sistem.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $keahlian = BidangKeahlian::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Bidang keahlian berhasil ditambahkan.',
            'data' => $keahlian,
        ], 201);
    }

    /**
     * Detail Bidang Keahlian
     */
    public function show(int $id): JsonResponse
    {
        $keahlian = BidangKeahlian::withCount('dosen')->find($id);

        if (! $keahlian) {
            return response()->json([
                'success' => false,
                'message' => 'Data bidang keahlian tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $keahlian,
        ]);
    }

    /**
     * Ubah Bidang Keahlian (Flowchart: Ubah kode atau nama -> Update Data di Database -> Tampilkan Pesan Berhasil)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $keahlian = BidangKeahlian::find($id);

        if (! $keahlian) {
            return response()->json([
                'success' => false,
                'message' => 'Data bidang keahlian tidak ditemukan.',
            ], 404);
        }

        $input = $request->all();
        if ($request->filled('nama') && ! $request->filled('nama_bidang')) {
            $input['nama_bidang'] = $request->input('nama');
        }

        $validator = Validator::make($input, [
            'nama_bidang' => 'required|string|max:100|unique:bidang_keahlian,nama_bidang,'.$id.',id_bidang_keahlian',
        ], [
            'nama_bidang.required' => 'Nama bidang keahlian wajib diisi.',
            'nama_bidang.unique' => 'Nama bidang keahlian sudah ada dalam sistem.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $keahlian->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Bidang keahlian berhasil diperbarui.',
            'data' => $keahlian,
        ]);
    }

    /**
     * Hapus Bidang Keahlian (Flowchart: Cek keterkaitan data ke data dosen -> Data Sedang Digunakan?
     * Ya -> Tampilkan Pesan Error: Data Masih Dipakai
     * Tidak -> Hapus Data dari Database -> Tampilkan Pesan Berhasil)
     */
    public function destroy(int $id): JsonResponse
    {
        $keahlian = BidangKeahlian::find($id);

        if (! $keahlian) {
            return response()->json([
                'success' => false,
                'message' => 'Data bidang keahlian tidak ditemukan.',
            ], 404);
        }

        // Cek keterkaitan data ke data dosen melalui relasi bidang_keahlian_dosen
        $isUsed = DB::table('bidang_keahlian_dosen')->where('id_bidang_keahlian', $id)->exists()
            || $keahlian->dosen()->exists();

        if ($isUsed) {
            // Ya -> Tampilkan Pesan Error: Data Masih Dipakai
            return response()->json([
                'success' => false,
                'message' => 'Data Masih Dipakai: Bidang keahlian tidak dapat dihapus karena masih digunakan oleh data dosen.',
            ], 422);
        }

        // Tidak -> Hapus Data dari Database
        $keahlian->delete();

        // Tampilkan Pesan Berhasil
        return response()->json([
            'success' => true,
            'message' => 'Data bidang keahlian berhasil dihapus.',
        ]);
    }
}
