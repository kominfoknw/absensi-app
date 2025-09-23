<?php

namespace App\Filament\Resources\PegawaiResource\Pages;

use App\Filament\Resources\PegawaiResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\User;

class EditPegawai extends EditRecord
{
    protected static string $resource = PegawaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Ambil pegawai yang sedang diedit
        $pegawai = $this->record;

        // Cari user berdasarkan email (users.email = pegawai.nip)
        $user = User::where('email', $pegawai->nip)->first();

        if ($user) {
            $user->kantor_id = $pegawai->kantor_id;
            $user->save();
        }
    }
}
