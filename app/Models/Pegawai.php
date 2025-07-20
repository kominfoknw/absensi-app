<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany; // <-- TAMBAHKAN INI!

class Pegawai extends Model
{
    use HasFactory;

    protected $fillable = [
        'kantor_id', 'lokasi_id', 'unit_id', 'shift_id', 'nama', 'jabatan', 'nip',
        'telepon', 'alamat', 'pangkat', 'golongan', 'kelas_jabatan',
        'jenis_kelamin', 'foto_face_recognition', 'foto_selfie', 'basic_tunjangan', 'atasan_id', // Tambahkan atasan_id di sini
    ];

    public function kantor(): BelongsTo
    {
        return $this->belongsTo(Kantor::class);
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    // Relasi ke model User, jika pegawai ini memiliki akun user (username = NIP)
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'email', 'nip');
    }

    public function kehadiran()
{
    return $this->hasMany(\App\Models\Kehadiran::class);
}

public function atasan() // Definisikan hubungan untuk atasan
    {
        return $this->belongsTo(User::class, 'atasan_id');
    }

    public function tugasLuars(): HasMany
    {
        return $this->hasMany(TugasLuar::class);
    }

    public function lapkins(): HasMany
    {
        return $this->hasMany(Lapkin::class);
    }

}
