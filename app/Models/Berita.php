<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage; // Tambahkan ini

class Berita extends Model
{
    use HasFactory;

    // Pastikan nama tabel benar
    protected $table = 'beritas';

    // Kolom yang bisa diisi secara massal
    protected $fillable = [
        'judul',
        'konten',
        'gambar_url',
    ];

    /**
     * Get the full URL for the berita image.
     *
     * @return string|null
     */
    public function getGambarUrlAttribute($value)
    {
        // Jika nilai sudah berupa URL lengkap (misal dari seeding atau inputan eksternal), biarkan saja
        if ($value && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // Jika ada nilai dan file ada di storage, kembalikan URL publik
        if ($value && Storage::disk('public')->exists($value)) {
            return Storage::url($value); // Menggunakan Storage::url() yang lebih bersih
        }

        // Jika tidak ada gambar atau file tidak ditemukan, kembalikan default atau null
        // Asumsi 'default-news.png' ada di public/images
        return asset('images/default-news.png'); // Ganti dengan path gambar default Anda
    }
}