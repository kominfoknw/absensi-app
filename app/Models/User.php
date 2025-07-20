<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email', // Ini akan digunakan sebagai username (NIP)
        'password',
        'role',
        'kantor_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Izinkan user dengan role 'superadmin', 'operator', ATAU 'pegawai'
        // untuk mengakses panel admin.
        if ($panel->getId() === 'admin') {
            return $this->role === 'superadmin' || $this->role === 'operator' || $this->role === 'pegawai';
        }
        // Jika ada panel lain, izinkan akses secara default (atau sesuaikan logika Anda)
        return true;
    }

    // Relasi untuk mendapatkan data pegawai jika user ini adalah seorang pegawai
    public function pegawai(): HasOne
    {
        return $this->hasOne(Pegawai::class, 'nip', 'email'); // Asumsi NIP pegawai adalah username (email) user
    }

    // Relasi BARU: Mendapatkan pegawai yang atasan_id-nya adalah ID user ini
    public function bawahanPegawai(): HasMany
    {
        return $this->hasMany(Pegawai::class, 'atasan_id', 'id');
    }

    public function tugasLuars(): HasMany
    {
        return $this->hasMany(TugasLuar::class);
    }

    public function kantor()
    {
        // Asumsi:
        // - Setiap User memiliki satu Kantor (many-to-one)
        // - Tabel 'users' memiliki kolom 'kantor_id'
        // - Model 'Kantor' ada di App\Models\Kantor
        return $this->belongsTo(Kantor::class);
    }
}