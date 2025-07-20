<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FaceCaptureController;
use App\Http\Controllers\ExportRekapController;
use App\Http\Controllers\PdfController; // Tambahkan ini
use App\Http\Controllers\BerandaController; // Tambahkan ini

Route::get('/', [BerandaController::class, 'index'])->name('beranda');

Route::post('/pegawai/{pegawai}/save-face', [FaceCaptureController::class, 'store'])->name('pegawai.save-face');

Route::get('/rekap-kehadiran/export-pdf', [ExportRekapController::class, 'export'])->name('rekap.export.pdf');

Route::get('/lapkin-report/print', [PdfController::class, 'printLapkin'])->name('lapkin.print');


