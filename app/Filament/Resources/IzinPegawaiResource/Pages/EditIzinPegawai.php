<?php

namespace App\Filament\Resources\IzinPegawaiResource\Pages;

use App\Filament\Resources\IzinPegawaiResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIzinPegawai extends EditRecord
{
    protected static string $resource = IzinPegawaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Mutate the form data before it's used to fill the form fields.
     * This is useful for setting default values or manipulating data from the record.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Jika kantor_id belum terisi di data (misalnya karena tersembunyi atau dinonaktifkan),
        // ambil dari relasi pegawai.
        if (empty($data['kantor_id']) && isset($data['pegawai_id'])) {
            $pegawai = \App\Models\Pegawai::find($data['pegawai_id']);
            if ($pegawai) {
                $data['kantor_id'] = $pegawai->kantor_id;
            }
        }

        return $data;
    }
}
