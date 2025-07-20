<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IzinStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Menunggu Persetujuan',
            self::Diterima => 'Diterima',
            self::Ditolak => 'Ditolak',
        };
    }
}