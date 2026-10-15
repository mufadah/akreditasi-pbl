<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pendidikan;
use App\Models\TenagaKependidikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TenagaKependidikanController extends Controller
{
    /**
     * Tampilkan Daftar Tendik (Flowchart: Buka Menu Tenaga Kependidikan -> Tampilkan Daftar Tendik)
     * Mendukung: Pencarian (NIP, Nama, Jenis Tendik, Kualifikasi), Filter Jabatan/Kualifikasi/Status, & Paginasi.
     */
    public function index(Request $request): JsonResponse
    {
        // Mendukung filter status: aktif (default), nonaktif (trashed), atau semua
        $status = strtolower((string) $request->query('status', 'aktif'));
        if ($status === 'nonaktif') {
            $query = TenagaKependidikan::onlyTrashed();
        } elseif ($status === 'semua') {
            $query = TenagaKependidikan::withTrashed();
        } else {
            $query = TenagaKependidikan::query();
        }

        // Eager load relasi pendidikan dan sertifikasi
        $query->with(['pendidikan', 'sertifikasi']);

        // Filter pencarian teks (NIP, Nama, Jenis/Jabatan Tendik, Kualifikasi)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_tendik', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('jabatan_tendik', 'like', "%{$search}%")
                    ->orWhereHas('pendidikan', function ($pq) use ($search) {
                        $pq->where('jenjang', 'like', "%{$search}%")
                            ->orWhere('program_studi', 'like', "%{$search}%")
                            ->orWhere('universitas', 'like', "%{$search}%");
                    });
            });
        }

        // Filter spesifik: Jenis Tendik / Jabatan Tendik
        if ($jenis = $request->query('jenis_tendik') ?: $request->query('jabatan_tendik')) {
            $query->where('jabatan_tendik', 'like', "%{$jenis}%");
        }

        // Filter spesifik: Kualifikasi Pendidikan (Jenjang)
        if ($kualifikasi = $request->query('kualifikasi')) {
            $query->whereHas('pendidikan', function ($pq) use ($kualifikasi) {
                $pq->where('jenjang', 'like', "%{$kualifikasi}%");
            });
        }

        // Urutkan terbaru
        $query->orderBy('id_tendik', 'desc');

        // Paginasi data (default 10 per halaman)
        $tendik = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'Daftar tenaga kependidikan berhasil diambil.',
            'data' => $tendik,
        ]);
    }

    /**
     * Tambah Data Tendik (Flowchart: Input NIP, Nama, Jenis Tendik, Kualifikasi -> Simpan ke Database -> Tampilkan Pesan Berhasil)
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();

        // 1. Mapping field fleksibel: nama -> nama_tendik
        if ($request->filled('nama') && ! $request->filled('nama_tendik')) {
            $input['nama_tendik'] = $request->input('nama');
        }

        // 2. Mapping field fleksibel: jenis_tendik -> jabatan_tendik
        if ($request->filled('jenis_tendik') && ! $request->filled('jabatan_tendik')) {
            $input['jabatan_tendik'] = $request->input('jenis_tendik');
        }

        // 3. Resolusi Kualifikasi / Pendidikan
        if ($request->filled('kualifikasi') && ! $request->filled('id_pendidikan')) {
            $kualifikasiVal = $request->input('kualifikasi');
            if (is_numeric($kualifikasiVal)) {
                $input['id_pendidikan'] = (int) $kualifikasiVal;
            } else {
                $pend = Pendidikan::where('jenjang', 'like', "%{$kualifikasiVal}%")->first();
                if (! $pend) {
                    $pend = Pendidikan::create([
                        'jenjang' => $kualifikasiVal,
                        'universitas' => 'Politeknik Negeri Semarang',
                        'program_studi' => 'Tenaga Kependidikan',
                        'tahun_lulus' => date('Y'),
                    ]);
                }
                $input['id_pendidikan'] = $pend->id_pendidikan;
            }
        } elseif ($request->filled('pendidikan') && ! $request->filled('id_pendidikan')) {
            $pendVal = $request->input('pendidikan');
            if (is_numeric($pendVal)) {
                $input['id_pendidikan'] = (int) $pendVal;
            } else {
                $pend = Pendidikan::where('jenjang', 'like', "%{$pendVal}%")->first();
                if (! $pend) {
                    $pend = Pendidikan::create([
                        'jenjang' => $pendVal,
                        'universitas' => 'Politeknik Negeri Semarang',
                        'program_studi' => 'Tenaga Kependidikan',
                        'tahun_lulus' => date('Y'),
                    ]);
                }
                $input['id_pendidikan'] = $pend->id_pendidikan;
            }
        }

        // Fallback default id_pendidikan jika belum terisi
        if (empty($input['id_pendidikan'])) {
            $defaultPend = Pendidikan::first();
            $input['id_pendidikan'] = $defaultPend ? $defaultPend->id_pendidikan : 1;
        }

        // Validasi input data
        $validator = Validator::make($input, [
            'id_pendidikan' => 'required|exists:pendidikan,id_pendidikan',
            'nama_tendik' => 'required|string|max:255',
            'jabatan_tendik' => 'required|string|max:255',
            'nip' => 'required|string|max:50|unique:tenaga_kependidikan,nip',
            'sertifikasi' => 'nullable|string|max:255',
        ], [
            'nip.unique' => 'NIP sudah terdaftar dalam sistem.',
            'nama_tendik.required' => 'Nama tenaga kependidikan wajib diisi.',
            'jabatan_tendik.required' => 'Jenis / Jabatan tendik wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Simpan ke Database
        $tendik = TenagaKependidikan::create($validated);

        // Tampilkan Pesan Berhasil (HTTP 201 Created)
        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil ditambahkan.',
            'data' => $tendik->load(['pendidikan', 'sertifikasi']),
        ], 201);
    }

    /**
     * Detail Tenaga Kependidikan (Screen: Detail Tendik)
     * Memuat profil, kualifikasi pendidikan, dan riwayat sertifikasi kompetensi.
     */
    public function show(int $id): JsonResponse
    {
        $tendik = TenagaKependidikan::withTrashed()
            ->with(['pendidikan', 'sertifikasi.evidence'])
            ->find($id);

        if (! $tendik) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga kependidikan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail data tenaga kependidikan berhasil diambil.',
            'data' => $tendik,
        ]);
    }

    /**
     * Ubah Data Tendik (Flowchart: Ubah Data Tendik -> Update Data di Database -> Tampilkan Pesan Berhasil)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tendik = TenagaKependidikan::find($id);

        if (! $tendik) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga kependidikan tidak ditemukan.',
            ], 404);
        }

        $input = $request->all();

        // Mapping fleksibel nama -> nama_tendik
        if ($request->filled('nama') && ! $request->filled('nama_tendik')) {
            $input['nama_tendik'] = $request->input('nama');
        }

        // Mapping fleksibel jenis_tendik -> jabatan_tendik
        if ($request->filled('jenis_tendik') && ! $request->filled('jabatan_tendik')) {
            $input['jabatan_tendik'] = $request->input('jenis_tendik');
        }

        // Resolusi Kualifikasi / Pendidikan
        if ($request->filled('kualifikasi') && ! $request->filled('id_pendidikan')) {
            $kualifikasiVal = $request->input('kualifikasi');
            if (is_numeric($kualifikasiVal)) {
                $input['id_pendidikan'] = (int) $kualifikasiVal;
            } else {
                $pend = Pendidikan::where('jenjang', 'like', "%{$kualifikasiVal}%")->first();
                if (! $pend) {
                    $pend = Pendidikan::create([
                        'jenjang' => $kualifikasiVal,
                        'universitas' => 'Politeknik Negeri Semarang',
                        'program_studi' => 'Tenaga Kependidikan',
                        'tahun_lulus' => date('Y'),
                    ]);
                }
                $input['id_pendidikan'] = $pend->id_pendidikan;
            }
        } elseif ($request->filled('pendidikan') && ! $request->filled('id_pendidikan')) {
            $pendVal = $request->input('pendidikan');
            if (is_numeric($pendVal)) {
                $input['id_pendidikan'] = (int) $pendVal;
            } else {
                $pend = Pendidikan::where('jenjang', 'like', "%{$pendVal}%")->first();
                if (! $pend) {
                    $pend = Pendidikan::create([
                        'jenjang' => $pendVal,
                        'universitas' => 'Politeknik Negeri Semarang',
                        'program_studi' => 'Tenaga Kependidikan',
                        'tahun_lulus' => date('Y'),
                    ]);
                }
                $input['id_pendidikan'] = $pend->id_pendidikan;
            }
        }

        // Validasi input update
        $validator = Validator::make($input, [
            'id_pendidikan' => 'sometimes|required|exists:pendidikan,id_pendidikan',
            'nama_tendik' => 'sometimes|required|string|max:255',
            'jabatan_tendik' => 'sometimes|required|string|max:255',
            'nip' => 'sometimes|required|string|max:50|unique:tenaga_kependidikan,nip,'.$id.',id_tendik',
            'sertifikasi' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update Data di Database
        $tendik->update($validator->validated());

        // Tampilkan Pesan Berhasil
        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil diperbarui.',
            'data' => $tendik->fresh(['pendidikan', 'sertifikasi']),
        ]);
    }

    /**
     * Nonaktifkan Tendik (Flowchart: Nonaktifkan Tendik -> Tampilkan Pesan Berhasil)
     */
    public function destroy(int $id): JsonResponse
    {
        $tendik = TenagaKependidikan::find($id);

        if (! $tendik) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga kependidikan tidak ditemukan.',
            ], 404);
        }

        // Nonaktifkan Tendik (Soft Delete)
        $tendik->delete();

        // Tampilkan Pesan Berhasil
        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil dinonaktifkan.',
        ]);
    }

    /**
     * Aktifkan Kembali Tendik (Restore)
     */
    public function restore(int $id): JsonResponse
    {
        $tendik = TenagaKependidikan::onlyTrashed()->find($id);

        if (! $tendik) {
            return response()->json([
                'success' => false,
                'message' => 'Data tenaga kependidikan tidak ditemukan dalam arsip nonaktif.',
            ], 404);
        }

        $tendik->restore();

        return response()->json([
            'success' => true,
            'message' => 'Data tenaga kependidikan berhasil diaktifkan kembali.',
            'data' => $tendik->fresh(['pendidikan', 'sertifikasi']),
        ]);
    }
}
