<?php

namespace App\Filament\Resources\PegawaiResource\Pages;

use App\Filament\Resources\PegawaiResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\User; // Import model User
use Illuminate\Support\Facades\Hash; // Import Hash

class CreatePegawai extends CreateRecord
{
    protected static string $resource = PegawaiResource::class;

    // Metode ini akan dipanggil setelah data pegawai berhasil dibuat
    protected function afterCreate(): void
    {
        $pegawai = $this->record; // Dapatkan record Pegawai yang baru saja dibuat

        // Cek apakah user dengan NIP ini sudah ada untuk menghindari duplikasi
        // Ini penting jika proses user creation bisa dipisah atau ada data NIP yang sudah jadi user
        if (!User::where('email', $pegawai->nip)->exists()) {
            // Buat user baru di tabel users
            User::create([
                'name' => $pegawai->nama,
                'email' => $pegawai->nip, // NIP sebagai username/email
                'password' => Hash::make('password'), // Password default, bisa diganti
                'role' => 'pegawai', // Role default untuk user pegawai, bisa disesuaikan
                'kantor_id' => $pegawai->kantor_id, // Kaitkan user dengan kantor pegawai
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Jika user adalah operator, otomatis tambahkan kantor_id dari user yang login
        if (auth()->user()->role === 'operator' && auth()->user()->kantor_id !== null) {
            $data['kantor_id'] = auth()->user()->kantor_id;
        }

        return $data;
    }
}

