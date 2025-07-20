<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Kehadiran;
use App\Models\User;
use App\Models\Shift;
use App\Models\Lokasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; // Untuk logging

class AbsensiController extends Controller
{
    // Fungsi dummy untuk Face Recognition
    // Dalam produksi, ini bisa diintegrasikan dengan ML library (misal: face_recognition Python library via API)
    private function verifyFace($capturedImageBase64, $dbImageFilename)
    {
        if (!$dbImageFilename) return false;
    
        try {
            // Ambil isi file gambar dari storage Laravel
            $fullPath = storage_path('app/public/' . $dbImageFilename);
    
            \Log::info("Checking file_exists on: {$fullPath}");
            
            if (!file_exists($fullPath)) {
                \Log::error("Foto face recognition tidak ditemukan di: {$fullPath}");
                return false;
            }
            
            // Ambil isi file langsung
            $dbImageContent = file_get_contents($fullPath);
            $dbImageBase64 = base64_encode($dbImageContent);
    
            // ✅ Perbaiki logging dengan variabel yang benar
            Log::info('Base64 Captured Length: ' . strlen($capturedImageBase64));
            Log::info('Base64 DB Length: ' . strlen($dbImageBase64));
    
            // Kirim request ke face recognition service
            $response = Http::timeout(10)->post('http://localhost:8001/verify-face', [
                'image_base64' => $capturedImageBase64,
                'db_image_base64' => $dbImageBase64,
            ]);
    
            return $response->ok() && $response->json('match') === true;
        } catch (\Exception $e) {
            \Log::error("Face verification error: " . $e->getMessage());
            return false;
        }
    }
    



    // Fungsi untuk menghitung jarak Haversine (km)
    private function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius; // Mengembalikan jarak dalam meter
    }

    public function checkIn(Request $request)
    {
        $user = $request->user();
        $pegawai = $user->pegawai;

        if (!$pegawai) {
            return response()->json(['message' => 'Data pegawai tidak ditemukan untuk user ini.'], 404);
        }

        $request->validate([
            'tanggal' => 'required|date_format:Y-m-d',
            'jam_masuk' => 'required|date_format:H:i:s',
            'lat_masuk' => 'required|numeric',
            'long_masuk' => 'required|numeric',
            'foto_wajah' => 'required|string', // Base64 string
        ]);

        $tanggalAbsen = Carbon::parse($request->tanggal);
        $jamMasukAbsen = Carbon::parse($request->jam_masuk);

        // 1. Cek apakah sudah absen masuk hari ini
        $existingAttendance = Kehadiran::where('user_id', $user->id)
            ->where('tanggal', $tanggalAbsen->toDateString())
            ->first();

        if ($existingAttendance && $existingAttendance->jam_masuk) {
            return response()->json(['message' => 'Anda sudah absen masuk hari ini.'], 400);
        }

        // 2. Validasi Face Recognition (Simulasi)
        if (!$this->verifyFace($request->foto_wajah, $pegawai->foto_face_recognition)) {
            return response()->json(['message' => 'Verifikasi wajah gagal. Wajah tidak cocok atau tidak terdeteksi.'], 400);
        }

        // 3. Validasi Lokasi (Radius)
        $lokasiKerja = Lokasi::find($pegawai->lokasi_id);
        if (!$lokasiKerja) {
            return response()->json(['message' => 'Lokasi kerja pegawai tidak ditemukan.'], 404);
        }

        $jarak = $this->haversineGreatCircleDistance(
            $request->lat_masuk,
            $request->long_masuk,
            $lokasiKerja->latitude,
            $lokasiKerja->longitude
        );

        if ($jarak > $lokasiKerja->radius) {
            return response()->json(['message' => 'Anda berada di luar jangkauan lokasi absen.'], 400);
        }

        // 4. Hitung Telat
$shift = Shift::find($pegawai->shift_id);
$isTelat = false;
$durasiTelat = null;

if ($shift) {
    $jamMasukShift = Carbon::parse($shift->jam_masuk);
    $toleransiTelat = $jamMasukShift->copy()->addMinutes(15);

    if ($jamMasukAbsen->greaterThan($toleransiTelat)) {
        $isTelat = true;

        // Hitung selisih antara jam absen dan jam masuk shift (tanpa toleransi)
        $durasiTelat = $jamMasukAbsen->diff($toleransiTelat)->format('%H:%I:%S');
    }
} else {
    // Jika pegawai tidak punya shift, atau shift tidak ditemukan
    Log::warning("Pegawai ID {$pegawai->id} tidak memiliki shift atau shift tidak ditemukan. Tidak menghitung telat.");
}

        // 5. Simpan/Update Kehadiran
        if ($existingAttendance) {
            $kehadiran = $existingAttendance;
        } else {
            $kehadiran = new Kehadiran();
            $kehadiran->user_id = $user->id;
            $kehadiran->pegawai_id = $pegawai->id;
            $kehadiran->tanggal = $tanggalAbsen->toDateString();
            $kehadiran->shift_id = $pegawai->shift_id;
            $kehadiran->status = 'hadir'; // Default hadir jika absen masuk
        }

        $kehadiran->jam_masuk = $jamMasukAbsen->toTimeString();
        $kehadiran->telat = $durasiTelat;
        $kehadiran->lat_masuk = $request->lat_masuk;
        $kehadiran->long_masuk = $request->long_masuk;
        // Jam pulang, pulang_cepat, lat_pulang, long_pulang di-default null atau 0
        $kehadiran->jam_pulang = null;
        $kehadiran->pulang_cepat = false;
        $kehadiran->lat_pulang = null;
        $kehadiran->long_pulang = null;

        $kehadiran->save();

        return response()->json(['message' => 'Absen masuk berhasil!', 'kehadiran' => $kehadiran], 200);
    }

    public function checkOut(Request $request)
    {
        $user = $request->user();
        $pegawai = $user->pegawai;

        if (!$pegawai) {
            return response()->json(['message' => 'Data pegawai tidak ditemukan untuk user ini.'], 404);
        }

        $request->validate([
            'tanggal' => 'required|date_format:Y-m-d',
            'jam_pulang' => 'required|date_format:H:i:s',
            'lat_pulang' => 'required|numeric',
            'long_pulang' => 'required|numeric',
            'foto_wajah' => 'required|string', // Base64 string
        ]);

        $tanggalAbsen = Carbon::parse($request->tanggal);
        $jamPulangAbsen = Carbon::parse($request->jam_pulang);

        // 1. Cek apakah sudah absen masuk dan belum absen pulang
        $kehadiran = Kehadiran::where('user_id', $user->id)
            ->where('tanggal', $tanggalAbsen->toDateString())
            ->first();

        if (!$kehadiran || !$kehadiran->jam_masuk) {
            return response()->json(['message' => 'Anda belum absen masuk hari ini.'], 400);
        }
        if ($kehadiran->jam_pulang) {
            return response()->json(['message' => 'Anda sudah absen pulang hari ini.'], 400);
        }

        // 2. Cek apakah sudah melewati jam 12.00 WITA
        // Asumsi server menggunakan zona waktu yang sama atau dikonversi dengan benar
        $currentDateTime = Carbon::now('Asia/Makassar'); // Pastikan zona waktu ini benar
        $jamMinimumPulang = Carbon::parse('12:00:00', 'Asia/Makassar');

        if ($currentDateTime->lt($jamMinimumPulang)) {
            return response()->json(['message' => 'Belum waktunya absen pulang. Pulang bisa dilakukan setelah jam 12.00 WITA.'], 400);
        }

        // 3. Validasi Face Recognition (Simulasi)
        if (!$this->verifyFace($request->foto_wajah, $pegawai->foto_face_recognition)) {
            return response()->json(['message' => 'Verifikasi wajah gagal. Wajah tidak cocok atau tidak terdeteksi.'], 400);
        }

        // 4. Validasi Lokasi (Radius)
        $lokasiKerja = Lokasi::find($pegawai->lokasi_id);
        if (!$lokasiKerja) {
            return response()->json(['message' => 'Lokasi kerja pegawai tidak ditemukan.'], 404);
        }

        $jarak = $this->haversineGreatCircleDistance(
            $request->lat_pulang,
            $request->long_pulang,
            $lokasiKerja->latitude,
            $lokasiKerja->longitude
        );

        if ($jarak > $lokasiKerja->radius) {
            return response()->json(['message' => 'Anda berada di luar jangkauan lokasi absen.'], 400);
        }

        // 5. Hitung Pulang Cepat
$shift = Shift::find($pegawai->shift_id);
$isPulangCepat = false;
$durasiPulangCepat = null;

if ($shift) {
    $jamPulangShift = Carbon::parse($shift->jam_pulang);
    $toleransiPulangCepat = $jamPulangShift->copy()->subMinutes(15);

    if ($jamPulangAbsen->lessThan($toleransiPulangCepat)) {
        $isPulangCepat = true;

        // Hitung durasi pulang cepat (bisa dari jam pulang shift atau dari toleransi)
        $durasiPulangCepat = $toleransiPulangCepat->diff($jamPulangAbsen)->format('%H:%I:%S');
    }
} else {
    Log::warning("Pegawai ID {$pegawai->id} tidak memiliki shift atau shift tidak ditemukan. Tidak menghitung pulang cepat.");
}


        // 6. Update Kehadiran
        $kehadiran->jam_pulang = $jamPulangAbsen->toTimeString();
        $kehadiran->pulang_cepat = $isPulangCepat;
        $kehadiran->lat_pulang = $request->lat_pulang;
        $kehadiran->long_pulang = $request->long_pulang;
        $kehadiran->status = 'hadir'; // Tetap hadir setelah pulang
        $kehadiran->save();

        return response()->json(['message' => 'Absen pulang berhasil!', 'kehadiran' => $kehadiran], 200);
    }

    public function history(Request $request)
{
    $user = $request->user();

    $query = Kehadiran::where('user_id', $user->id)
        ->with(['shift', 'user.pegawai']);

    if ($request->has('tanggal')) {
        $query->whereDate('tanggal', $request->tanggal);
    }
    if ($request->has('bulan') && $request->has('tahun')) {
        $query->whereMonth('tanggal', $request->bulan)
              ->whereYear('tanggal', $request->tahun);
    }

    $kehadiran = $query->orderBy('tanggal', 'desc')->get();

    // Ubah tanggal menjadi string tanpa timezone
    $kehadiran->transform(function ($item) {
        $item->tanggal = $item->tanggal->format('Y-m-d');
        return $item;
    });

    return response()->json($kehadiran);
}

}