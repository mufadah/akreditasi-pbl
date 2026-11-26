<?php

namespace App\Services;

use App\Http\Controllers\Api\StatistikController;
use Illuminate\Http\Request;

class LaporanService
{
    private const LABEL_BIDANG = [
        'penelitian' => 'Penelitian',
        'pkm' => 'Pengabdian kepada Masyarakat (PkM)',
        'kerjasama' => 'Kerja Sama',
    ];

    private const JUDUL_LEMBAR = [
        'penelitian' => 'Penelitian',
        'pkm' => 'PkM',
        'kerjasama' => 'Kerja Sama',
    ];

    public function __construct(
        private AssessmentKegiatanService $kegiatan,
        private AssessmentKerjaSamaService $kerjaSama,
        private GapKompetensiDosenService $gap,
        private EarlyWarningService $earlyWarning,
    ) {}

    public function assessment($tahun, $jenjang, $bidang = 'semua')
    {
        $daftar = $bidang === 'semua' ? array_keys(self::LABEL_BIDANG) : [$bidang];
        $hasil = [];

        foreach ($daftar as $nama) {
            $hasil[$nama] = $nama === 'kerjasama'
                ? $this->kerjaSama->hitung($tahun, $jenjang)
                : $this->kegiatan->hitung($nama, $tahun, $jenjang);
        }

        $gap = $this->gap->hitung($jenjang);

        return [
            'judul' => 'Laporan Hasil Assessment dan Gap Analysis',
            'tahun' => (int) $tahun,
            'jenjang' => $jenjang,
            'dibuat' => now()->format('d-m-Y H:i'),
            'label' => self::LABEL_BIDANG,
            'bidang' => $hasil,
            'gap_kompetensi' => $gap,
            'rekomendasi' => $this->rekomendasi($hasil, $gap),
            'catatan' => $this->catatan($hasil, $gap),
        ];
    }

    public function lembarAssessment($laporan)
    {
        $ringkasan = [];
        $lembarBidang = [];

        foreach ($laporan['bidang'] as $nama => $hasil) {
            $ringkasan[] = [
                self::LABEL_BIDANG[$nama],
                $hasil['ringkasan']['nilai_bidang'],
                $hasil['ringkasan']['total_indikator'],
                $hasil['ringkasan']['indikator_terpenuhi'],
                $hasil['ringkasan']['gap_terdeteksi'],
            ];

            $lembarBidang[] = [
                'judul' => self::JUDUL_LEMBAR[$nama],
                'headings' => ['Kode', 'Indikator', 'Nilai Metrik', 'Satuan', 'Bobot', 'Skor', 'Skor Target', 'Status', 'Gap', 'Rekomendasi'],
                'rows' => array_map(fn ($item) => [
                    $item['kode'], $item['nama'], $item['nilai_metrik'], $item['satuan'], $item['bobot'],
                    $item['skor'], $item['skor_target'], $item['status'], $item['gap'], $item['rekomendasi'],
                ], $hasil['indikator']),
            ];
        }

        $gapBaris = array_map(fn ($item) => [
            $item['kode'],
            $item['nama'],
            $item['jumlah_dosen'],
            $item['persentase'],
            $item['standar_persen'] ?? 'belum diatur',
            $item['selisih_persen'] ?? '-',
            $item['kekurangan_dosen'] ?? '-',
            str_replace('_', ' ', $item['status']),
        ], $laporan['gap_kompetensi']['indikator']);

        $rekomendasiBaris = [];

        foreach ($laporan['rekomendasi'] as $urutan => $item) {
            $rekomendasiBaris[] = [$urutan + 1, $item['sumber'], $item['rekomendasi']];
        }

        return array_merge(
            [['judul' => 'Ringkasan', 'headings' => ['Bidang', 'Nilai Bidang', 'Total Indikator', 'Terpenuhi', 'Gap'], 'rows' => $ringkasan]],
            $lembarBidang,
            [
                ['judul' => 'Gap Kompetensi', 'headings' => ['Kode', 'Indikator', 'Jumlah Dosen', 'Persentase', 'Standar (%)', 'Selisih (%)', 'Kekurangan Dosen', 'Status'], 'rows' => $gapBaris],
                ['judul' => 'Rekomendasi', 'headings' => ['No', 'Sumber', 'Rekomendasi'], 'rows' => $rekomendasiBaris],
            ]
        );
    }

    public function dashboard($tahun)
    {
        $request = Request::create('/', 'GET', ['tahun' => $tahun, 'ts' => $tahun, 'rentang' => 4]);
        $statistik = app(StatistikController::class);
        $earlyWarning = $this->earlyWarning->hitung(['tahun' => (int) $tahun]);

        return [
            'judul' => 'Laporan Dashboard Tri Dharma',
            'tahun' => (int) $tahun,
            'dibuat' => now()->format('d-m-Y H:i'),
            'ringkasan' => $this->data($statistik->tridharmaSummary($request)),
            'produktivitas' => $this->data($statistik->produktivitasDosen($request)),
            'rasio' => $this->rasio($statistik, $request),
            'tren' => $this->data($statistik->trenTriDharma($request)),
            'early_warning' => $earlyWarning,
            'early_warning_baris' => $this->barisEarlyWarning($earlyWarning),
        ];
    }

    public function lembarDashboard($laporan)
    {
        $ringkasan = $laporan['ringkasan'];
        $produktivitas = $laporan['produktivitas'];
        $rasio = $laporan['rasio'];

        $barisRingkasan = [['Total kegiatan', data_get($ringkasan, 'kegiatan.total', 0)]];

        foreach (['penelitian' => 'Penelitian', 'pkm' => 'PkM'] as $kunci => $nama) {
            $barisRingkasan[] = ["{$nama} - total", data_get($ringkasan, "kegiatan.{$kunci}.total", 0)];

            foreach (['berjalan', 'selesai', 'usulan', 'lainnya'] as $status) {
                $barisRingkasan[] = ["{$nama} - {$status}", data_get($ringkasan, "kegiatan.{$kunci}.by_status.{$status}", 0)];
            }
        }

        $barisRingkasan[] = ['Luaran - total', data_get($ringkasan, 'luaran.total', 0)];

        foreach (data_get($ringkasan, 'luaran.by_jenis', []) as $jenis => $jumlah) {
            $barisRingkasan[] = ["Luaran - {$jenis}", $jumlah];
        }

        $barisRingkasan[] = ['Grand total', data_get($ringkasan, 'grand_total', 0)];

        return [
            ['judul' => 'Ringkasan', 'headings' => ['Indikator', 'Nilai'], 'rows' => $barisRingkasan],
            [
                'judul' => 'Produktivitas',
                'headings' => ['Indikator', 'Nilai'],
                'rows' => [
                    ['Total DTPS', $produktivitas['total_dtps'] ?? 0],
                    ['Rata-rata penelitian per DTPS', $produktivitas['rata_penelitian_dtps'] ?? 0],
                    ['Rata-rata PkM per DTPS', $produktivitas['rata_pkm_dtps'] ?? 0],
                    ['Rata-rata luaran per DTPS', $produktivitas['rata_luaran_dtps'] ?? 0],
                    ['Skor produktivitas rata-rata', $produktivitas['skor_produktivitas_avg'] ?? 0],
                ],
            ],
            [
                'judul' => 'Luaran per Dosen',
                'headings' => ['Dosen', 'NIDN', 'Jumlah Luaran'],
                'rows' => array_map(
                    fn ($item) => [$item['nama_dosen'], $item['nidn'], $item['jumlah_luaran']],
                    $produktivitas['daftar_dosen'] ?? []
                ),
            ],
            [
                'judul' => 'Rasio',
                'headings' => ['Indikator', 'Nilai'],
                'rows' => [
                    ['Program studi', $rasio['program_studi'] ?? '-'],
                    ['Total mahasiswa (K1)', $rasio['total_mahasiswa_k1'] ?? 'tidak tersedia'],
                    ['Total DTPS', $rasio['total_dtps'] ?? 0],
                    ['Total dosen aktif', $rasio['total_dosen_aktif'] ?? 0],
                    ['Rasio mahasiswa per DTPS', $rasio['rasio_dosen_mahasiswa'] ?? 'tidak tersedia'],
                    ['Status', $rasio['status_rasio'] ?? 'Data tidak tersedia'],
                ],
            ],
            [
                'judul' => 'Sebaran Jabatan',
                'headings' => ['Jabatan Akademik', 'Total Dosen'],
                'rows' => array_map(
                    fn ($item) => [$item['nama_jabatan'], $item['total_dosen']],
                    $rasio['sebaran_jabatan'] ?? []
                ),
            ],
            [
                'judul' => 'Tren',
                'headings' => ['Periode', 'Tahun', 'Penelitian', 'PkM', 'Luaran', 'Kerja Sama', 'Total'],
                'rows' => array_map(
                    fn ($item) => [$item['label'], $item['tahun'], $item['penelitian'], $item['pkm'], $item['luaran'], $item['kerjasama'], $item['total']],
                    data_get($laporan['tren'], 'tren_data', [])
                ),
            ],
            [
                'judul' => 'Early Warning',
                'headings' => ['Indikator', 'Dosen', 'NIDN', 'Keterangan'],
                'rows' => $laporan['early_warning_baris'],
            ],
        ];
    }

    private function data($respons)
    {
        return $respons->getData(true)['data'] ?? [];
    }

    private function rasio($statistik, $request)
    {
        try {
            return $this->data($statistik->rasioDosenMahasiswa($request, app(K1ApiService::class)));
        } catch (\Throwable) {
            return [];
        }
    }

    private function barisEarlyWarning($earlyWarning)
    {
        $baris = [];

        foreach ($earlyWarning['indikator'] as $indikator) {
            if ($indikator['kode'] === 'EW-RASIO') {
                $baris[] = [
                    $indikator['nama'],
                    '-',
                    '-',
                    'Status: '.str_replace('_', ' ', $indikator['status']).'; rasio '.($indikator['rasio_dosen_mahasiswa'] ?? '-').'; batas '.($indikator['batas_maksimal'] ?? '-'),
                ];

                continue;
            }

            foreach ($indikator['daftar'] as $item) {
                $keterangan = $indikator['kode'] === 'EW-SERTIFIKASI'
                    ? "{$item['jenis_sertifikasi']}, berakhir {$item['tanggal_kedaluwarsa']} ({$item['status']})"
                    : 'Tahun terakhir: '.($item['tahun_terakhir'] ?? 'belum ada');

                $baris[] = [$indikator['nama'], $item['nama_dosen'], $item['nidn'], $keterangan];
            }
        }

        return $baris;
    }

    private function rekomendasi($hasil, $gap)
    {
        $daftar = [];

        foreach ($hasil as $nama => $item) {
            foreach ($item['indikator'] as $indikator) {
                if ($indikator['status'] === 'Gap Terdeteksi') {
                    $daftar[] = [
                        'sumber' => self::LABEL_BIDANG[$nama].' - '.$indikator['kode'].' '.$indikator['nama'],
                        'rekomendasi' => $indikator['rekomendasi'],
                    ];
                }
            }
        }

        foreach ($gap['indikator'] as $indikator) {
            if ($indikator['status'] === 'belum_terpenuhi') {
                $daftar[] = [
                    'sumber' => 'Gap Kompetensi Dosen - '.$indikator['nama'],
                    'rekomendasi' => "Tambah {$indikator['kekurangan_dosen']} dosen agar mencapai standar {$indikator['standar_persen']}% (saat ini {$indikator['persentase']}%).",
                ];
            }
        }

        return $daftar;
    }

    private function catatan($hasil, $gap)
    {
        $catatan = [];

        if (collect($hasil)->contains(fn ($item) => ($item['sumber_rubrik'] ?? '') === 'konfigurasi_lokal_sementara')) {
            $catatan[] = 'Rubrik dan bobot indikator memakai konfigurasi lokal sementara sampai rubrik resmi dari K3 tersedia.';
        }

        foreach ($hasil as $item) {
            foreach ($item['catatan_data'] ?? [] as $teks) {
                $catatan[] = $teks;
            }
        }

        $belumDiatur = $gap['ringkasan']['standar_belum_diatur'] ?? 0;

        if ($belumDiatur > 0) {
            $catatan[] = "{$belumDiatur} indikator gap kompetensi dosen belum memiliki standar persentase sehingga belum dinilai.";
        }

        return $catatan;
    }
}