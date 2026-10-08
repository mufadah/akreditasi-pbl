<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\DosenImport;
use App\Models\Dosen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

/**
 * Controller untuk mengelola data master Dosen, riwayat keahlian, dan impor batch Excel.
 * Mendukung siklus hidup data dengan soft deletes serta optimasi eager loading relasi akademik.
 */
class DosenController extends Controller
{
    /**
     * Mengambil daftar dosen terpaginasi dengan filter status dan pencarian NIDN/Nama.
     * Eager loading diterapkan pada relasi akademik guna mengeliminasi masalah N+1 query.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Inisialisasi query builder dengan eager loading relasi akademik untuk menghindari N+1 query problem
        $query = Dosen::with(['jabatanAkademik', 'pendidikan', 'bidangKeahlian']);

        // 2. Pencarian fleksibel: mencocokkan sebagian string (LIKE) pada nama dosen atau NIDN
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_dosen', 'like', "%{$search}%")
                    ->orWhere('nidn', 'like', "%{$search}%");
            });
        }

        // 3. Filter eksak berdasarkan status kepegawaian (misal: 'Aktif', 'Tugas Belajar', dll.)
        if ($status = $request->query('status')) {
            $query->where('status_dosen', $status);
        }

        // 4. Opsi untuk menyertakan data dosen yang telah di-soft-delete jika parameter with_trashed bernilai true
        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        // 5. Eksekusi pagination database (default 10 baris per halaman) untuk efisiensi transfer data
        $dosen = $query->paginate($request->integer('per_page', 10));

        // 6. Kembalikan respon sukses beserta payload data pagination
        return response()->json([
            'success' => true,
            'data' => $dosen,
        ]);
    }

    /**
     * Menyimpan data dosen baru beserta sinkronisasi relasi many-to-many bidang keahlian.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validasi input: pastikan foreign key valid, email dan NIDN unik, serta atribut wajib terisi
        $validated = $request->validate([
            'id_jabatan' => 'required|exists:jabatan_akademik,id_jabatan',
            'id_pendidikan' => 'required|exists:pendidikan,id_pendidikan',
            'nidn' => 'required|string|unique:dosen,nidn|max:30',
            'nama_dosen' => 'required|string|max:255',
            'email' => 'required|email|unique:dosen,email|max:255',
            'status_dosen' => 'nullable|string|max:50',
            'sertifikasi' => 'nullable|string|max:255',
            'bidang_keahlian_ids' => 'nullable|array',
            'bidang_keahlian_ids.*' => 'exists:bidang_keahlian,id_bidang_keahlian',
        ]);

        // 2. Persistensi record utama dosen ke dalam database
        $dosen = Dosen::create($validated);

        // 3. Sinkronisasi tabel pivot (many-to-many) bidang keahlian jika ada data array ID yang dikirim
        if (! empty($validated['bidang_keahlian_ids'])) {
            $dosen->bidangKeahlian()->sync($validated['bidang_keahlian_ids']);
        }

        // 4. Kembalikan respon HTTP 201 Created dengan relasi termuat lengkap
        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil ditambahkan.',
            'data' => $dosen->load(['jabatanAkademik', 'pendidikan', 'bidangKeahlian']),
        ], 201);
    }

    /**
     * Menampilkan rincian data dosen tunggal beserta seluruh riwayat Tridharma terkait.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // 1. Ambil data dosen berdasarkan primary key sekaligus memuat seluruh relasi Tridharma (penelitian & PKM)
        $dosen = Dosen::with(['jabatanAkademik', 'pendidikan', 'bidangKeahlian', 'penelitian', 'pkm'])->find($id);

        // 2. Pengecekan keberadaan data: jika tidak ditemukan, kembalikan respon 404 Not Found
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        // 3. Kembalikan data profil dosen lengkap beserta riwayat kegiatannya
        return response()->json(['success' => true, 'data' => $dosen]);
    }

    /**
     * Memperbarui profil dosen dan menyinkronkan ulang data pivot bidang keahlian.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 1. Cari data dosen yang hendak diperbarui
        $dosen = Dosen::find($id);

        // 2. Gagalkan pembaruan dengan 404 jika ID dosen tidak terdaftar
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        // 3. Validasi parsial (sometimes) untuk mendukung update sebagian kolom; pengecualian unik NIDN/email untuk ID aktif
        $validated = $request->validate([
            'id_jabatan' => 'sometimes|required|exists:jabatan_akademik,id_jabatan',
            'id_pendidikan' => 'sometimes|required|exists:pendidikan,id_pendidikan',
            'nidn' => 'sometimes|required|string|max:30|unique:dosen,nidn,'.$id.',id_dosen',
            'nama_dosen' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:dosen,email,'.$id.',id_dosen',
            'status_dosen' => 'nullable|string|max:50',
            'sertifikasi' => 'nullable|string|max:255',
            'bidang_keahlian_ids' => 'nullable|array',
            'bidang_keahlian_ids.*' => 'exists:bidang_keahlian,id_bidang_keahlian',
        ]);

        // 4. Terapkan perubahan atribut pada record dosen
        $dosen->update($validated);

        // 5. Perbarui relasi pivot many-to-many bidang keahlian jika disertakan dalam request
        if ($request->has('bidang_keahlian_ids')) {
            $dosen->bidangKeahlian()->sync($validated['bidang_keahlian_ids']);
        }

        // 6. Muat ulang data terbaru (fresh) dari database untuk disajikan dalam respon sukses
        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil diperbarui.',
            'data' => $dosen->fresh(['jabatanAkademik', 'pendidikan', 'bidangKeahlian']),
        ]);
    }

    /**
     * Menandai status kepegawaian nonaktif dan mengeksekusi soft delete pada record dosen.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        // 1. Cari record dosen aktif
        $dosen = Dosen::find($id);

        // 2. Proteksi 404 jika dosen tidak ditemukan
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Data dosen tidak ditemukan.'], 404);
        }

        // 3. Ubah status dosen menjadi NONAKTIF terlebih dahulu sebelum melakukan soft delete
        $dosen->update(['status_dosen' => 'NONAKTIF']);

        // 4. Eksekusi soft delete (mengisi kolom deleted_at tanpa menghapus baris fisik dari tabel)
        $dosen->delete();

        // 5. Kembalikan respon konfirmasi penonaktifan
        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil dinonaktifkan (soft deleted).',
        ]);
    }

    /**
     * Memulihkan dosen yang terhapus secara logis dan mengembalikan status kepegawaiannya.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        // 1. Cari record khusus pada baris dosen yang telah berstatus soft-deleted (onlyTrashed)
        $dosen = Dosen::onlyTrashed()->find($id);

        // 2. Jika tidak ditemukan di tabel sampah (trash), kembalikan respon 404
        if (! $dosen) {
            return response()->json(['success' => false, 'message' => 'Dosen terhapus tidak ditemukan.'], 404);
        }

        // 3. Pulihkan record (mengosongkan kembali kolom deleted_at)
        $dosen->restore();

        // 4. Kembalikan status kepegawaian aktif (misal DTPS: Dosen Tetap Program Studi)
        $dosen->update(['status_dosen' => 'DTPS']);

        // 5. Kembalikan respon sukses pemulihan data
        return response()->json([
            'success' => true,
            'message' => 'Data dosen berhasil diaktifkan kembali.',
            'data' => $dosen,
        ]);
    }

    /**
     * Memproses impor massal data dosen via Excel dengan penanganan error validasi baris.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Maatwebsite\Excel\Validators\ValidationException|\Throwable
     */
    public function importExcel(Request $request): JsonResponse
    {
        // 1. Validasi berkas spreadsheet: wajib ada, ekstensi excel/csv, dan batas ukuran maksimal 5MB
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            // 2. Parsing dan persistensi baris data Excel menggunakan kelas import DosenImport
            Excel::import(new DosenImport, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Data dosen berhasil diimpor dari file Excel.',
            ]);
        } catch (ValidationException $e) {
            // 3. Tangani kegagalan validasi spesifik pada baris-baris tertentu di dalam berkas Excel
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
                'message' => 'Terdapat kesalahan validasi pada data Excel.',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            // 4. Tangani error tak terduga lainnya (misal: berkas rusak / format sheet tidak sesuai)
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor file: '.$e->getMessage(),
            ], 500);
        }
    }
}
