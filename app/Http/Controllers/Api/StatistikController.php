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

class StatistikController extends Controller
{
    // Helper format response JSON standar
    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // Statistik demografi, status, jabatan, dan jenjang pendidikan dosen
    public function dosenStatistik(): JsonResponse
    {
        // Hitung total seluruh dosen dan dosen yang aktif
        $totalDosen = Dosen::count();
        $totalAktif = Dosen::where('status_dosen', 'Aktif')->count();

        // Agregasi sebaran status kepegawaian
        $sebaranStatus = Dosen::select('status_dosen', DB::raw('count(*) as total'))
            ->groupBy('status_dosen')
            ->pluck('total', 'status_dosen');

        // Agregasi sebaran jabatan akademik
        $sebaranJabatan = JabatanAkademik::withCount([
            'dosen' => fn ($q) => $q->whereNull('deleted_at'),
        ])
            ->get()
            ->map(fn ($j) => [
                'id_jabatan'   => $j->id_jabatan,
                'nama_jabatan' => $j->nama_jabatan,
                'total_dosen'  => $j->dosen_count,
            ]);

        // Agregasi sebaran jenjang pendidikan (S2, S3, dll.)
        $sebaranPendidikan = DB::table('dosen')
            ->join('pendidikan', 'dosen.id_pendidikan', '=', 'pendidikan.id_pendidikan')
            ->whereNull('dosen.deleted_at')
            ->select('pendidikan.jenjang', DB::raw('count(dosen.id_dosen) as total_dosen'))
            ->groupBy('pendidikan.jenjang')
            ->orderBy('pendidikan.jenjang')
            ->get();

        return $this->respond(true, 200, 'Statistik data dosen berhasil dihitung.', [
            'total_dosen'        => $totalDosen,
            'total_aktif'        => $totalAktif,
            'sebaran_status'     => $sebaranStatus,
            'sebaran_jabatan'    => $sebaranJabatan,
            'sebaran_pendidikan' => $sebaranPendidikan,
        ]);
    }

    // Rekapitulasi kuantitas kegiatan Tridharma (penelitian, PKM, publikasi)
    public function tridharmaSummary(Request $request): JsonResponse
    {
        // Validasi parameter tahun jika dikirimkan
        $tahun = $request->query('tahun');

        if ($tahun && ! is_numeric($tahun)) {
            return $this->respond(false, 422, 'Parameter tahun harus berupa angka 4 digit.');
        }

        // Query builder dasar untuk masing-masing bidang
        $penelitianQuery = Penelitian::query();
        $pkmQuery        = Pkm::query();
        $publikasiQuery  = Publikasi::query();

        // Filter berdasarkan tahun jika ada
        if ($tahun) {
            $penelitianQuery->where('tahun', $tahun);
            $pkmQuery->where('tahun', $tahun);
            $publikasiQuery->where('tahun', $tahun);
        }

        // Hitung total dan sebaran status penelitian
        $totalPenelitian    = (clone $penelitianQuery)->count();
        $penelitianByStatus = (clone $penelitianQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Hitung total dan sebaran status PKM
        $totalPkm    = (clone $pkmQuery)->count();
        $pkmByStatus = (clone $pkmQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Hitung total publikasi ilmiah
        $totalPublikasi = (clone $publikasiQuery)->count();

        // Hitung total keseluruhan kegiatan Tridharma
        $grandTotal = $totalPenelitian + $totalPkm + $totalPublikasi;

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

    // Ambil daftar kerja sama yang masih aktif (tanggal selesai >= hari ini)
    public function kerjasamaAktif(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        // Query kerja sama aktif dengan relasi mitra
        $query = KerjaSama::with('mitra')
            ->where('tanggal_selesai', '>=', $today);

        // Filter pencarian judul atau nama mitra
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_kerja_sama', 'like', "%{$search}%")
                  ->orWhereHas('mitra', function ($m) use ($search) {
                      $m->where('nama_instansi', 'like', "%{$search}%");
                  });
            });
        }

        // Hitung total data kerja sama aktif
        $totalAktif = (clone $query)->count();

        // Paginasi data berdasarkan tanggal selesai terdekat
        $perPage   = $request->integer('per_page', 10);
        $paginated = $query->orderBy('tanggal_selesai', 'asc')->paginate($perPage);

        return $this->respond(true, 200, 'Daftar kerja sama aktif berhasil diambil.', [
            'total_aktif' => $totalAktif,
            'kerjasama'   => $paginated,
        ]);
    }
}
