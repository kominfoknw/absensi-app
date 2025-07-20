<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IzinPegawai;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Enums\IzinStatus; // Pastikan Enum diimpor jika digunakan

class IzinPegawaiController extends Controller
{
    /**
     * Tampilkan daftar izin hanya untuk pegawai yang login.
     */
    public function index(Request $request) // Tambahkan Request $request
    {
        // Akses user melalui request untuk konsistensi, lalu load relasi pegawai
        $user = $request->user();
        $user->load('pegawai'); // Pastikan relasi pegawai dimuat

        if (!$user->pegawai) {
            return response()->json(['message' => 'Profil pegawai tidak terkait dengan akun Anda.'], 403);
        }

        $pegawaiId = $user->pegawai->id;

        $izin = IzinPegawai::with(['pegawai', 'kantor'])
                           ->where('pegawai_id', $pegawaiId)
                           ->orderBy('created_at', 'desc')
                           ->get();

        return response()->json(['data' => $izin]);
    }

    /**
     * Simpan izin baru untuk pegawai yang login.
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

        $validator = Validator::make($request->all(), [
            'nama_izin' => 'required|string|max:255',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // Max 2MB
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        // --- Validasi Tumpang Tindih Tanggal ---
        $tanggalMulai = $request->input('tanggal_mulai');
        $tanggalSelesai = $request->input('tanggal_selesai');

        $tumpangTindih = IzinPegawai::where('pegawai_id', $pegawaiId)
            ->where(function ($query) use ($tanggalMulai, $tanggalSelesai) {
                $query->whereBetween('tanggal_mulai', [$tanggalMulai, $tanggalSelesai])
                      ->orWhereBetween('tanggal_selesai', [$tanggalMulai, $tanggalSelesai])
                      ->orWhere(function ($subQuery) use ($tanggalMulai, $tanggalSelesai) {
                          $subQuery->where('tanggal_mulai', '<=', $tanggalMulai)
                                   ->where('tanggal_selesai', '>=', $tanggalSelesai);
                      });
            })
            ->exists();

        if ($tumpangTindih) {
            return response()->json(['message' => 'Pegawai ini sudah memiliki izin pada rentang tanggal tersebut.'], 422);
        }
        // --- Akhir Validasi Tumpang Tindih ---

        $path = null;
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('izin_files', 'public');
        }

        $izin = IzinPegawai::create([
            'user_id' => $user->id, // User yang sedang login (ID user)
            'pegawai_id' => $pegawaiId, // ID pegawai dari user yang login
            'kantor_id' => $pegawai->kantor_id, // Ambil kantor_id dari objek pegawai
            'nama_izin' => $request->nama_izin,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'file' => $path,
            'status' => IzinStatus::Pending, // Default status
            'keterangan' => $request->keterangan,
        ]);

        return response()->json(['message' => 'Izin berhasil diajukan', 'data' => $izin], 201);
    }

    /**
     * Hapus izin. Hanya jika status bukan 'diterima'.
     */
    public function destroy(Request $request, $id) // Tambahkan Request $request
    {
        $izin = IzinPegawai::find($id);

        if (!$izin) {
            return response()->json(['message' => 'Izin tidak ditemukan'], 404);
        }

        // Akses user melalui request untuk konsistensi, lalu load relasi pegawai
        $user = $request->user();
        $user->load('pegawai'); // Pastikan relasi pegawai dimuat

        // Pastikan hanya pegawai yang punya izin ini dan terhubung ke akun yang bisa menghapus
        if (!$user->pegawai || $izin->pegawai_id !== $user->pegawai->id) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk menghapus izin ini.'], 403);
        }

        // Cek status, tidak bisa dihapus jika sudah diterima
        if ($izin->status === IzinStatus::Diterima) {
            return response()->json(['message' => 'Izin dengan status DITERIMA tidak dapat dihapus.'], 403);
        }

        // Hapus file jika ada
        if ($izin->file) {
            Storage::disk('public')->delete($izin->file);
        }

        $izin->delete();

        return response()->json(['message' => 'Izin berhasil dihapus'], 200);
    }
}