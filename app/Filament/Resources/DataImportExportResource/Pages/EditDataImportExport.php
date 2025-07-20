<?php

namespace App\Filament\Resources\DataImportExportResource\Pages;

use App\Filament\Resources\DataImportExportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDataImportExport extends EditRecord
{
    protected static string $resource = DataImportExportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
