<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PegawaiResource\Pages;
use App\Filament\Resources\PegawaiResource\RelationManagers;
use App\Models\Pegawai;
use App\Models\Kantor;
use App\Models\Lokasi;
use App\Models\Unit;
use App\Models\Shift;
use App\Models\User; // Pastikan model User diimpor
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Blade;
use Illuminate\Database\Eloquent\Model; // Impor Model

class PegawaiResource extends Resource
{
    protected static ?string $model = Pegawai::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Manajemen Kepegawaian';

    protected static ?string $navigationLabel = 'Pegawai';

    protected static ?string $modelLabel = 'Pegawai';

    public static function canAccess(): bool
{
    return auth()->check() && (auth()->user()->role === 'superadmin' || auth()->user()->role === 'operator');
}

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Select::make('kantor_id')
    ->label('Kantor')
    ->options(function () {
        $user = auth()->user();

        if ($user->role === 'superadmin') {
            return Kantor::whereNotNull('nama_kantor')
                ->pluck('nama_kantor', 'id');
        }

        return Kantor::where('id', $user->kantor_id)
            ->pluck('nama_kantor', 'id');
    })
    ->searchable()
    ->required()
    ->visible(fn (): bool => auth()->user()->role === 'superadmin' || auth()->user()->role === 'operator')
    ->live(),

                        Select::make('lokasi_id')
                            ->label('Lokasi')
                            ->options(Lokasi::whereNotNull('nama_lokasi')->pluck('nama_lokasi', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('unit_id')
                            ->label('Unit Kerja')
                            ->options(Unit::whereNotNull('nama_unit')->pluck('nama_unit', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('shift_id')
                            ->label('Shift Kerja')
                            ->options(Shift::whereNotNull('nama_shift')->pluck('nama_shift', 'id'))
                            ->searchable()
                            ->required(),

                        // --- Bidang Atasan ID ---
                        Select::make('atasan_id')
                            ->label('Atasan Langsung')
                            ->options(function (Forms\Get $get) {
                                $kantorId = $get('kantor_id');
                                if ($kantorId) {
                                    // Filter pengguna berdasarkan kantor_id yang dipilih
                                    // Anda mungkin ingin memfilter berdasarkan peran di sini juga, misalnya, hanya pengguna dengan peran 'manager'
                                    return User::where('kantor_id', $kantorId)->pluck('name', 'id');
                                }
                                return collect(); // Kembalikan koleksi kosong jika tidak ada kantor_id yang dipilih
                            })
                            ->searchable()
                            ->nullable(), // Atasan bisa kosong
                        // --- Akhir Bidang Atasan ID ---

                        TextInput::make('nama')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('jabatan')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('nip')
                            ->label('NIP (Nomor Induk Pegawai)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('telepon')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(20)
                            ->nullable(),
                        Textarea::make('alamat')
                            ->label('Alamat Lengkap')
                            ->rows(3)
                            ->columnSpan('full')
                            ->nullable(),
                        TextInput::make('pangkat')
                            ->label('Pangkat')
                            ->maxLength(255)
                            ->nullable(),
                        TextInput::make('golongan')
                            ->label('Golongan')
                            ->maxLength(255)
                            ->nullable(),
                        TextInput::make('kelas_jabatan')
                            ->label('Kelas Jabatan')
                            ->maxLength(255)
                            ->nullable(),
                        ToggleButtons::make('jenis_kelamin')
                            ->label('Jenis Kelamin')
                            ->options([
                                'Laki-laki' => 'Laki-laki',
                                'Perempuan' => 'Perempuan',
                            ])
                            ->icons([
                                'Laki-laki' => 'heroicon-o-user',
                                'Perempuan' => 'heroicon-o-user',
                            ])
                            ->inline()
                            ->required(),
                        FileUpload::make('foto_selfie')
                            ->label('Foto Selfie')
                            ->image()
                            ->disk('public')
                            ->directory('selfie-photos')
                            ->nullable(),
                        TextInput::make('basic_tunjangan')
                            ->label('Tunjangan Pokok')
                            ->numeric()
                            ->prefix('Rp')
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nama')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kantor.nama_kantor')
                    ->label('Kantor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('lokasi.nama_lokasi')
                    ->label('Lokasi')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unit.nama_unit')
                    ->label('Unit')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shift.nama_shift')
                    ->label('Shift'),
                TextColumn::make('atasan.name') // Tampilkan nama atasan
                    ->label('Atasan')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false), // Jadikan terlihat secara default

                TextColumn::make('telepon')
                    ->label('Telepon')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('jenis_kelamin')
                    ->label('Jenis Kelamin'),
                ImageColumn::make('foto_face_recognition')
                    ->label('Wajah FR')
                    ->disk('public')
                    ->circular(),

                ImageColumn::make('foto_selfie')
                    ->label('Foto Diri')
                    ->disk('public')
                    ->circular(),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kantor_id')
                    ->label('Filter Berdasarkan Kantor')
                    ->options(Kantor::whereNotNull('nama_kantor')->pluck('nama_kantor', 'id'))
                    ->visible(fn (): bool => auth()->user()->role === 'superadmin'),
                SelectFilter::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options([
                        'Laki-laki' => 'Laki-laki',
                        'Perempuan' => 'Perempuan',
                    ]),
                // Tambahkan filter untuk atasan_id jika diperlukan
                SelectFilter::make('atasan_id')
                    ->label('Filter Berdasarkan Atasan')
                    ->options(User::pluck('name', 'id')) // Anda mungkin ingin memfilter daftar ini juga
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Action::make('Rekam Wajah')
                    ->label('Rekam Wajah')
                    ->icon('heroicon-o-camera')
                    ->color('info')
                    ->url(fn (Pegawai $record) => static::getUrl('rekam-wajah', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListPegawais::route('/'),
            'create' => Pages\CreatePegawai::route('/create'),
            'edit' => Pages\EditPegawai::route('/{record}/edit'),
            'rekam-wajah' => Pages\CaptureFace::route('/{record}/rekam-wajah'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->check() && auth()->user()->role === 'operator') {
            $query->where('kantor_id', auth()->user()->kantor_id);
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === 'superadmin' || (auth()->user()->role === 'operator' && auth()->user()->kantor_id !== null);
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->role === 'superadmin' || (auth()->user()->role === 'operator' && $record->kantor_id === auth()->user()->kantor_id);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->role === 'superadmin' || (auth()->user()->role === 'operator' && $record->kantor_id === auth()->user()->kantor_id);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->role === 'superadmin' || (auth()->user()->role === 'operator' && $record->kantor_id === auth()->user()->kantor_id);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->role === 'superadmin' || (auth()->user()->role === 'operator' && auth()->user()->kantor_id !== null);
    }

    public static function canViewAny(): bool
    {
        return true;
    }
}