<?php

namespace App\Filament\Resources\RekapPerhitunganResource\Pages;

use App\Filament\Resources\RekapPerhitunganResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRekapPerhitungan extends ViewRecord
{
    protected static string $resource = RekapPerhitunganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
