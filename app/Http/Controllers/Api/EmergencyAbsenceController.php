<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kantor;
use App\Models\Kehadiran;
use App\Models\User;
use App\Models\Pegawai; // <-- Penting: Impor model Pegawai
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EmergencyAbsenceController extends Controller
{
    public function recordAbsence(Request $request)
    {
        // Validasi input dari Flutter
        $validator = Validator::make($request->all(), [
            'qr_code_secret' => 'required|string',
            'user_id' => 'required|exists:users,id', // Pastikan user_id dari Flutter valid
            'absence_type' => 'required|in:masuk,pulang', // Tipe absen harus 'masuk' atau 'pulang'
        ]);

        if ($validator->fails()) {
            Log::warning('Emergency Absence Validation Failed', ['errors' => $validator->errors()->toArray(), 'request' => $request->all()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak valid.',
                'errors' => $validator->errors()
            ], 422);
        }

        $qrCodeSecret = $request->qr_code_secret;
        $userId = $request->user_id; // Ini adalah user_id dari Flutter
        $absenceType = $request->absence_type; // 'masuk' atau 'pulang'

        try {
            $kantor = null;
            $qrGeneratedAt = null;

            // Cari kantor berdasarkan QR Code Secret yang diterima
            if ($absenceType === 'masuk') {
                $kantor = Kantor::where('qr_code_secret_masuk', $qrCodeSecret)->first();
                if ($kantor) {
                    $qrGeneratedAt = $kantor->qr_code_masuk_generated_at;
                }
            } elseif ($absenceType === 'pulang') {
                $kantor = Kantor::where('qr_code_secret_pulang', $qrCodeSecret)->first();
                if ($kantor) {
                    $qrGeneratedAt = $kantor->qr_code_pulang_generated_at;
                }
            }

            if (!$kantor) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'QR Code tidak valid atau tidak cocok untuk jenis absen ini.',
                ], 404);
            }

            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak ditemukan.',
                ], 404);
            }

            // --- PENTING: Mendapatkan pegawai_id dari relasi User ---
            $pegawai = $user->pegawai; // Mengakses relasi 'pegawai' dari objek User
            if (!$pegawai) {
                Log::warning('User has no associated Pegawai record.', ['user_id' => $userId]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data pegawai tidak ditemukan untuk user ini. Harap hubungi admin.',
                ], 404); // Atau 403 Forbidden
            }
            $pegawaiId = $pegawai->id; // Ambil ID dari objek Pegawai
            // ----------------------------------------------------

            $today = Carbon::today();

            // --- VALIDASI QR CODE HANYA BERLAKU UNTUK HARI INI ---
            if (!$qrGeneratedAt || !$qrGeneratedAt->isSameDay($today)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'QR Code ini sudah kadaluarsa. Silakan gunakan QR Code yang baru untuk hari ini.',
                ], 403); // Forbidden
            }
            // --- AKHIR VALIDASI HARI ---

            // Cek apakah user sudah absen hari ini menggunakan user_id atau pegawai_id?
            // Biasanya, Kehadiran akan terkait langsung dengan user yang login atau pegawai.
            // Jika user_id dan pegawai_id berbeda, dan tabel kehadiran menggunakan pegawai_id sebagai foreign key utama,
            // maka cari berdasarkan pegawai_id. Jika 'user_id' juga ada di kehadiran, gunakan itu.
            // Saya akan menggunakan user_id untuk pencarian awal karena itu yang ada di request Flutter,
            // dan diasumsikan Kehadiran.user_id adalah foreign key ke Users.id
            $existingAbsence = Kehadiran::where('user_id', $userId)
                                        ->where('tanggal', $today->toDateString())
                                        ->first();

            if ($absenceType === 'masuk') {
                if ($existingAbsence && $existingAbsence->jam_masuk) {
                    return response()->json([
                        'status' => 'warning',
                        'message' => 'Anda sudah melakukan absen masuk darurat hari ini.',
                        'data' => $existingAbsence,
                    ], 200);
                }

                // Catat kehadiran masuk
                $kehadiran = Kehadiran::create([
                    'user_id' => $userId,
                    'pegawai_id' => $pegawaiId, // <-- PENTING: Gunakan pegawai_id yang sudah didapatkan
                    'kantor_id' => $kantor->id,
                    'tanggal' => $today->toDateString(),
                    'jam_masuk' => Carbon::now()->toTimeString(),
                    'status' => 'tugas_luar',
                ]);

            } elseif ($absenceType === 'pulang') {
                if (!$existingAbsence || !$existingAbsence->jam_masuk) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Anda harus melakukan absen masuk darurat terlebih dahulu.',
                    ], 400);
                }
                if ($existingAbsence->jam_pulang) {
                    return response()->json([
                        'status' => 'warning',
                        'message' => 'Anda sudah melakukan absen pulang darurat hari ini.',
                        'data' => $existingAbsence,
                    ], 200);
                }

                // Update kehadiran pulang
                $existingAbsence->update([
                    'jam_pulang' => Carbon::now()->toTimeString(),
                ]);
                $kehadiran = $existingAbsence;
            }

            Log::info('Emergency Absence Recorded', [
                'user_id' => $userId,
                'pegawai_id' => $pegawaiId, // <-- Log juga pegawai_id
                'kantor_id' => $kantor->id,
                'tanggal' => $kehadiran->tanggal,
                'type' => $absenceType,
                'jam_masuk' => $kehadiran->jam_masuk,
                'jam_pulang' => $kehadiran->jam_pulang,
                'status' => $kehadiran->status,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Absen darurat '.$absenceType.' berhasil dicatat.',
                'data' => $kehadiran,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Emergency Absence API Error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage(),
            ], 500);
        }
    }
}