<?php

use App\Http\Controllers\Api\AktivitasKerjaSamaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BidangKeahlianController;
use App\Http\Controllers\Api\BukuController;
use App\Http\Controllers\Api\DosenController;
use App\Http\Controllers\Api\EvidenceController;
use App\Http\Controllers\Api\HkiController;
use App\Http\Controllers\Api\JabatanAkademikController;
use App\Http\Controllers\Api\JenisKegiatanController;
use App\Http\Controllers\Api\JenisKerjaSamaController;
use App\Http\Controllers\Api\JenisLuaranController;
use App\Http\Controllers\Api\JenisPenelitianController;
use App\Http\Controllers\Api\JenisPublikasiController;
use App\Http\Controllers\Api\KegiatanController;
use App\Http\Controllers\Api\KerjaSamaController;
use App\Http\Controllers\Api\LuaranController;
use App\Http\Controllers\Api\MitraController;
use App\Http\Controllers\Api\PendidikanController;
use App\Http\Controllers\Api\PenelitianController;
use App\Http\Controllers\Api\PkmController;
use App\Http\Controllers\Api\PublikasiController;
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

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware(['auth.jwt'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// 1. Menu Master Data (Khusus Administrator)
Route::middleware(['auth.jwt', 'jwt.role:ADMINISTRATOR'])->group(function () {
    Route::post('/dosen/import', [DosenController::class, 'importExcel']);
    Route::post('/dosen/{id}/restore', [DosenController::class, 'restore']);
    Route::post('/dosen/{id}/nonaktifkan', [DosenController::class, 'destroy']);
    Route::delete('/dosen/{id}/nonaktifkan', [DosenController::class, 'destroy']);
    Route::apiResource('dosen', DosenController::class);

    Route::apiResource('jabatan-akademik', JabatanAkademikController::class);
    Route::apiResource('pendidikan', PendidikanController::class);
    Route::apiResource('bidang-keahlian', BidangKeahlianController::class);
    Route::apiResource('tendik', TenagaKependidikanController::class);
    Route::apiResource('jenis-penelitian', JenisPenelitianController::class);
    Route::apiResource('jenis-publikasi', JenisPublikasiController::class);
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
    // Kerja Sama
    Route::get('/kerja-sama/{id}/aktivitas', [KerjaSamaController::class, 'getAktivitas']);
    Route::post('/kerja-sama/{id}/aktivitas', [KerjaSamaController::class, 'storeAktivitas']);
    Route::post('/kerja-sama/{id}/perpanjang', [KerjaSamaController::class, 'perpanjang']);
    Route::apiResource('kerja-sama', KerjaSamaController::class);
    Route::apiResource('aktivitas-kerja-sama', AktivitasKerjaSamaController::class);

    // Kegiatan & Luaran
    Route::apiResource('kegiatan', KegiatanController::class);
    Route::apiResource('luaran', LuaranController::class);

    // Penelitian, PkM, Publikasi, Buku, HKI
    Route::apiResource('penelitian', PenelitianController::class);
    Route::apiResource('pkm', PkmController::class);
    Route::apiResource('publikasi', PublikasiController::class);
    Route::apiResource('buku', BukuController::class);
    Route::apiResource('hki', HkiController::class);

    // Repository Evidence
    Route::post('/evidence/{id}/ganti-link', [EvidenceController::class, 'gantiLink']);
    Route::get('/evidence/{id}/download', [EvidenceController::class, 'download']);
    Route::apiResource('evidence', EvidenceController::class);

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
