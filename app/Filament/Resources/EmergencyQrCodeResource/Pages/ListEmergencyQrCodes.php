<?php

namespace App\Filament\Resources\EmergencyQrCodeResource\Pages;

use App\Filament\Resources\EmergencyQrCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmergencyQrCodes extends ListRecords
{
    protected static string $resource = EmergencyQrCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => auth()->user()->role === 'superadmin'), // Hanya superadmin bisa membuat kantor baru
        ];
    }
}