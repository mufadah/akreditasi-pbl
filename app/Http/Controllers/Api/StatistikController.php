<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\Kegiatan;
use App\Models\KerjaSama;
use App\Models\Luaran;
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
            'code' => $code,
            'message' => $message,
            'data' => $data,
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
                'id_jabatan' => $j->id_jabatan,
                'nama_jabatan' => $j->nama_jabatan,
                'total_dosen' => $j->dosen_count,
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
            'total_dosen' => $totalDosen,
            'total_aktif' => $totalAktif,
            'sebaran_status' => $sebaranStatus,
            'sebaran_jabatan' => $sebaranJabatan,
            'sebaran_pendidikan' => $sebaranPendidikan,
        ]);
    }

    // Rekapitulasi kuantitas kegiatan Tridharma (Kegiatan Penelitian & PkM, serta Luaran)
    public function tridharmaSummary(Request $request): JsonResponse
    {
        // Validasi parameter tahun jika dikirimkan
        $tahun = $request->query('tahun');

        if ($tahun && ! is_numeric($tahun)) {
            return $this->respond(false, 422, 'Parameter tahun harus berupa angka 4 digit.');
        }

        // 1. Query Kegiatan (Unified Penelitian & PkM)
        $kegiatanQuery = Kegiatan::query();

        if ($tahun) {
            $kegiatanQuery->where(function ($q) use ($tahun) {
                $q->where('tahun_kegiatan', $tahun)
                    ->orWhere('tahun_pelaksanaan', $tahun)
                    ->orWhere('tahun_usulan', $tahun);
            });
        }

        $penelitianQuery = (clone $kegiatanQuery)->whereHas('jenisKegiatan', function ($q) {
            $q->where('nama_jenis_kegiatan', 'Penelitian');
        });

        $pkmQuery = (clone $kegiatanQuery)->whereHas('jenisKegiatan', function ($q) {
            $q->where('nama_jenis_kegiatan', 'PkM');
        });

        $totalPenelitian = (clone $penelitianQuery)->count();
        $penelitianByStatus = (clone $penelitianQuery)
            ->select('status_kegiatan', DB::raw('count(*) as total'))
            ->groupBy('status_kegiatan')
            ->pluck('total', 'status_kegiatan');

        $totalPkm = (clone $pkmQuery)->count();
        $pkmByStatus = (clone $pkmQuery)
            ->select('status_kegiatan', DB::raw('count(*) as total'))
            ->groupBy('status_kegiatan')
            ->pluck('total', 'status_kegiatan');

        // 2. Query Luaran Unified (Buku, Jurnal, Prosiding, HKI, Produk)
        $luaranQuery = Luaran::query();

        if ($tahun) {
            $luaranQuery->where(function ($q) use ($tahun) {
                $q->where('tahun_terbit', $tahun)
                    ->orWhereYear('tanggal_terbit', $tahun)
                    ->orWhereYear('tanggal_seminar', $tahun)
                    ->orWhereYear('tanggal_pendaftaran_hki', $tahun)
                    ->orWhereYear('tanggal_pembuatan', $tahun);
            });
        }

        $totalLuaran = (clone $luaranQuery)->count();
        $luaranByJenis = (clone $luaranQuery)
            ->join('jenis_luaran', 'luaran.id_jenis_luaran', '=', 'jenis_luaran.id_jenis_publikasi')
            ->select('jenis_luaran.nama_jenis_luaran', DB::raw('count(luaran.id_luaran) as total'))
            ->groupBy('jenis_luaran.nama_jenis_luaran')
            ->pluck('total', 'nama_jenis_luaran');

        // Fallback ke tabel lama jika tabel unifikasi belum di-backfill dan data lama ada
        if ($totalPenelitian === 0 && $totalPkm === 0 && $totalLuaran === 0) {
            $legacyPenelitian = Penelitian::when($tahun, fn ($q) => $q->where('tahun', $tahun));
            $legacyPkm = Pkm::when($tahun, fn ($q) => $q->where('tahun', $tahun));
            $legacyPublikasi = Publikasi::when($tahun, fn ($q) => $q->where('tahun', $tahun));

            if ($legacyPenelitian->exists() || $legacyPkm->exists() || $legacyPublikasi->exists()) {
                $totalPenelitian = (clone $legacyPenelitian)->count();
                $penelitianByStatus = (clone $legacyPenelitian)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');
                $totalPkm = (clone $legacyPkm)->count();
                $pkmByStatus = (clone $legacyPkm)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');
                $totalLuaran = (clone $legacyPublikasi)->count();
            }
        }

        // Total keseluruhan
        $grandTotal = $totalPenelitian + $totalPkm + $totalLuaran;

        return $this->respond(true, 200, 'Ringkasan kegiatan Tridharma berhasil dihitung.', [
            'filter_tahun' => $tahun ? (int) $tahun : null,
            'kegiatan' => [
                'total' => $totalPenelitian + $totalPkm,
                'penelitian' => [
                    'total' => $totalPenelitian,
                    'by_status' => [
                        'berjalan' => $penelitianByStatus['Berjalan'] ?? 0,
                        'selesai' => $penelitianByStatus['Selesai'] ?? 0,
                        'usulan' => $penelitianByStatus['Usulan'] ?? 0,
                        'lainnya' => $penelitianByStatus->except(['Berjalan', 'Selesai', 'Usulan'])->sum(),
                    ],
                ],
                'pkm' => [
                    'total' => $totalPkm,
                    'by_status' => [
                        'berjalan' => $pkmByStatus['Berjalan'] ?? 0,
                        'selesai' => $pkmByStatus['Selesai'] ?? 0,
                        'usulan' => $pkmByStatus['Usulan'] ?? 0,
                        'lainnya' => $pkmByStatus->except(['Berjalan', 'Selesai', 'Usulan'])->sum(),
                    ],
                ],
            ],
            'luaran' => [
                'total' => $totalLuaran,
                'by_jenis' => $luaranByJenis,
            ],
            // Kompatibilitas respons lama
            'penelitian' => [
                'total' => $totalPenelitian,
                'by_status' => [
                    'berjalan' => $penelitianByStatus['Berjalan'] ?? 0,
                    'selesai' => $penelitianByStatus['Selesai'] ?? 0,
                    'lainnya' => $penelitianByStatus->except(['Berjalan', 'Selesai'])->sum(),
                ],
            ],
            'pkm' => [
                'total' => $totalPkm,
                'by_status' => [
                    'berjalan' => $pkmByStatus['Berjalan'] ?? 0,
                    'selesai' => $pkmByStatus['Selesai'] ?? 0,
                    'lainnya' => $pkmByStatus->except(['Berjalan', 'Selesai'])->sum(),
                ],
            ],
            'publikasi' => [
                'total' => $totalLuaran,
            ],
            'grand_total' => $grandTotal,
        ]);
    }

    // Ambil daftar kerja sama yang masih aktif
    public function kerjasamaAktif(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        // Query kerja sama aktif dengan filter status 'Aktif' atau tanggal selesai >= hari ini
        $query = KerjaSama::with(['mitra', 'jenisKerjaSama', 'dosen'])
            ->where(function ($q) use ($today) {
                $q->where('status', 'Aktif')
                    ->orWhere('tanggal_selesai', '>=', $today);
            });

        // Filter pencarian judul, nomor dokumen, atau nama mitra
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_kerja_sama', 'like', "%{$search}%")
                    ->orWhere('nomor_dokumen', 'like', "%{$search}%")
                    ->orWhereHas('mitra', function ($m) use ($search) {
                        $m->where('nama_instansi', 'like', "%{$search}%");
                    });
            });
        }

        // Hitung total data kerja sama aktif
        $totalAktif = (clone $query)->count();

        // Paginasi data berdasarkan tanggal selesai terdekat
        $perPage = $request->integer('per_page', 10);
        $paginated = $query->orderBy('tanggal_selesai', 'asc')->paginate($perPage);

        return $this->respond(true, 200, 'Daftar kerja sama aktif berhasil diambil.', [
            'total_aktif' => $totalAktif,
            'kerjasama' => $paginated,
        ]);
    }
}
