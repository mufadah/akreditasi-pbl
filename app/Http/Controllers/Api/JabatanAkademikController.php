<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JabatanAkademikController extends Controller
{
    /**
     * Tampilkan Daftar Jabatan Akademik (Flowchart: Buka Menu Atribut Dosen -> Pilih Tipe Jabatan -> Tampilkan Daftar)
     */
    public function index(Request $request): JsonResponse
    {
        $query = JabatanAkademik::query();

        // Hitung keterkaitan ke data dosen
        $query->withCount('dosen');

        if ($search = $request->query('search')) {
            $query->where('nama_jabatan', 'like', "%{$search}%");
        }

        $jabatan = $request->has('per_page')
            ? $query->paginate($request->integer('per_page', 10))
            : $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar jabatan akademik berhasil diambil.',
            'data' => $jabatan,
        ]);
    }

    /**
     * Tambah Jabatan Akademik (Flowchart: Input kode dan nama -> Simpan ke Database -> Tampilkan Pesan Berhasil)
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();

        // Mapping fleksibel nama -> nama_jabatan
        if ($request->filled('nama') && ! $request->filled('nama_jabatan')) {
            $input['nama_jabatan'] = $request->input('nama');
        }

        $validator = Validator::make($input, [
            'nama_jabatan' => 'required|string|max:100|unique:jabatan_akademik,nama_jabatan',
        ], [
            'nama_jabatan.required' => 'Nama jabatan akademik wajib diisi.',
            'nama_jabatan.unique' => 'Nama jabatan akademik sudah ada dalam sistem.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $jabatan = JabatanAkademik::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Jabatan akademik berhasil ditambahkan.',
            'data' => $jabatan,
        ], 201);
    }

    /**
     * Detail Jabatan Akademik
     */
    public function show(int $id): JsonResponse
    {
        $jabatan = JabatanAkademik::withCount('dosen')->find($id);

        if (! $jabatan) {
            return response()->json([
                'success' => false,
                'message' => 'Data jabatan akademik tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $jabatan,
        ]);
    }

    /**
     * Ubah Jabatan Akademik (Flowchart: Ubah kode atau nama -> Update Data di Database -> Tampilkan Pesan Berhasil)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $jabatan = JabatanAkademik::find($id);

        if (! $jabatan) {
            return response()->json([
                'success' => false,
                'message' => 'Data jabatan akademik tidak ditemukan.',
            ], 404);
        }

        $input = $request->all();
        if ($request->filled('nama') && ! $request->filled('nama_jabatan')) {
            $input['nama_jabatan'] = $request->input('nama');
        }

        $validator = Validator::make($input, [
            'nama_jabatan' => 'required|string|max:100|unique:jabatan_akademik,nama_jabatan,'.$id.',id_jabatan',
        ], [
            'nama_jabatan.required' => 'Nama jabatan akademik wajib diisi.',
            'nama_jabatan.unique' => 'Nama jabatan akademik sudah ada dalam sistem.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $jabatan->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Jabatan akademik berhasil diperbarui.',
            'data' => $jabatan,
        ]);
    }

    /**
     * Hapus Jabatan Akademik (Flowchart: Cek keterkaitan data ke data dosen -> Data Sedang Digunakan?
     * Ya -> Tampilkan Pesan Error: Data Masih Dipakai
     * Tidak -> Hapus Data dari Database -> Tampilkan Pesan Berhasil)
     */
    public function destroy(int $id): JsonResponse
    {
        $jabatan = JabatanAkademik::find($id);

        if (! $jabatan) {
            return response()->json([
                'success' => false,
                'message' => 'Data jabatan akademik tidak ditemukan.',
            ], 404);
        }

        // Cek keterkaitan data ke data dosen
        $isUsed = Dosen::where('id_jabatan', $id)->exists();

        if ($isUsed) {
            // Ya -> Tampilkan Pesan Error: Data Masih Dipakai
            return response()->json([
                'success' => false,
                'message' => 'Data Masih Dipakai: Jabatan akademik tidak dapat dihapus karena masih digunakan oleh data dosen.',
            ], 422);
        }

        // Tidak -> Hapus Data dari Database
        $jabatan->delete();

        // Tampilkan Pesan Berhasil
        return response()->json([
            'success' => true,
            'message' => 'Data jabatan akademik berhasil dihapus.',
        ]);
    }
}
