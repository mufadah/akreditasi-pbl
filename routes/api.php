<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BidangKeahlianController;
use App\Http\Controllers\Api\DosenController;
use App\Http\Controllers\Api\JabatanAkademikController;
use App\Http\Controllers\Api\PendidikanController;
use App\Http\Controllers\Api\TenagaKependidikanController;
use App\Http\Controllers\Api\JenisPenelitianController;
use App\Http\Controllers\Api\JenisPublikasiController;
use App\Http\Controllers\Api\MitraController;
use App\Http\Controllers\Api\PenelitianController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PkmController;
use App\Http\Controllers\Api\KerjaSamaController;
use App\Http\Controllers\Api\PublikasiController;


Route::middleware(['auth.jwt'])->get('/test-auth', function (Request $request) {
    return response()->json([
        'status'    => 'OK',
        'message'   => 'Middleware berhasil membaca token!',
        'auth_user' => $request->input('auth_user'),
    ]);
});
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('jwt.role:ADMINISTRATOR')->group(function () {
    Route::post('/dosen/import', [DosenController::class, 'importExcel']);
    Route::post('/dosen/{id}/restore', [DosenController::class, 'restore']);
    Route::apiResource('dosen', DosenController::class);

    Route::apiResource('jabatan-akademik', JabatanAkademikController::class);
    Route::apiResource('pendidikan', PendidikanController::class);
    Route::apiResource('bidang-keahlian', BidangKeahlianController::class);
    Route::apiResource('tendik', TenagaKependidikanController::class);
    Route::apiResource('jenis-penelitian', JenisPenelitianController::class);
    Route::apiResource('jenis-publikasi', JenisPublikasiController::class);
    Route::apiResource('mitra', MitraController::class);
    Route::apiResource('penelitian', PenelitianController::class);
    Route::apiResource('pkm', PkmController::class);
    Route::apiResource('kerja-sama', KerjaSamaController::class);
    Route::apiResource('publikasi', PublikasiController::class);
});