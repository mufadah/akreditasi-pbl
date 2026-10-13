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
use App\Services\K1ApiService;
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
        $totalDosen = Dosen::count();
        $totalAktif = Dosen::where('status_dosen', 'DTPS')->count();

        $sebaranStatus = Dosen::select('status_dosen', DB::raw('count(*) as total'))
            ->groupBy('status_dosen')
            ->pluck('total', 'status_dosen');

        $sebaranJabatan = JabatanAkademik::withCount([
            'dosen' => fn ($q) => $q->whereNull('deleted_at'),
        ])
            ->get()
            ->map(fn ($j) => [
                'id_jabatan' => $j->id_jabatan,
                'nama_jabatan' => $j->nama_jabatan,
                'total_dosen' => $j->dosen_count,
            ]);

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
                    ->orWhereYear('tanggal_pembuatan', $tahun)
                    ->orWhereYear('created_at', $tahun);
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
                    ->orWhereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', $today);
            });

        // Filter pencarian judul, nomor dokumen, atau nama mitra
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_kerja_sama', 'like', "%{$search}%")
                    ->orWhere('nomor_dokumen', 'like', "%{$search}%")
                    ->orWhere('jenis_dokumen', 'like', "%{$search}%")
                    ->orWhereHas('mitra', function ($m) use ($search) {
                        $m->where('nama_instansi', 'like', "%{$search}%")
                            ->orWhere('nama_mitra', 'like', "%{$search}%");
                    });
            });
        }

        $totalAktif = (clone $query)->count();

        // Paginasi data berdasarkan tanggal selesai terdekat
        $perPage = $request->integer('per_page', 10);
        $paginated = $query->orderBy('tanggal_selesai', 'asc')->paginate($perPage);

        return $this->respond(true, 200, 'Daftar kerja sama aktif berhasil diambil.', [
            'total_aktif' => $totalAktif,
            'kerjasama' => $paginated,
        ]);
    }

    // TUGAS 7: Kalkulasi Rasio Dosen : Mahasiswa via API K1 & Sebaran Jabatan
    public function rasioDosenMahasiswa(Request $request, K1ApiService $k1): JsonResponse
    {
        $tahun = $request->query('tahun', date('Y'));
        $prodi = $request->query('prodi', 'Teknologi Rekayasa Komputer');

        $totalDtps = Dosen::where('status_dosen', 'DTPS')->whereNull('deleted_at')->count();
        $totalDosenAktif = Dosen::whereNull('deleted_at')->count();

        $dataK1 = $k1->getStatistikMahasiswa(['tahun' => $tahun, 'prodi' => $prodi]);
        $totalMahasiswa = $dataK1['total_mahasiswa'] ?? null;

        $rasio = null;

        if ($totalMahasiswa !== null && $totalDtps > 0) {
            $rasio = round($totalMahasiswa / $totalDtps, 2);
            $statusRasio = 'Tersedia';
        } elseif ($totalDtps === 0) {
            $statusRasio = 'Peringatan: Belum ada data Dosen DTPS di database untuk menghitung rasio.';
        } else {
            $statusRasio = 'Peringatan: Data mahasiswa dari API K1 belum tersedia.';
        }

        $sebaranJabatan = JabatanAkademik::withCount(['dosen' => fn ($q) => $q->whereNull('deleted_at')])
            ->get()
            ->map(fn ($j) => [
                'nama_jabatan' => $j->nama_jabatan,
                'total_dosen' => $j->dosen_count,
            ]);

        return $this->respond(true, 200, 'Kalkulasi rasio dosen:mahasiswa dan sebaran jabatan berhasil diambil.', [
            'tahun' => (int) $tahun,
            'program_studi' => $prodi,
            'total_mahasiswa_k1' => $totalMahasiswa,
            'total_dtps' => $totalDtps,
            'total_dosen_aktif' => $totalDosenAktif,
            'rasio_dosen_mahasiswa' => $rasio,
            'status_rasio' => $statusRasio,
            'sebaran_jabatan' => $sebaranJabatan,
        ]);
    }

    // TUGAS 8: Kalkulasi Produktivitas Dosen (Rata-rata Tridharma per Dosen DTPS)
    public function produktivitasDosen(Request $request): JsonResponse
    {
        $tahun = $request->query('tahun');

        $dtpsQuery = Dosen::where('status_dosen', 'DTPS')->whereNull('deleted_at');
        $totalDtps = $dtpsQuery->count();

        $penelitianQuery = Kegiatan::whereHas('jenisKegiatan', fn ($q) => $q->where('nama_jenis_kegiatan', 'Penelitian'));
        $pkmQuery = Kegiatan::whereHas('jenisKegiatan', fn ($q) => $q->where('nama_jenis_kegiatan', 'PkM'));
        $luaranQuery = Luaran::query();

        if ($tahun) {
            $penelitianQuery->where('tahun_kegiatan', $tahun);
            $pkmQuery->where('tahun_kegiatan', $tahun);
            $luaranQuery->whereYear('created_at', $tahun);
        }

        $totalPenelitian = $penelitianQuery->count();
        $totalPkm = $pkmQuery->count();
        $totalLuaran = $luaranQuery->count();

        $rataPenelitian = $totalDtps > 0 ? round($totalPenelitian / $totalDtps, 2) : 0;
        $rataPkm = $totalDtps > 0 ? round($totalPkm / $totalDtps, 2) : 0;
        $rataLuaran = $totalDtps > 0 ? round($totalLuaran / $totalDtps, 2) : 0;
        $skorTotal = round($rataPenelitian + $rataPkm + $rataLuaran, 2);

        $dosenList = Dosen::where('status_dosen', 'DTPS')
            ->whereNull('deleted_at')
            ->withCount(['luaran'])
            ->get()
            ->map(fn ($d) => [
                'id_dosen' => $d->id_dosen,
                'nama_dosen' => $d->nama_dosen,
                'nidn' => $d->nidn,
                'jumlah_luaran' => $d->luaran_count,
            ]);

        return $this->respond(true, 200, 'Kalkulasi produktivitas dosen berhasil dihitung.', [
            'tahun' => $tahun ? (int) $tahun : 'Semua Periode',
            'total_dtps' => $totalDtps,
            'rata_penelitian_dtps' => $rataPenelitian,
            'rata_pkm_dtps' => $rataPkm,
            'rata_luaran_dtps' => $rataLuaran,
            'skor_produktivitas_avg' => $skorTotal,
            'daftar_dosen' => $dosenList,
        ]);
    }

    public function trenTriDharma(Request $request): JsonResponse
    {
        $ts = (int) $request->query('ts', date('Y'));
        $n = (int) $request->query('rentang', 4);

        $tren = [];

        for ($i = $n; $i >= 0; $i--) {
            $tahunHitung = $ts - $i;
            $label = $i === 0 ? 'TS' : "TS-{$i}";

            $penelitian = Kegiatan::whereHas('jenisKegiatan', fn ($q) => $q->where('nama_jenis_kegiatan', 'Penelitian'))
                ->where('tahun_kegiatan', $tahunHitung)->count();
            $pkm = Kegiatan::whereHas('jenisKegiatan', fn ($q) => $q->where('nama_jenis_kegiatan', 'PkM'))
                ->where('tahun_kegiatan', $tahunHitung)->count();
            $luaran = Luaran::whereYear('created_at', $tahunHitung)->count();
            $kerjaSama = KerjaSama::whereYear('tanggal_mulai', '<=', $tahunHitung)
                ->where(function ($q) use ($tahunHitung) {
                    $q->whereNull('tanggal_selesai')
                        ->orWhereYear('tanggal_selesai', '>=', $tahunHitung);
                })
                ->count();

            $tren[] = [
                'label' => $label,
                'tahun' => $tahunHitung,
                'penelitian' => $penelitian,
                'pkm' => $pkm,
                'luaran' => $luaran,
                'kerjasama' => $kerjaSama,
                'total' => $penelitian + $pkm + $luaran,
            ];
        }

        return $this->respond(true, 200, 'Analitik tren TS sampai TS-n berhasil dihitung.', [
            'tahun_sekarang' => $ts,
            'rentang_tahun' => $n,
            'tren_data' => $tren,
        ]);
    }

    // Endpoint Dashboard Terpadu (4 Tab Sesuai Flowchart 14)
    public function dashboard(Request $request, K1ApiService $k1): JsonResponse
    {
        $tab = $request->query('tab', 'ringkasan');

        return match ($tab) {
            'produktivitas' => $this->produktivitasDosen($request),
            'rasio_jabatan' => $this->rasioDosenMahasiswa($request, $k1),
            'tren' => $this->trenTriDharma($request),
            default => $this->tridharmaSummary($request),
        };
    }
}
