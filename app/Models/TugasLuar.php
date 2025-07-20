<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TugasLuar extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pegawai_id',
        'kantor_id',
        'nama_tugas',
        'tanggal_mulai',
        'tanggal_selesai',
        'file',
        'status',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'status' => \App\Enums\TugasLuarStatus::class, // Menggunakan Enum untuk status
    ];

    /**
     * Get the user that owns the TugasLuar.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pegawai associated with the TugasLuar.
     */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    /**
     * Get the kantor associated with the TugasLuar.
     */
    public function kantor(): BelongsTo
    {
        return $this->belongsTo(Kantor::class);
    }
}