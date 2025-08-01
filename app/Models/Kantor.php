<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str; // ✅ Tambahkan ini
use Carbon\Carbon; // ✅ Pastikan juga ini ditambahkan

class Kantor extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_kantor', 'alamat', 'website', 'status',  'qr_code_secret_masuk',
        'qr_code_masuk_generated_at',
        'qr_code_secret_pulang',
        'qr_code_pulang_generated_at', 'keterangan',
    ];

    /**
     * Generate a unique QR Code secret for the office if it doesn't exist.
     */

    protected $casts = [
        'qr_code_masuk_generated_at' => 'datetime',
        'qr_code_pulang_generated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($kantor) {
            if (empty($kantor->qr_code_secret_masuk)) {
                $kantor->qr_code_secret_masuk = (string) Str::uuid();
                $kantor->qr_code_masuk_generated_at = Carbon::now();
            }
            if (empty($kantor->qr_code_secret_pulang)) {
                $kantor->qr_code_secret_pulang = (string) Str::uuid();
                $kantor->qr_code_pulang_generated_at = Carbon::now();
            }
        });

        // Tidak perlu logika updating di sini, karena scheduler akan mengurus update harian
        // dan jika generate via tombol di Filament, itu akan mengupdate timestamp
    }


    public function lokasis(): HasMany
    {
        return $this->hasMany(Lokasi::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function pegawais(): HasMany
    {
        return $this->hasMany(Pegawai::class);
    }
}