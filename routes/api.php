<?php

use App\Http\Controllers\Api\AktivitasKerjaSamaController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\BidangKeahlianController;
use App\Http\Controllers\Api\DosenController;
use App\Http\Controllers\Api\EvidenceController;
use App\Http\Controllers\Api\JabatanAkademikController;
use App\Http\Controllers\Api\JenisKegiatanController;
use App\Http\Controllers\Api\JenisKerjaSamaController;
use App\Http\Controllers\Api\JenisLuaranController;
use App\Http\Controllers\Api\KegiatanController;
use App\Http\Controllers\Api\KerjaSamaController;
use App\Http\Controllers\Api\LuaranController;
use App\Http\Controllers\Api\MitraController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\KonfigurasiMetrikController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\PendidikanController;
use App\Http\Controllers\Api\SertifikasiController;
use App\Http\Controllers\Api\StatistikController;
use App\Http\Controllers\Api\TenagaKependidikanController;
use App\Services\K1ApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.jwt'])->get('/test-auth', function (Request $request) {
    return response()->json([
        'status' => 'OK',
        'message' => 'Middleware berhasil membaca token!',
        'auth_user' => $request->input('auth_user'),
    ]);
});

// Resource Server: Autentikasi dan penerbitan token dilakukan terpusat melalui SSO Kelompok 1
Route::match(['get', 'post'], '/login', [ProfileController::class, 'resourceServerInfo']);
Route::match(['get', 'post'], '/register', [ProfileController::class, 'resourceServerInfo']);

Route::middleware(['auth.jwt'])->group(function () {
    Route::get('/me', [ProfileController::class, 'me']);
    Route::post('/logout', function () {
        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => 'Logout berhasil. Sesi token telah dinonaktifkan di sisi klien.',
            'login_url' => config('services.k1.login_url', 'http://akademik.fayra.my.id/api/auth/login'),
        ]);
    });
});

// 1. CRUD Master Dosen (Diproteksi Granular oleh Permission JWT SSO K1)
Route::middleware(['auth.jwt'])->group(function () {
    Route::get('/dosen', [DosenController::class, 'index'])->middleware('jwt.permission:dosen.read');
    Route::get('/dosen/{id}', [DosenController::class, 'show'])->middleware('jwt.permission:dosen.read');
    Route::post('/dosen', [DosenController::class, 'store'])->middleware('jwt.permission:dosen.create');
    Route::post('/dosen/import', [DosenController::class, 'importExcel'])->middleware('jwt.permission:dosen.create');
    Route::match(['put', 'patch'], '/dosen/{id}', [DosenController::class, 'update'])->middleware('jwt.permission:dosen.update');
    Route::delete('/dosen/{id}', [DosenController::class, 'destroy'])->middleware('jwt.permission:dosen.delete');
    Route::match(['post', 'delete'], '/dosen/{id}/nonaktifkan', [DosenController::class, 'destroy'])->middleware('jwt.permission:dosen.delete');
    Route::post('/dosen/{id}/restore', [DosenController::class, 'restore'])->middleware('jwt.permission:dosen.delete');
});

// 2. Menu Master Data Lainnya (Khusus Administrator)
Route::middleware(['auth.jwt', 'jwt.role:ADMINISTRATOR'])->group(function () {

    Route::apiResource('jabatan-akademik', JabatanAkademikController::class);
    Route::apiResource('pendidikan', PendidikanController::class);
    Route::apiResource('bidang-keahlian', BidangKeahlianController::class);
    Route::post('/tendik/{id}/restore', [TenagaKependidikanController::class, 'restore']);
    Route::post('/tendik/{id}/nonaktifkan', [TenagaKependidikanController::class, 'destroy']);
    Route::delete('/tendik/{id}/nonaktifkan', [TenagaKependidikanController::class, 'destroy']);
    Route::apiResource('tendik', TenagaKependidikanController::class);
    Route::apiResource('jenis-kegiatan', JenisKegiatanController::class);
    Route::apiResource('jenis-luaran', JenisLuaranController::class);
    Route::apiResource('jenis-kerja-sama', JenisKerjaSamaController::class);
    Route::apiResource('jenis-kerjasama', JenisKerjaSamaController::class);
    Route::apiResource('mitra', MitraController::class);
});

// 2. Menu Validasi Evidence (Hanya Administrator, Kaprodi, dan Asesor - Dosen Ditolak 403)
Route::middleware(['auth.jwt', 'jwt.role:ADMINISTRATOR,KAPRODI,ASESOR'])->group(function () {
    Route::post('/evidence/{id}/validasi', [EvidenceController::class, 'validasi']);
});

// 3. Menu Tridharma, Repository Evidence, & Interaksi (Admin, Kaprodi, Dosen, Asesor)
Route::middleware(['auth.jwt', 'jwt.role:ADMINISTRATOR,KAPRODI,DOSEN,ASESOR'])->group(function () {
    // Kerja Sama (Fitur 10 & Reminder Kedaluwarsa)
    Route::get('/kerja-sama/reminder-kedaluwarsa', [KerjaSamaController::class, 'reminderKedaluwarsa']);
    Route::post('/kerja-sama/check-kedaluwarsa', [KerjaSamaController::class, 'checkKedaluwarsa']);
    Route::get('/kerja-sama/{id}/aktivitas', [KerjaSamaController::class, 'getAktivitas']);
    Route::post('/kerja-sama/{id}/aktivitas', [KerjaSamaController::class, 'storeAktivitas']);
    Route::post('/kerja-sama/{id}/perpanjang', [KerjaSamaController::class, 'perpanjang']);
    Route::apiResource('kerja-sama', KerjaSamaController::class);
    Route::apiResource('aktivitas-kerja-sama', AktivitasKerjaSamaController::class);

    // Kegiatan & Luaran (Unified Tridharma & Luaran Ilmiah Sesuai ERD Terbaru)
    Route::apiResource('kegiatan', KegiatanController::class);
    Route::apiResource('luaran', LuaranController::class);
    Route::post('/sertifikasi/sync-profil', [SertifikasiController::class, 'syncProfil']);
    Route::apiResource('sertifikasi', SertifikasiController::class);

    // Repository Evidence
    Route::post('/evidence/{id}/ganti-link', [EvidenceController::class, 'gantiLink']);
    Route::get('/evidence/{id}/download', [EvidenceController::class, 'download']);
    Route::apiResource('evidence', EvidenceController::class);

    // Fitur 13: Konfigurasi Metrik (API K3)
    Route::get('/konfigurasi-metrik/indikator-k3', [KonfigurasiMetrikController::class, 'getIndikatorK3']);
    Route::apiResource('konfigurasi-metrik', KonfigurasiMetrikController::class);

    // Fitur 12: Assessment SDM & Tri Dharma (Engine Gap Analysis SDM & Rekomendasi)
    Route::post('/assessment/run', [AssessmentController::class, 'runAssessment']);
    Route::get('/assessment/hasil', [AssessmentController::class, 'index']);

    // Fitur 16: Notifikasi & Audit Log (Checklist 10 & 11)
    Route::get('/notifikasi', [NotifikasiController::class, 'index']);
    Route::post('/notifikasi', [NotifikasiController::class, 'store']);
    Route::post('/notifikasi/{id}/baca', [NotifikasiController::class, 'markAsRead']);
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'markAllAsRead']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/{id}', [AuditLogController::class, 'show']);

    // API V1: Endpoint Statistik & Agregasi Data Dashboard
    Route::prefix('v1')->group(function () {
        Route::get('/dosen/statistik', [StatistikController::class, 'dosenStatistik']);
        Route::get('/tridharma/summary', [StatistikController::class, 'tridharmaSummary']);
        Route::get('/kerjasama/aktif', [StatistikController::class, 'kerjasamaAktif']);
        // Rute Dashboard & 4 Tab Analitik
        Route::get('/dashboard', [StatistikController::class, 'dashboard']);
        Route::get('/rasio-dosen-mahasiswa', [StatistikController::class, 'rasioDosenMahasiswa']);
        Route::get('/produktivitas-dosen', [StatistikController::class, 'produktivitasDosen']);
        Route::get('/tren-tridharma', [StatistikController::class, 'trenTriDharma']);
        // Alias Endpoint Statistik Postman
        Route::get('/dosen/rasio', [StatistikController::class, 'rasioDosenMahasiswa']);
        Route::get('/dosen/produktivitas', [StatistikController::class, 'produktivitasDosen']);
        Route::get('/tridharma/tren', [StatistikController::class, 'trenTriDharma']);
    });
});

// Endpoint Interoperabilitas: Validasi NIM Mahasiswa via API K1
Route::post('/k1/validasi-nim', function (Request $request, K1ApiService $k1) {
    $request->validate(['nim' => 'required|string']);
    $isValid = $k1->validateNim($request->input('nim'));

    if (! $isValid) {
        return response()->json([
            'success' => false,
            'code' => 422,
            'message' => "NIM Tidak Valid: '{$request->input('nim')}' tidak terdaftar di sistem K1.",
            'data' => ['valid' => false],
        ], 422);
    }

    return response()->json([
        'success' => true,
        'code' => 200,
        'message' => 'NIM valid dan aktif di sistem K1.',
        'data' => ['valid' => true, 'nim' => $request->input('nim')],
    ], 200);
});
