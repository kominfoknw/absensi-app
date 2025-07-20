<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AbsensiController;
use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\BeritaController;
use App\Http\Controllers\Api\IzinPegawaiController; 
use App\Http\Controllers\Api\TugasLuarController; 
use App\Http\Controllers\Api\LapkinController; // <-- TAMBAHKAN INI
use App\Http\Controllers\Api\EmergencyAbsenceController; // <-- Tambahkan baris ini


Route::post('/login', [AuthController::class, 'login']);

 // Berita
 Route::get('/berita', [BeritaController::class, 'index']);
 Route::get('/berita/{id}', [BeritaController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Absensi
    Route::post('/absensi/checkin', [AbsensiController::class, 'checkIn']);
    Route::post('/absensi/checkout', [AbsensiController::class, 'checkOut']);
    Route::get('/absensi/history', [AbsensiController::class, 'history']);

    // Data Master
    Route::get('/lokasi/{id}', [DataController::class, 'getLokasi']);
    Route::get('/shift/{id}', [DataController::class, 'getShift']);


    // Rute API untuk Izin Pegawai
    Route::get('/izin-pegawai', [IzinPegawaiController::class, 'index']); // Daftar izin pegawai sendiri
    Route::post('/izin-pegawai', [IzinPegawaiController::class, 'store']); // Mengajukan izin
    Route::delete('/izin-pegawai/{izin}', [IzinPegawaiController::class, 'destroy']); // Menghapus izin

    Route::resource('tugas_luar', TugasLuarController::class)->only(['index', 'store', 'destroy']); // <-- TAMBAHKAN INI

    Route::resource('lapkin', LapkinController::class)->only(['index', 'store', 'destroy']); // <-- TAMBAHKAN INI

    Route::get('/history', [AbsensiController::class, 'history']); // <- Tambahkan ini

    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');

    // --- TAMBAHKAN RUTE INI UNTUK ABSEN DARURAT ---
Route::post('/emergency-absence', [EmergencyAbsenceController::class, 'recordAbsence']);
// ---------------------------------------------


    // Route::post('/pengajuan/izin', ...);
    // Route::post('/pengajuan/cuti', ...);
    // Route::post('/pengajuan/tugas-luar', ...);
});