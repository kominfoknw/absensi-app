<?php

namespace App\Filament\Resources\DataImportExportResource\Pages;

use App\Filament\Resources\DataImportExportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDataImportExports extends ListRecords
{
    protected static string $resource = DataImportExportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
