<?php

namespace App\Filament\Resources\TugasLuarResource\Pages;

use App\Filament\Resources\TugasLuarResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTugasLuar extends EditRecord
{
    protected static string $resource = TugasLuarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
