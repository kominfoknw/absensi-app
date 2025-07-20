<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Kredensial tidak valid.'
            ], 401);
        }

        $user = $request->user();

        // Pastikan user adalah pegawai
        if ($user->role !== 'pegawai') {
            Auth::logout();
            return response()->json([
                'message' => 'Anda tidak memiliki akses sebagai pegawai.'
            ], 403);
        }

        // Hapus token lama untuk mencegah penumpukan
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil!',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'kantor_id' => $user->kantor_id,
                'pegawai' => $user->pegawai ? [ // Load relasi pegawai
                    'id' => $user->pegawai->id,
                    'nama' => $user->pegawai->nama,
                    'jabatan' => $user->pegawai->jabatan,
                    'nip' => $user->pegawai->nip,
                    'pangkat' => $user->pegawai->pangkat,
                    'foto_face_recognition' => $user->pegawai->foto_face_recognition ? asset('storage/' . $user->pegawai->foto_face_recognition) : null,
                    'lokasi_id' => $user->pegawai->lokasi_id,
                    'foto_selfie' => $user->pegawai->foto_selfie ? asset('storage/' . $user->pegawai->foto_selfie) : null,
                    'shift_id' => $user->pegawai->shift_id,
                    'shift' => $user->pegawai->shift ? [
                        'id' => $user->pegawai->shift->id,
                        'nama' => $user->pegawai->shift->nama,
                        'jam_masuk' => $user->pegawai->shift->jam_masuk,
                        'jam_pulang' => $user->pegawai->shift->jam_pulang,
                    ] : null,
                ] : null,
            ]
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        // Memuat relasi 'pegawai' beserta relasi 'unit' di dalamnya
        // Dan memuat relasi 'kantor' pada user
        $user->load(['pegawai.unit', 'kantor']);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            // Data Kantor diambil dari relasi langsung user
            'kantor_id' => $user->kantor_id, // Tetap sertakan ID jika perlu
            'kantor' => $user->kantor ? $user->kantor->nama_kantor : null, // Langsung nama kantor
            'pegawai' => $user->pegawai ? [
                'id' => $user->pegawai->id,
                'nama' => $user->pegawai->nama,
                'jabatan' => $user->pegawai->jabatan,
                'kelas_jabatan' => $user->pegawai->kelas_jabatan,
                'nip' => $user->pegawai->nip,
                'pangkat' => $user->pegawai->pangkat,
                'foto_face_recognition' => $user->pegawai->foto_face_recognition ? asset('storage/' . $user->pegawai->foto_face_recognition) : null,
                'foto_selfie' => $user->pegawai->foto_selfie ? asset('storage/' . $user->pegawai->foto_selfie) : null,
                'lokasi_id' => $user->pegawai->lokasi_id,
                'shift_id' => $user->pegawai->shift_id,
                'shift' => $user->pegawai->shift ? [
                    'id' => $user->pegawai->shift->id,
                    'nama' => $user->pegawai->shift->nama,
                    'jam_masuk' => $user->pegawai->shift->jam_masuk,
                    'jam_pulang' => $user->pegawai->shift->jam_pulang,
                ] : null,
                // --- TAMBAH DATA UNIT DI SINI ---
                'unit' => $user->pegawai->unit ? $user->pegawai->unit->nama_unit : null,
                // --- AKHIR TAMBAHAN ---
            ] : null,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil!'
        ]);
    }

    public function changePassword(Request $request)
{
    $request->validate([
        'current_password' => 'required',
        'new_password' => 'required|string|min:6|confirmed',
    ]);

    $user = $request->user();

    if (!Hash::check($request->current_password, $user->password)) {
        throw ValidationException::withMessages([
            'current_password' => ['Password lama tidak cocok.'],
        ]);
    }

    $user->password = Hash::make($request->new_password);
    $user->save();

    return response()->json(['message' => 'Password berhasil diperbarui!']);
}
}