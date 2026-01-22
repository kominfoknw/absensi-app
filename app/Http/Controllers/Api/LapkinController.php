<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lapkin;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class LapkinController extends Controller
{
    /**
     * Display a listing of the resource.
     * Filter by month and year for the logged-in pegawai.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $user->load('pegawai');

        if (!$user->pegawai) {
            return response()->json(['message' => 'Profil pegawai tidak terkait dengan akun Anda.'], 403);
        }

        $pegawaiId = $user->pegawai->id;
        $query = Lapkin::where('pegawai_id', $pegawaiId);

        // Filter bulan dan tahun
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        $lapkins = $query->whereYear('tanggal', $year)
                        ->whereMonth('tanggal', $month)
                        ->orderBy('tanggal', 'desc')
                        ->get();

        $formatted = $lapkins->map(function ($lapkin) {
            return [
                'id' => $lapkin->id,
                'hari' => $lapkin->hari,
                'tanggal' => $lapkin->tanggal->format('Y-m-d'), // aman untuk Flutter
                'nama_kegiatan' => $lapkin->nama_kegiatan,
                'tempat' => $lapkin->tempat,
                'target' => $lapkin->target,
                'output' => $lapkin->output,
                'lampiran' => $lapkin->lampiran ? asset('storage/'.$lapkin->lampiran) : null,
                'kualitas_hasil' => $lapkin->kualitas_hasil,
            ];
        });

        return response()->json($formatted);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         // Akses user melalui request untuk konsistensi, lalu load relasi pegawai
         $user = $request->user();
         $user->load('pegawai'); // Pastikan relasi pegawai dimuat
 
         if (!$user->pegawai) {
             return response()->json(['message' => 'Anda tidak dapat mengajukan izin tanpa profil pegawai yang terkait.'], 403);
         }
 
         $pegawai = $user->pegawai; // Gunakan objek pegawai yang sudah dimuat
         $pegawaiId = $pegawai->id;

        $validatedData = $request->validate([
            'tanggal' => 'required|date',
            'nama_kegiatan' => 'required|string|max:255',
            'tempat' => 'required|string|max:255',
            'target' => 'nullable|string|max:255',
            'output' => 'nullable|string|max:255',
            'lampiran' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // Max 2MB
            // kualitas_hasil tidak diinput dari mobile, default 0
        ]);

        $tanggal = Carbon::parse($validatedData['tanggal']);

        // Check for existing Lapkin on the same date for the same pegawai
        $existingLapkin = Lapkin::where('pegawai_id', $pegawaiId)
                                ->whereDate('tanggal', $tanggal->toDateString())
                                ->first();

        if ($existingLapkin) {
            return response()->json(['message' => 'Anda sudah memiliki Laporan Kinerja pada tanggal ini.'], 409); // Conflict
        }

        $filePath = null;
        if ($request->hasFile('lampiran')) {
            $filePath = $request->file('lampiran')->store('lapkin_lampiran', 'public');
        }

        $lapkin = Lapkin::create([
            'user_id' => $user->id,
            'pegawai_id' => $pegawai->id,
            'kantor_id' => $pegawai->kantor_id, // Asumsi pegawai memiliki kantor_id
            'hari' => $tanggal->translatedFormat('l'), // Mengambil nama hari dari tanggal
            'tanggal' => $tanggal->toDateString(),
            'nama_kegiatan' => $validatedData['nama_kegiatan'],
            'tempat' => $validatedData['tempat'],
            'target' => $validatedData['target'],
            'output' => $validatedData['output'],
            'lampiran' => $filePath,
            'kualitas_hasil' => 0, // Default 0, hanya bisa diubah admin/operator Filament
        ]);

        return response()->json(['message' => 'Laporan Kinerja berhasil diajukan!', 'lapkin' => $lapkin], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
{
    $user = $request->user();
    $user->load('pegawai');

    if (!$user->pegawai) {
        return response()->json(['message' => 'Pegawai tidak ditemukan untuk user ini.'], 404);
    }

    $pegawai = $user->pegawai;

    $lapkin = Lapkin::where('id', $id)
        ->where('pegawai_id', $pegawai->id)
        ->first();

    if (!$lapkin) {
        return response()->json(['message' => 'Laporan Kinerja tidak ditemukan atau Anda tidak memiliki akses.'], 404);
    }

    // Hapus file lampiran kalau ada
    if ($lapkin->lampiran) {
        Storage::disk('public')->delete($lapkin->lampiran);
    }

    $lapkin->delete();

    return response()->json(['message' => 'Laporan Kinerja berhasil dihapus.']);
}

}