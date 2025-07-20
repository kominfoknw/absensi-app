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
        $user->load('pegawai'); // Pastikan relasi pegawai dimuat

        if (!$user->pegawai) {
            return response()->json(['message' => 'Profil pegawai tidak terkait dengan akun Anda.'], 403);
        }

        $pegawaiId = $user->pegawai->id;

        $query = Lapkin::where('pegawai_id', $pegawaiId);

        // Filter by month and year if provided
        if ($request->has('month') && $request->has('year')) {
            $month = $request->input('month');
            $year = $request->input('year');
            $query->whereYear('tanggal', $year)
                  ->whereMonth('tanggal', $month);
        } else {
            // Default to current month and year if no filter is provided
            $query->whereYear('tanggal', Carbon::now()->year)
                  ->whereMonth('tanggal', Carbon::now()->month);
        }

        $lapkins = $query->orderBy('tanggal', 'desc')->get();

        return response()->json($lapkins);
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
    public function destroy(string $id)
    {
        $user = Auth::user();
        $pegawai = Pegawai::where('user_id', $user->id)->first();

        if (!$pegawai) {
            return response()->json(['message' => 'Pegawai tidak ditemukan untuk user ini.'], 404);
        }

        $lapkin = Lapkin::where('id', $id)->where('pegawai_id', $pegawai->id)->first();

        if (!$lapkin) {
            return response()->json(['message' => 'Laporan Kinerja tidak ditemukan atau Anda tidak memiliki akses.'], 404);
        }

        // Jika ada lampiran, hapus file-nya
        if ($lapkin->lampiran) {
            Storage::disk('public')->delete($lapkin->lampiran);
        }

        $lapkin->delete();

        return response()->json(['message' => 'Laporan Kinerja berhasil dihapus.']);
    }
}