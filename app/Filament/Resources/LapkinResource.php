<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LapkinResource\Pages;
use App\Models\Lapkin;
use App\Models\Pegawai;
use App\Models\Kantor;
use App\Models\User;
use Filament\Forms\Form; // Pastikan ini diimpor
use Filament\Resources\Resource;
use Filament\Tables\Table; // Pastikan ini diimpor
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon; // Pastikan ini diimpor

class LapkinResource extends Resource
{
    protected static ?string $model = Lapkin::class;
    protected static ?string $navigationIcon = 'heroicon-o-printer'; // Icon printer untuk laporan

    protected static ?string $modelLabel = 'Laporan Kinerja Pegawai';
    protected static ?string $pluralModelLabel = 'Laporan Kinerja Pegawai';
    protected static ?string $slug = 'laporan-kinerja';

    public static function canAccess(): bool
{
    return auth()->check() && (auth()->user()->role === 'superadmin' || auth()->user()->role === 'operator');
}

    // Metode form() dikosongkan, karena form filter ada di halaman kustom
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    // Metode table() dikosongkan, karena tabel ada di halaman kustom
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\RekapLapkin::route('/'), // <-- Mengarahkan ke halaman kustom RekapLapkin
        ];
    }

    // --- LOGIKA HAK AKSES ---
    // Logika hak akses ini tetap di sini untuk mengontrol akses ke resource secara keseluruhan
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->check()) {
            $user = auth()->user();

            if ($user->role === 'superadmin') {
                return $query; // Superadmin bisa melihat semua
            } elseif ($user->role === 'operator') {
                // Operator hanya bisa melihat lapkin pegawai yang berada di kantornya
                $query->whereHas('pegawai', function (Builder $pegawaiQuery) use ($user) {
                    $pegawaiQuery->where('kantor_id', $user->kantor_id);
                });
            } else {
                // Untuk peran lain (misal pegawai), defaultnya tidak menampilkan apa-apa
                // Karena resource ini untuk laporan yang diakses admin/operator
                return $query->whereNull('id'); // Tidak menampilkan data jika bukan superadmin/operator
            }
        }
        return $query; // Mengembalikan query yang mungkin sudah difilter
    }

    public static function canCreate(): bool
    {
        return false; // Laporan tidak dibuat dari sini
    }

    public static function canEdit(Model $record): bool
    {
        return false; // Laporan tidak diedit dari sini
    }

    public static function canDelete(Model $record): bool
    {
        return false; // Laporan tidak dihapus dari sini
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        // Hanya Superadmin dan Operator yang bisa mengakses halaman laporan ini
        return auth()->check() && (auth()->user()->role === 'superadmin' || auth()->user()->role === 'operator');
    }
}