<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\KerjaSama;
use App\Models\Penelitian;
use App\Models\Pkm;
use App\Models\Publikasi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller penyedia metrik analitik, agregasi data Tridharma, dan monitoring instrumen akreditasi.
 * Seluruh komputasi didorong ke level SQL database engine untuk mencegah memory exhaustion di PHP.
 */
class StatistikController extends Controller
{
    /**
     * Helper response standar: {success, code, message, data}
     *
     * @param  bool  $success
     * @param  int  $code
     * @param  string  $message
     * @param  mixed  $data
     * @return \Illuminate\Http\JsonResponse
     */
    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Menghitung demografi dosen, rasio keaktifan, sebaran jabatan, dan jenjang pendidikan via SQL agregat.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function dosenStatistik(): JsonResponse
    {
        // 1. Hitung total seluruh dosen serta dosen dengan status 'Aktif' (mengecualikan soft-deleted secara otomatis)
        $totalDosen = Dosen::count();
        $totalAktif = Dosen::where('status_dosen', 'Aktif')->count();

        // 2. Agregasi sebaran dosen per status kepegawaian menggunakan GROUP BY SQL
        $sebaranStatus = Dosen::select('status_dosen', DB::raw('count(*) as total'))
            ->groupBy('status_dosen')
            ->pluck('total', 'status_dosen');

        // 3. Agregasi per jabatan akademik menggunakan withCount agar jabatan berkuota 0 tetap muncul dalam matriks akreditasi
        $sebaranJabatan = JabatanAkademik::withCount([
            'dosen' => fn ($q) => $q->whereNull('deleted_at'),
        ])
            ->get()
            ->map(fn ($j) => [
                'id_jabatan'   => $j->id_jabatan,
                'nama_jabatan' => $j->nama_jabatan,
                'total_dosen'  => $j->dosen_count,
            ]);

        // 4. Agregasi per jenjang pendidikan tertinggi (S2, S3, dll.) via JOIN dengan filter soft deletes
        $sebaranPendidikan = DB::table('dosen')
            ->join('pendidikan', 'dosen.id_pendidikan', '=', 'pendidikan.id_pendidikan')
            ->whereNull('dosen.deleted_at')
            ->select('pendidikan.jenjang', DB::raw('count(dosen.id_dosen) as total_dosen'))
            ->groupBy('pendidikan.jenjang')
            ->orderBy('pendidikan.jenjang')
            ->get();

        // 5. Kembalikan data metrik demografi dosen lengkap
        return $this->respond(true, 200, 'Statistik data dosen berhasil dihitung.', [
            'total_dosen'        => $totalDosen,
            'total_aktif'        => $totalAktif,
            'sebaran_status'     => $sebaranStatus,
            'sebaran_jabatan'    => $sebaranJabatan,
            'sebaran_pendidikan' => $sebaranPendidikan,
        ]);
    }

    /**
     * Merekapitulasi produktivitas Tridharma dengan pemisahan status menggunakan query cloning terisolasi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function tridharmaSummary(Request $request): JsonResponse
    {
        // 1. Ekstrak dan validasi format query parameter tahun
        $tahun = $request->query('tahun');

        if ($tahun && ! is_numeric($tahun)) {
            return $this->respond(false, 422, 'Parameter tahun harus berupa angka 4 digit.');
        }

        // 2. Siapkan query builder dasar untuk masing-masing pilar kegiatan Tridharma
        $penelitianQuery = Penelitian::query();
        $pkmQuery        = Pkm::query();
        $publikasiQuery  = Publikasi::query();

        // 3. Terapkan filter tahun secara seragam jika parameter tahun dikirimkan
        if ($tahun) {
            $penelitianQuery->where('tahun', $tahun);
            $pkmQuery->where('tahun', $tahun);
            $publikasiQuery->where('tahun', $tahun);
        }

        // 4. Komputasi kuantitas penelitian (total dan per status 'Berjalan'/'Selesai') menggunakan clone query builder
        $totalPenelitian    = (clone $penelitianQuery)->count();
        $penelitianByStatus = (clone $penelitianQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // 5. Komputasi kuantitas PKM (total dan per status 'Berjalan'/'Selesai')
        $totalPkm    = (clone $pkmQuery)->count();
        $pkmByStatus = (clone $pkmQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // 6. Komputasi kuantitas publikasi ilmiah
        $totalPublikasi = (clone $publikasiQuery)->count();

        // 7. Kalkulasi akumulasi grand total aktivitas Tridharma
        $grandTotal = $totalPenelitian + $totalPkm + $totalPublikasi;

        // 8. Sajikan respon rangkuman kegiatan
        return $this->respond(true, 200, 'Ringkasan kegiatan Tridharma berhasil dihitung.', [
            'filter_tahun' => $tahun ? (int) $tahun : null,
            'penelitian'   => [
                'total'     => $totalPenelitian,
                'by_status' => [
                    'berjalan' => $penelitianByStatus['Berjalan'] ?? 0,
                    'selesai'  => $penelitianByStatus['Selesai'] ?? 0,
                    'lainnya'  => $penelitianByStatus->except(['Berjalan', 'Selesai'])->sum(),
                ],
            ],
            'pkm'          => [
                'total'     => $totalPkm,
                'by_status' => [
                    'berjalan' => $pkmByStatus['Berjalan'] ?? 0,
                    'selesai'  => $pkmByStatus['Selesai'] ?? 0,
                    'lainnya'  => $pkmByStatus->except(['Berjalan', 'Selesai'])->sum(),
                ],
            ],
            'publikasi'    => [
                'total' => $totalPublikasi,
            ],
            'grand_total'  => $grandTotal,
        ]);
    }

    /**
     * Menyajikan daftar kemitraan yang masih aktif berbasis perbandingan sargable index tanggal selesai.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function kerjasamaAktif(Request $request): JsonResponse
    {
        // 1. Dapatkan tanggal hari ini (YYYY-MM-DD) sebagai acuan batas aktif
        $today = Carbon::today()->toDateString();

        // 2. Query kerja sama aktif dengan eager loading mitra (sargable query memanfaatkan indeks B-Tree tanggal_selesai)
        $query = KerjaSama::with('mitra')
            ->where('tanggal_selesai', '>=', $today);

        // 3. Pencarian opsional berdasarkan judul dokumen atau nama instansi mitra
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_kerja_sama', 'like', "%{$search}%")
                  ->orWhereHas('mitra', function ($m) use ($search) {
                      $m->where('nama_instansi', 'like', "%{$search}%");
                  });
            });
        }

        // 4. Hitung total data aktif sebelum dilakukan limitasi per halaman
        $totalAktif = (clone $query)->count();

        // 5. Paginasikan data dengan pengurutan tanggal selesai terdekat (ascending)
        $perPage   = $request->integer('per_page', 10);
        $paginated = $query->orderBy('tanggal_selesai', 'asc')->paginate($perPage);

        // 6. Kembalikan data daftar kerja sama aktif beserta total kuantitasnya
        return $this->respond(true, 200, 'Daftar kerja sama aktif berhasil diambil.', [
            'total_aktif' => $totalAktif,
            'kerjasama'   => $paginated,
        ]);
    }
}
