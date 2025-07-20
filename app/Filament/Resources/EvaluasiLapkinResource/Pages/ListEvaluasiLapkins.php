<?php

namespace App\Filament\Resources\EvaluasiLapkinResource\Pages;

use App\Filament\Resources\EvaluasiLapkinResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEvaluasiLapkins extends ListRecords
{
    protected static string $resource = EvaluasiLapkinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Aksi Tambah tidak diperlukan di halaman evaluasi
        ];
    }
}