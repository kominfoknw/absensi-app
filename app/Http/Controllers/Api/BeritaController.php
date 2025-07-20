<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Berita; // Pastikan model Berita diimpor
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // Tambahkan ini untuk logging

class BeritaController extends Controller
{
    public function index()
    {
        try {
            // Ambil 6 berita terbaru. Accessor 'gambar_url' akan otomatis bekerja di sini.
            $berita = Berita::orderBy('created_at', 'desc')->limit(6)->get();

            return response()->json([
                'message' => 'Berita berhasil diambil',
                'data' => $berita
            ]);
        } catch (\Exception $e) {
            // Log error untuk debugging lebih lanjut
            Log::error('Error fetching news index: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'message' => 'Gagal mengambil daftar berita. Terjadi kesalahan server.',
                'error' => $e->getMessage() // Sertakan pesan error untuk debugging
            ], 500); // Mengembalikan status 500 Internal Server Error
        }
    }

    public function show($id)
    {
        try {
            // Temukan berita berdasarkan ID. Accessor 'gambar_url' akan otomatis bekerja di sini.
            $berita = Berita::find($id);

            if (!$berita) {
                return response()->json(['message' => 'Berita tidak ditemukan.'], 404);
            }

            return response()->json($berita);
        } catch (\Exception $e) {
            // Log error untuk debugging lebih lanjut
            Log::error('Error fetching news detail (ID: ' . $id . '): ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'message' => 'Gagal mengambil detail berita. Terjadi kesalahan server.',
                'error' => $e->getMessage() // Sertakan pesan error untuk debugging
            ], 500); // Mengembalikan status 500 Internal Server Error
        }
    }
}