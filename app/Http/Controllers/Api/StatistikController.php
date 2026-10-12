<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\JabatanAkademik;
use App\Models\KerjaSama;
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
        $pkmQuery = Pkm::query();
        $publikasiQuery = Publikasi::query();

        // Filter berdasarkan tahun jika ada
        if ($tahun) {
            $penelitianQuery->where('tahun', $tahun);
            $pkmQuery->where('tahun', $tahun);
            $publikasiQuery->where('tahun', $tahun);
        }

        // Hitung total dan sebaran status penelitian
        $totalPenelitian = (clone $penelitianQuery)->count();
        $penelitianByStatus = (clone $penelitianQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Hitung total dan sebaran status PKM
        $totalPkm = (clone $pkmQuery)->count();
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
                'total' => $totalPublikasi,
            ],
            'grand_total' => $grandTotal,
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

        // Ambil data mahasiswa dari API K1
        $dataK1 = $k1->getStatistikMahasiswa(['tahun' => $tahun, 'prodi' => $prodi]);
        $totalMahasiswa = $dataK1['total_mahasiswa'] ?? null;

        $rasio = null;
        $statusPeringatan = null;

        if ($totalMahasiswa !== null && $totalDtps > 0) {
            $rasio = round($totalMahasiswa / $totalDtps, 2);
            $statusRasio = 'Tersedia';
        } elseif ($totalDtps === 0) {
            $statusRasio = 'Peringatan: Belum ada data Dosen DTPS di database untuk menghitung rasio.';
        } else {
            $statusRasio = 'Peringatan: Data mahasiswa dari API K1 belum tersedia.';
        }

        // Sebaran Jabatan Akademik
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

        $penelitianQuery = Penelitian::query();
        $pkmQuery = Pkm::query();
        $publikasiQuery = Publikasi::query();

        if ($tahun) {
            $penelitianQuery->where('tahun', $tahun);
            $pkmQuery->where('tahun', $tahun);
            $publikasiQuery->where('tahun', $tahun);
        }

        $totalPenelitian = $penelitianQuery->count();
        $totalPkm = $pkmQuery->count();
        $totalPublikasi = $publikasiQuery->count();

        // Hitung rata-rata per dosen DTPS
        $rataPenelitian = $totalDtps > 0 ? round($totalPenelitian / $totalDtps, 2) : 0;
        $rataPkm = $totalDtps > 0 ? round($totalPkm / $totalDtps, 2) : 0;
        $rataPublikasi = $totalDtps > 0 ? round($totalPublikasi / $totalDtps, 2) : 0;
        $skorTotal = round($rataPenelitian + $rataPkm + $rataPublikasi, 2);

        // Rekapitulasi per individu dosen
        $dosenList = Dosen::where('status_dosen', 'DTPS')
            ->whereNull('deleted_at')
            ->withCount(['penelitian', 'pkm', 'publikasi'])
            ->get()
            ->map(fn ($d) => [
                'id_dosen' => $d->id_dosen,
                'nama_dosen' => $d->nama_dosen,
                'nidn' => $d->nidn,
                'jumlah_penelitian' => $d->penelitian_count,
                'jumlah_pkm' => $d->pkm_count,
                'jumlah_publikasi' => $d->publikasi_count,
                'total_produktivitas' => $d->penelitian_count + $d->pkm_count + $d->publikasi_count,
            ]);

        return $this->respond(true, 200, 'Kalkulasi produktivitas dosen berhasil dihitung.', [
            'tahun' => $tahun ? (int) $tahun : 'Semua Periode',
            'total_dtps' => $totalDtps,
            'rata_penelitian_dtps' => $rataPenelitian,
            'rata_pkm_dtps' => $rataPkm,
            'rata_publikasi_dtps' => $rataPublikasi,
            'skor_produktivitas_avg' => $skorTotal,
            'daftar_dosen' => $dosenList,
        ]);
    }

    public function trenTriDharma(Request $request): JsonResponse
    {
        $ts = (int) $request->query('ts', date('Y'));
        $n = (int) $request->query('rentang', 4); // default 4 tahun ke belakang (TS-4 s/d TS)

        $tren = [];

        for ($i = $n; $i >= 0; $i--) {
            $tahunHitung = $ts - $i;
            $label = $i === 0 ? 'TS' : "TS-{$i}";

            $penelitian = Penelitian::where('tahun', $tahunHitung)->count();
            $pkm = Pkm::where('tahun', $tahunHitung)->count();
            $publikasi = Publikasi::where('tahun', $tahunHitung)->count();
            $kerjaSama = KerjaSama::whereYear('tanggal_mulai', '<=', $tahunHitung)
                ->whereYear('tanggal_selesai', '>=', $tahunHitung)
                ->count();

            $tren[] = [
                'label' => $label,
                'tahun' => $tahunHitung,
                'penelitian' => $penelitian,
                'pkm' => $pkm,
                'publikasi' => $publikasi,
                'kerjasama' => $kerjaSama,
                'total' => $penelitian + $pkm + $publikasi,
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
