<?php

namespace App\Filament\Resources\LapkinResource\Pages;

use App\Filament\Resources\LapkinResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLapkin extends EditRecord
{
    protected static string $resource = LapkinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
