<?php

namespace App\Filament\Resources\EvaluasiLapkinResource\Pages;

use App\Filament\Resources\EvaluasiLapkinResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEvaluasiLapkin extends EditRecord
{
    protected static string $resource = EvaluasiLapkinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
