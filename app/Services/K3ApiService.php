<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class K3ApiService
{
    protected ?string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.k3.url', env('K3_API_URL', ''));
        $this->apiKey  = config('services.k3.key', env('K3_API_KEY', ''));
    }

    /**
     * Ambil daftar indikator aktif dari K3.
     */
    public function getIndikatorAktif(): array
    {
        if (empty($this->baseUrl)) {
            // Mock daftar indikator akreditasi standar LAM jika server K3 belum online
            return [
                ['kode' => 'SDM-01', 'nama' => 'Kecukupan Dosen Tetap Perguruan Tinggi (DTPS)', 'bidang' => 'SDM'],
                ['kode' => 'SDM-02', 'nama' => 'Kualifikasi Akademik Dosen Tetap (Doktor)', 'bidang' => 'SDM'],
                ['kode' => 'SDM-03', 'nama' => 'Jabatan Akademik Dosen Tetap (Lektor Kepala/Guru Besar)', 'bidang' => 'SDM'],
                ['kode' => 'PEN-01', 'nama' => 'Produktivitas Penelitian DTPS per Tahun', 'bidang' => 'Penelitian'],
                ['kode' => 'PKM-01', 'nama' => 'Produktivitas PkM DTPS per Tahun', 'bidang' => 'PkM'],
                ['kode' => 'KS-01',  'nama' => 'Kerja Sama Tridharma Internasional & Nasional', 'bidang' => 'Kerja Sama'],
            ];
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-API-KEY' => $this->apiKey])
                ->get("{$this->baseUrl}/instrumen/indikator-aktif");

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }
        } catch (\Throwable $e) {
            Log::warning("Gagal mengambil indikator dari API K3: {$e->getMessage()}");
        }

        return [];
    }

    /**
     * Ambil ambang batas rubrik skor per jenjang (D3, D4, S1, S2, S3).
     */
    public function getAmbangRubrik(string $kodeIndikator, string $jenjang = 'S1'): float
    {
        // Target default nilai akreditasi unggul = 3.5 s/d 4.0
        return 3.5;
    }

    /**
     * Mengirimkan hasil kalkulasi skor metrik & rekomendasi gap ke API K3.
     */
    public function kirimHasilAssessment(array $payload): bool
    {
        if (empty($this->baseUrl)) {
            Log::info("Mock: Hasil assessment berhasil dikirim ke K3.", $payload);
            return true;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-API-KEY' => $this->apiKey])
                ->post("{$this->baseUrl}/assessment/submit", $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Gagal mengirim hasil assessment ke API K3: {$e->getMessage()}");
            return false;
        }
    }
}
