<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lapkin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pegawai_id',
        'kantor_id',
        'hari',
        'tanggal',
        'nama_kegiatan',
        'tempat',
        'target',
        'output',
        'lampiran',
        'kualitas_hasil',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    /**
     * Get the user that owns the Lapkin.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pegawai associated with the Lapkin.
     */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    /**
     * Get the kantor associated with the Lapkin.
     */
    public function kantor(): BelongsTo
    {
        return $this->belongsTo(Kantor::class);
    }
}