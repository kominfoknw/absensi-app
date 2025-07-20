<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IzinPegawai extends Model
{
    use HasFactory;

    protected $table = 'izin_pegawai'; // Definisikan nama tabel secara eksplisit

    protected $fillable = [
        'user_id',
        'pegawai_id',
        'kantor_id',
        'nama_izin',
        'tanggal_mulai',
        'tanggal_selesai',
        'file',
        'status',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'status' => \App\Enums\IzinStatus::class, // Nanti kita buat Enum ini
    ];

    // Relasi
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function kantor()
    {
        return $this->belongsTo(Kantor::class);
    }
}