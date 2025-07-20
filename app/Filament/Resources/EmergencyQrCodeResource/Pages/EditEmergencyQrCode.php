<?php

namespace App\Filament\Resources\EmergencyQrCodeResource\Pages;

use App\Filament\Resources\EmergencyQrCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmergencyQrCode extends EditRecord
{
    protected static string $resource = EmergencyQrCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => auth()->user()->role === 'superadmin'), // Hanya superadmin bisa hapus
        ];
    }
}