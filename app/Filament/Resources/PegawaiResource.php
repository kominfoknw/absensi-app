<?php

namespace App\Filament\Resources;

use App\Models\{Pegawai, Kantor, Lokasi, Unit, Shift, User};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Columns\{TextColumn, ImageColumn};
use Filament\Forms\Components\{
    Card, Select, TextInput, Textarea,
    ToggleButtons, FileUpload
};
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Resources\PegawaiResource\Pages;

class PegawaiResource extends Resource
{
    protected static ?string $model = Pegawai::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Manajemen Kepegawaian';
    protected static ?string $navigationLabel = 'Pegawai';
    protected static ?string $modelLabel = 'Pegawai';

    public static function canAccess(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['superadmin', 'operator']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Card::make()->schema([
                // Kantor
                Select::make('kantor_id')
                    ->label('Kantor')
                    ->options(function () {
                        $user = auth()->user();
                        if ($user->role === 'superadmin') {
                            return Kantor::pluck('nama_kantor', 'id');
                        }
                        return Kantor::where('id', $user->kantor_id)->pluck('nama_kantor', 'id');
                    })
                    ->searchable()
                    ->required()
                    ->visible(fn () => in_array(auth()->user()->role, ['superadmin', 'operator']))
                    ->live(),

                // Lokasi berdasarkan kantor
                Select::make('lokasi_id')
                    ->label('Lokasi')
                    ->options(fn (Get $get) => Lokasi::where('kantor_id', $get('kantor_id'))->pluck('nama_lokasi', 'id'))
                    ->searchable()
                    ->required()
                    ->reactive(),

                // Unit berdasarkan kantor
                Select::make('unit_id')
                    ->label('Unit Kerja')
                    ->options(fn (Get $get) => Unit::where('kantor_id', $get('kantor_id'))->pluck('nama_unit', 'id'))
                    ->searchable()
                    ->required()
                    ->reactive(),

                // Shift
                Select::make('shift_id')
                    ->label('Shift Kerja')
                    ->options(Shift::pluck('nama_shift', 'id'))
                    ->searchable()
                    ->required(),

                // Atasan langsung berdasarkan kantor
                Select::make('atasan_id')
                    ->label('Atasan Langsung')
                    ->options(fn (Get $get) => User::where('kantor_id', $get('kantor_id'))->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                TextInput::make('nama')->label('Nama Lengkap')->required()->maxLength(255),
                TextInput::make('jabatan')->required()->maxLength(255),
                TextInput::make('nip')->label('NIP')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('telepon')->label('Nomor Telepon')->tel()->nullable()->maxLength(20),
                Textarea::make('alamat')->label('Alamat Lengkap')->rows(3)->columnSpan('full')->nullable(),
                TextInput::make('pangkat')->label('Pangkat')->maxLength(255)->nullable(),
                TextInput::make('golongan')->label('Golongan')->maxLength(255)->nullable(),
                TextInput::make('kelas_jabatan')->label('Kelas Jabatan')->maxLength(255)->nullable(),
                ToggleButtons::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options(['Laki-laki' => 'Laki-laki', 'Perempuan' => 'Perempuan'])
                    ->icons(['Laki-laki' => 'heroicon-o-user', 'Perempuan' => 'heroicon-o-user'])
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
                TextColumn::make('nip')->label('NIP')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama Pegawai')->searchable()->sortable(),
                TextColumn::make('kantor.nama_kantor')->label('Kantor')->searchable()->sortable(),
                TextColumn::make('lokasi.nama_lokasi')->label('Lokasi')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unit.nama_unit')->label('Unit')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shift.nama_shift')->label('Shift'),
                TextColumn::make('atasan.name')->label('Atasan')->searchable()->sortable(),
                TextColumn::make('telepon')->label('Telepon')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('jenis_kelamin')->label('Jenis Kelamin'),
                ImageColumn::make('foto_face_recognition')->label('Wajah FR')->disk('public')->circular(),
                ImageColumn::make('foto_selfie')->label('Foto Diri')->disk('public')->circular(),
                TextColumn::make('created_at')->label('Dibuat Pada')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kantor_id')
                    ->label('Filter Berdasarkan Kantor')
                    ->options(Kantor::pluck('nama_kantor', 'id'))
                    ->visible(fn () => auth()->user()->role === 'superadmin'),
                SelectFilter::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options(['Laki-laki' => 'Laki-laki', 'Perempuan' => 'Perempuan']),
                SelectFilter::make('atasan_id')
                    ->label('Filter Berdasarkan Atasan')
                    ->options(User::pluck('name', 'id'))
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
        return [];
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

    // Query terbatas untuk operator berdasarkan kantor
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        if (auth()->check() && auth()->user()->role === 'operator') {
            $query->where('kantor_id', auth()->user()->kantor_id);
        }
        return $query;
    }

    // Permissions
    public static function canCreate(): bool
    {
        return auth()->user()->role === 'superadmin' || (auth()->user()->role === 'operator' && auth()->user()->kantor_id);
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->role === 'superadmin' || $record->kantor_id === auth()->user()->kantor_id;
    }

    public static function canEdit(Model $record): bool
    {
        return self::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        return self::canView($record);
    }

    public static function canDeleteAny(): bool
    {
        return self::canCreate();
    }

    public static function canViewAny(): bool
    {
        return true;
    }
}
