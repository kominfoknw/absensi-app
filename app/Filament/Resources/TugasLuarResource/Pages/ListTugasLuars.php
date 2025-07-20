<?php

namespace App\Filament\Resources\TugasLuarResource\Pages;

use App\Filament\Resources\TugasLuarResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTugasLuars extends ListRecords
{
    protected static string $resource = TugasLuarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
