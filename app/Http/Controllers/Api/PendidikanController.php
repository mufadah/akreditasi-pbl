<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\Pendidikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PendidikanController extends Controller
{
    /**
     * Tampilkan Daftar Jenjang Pendidikan (Flowchart: Buka Menu Atribut Dosen -> Pilih Tipe Jenjang -> Tampilkan Daftar)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Pendidikan::query();

        // Hitung keterkaitan ke data dosen
        $query->withCount('dosen');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('jenjang', 'like', "%{$search}%")
                    ->orWhere('program_studi', 'like', "%{$search}%")
                    ->orWhere('universitas', 'like', "%{$search}%");
            });
        }

        $pendidikan = $request->has('per_page')
            ? $query->paginate($request->integer('per_page', 10))
            : $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar jenjang pendidikan berhasil diambil.',
            'data' => $pendidikan,
        ]);
    }

    /**
     * Tambah Jenjang Pendidikan (Flowchart: Input kode dan nama -> Simpan ke Database -> Tampilkan Pesan Berhasil)
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();

        // Mapping fleksibel nama -> jenjang jika jenjang kosong
        if ($request->filled('nama') && ! $request->filled('jenjang')) {
            $input['jenjang'] = $request->input('nama');
        }

        $validator = Validator::make($input, [
            'jenjang' => 'required|string|max:50',
            'universitas' => 'nullable|string|max:255',
            'program_studi' => 'nullable|string|max:255',
            'tahun_lulus' => 'nullable|integer',
        ], [
            'jenjang.required' => 'Jenjang pendidikan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $validated['universitas'] = $validated['universitas'] ?? 'Politeknik Negeri Semarang';
        $validated['program_studi'] = $validated['program_studi'] ?? 'Teknik';
        $validated['tahun_lulus'] = $validated['tahun_lulus'] ?? (int) date('Y');

        $pendidikan = Pendidikan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil ditambahkan.',
            'data' => $pendidikan,
        ], 201);
    }

    /**
     * Detail Jenjang Pendidikan
     */
    public function show(int $id): JsonResponse
    {
        $pendidikan = Pendidikan::withCount('dosen')->find($id);

        if (! $pendidikan) {
            return response()->json([
                'success' => false,
                'message' => 'Data jenjang pendidikan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $pendidikan,
        ]);
    }

    /**
     * Ubah Jenjang Pendidikan (Flowchart: Ubah kode atau nama -> Update Data di Database -> Tampilkan Pesan Berhasil)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pendidikan = Pendidikan::find($id);

        if (! $pendidikan) {
            return response()->json([
                'success' => false,
                'message' => 'Data jenjang pendidikan tidak ditemukan.',
            ], 404);
        }

        $input = $request->all();
        if ($request->filled('nama') && ! $request->filled('jenjang')) {
            $input['jenjang'] = $request->input('nama');
        }

        $validator = Validator::make($input, [
            'jenjang' => 'required|string|max:50',
            'universitas' => 'nullable|string|max:255',
            'program_studi' => 'nullable|string|max:255',
            'tahun_lulus' => 'nullable|integer',
        ], [
            'jenjang.required' => 'Jenjang pendidikan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $pendidikan->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil diperbarui.',
            'data' => $pendidikan,
        ]);
    }

    /**
     * Hapus Jenjang Pendidikan (Flowchart: Cek keterkaitan data ke data dosen -> Data Sedang Digunakan?
     * Ya -> Tampilkan Pesan Error: Data Masih Dipakai
     * Tidak -> Hapus Data dari Database -> Tampilkan Pesan Berhasil)
     */
    public function destroy(int $id): JsonResponse
    {
        $pendidikan = Pendidikan::find($id);

        if (! $pendidikan) {
            return response()->json([
                'success' => false,
                'message' => 'Data jenjang pendidikan tidak ditemukan.',
            ], 404);
        }

        // Cek keterkaitan data ke data dosen
        $isUsed = Dosen::where('id_pendidikan', $id)->exists();

        if ($isUsed) {
            // Ya -> Tampilkan Pesan Error: Data Masih Dipakai
            return response()->json([
                'success' => false,
                'message' => 'Data Masih Dipakai: Data jenjang pendidikan tidak dapat dihapus karena masih digunakan oleh data dosen.',
            ], 422);
        }

        // Tidak -> Hapus Data dari Database
        $pendidikan->delete();

        // Tampilkan Pesan Berhasil
        return response()->json([
            'success' => true,
            'message' => 'Data jenjang pendidikan berhasil dihapus.',
        ]);
    }
}
