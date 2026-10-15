<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\DosenImport;
use App\Models\BidangKeahlian;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\Kegiatan;
use App\Models\Pendidikan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class DosenController extends Controller
{
    // Ambil daftar dosen dengan filter pencarian dan status
    public function index(Request $request): JsonResponse
    {
        // Query dengan relasi jabatan, pendidikan, dan bidang keahlian
        $query = Dosen::with(['jabatanAkademik', 'pendidikan', 'bidangKeahlian']);

        // Filter pencarian nama, NIDN, atau NUPTK
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_dosen', 'like', "%{$search}%")
                    ->orWhere('nidn', 'like', "%{$search}%")
                    ->orWhere('nuptk', 'like', "%{$search}%");
            });
        }

        // Filter status dosen (DTPS, DTT, NONAKTIF)
        if ($status = $request->query('status')) {
            $query->where('status_dosen', $status);
        }

        // Sertakan data yang di-soft-delete jika parameter with_trashed ada
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        // Paginasi data 10 per halaman
        $dosen = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $dosen,
        ]);
    }

    // Tambah data dosen baru (Aksi 2: Input NIDN, NUPTK, Nama, Jabatan, Bidang Keahlian, Status DTPS atau DTT)
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();

        // Mapping nama -> nama_dosen
        if ($request->filled('nama') && ! $request->filled('nama_dosen')) {
            $input['nama_dosen'] = $request->input('nama');
        }

        // Resolusi jabatan (ID atau string nama jabatan)
        if ($request->filled('jabatan') && ! $request->filled('id_jabatan')) {
            $jabatanVal = $request->input('jabatan');
            if (is_numeric($jabatanVal)) {
                $input['id_jabatan'] = (int) $jabatanVal;
            } else {
                $jabatan = JabatanAkademik::where('nama_jabatan', $jabatanVal)->first();
                $input['id_jabatan'] = $jabatan ? $jabatan->id_jabatan : 1;
            }
        } elseif (! $request->filled('id_jabatan')) {
            $input['id_jabatan'] = 1;
        }

        // Resolusi pendidikan (ID atau string jenjang)
        if ($request->filled('pendidikan') && ! $request->filled('id_pendidikan')) {
            $pendidikanVal = $request->input('pendidikan');
            if (is_numeric($pendidikanVal)) {
                $input['id_pendidikan'] = (int) $pendidikanVal;
            } else {
                $pendidikan = Pendidikan::where('jenjang', $pendidikanVal)->first();
                $input['id_pendidikan'] = $pendidikan ? $pendidikan->id_pendidikan : 1;
            }
        } elseif (! $request->filled('id_pendidikan')) {
            $input['id_pendidikan'] = 1;
        }

        // Email otomatis jika tidak disediakan
        if (! $request->filled('email') && $request->filled('nidn')) {
            $input['email'] = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $request->input('nidn'))).'@polines.ac.id';
        }

        // Status DTPS atau DTT
        if ($request->filled('status_dosen')) {
            $statusUpper = strtoupper((string) $request->input('status_dosen'));
            $input['status_dosen'] = in_array($statusUpper, ['DTT', 'NONAKTIF']) ? $statusUpper : 'DTPS';
        } else {
            $input['status_dosen'] = 'DTPS';
        }

        // Resolusi bidang keahlian jika dikirim array nama bidang
        if ($request->filled('bidang_keahlian') && ! $request->filled('bidang_keahlian_ids')) {
            $bidangVal = $request->input('bidang_keahlian');
            $bidangIds = [];
            foreach ((array) $bidangVal as $b) {
                if (is_numeric($b)) {
                    $bidangIds[] = (int) $b;
                } else {
                    $item = BidangKeahlian::where('nama_bidang', $b)->first();
                    if ($item) {
                        $bidangIds[] = $item->id_bidang_keahlian;
                    }
                }
            }
            $input['bidang_keahlian_ids'] = $bidangIds;
        }

        // Validasi input data dosen
        $validator = Validator::make($input, [
            'id_jabatan' => 'required|exists:jabatan_akademik,id_jabatan',
            'id_pendidikan' => 'required|exists:pendidikan,id_pendidikan',
            'nidn' => 'required|string|unique:dosen,nidn|max:30',
            'nuptk' => 'nullable|string|max:50',
            'nama_dosen' => 'required|string|max:255',
            'email' => 'required|email|unique:dosen,email|max:255',
            'status_dosen' => 'nullable|string|in:DTPS,DTT,NONAKTIF',
            'sertifikasi' => 'nullable|string|max:255',
            'bidang_keahlian_ids' => 'nullable|array',
            'bidang_keahlian_ids.*' => 'exists:bidang_keahlian,id_bidang_keahlian',
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
        $dosen = Dosen::create($validated);

        // Simpan relasi bidang keahlian jika ada
        if (! empty($validated['bidang_keahlian_ids'])) {
            $syncData = [];
            $bidangItems = BidangKeahlian::whereIn('id_bidang_keahlian', $validated['bidang_keahlian_ids'])->get();
            foreach ($bidangItems as $item) {
                $syncData[$item->id_bidang_keahlian] = ['nama_bidang_keahlian_dosen' => $item->nama_bidang];
            }
            $dosen->bidangKeahlian()->sync($syncData);
        }

        // Tampilkan Pesan Berhasil
        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil ditambahkan.',
            'data' => $dosen->load(['jabatanAkademik', 'pendidikan', 'bidangKeahlian']),
        ], 201);
    }

    // Ambil detail satu dosen beserta riwayat Tridharma (Screen: Detail Dosen)
    public function show(int $id): JsonResponse
    {
        $dosen = Dosen::with([
            'jabatanAkademik',
            'pendidikan',
            'bidangKeahlian',
            'penelitian.jenisKegiatan',
            'pkm.jenisKegiatan',
            'luaran.jenisLuaran',
            'buku',
            'hki',
            'sertifikasi',
        ])->find($id);

        // Cek data ada atau tidak
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $dosen]);
    }

    // Ubah Data Dosen (Aksi 4: Ubah Data Dosen -> Update Data di Database -> Tampilkan Pesan Berhasil)
    public function update(Request $request, int $id): JsonResponse
    {
        $dosen = Dosen::find($id);

        // Cek data ada atau tidak
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        $input = $request->all();

        // Mapping nama -> nama_dosen
        if ($request->filled('nama') && ! $request->filled('nama_dosen')) {
            $input['nama_dosen'] = $request->input('nama');
        }

        // Resolusi jabatan
        if ($request->filled('jabatan') && ! $request->filled('id_jabatan')) {
            $jabatanVal = $request->input('jabatan');
            if (is_numeric($jabatanVal)) {
                $input['id_jabatan'] = (int) $jabatanVal;
            } else {
                $jabatan = JabatanAkademik::where('nama_jabatan', $jabatanVal)->first();
                if ($jabatan) {
                    $input['id_jabatan'] = $jabatan->id_jabatan;
                }
            }
        }

        // Resolusi pendidikan
        if ($request->filled('pendidikan') && ! $request->filled('id_pendidikan')) {
            $pendidikanVal = $request->input('pendidikan');
            if (is_numeric($pendidikanVal)) {
                $input['id_pendidikan'] = (int) $pendidikanVal;
            } else {
                $pendidikan = Pendidikan::where('jenjang', $pendidikanVal)->first();
                if ($pendidikan) {
                    $input['id_pendidikan'] = $pendidikan->id_pendidikan;
                }
            }
        }

        // Cek keterkaitan jika status hendak diubah ke NONAKTIF
        if ($request->filled('status_dosen')) {
            $statusUpper = strtoupper((string) $request->input('status_dosen'));
            $input['status_dosen'] = in_array($statusUpper, ['DTT', 'NONAKTIF']) ? $statusUpper : 'DTPS';

            if ($input['status_dosen'] === 'NONAKTIF') {
                if ($this->isDosenUsedInTridharma($id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Dosen Masih Aktif: Data dosen tidak dapat dinonaktifkan karena sedang digunakan dalam kegiatan Penelitian atau PkM.',
                    ], 422);
                }
            }
        }

        // Resolusi bidang keahlian
        if ($request->has('bidang_keahlian') && ! $request->has('bidang_keahlian_ids')) {
            $bidangVal = $request->input('bidang_keahlian');
            $bidangIds = [];
            foreach ((array) $bidangVal as $b) {
                if (is_numeric($b)) {
                    $bidangIds[] = (int) $b;
                } else {
                    $item = BidangKeahlian::where('nama_bidang', $b)->first();
                    if ($item) {
                        $bidangIds[] = $item->id_bidang_keahlian;
                    }
                }
            }
            $input['bidang_keahlian_ids'] = $bidangIds;
        }

        // Validasi input data dosen untuk update
        $validator = Validator::make($input, [
            'id_jabatan' => 'sometimes|required|exists:jabatan_akademik,id_jabatan',
            'id_pendidikan' => 'sometimes|required|exists:pendidikan,id_pendidikan',
            'nidn' => 'sometimes|required|string|max:30|unique:dosen,nidn,'.$id.',id_dosen',
            'nuptk' => 'nullable|string|max:50',
            'nama_dosen' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:dosen,email,'.$id.',id_dosen',
            'status_dosen' => 'nullable|string|in:DTPS,DTT,NONAKTIF',
            'sertifikasi' => 'nullable|string|max:255',
            'bidang_keahlian_ids' => 'nullable|array',
            'bidang_keahlian_ids.*' => 'exists:bidang_keahlian,id_bidang_keahlian',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Update Data di Database
        $dosen->update($validated);

        // Update relasi bidang keahlian jika ada
        if (isset($validated['bidang_keahlian_ids']) || array_key_exists('bidang_keahlian_ids', $input)) {
            $ids = $validated['bidang_keahlian_ids'] ?? [];
            $syncData = [];
            if (! empty($ids)) {
                $bidangItems = BidangKeahlian::whereIn('id_bidang_keahlian', $ids)->get();
                foreach ($bidangItems as $item) {
                    $syncData[$item->id_bidang_keahlian] = ['nama_bidang_keahlian_dosen' => $item->nama_bidang];
                }
            }
            $dosen->bidangKeahlian()->sync($syncData);
        }

        // Tampilkan Pesan Berhasil
        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil diperbarui.',
            'data' => $dosen->fresh(['jabatanAkademik', 'pendidikan', 'bidangKeahlian']),
        ]);
    }

    // Aksi 5: Nonaktifkan dosen (Cek Keterkaitan ke Penelitian dan PkM -> Data Sedang Digunakan?)
    public function destroy(int $id): JsonResponse
    {
        $dosen = Dosen::find($id);

        // Cek data ada atau tidak
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        // Cek Keterkaitan ke Penelitian dan PkM
        // Data Sedang Digunakan?
        if ($this->isDosenUsedInTridharma($id)) {
            // Ya -> Tampilkan Pesan Error: Dosen Masih Aktif
            return response()->json([
                'success' => false,
                'message' => 'Dosen Masih Aktif: Data dosen tidak dapat dinonaktifkan karena sedang digunakan dalam kegiatan Penelitian atau PkM.',
            ], 422);
        }

        // Tidak -> Nonaktifkan dosen (status_dosen = NONAKTIF, soft delete)
        $dosen->update(['status_dosen' => 'NONAKTIF']);
        $dosen->delete();

        // Tampilkan Pesan Berhasil
        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil dinonaktifkan.',
        ]);
    }

    // Pulihkan data dosen yang terhapus (restore)
    public function restore(int $id): JsonResponse
    {
        // Cari data dosen yang di-soft-delete
        $dosen = Dosen::onlyTrashed()->find($id);

        // Cek data ada atau tidak
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Dosen terhapus tidak ditemukan.'], 404);
        }

        // Restore dan ubah status kembali ke DTPS
        $dosen->restore();
        $dosen->update(['status_dosen' => 'DTPS']);

        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil diaktifkan kembali.',
            'data' => $dosen,
        ]);
    }

    // Aksi 3: Impor data dosen dari file Excel atau CSV
    public function importExcel(Request $request): JsonResponse
    {
        // Validasi file upload (wajib file spreadsheet Excel atau CSV, maks 5MB)
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
        ]);

        try {
            // Proses import file Excel atau CSV
            Excel::import(new DosenImport, $request->file('file'));

            // Tampilkan Pesan Berhasil
            return response()->json([
                'success' => true,
                'message' => 'Data dosen berhasil diimpor dari file Excel atau CSV.',
            ]);
        } catch (ValidationException $e) {
            // Tangkap error jika ada baris yang tidak valid
            $failures = $e->failures();
            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            return response()->json([
                'success' => false,
                'message' => 'Terdapat kesalahan validasi pada data Excel atau CSV.',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            // Tangkap error umum lainnya
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor file: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cek keterkaitan dosen ke kegiatan Penelitian dan PkM
     */
    private function isDosenUsedInTridharma(int $id): bool
    {
        // 1. Cek pada kegiatan terpadu (kegiatan & anggota_kegiatan)
        $penelitianKegiatanExists = Kegiatan::whereHas('jenisKegiatan', function ($q) {
            $q->where('nama_jenis_kegiatan', 'like', '%Penelitian%');
        })->whereHas('anggotaKegiatan', function ($q) use ($id) {
            $q->where('id_dosen', $id);
        })->exists();

        if ($penelitianKegiatanExists) {
            return true;
        }

        $pkmKegiatanExists = Kegiatan::whereHas('jenisKegiatan', function ($q) {
            $q->where('nama_jenis_kegiatan', 'like', '%PkM%');
        })->whereHas('anggotaKegiatan', function ($q) use ($id) {
            $q->where('id_dosen', $id);
        })->exists();

        if ($pkmKegiatanExists) {
            return true;
        }

        // 2. Cek juga pada tabel mandiri jika ada
        if (Schema::hasTable('penelitian')) {
            $penelitianTableExists = DB::table('penelitian')->where('id_dosen', $id)->exists()
                || (Schema::hasTable('anggota_penelitian') && DB::table('anggota_penelitian')->where('id_dosen', $id)->exists());

            if ($penelitianTableExists) {
                return true;
            }
        }

        if (Schema::hasTable('pkm')) {
            $pkmTableExists = DB::table('pkm')->where('id_dosen', $id)->exists()
                || (Schema::hasTable('anggota_pkm') && DB::table('anggota_pkm')->where('id_dosen', $id)->exists());

            if ($pkmTableExists) {
                return true;
            }
        }

        return false;
    }
}
