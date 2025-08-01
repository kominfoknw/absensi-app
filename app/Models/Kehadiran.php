<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kehadiran extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shift_id',
        'pegawai_id',
        'tanggal',
        'jam_masuk',
        'telat',
        'lat_masuk',
        'long_masuk',
        'jam_pulang',
        'pulang_cepat',
        'lat_pulang',
        'long_pulang',
        'status',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // --- PASTIkan metode ini ada dan benar ---
    public function pegawai(): BelongsTo
    {
        // Asumsi: tabel 'kehadiran' memiliki kolom 'pegawai_id'
        // yang mereferensikan 'id' di tabel 'pegawai'.
        return $this->belongsTo(Pegawai::class);
    }
    // --- Akhir dari bagian yang perlu diperiksa ---

    /**
     * Get the user that owns the kehadiran record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the shift associated with the kehadiran record.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}