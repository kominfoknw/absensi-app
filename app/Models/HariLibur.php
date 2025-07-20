<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HariLibur extends Model
{
    protected $fillable = ['tanggal', 'keterangan'];

    public $timestamps = true;

    protected $casts = [
        'tanggal' => 'date',
    ];
}
