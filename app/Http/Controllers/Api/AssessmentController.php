<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentHasil;
use App\Models\Dosen;
use App\Models\Kegiatan;
use App\Models\KerjaSama;
use App\Models\KonfigurasiMetrik;
use App\Services\AuditLogService;
use App\Services\K3ApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AssessmentController extends Controller
{
    private function respond(bool $success, int $code, string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Ambil riwayat hasil assessment & gap analysis yang tersimpan.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssessmentHasil::query();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($versi = $request->query('versi_instrumen')) {
            $query->where('versi_instrumen', $versi);
        }

        $hasil = $query->latest('id_assessment')->paginate($request->integer('per_page', 15));

        return $this->respond(true, 200, 'Daftar hasil assessment berhasil diambil.', $hasil);
    }

    /**
     * Jalankan proses Assessment & Gap Analysis sesuai Flowchart Fitur 12.
     */
    public function runAssessment(Request $request, K3ApiService $k3): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bidang' => 'required|string|in:SDM,Penelitian,PkM,Kerja Sama,Kerjasama,Semua',
            'versi_instrumen' => 'nullable|string|max:100',
            'jenjang' => 'nullable|string|max:50',
            'tahun' => 'nullable|integer|digits:4',
        ]);

        if ($validator->fails()) {
            return $this->respond(false, 422, 'Validasi gagal.', $validator->errors());
        }

        $bidangDipilih = $request->input('bidang');
        $versiInstrumen = $request->input('versi_instrumen', 'LAM-INFOKOM-2.1');
        $jenjang = $request->input('jenjang', 'S1');
        $tahun = $request->input('tahun', (int) date('Y'));

        // 1. Ambil daftar indikator dari K3 atau filter berdasarkan bidang
        $indikatorList = $k3->getIndikatorAktif();
        if ($bidangDipilih !== 'Semua') {
            $indikatorList = array_values(array_filter($indikatorList, function ($item) use ($bidangDipilih) {
                return strcasecmp($item['bidang'], $bidangDipilih) === 0;
            }));
        }

        if (empty($indikatorList)) {
            // Fallback default jika tidak ada indikator dari K3
            $indikatorList = [
                ['kode' => 'SDM-01', 'nama' => 'Kecukupan Dosen Tetap Perguruan Tinggi (DTPS)', 'bidang' => 'SDM'],
                ['kode' => 'PEN-01', 'nama' => 'Produktivitas Penelitian DTPS per Tahun', 'bidang' => 'Penelitian'],
                ['kode' => 'PKM-01', 'nama' => 'Produktivitas PkM DTPS per Tahun', 'bidang' => 'PkM'],
                ['kode' => 'KS-01',  'nama' => 'Kerja Sama Tridharma Aktif', 'bidang' => 'Kerja Sama'],
            ];
        }

        // Data agregasi dasar untuk perhitungan metrik
        $totalDtps = Dosen::where('status_dosen', 'DTPS')->whereNull('deleted_at')->count();
        $totalDosenS3 = Dosen::where('status_dosen', 'DTPS')
            ->whereHas('pendidikan', fn ($p) => $p->where('jenjang', 'S3'))
            ->whereNull('deleted_at')
            ->count();
        $totalPenelitian = Kegiatan::whereHas('jenisKegiatan', fn ($q) => $q->where('nama_jenis_kegiatan', 'Penelitian'))->count();
        $totalPkm = Kegiatan::whereHas('jenisKegiatan', fn ($q) => $q->where('nama_jenis_kegiatan', 'PkM'))->count();
        $totalKerjasama = KerjaSama::where('status', 'Aktif')->count();

        $hasilList = [];
        $gapCount = 0;

        foreach ($indikatorList as $ind) {
            $kode = $ind['kode'];
            $bidang = $ind['bidang'];

            // 2. Cari pemetaan indikator ke metrik di F13 (Fitur 13)
            $metrikConfig = KonfigurasiMetrik::where('kode_indikator', $kode)->first();

            // Jika pemetaan belum tersedia di F13 -> Tandai Butuh Konfigurasi Admin
            if (! $metrikConfig) {
                $assessment = AssessmentHasil::updateOrCreate(
                    ['kode_indikator' => $kode, 'versi_instrumen' => $versiInstrumen],
                    [
                        'nilai_metrik' => null,
                        'skor_capaian' => null,
                        'skor_target' => $k3->getAmbangRubrik($kode, $jenjang),
                        'status' => 'Butuh Konfigurasi Admin',
                        'gap' => null,
                        'rekomendasi' => "Indikator {$kode} belum dikonfigurasi rumusnya pada Fitur 13 (Konfigurasi Metrik). Silakan atur formula metrik terlebih dahulu.",
                        'dikirim_ke_k3_at' => null,
                    ]
                );

                $hasilList[] = $assessment;
                continue;
            }

            // 3. Ambil ambang rubrik per jenjang dari API K3
            $skorTarget = $metrikConfig->ambang_target ?? $k3->getAmbangRubrik($kode, $jenjang);

            // 4. Hitung nilai metrik dari data lokal
            $nilaiMetrik = 0.0;
            if (str_contains(strtoupper($kode), 'SDM') || strcasecmp($bidang, 'SDM') === 0) {
                $nilaiMetrik = (float) $totalDtps;
            } elseif (str_contains(strtoupper($kode), 'PEN') || strcasecmp($bidang, 'Penelitian') === 0) {
                $nilaiMetrik = $totalDtps > 0 ? round($totalPenelitian / $totalDtps, 2) : 0;
            } elseif (str_contains(strtoupper($kode), 'PKM') || strcasecmp($bidang, 'PkM') === 0) {
                $nilaiMetrik = $totalDtps > 0 ? round($totalPkm / $totalDtps, 2) : 0;
            } else {
                $nilaiMetrik = (float) $totalKerjasama;
            }

            // 5. Terapkan rumus dan rubrik untuk menghitung skor capaian (Skala 0 s/d 4.0)
            $skorCapaian = min(4.0, round(($nilaiMetrik / max(1.0, (float) $skorTarget)) * 4.0, 2));

            // 6. Cek apakah skor di bawah target?
            if ($skorCapaian < (float) $skorTarget) {
                $status = 'Gap Terdeteksi';
                $gap = round((float) $skorTarget - $skorCapaian, 2);
                $rekomendasi = "Capaian {$skorCapaian} berada di bawah target {$skorTarget} (Defisit: {$gap}). Diperlukan penambahan kuantitas data {$bidang} sebesar minimal " . ceil($gap * 2) . " kegiatan.";
                $gapCount++;
            } else {
                $status = 'Terpenuhi';
                $gap = 0.0;
                $rekomendasi = 'Indikator telah memenuhi atau melampaui ambang batas rubrik instrumen akreditasi.';
            }

            // 7. Simpan skor indikator ke assessment_hasil
            $assessment = AssessmentHasil::updateOrCreate(
                ['kode_indikator' => $kode, 'versi_instrumen' => $versiInstrumen],
                [
                    'nilai_metrik' => $nilaiMetrik,
                    'skor_capaian' => $skorCapaian,
                    'skor_target' => $skorTarget,
                    'status' => $status,
                    'gap' => $gap,
                    'rekomendasi' => $rekomendasi,
                ]
            );

            // 8. Kirim hasil ke K3 via API
            $sent = $k3->kirimHasilAssessment($assessment->toArray());
            if ($sent) {
                $assessment->update(['dikirim_ke_k3_at' => now()]);
            }

            $hasilList[] = $assessment;
        }

        if (class_exists(AuditLogService::class)) {
            AuditLogService::log('ASSESSMENT', 'AssessmentHasil', 0, null, [
                'bidang' => $bidangDipilih,
                'versi_instrumen' => $versiInstrumen,
                'total_indikator' => count($hasilList),
                'gap_terdeteksi' => $gapCount,
            ]);
        }

        return $this->respond(true, 200, 'Assessment dan Gap Analysis berhasil dijalankan serta dikirim ke K3.', [
            'bidang' => $bidangDipilih,
            'versi_instrumen' => $versiInstrumen,
            'total_indikator' => count($hasilList),
            'total_gap' => $gapCount,
            'hasil' => $hasilList,
        ]);
    }
}
