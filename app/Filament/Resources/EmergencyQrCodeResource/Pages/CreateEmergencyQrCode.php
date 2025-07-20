<?php

namespace App\Filament\Resources\EmergencyQrCodeResource\Pages;

use App\Filament\Resources\EmergencyQrCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CreateEmergencyQrCode extends CreateRecord
{
    protected static string $resource = EmergencyQrCodeResource::class;

    // Otomatis mengisi kedua qr_code_secret saat membuat kantor baru
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['qr_code_secret_masuk'] = (string) Str::uuid();
        $data['qr_code_masuk_generated_at'] = Carbon::now();
        $data['qr_code_secret_pulang'] = (string) Str::uuid();
        $data['qr_code_pulang_generated_at'] = Carbon::now();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}