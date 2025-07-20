<?php

namespace App\Filament\Resources\RekapPerhitunganResource\Pages;

use App\Filament\Resources\RekapPerhitunganResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRekapPerhitungan extends EditRecord
{
    protected static string $resource = RekapPerhitunganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
