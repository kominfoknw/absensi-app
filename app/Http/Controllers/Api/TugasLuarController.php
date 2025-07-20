<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TugasLuar;
use App\Models\Pegawai;
use App\Enums\TugasLuarStatus; // Pastikan Enum TugasLuarStatus ada dan diimpor
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class TugasLuarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Mendapatkan user yang sedang login
        $user = Auth::user();

        $user->load('pegawai'); // Pastikan relasi pegawai dimuat

        if (!$user->pegawai) {
            return response()->json(['message' => 'Profil pegawai tidak terkait dengan akun Anda.'], 403);
        }

        $pegawaiId = $user->pegawai->id;

        // Mengambil semua tugas luar berdasarkan pegawai_id dari pegawai yang login
        $tugasLuars = TugasLuar::where('pegawai_id', $pegawaiId)
                                ->orderBy('created_at', 'desc')
                                ->get();

        return response()->json($tugasLuars);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $user->load('pegawai'); // Pastikan relasi pegawai dimuat

        if (!$user->pegawai) {
            return response()->json(['message' => 'Anda tidak dapat mengajukan izin tanpa profil pegawai yang terkait.'], 403);
        }

        $pegawai = $user->pegawai; // Gunakan objek pegawai yang sudah dimuat
        $pegawaiId = $pegawai->id;

        $validatedData = $request->validate([
            'nama_tugas' => 'required|string|max:255',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // Max 2MB
            'keterangan' => 'nullable|string',
        ]);

        $tanggalMulai = Carbon::parse($validatedData['tanggal_mulai']);
        $tanggalSelesai = Carbon::parse($validatedData['tanggal_selesai']);

        // Check for overlapping tugas luar for the same pegawai
        $overlappingTugasLuar = TugasLuar::where('pegawai_id', $pegawai->id)
            ->where(function ($query) use ($tanggalMulai, $tanggalSelesai) {
                $query->whereBetween('tanggal_mulai', [$tanggalMulai, $tanggalSelesai])
                      ->orWhereBetween('tanggal_selesai', [$tanggalMulai, $tanggalSelesai])
                      ->orWhere(function ($q) use ($tanggalMulai, $tanggalSelesai) {
                          $q->where('tanggal_mulai', '<=', $tanggalMulai)
                            ->where('tanggal_selesai', '>=', $tanggalSelesai);
                      });
            })
            ->exists();

        if ($overlappingTugasLuar) {
            return response()->json(['message' => 'Anda sudah memiliki tugas luar pada rentang tanggal tersebut.'], 409); // Conflict
        }

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('tugas_luar_files', 'public');
        }

        $tugasLuar = TugasLuar::create([
            'user_id' => $user->id,
            'pegawai_id' => $pegawai->id,
            'kantor_id' => $pegawai->kantor_id, // Asumsi pegawai memiliki kantor_id
            'nama_tugas' => $validatedData['nama_tugas'],
            'tanggal_mulai' => $validatedData['tanggal_mulai'],
            'tanggal_selesai' => $validatedData['tanggal_selesai'],
            'file' => $filePath,
            'status' => TugasLuarStatus::Pending, // Default status pending
            'keterangan' => $validatedData['keterangan'],
        ]);

        return response()->json(['message' => 'Tugas luar berhasil diajukan!', 'tugas_luar' => $tugasLuar], 201);
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

        $tugasLuar = TugasLuar::where('id', $id)->where('pegawai_id', $pegawai->id)->first();

        if (!$tugasLuar) {
            return response()->json(['message' => 'Tugas luar tidak ditemukan atau Anda tidak memiliki akses.'], 404);
        }

        // Tidak bisa menghapus jika status sudah diterima atau ditolak
        if ($tugasLuar->status !== TugasLuarStatus::Pending) {
            return response()->json(['message' => 'Tugas luar dengan status Diterima/Ditolak tidak bisa dihapus.'], 403); // Forbidden
        }

        if ($tugasLuar->file) {
            Storage::disk('public')->delete($tugasLuar->file);
        }

        $tugasLuar->delete();

        return response()->json(['message' => 'Tugas luar berhasil dihapus.']);
    }
}