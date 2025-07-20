<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LokasiResource\Pages;
use App\Models\Lokasi;
use App\Models\Kantor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LokasiResource extends Resource
{
    protected static ?string $model = Lokasi::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Lokasi';

    public static function canAccess(): bool
{
    return auth()->check() && (auth()->user()->role === 'superadmin');
}

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Select::make('kantor_id')
                ->relationship('kantor', 'nama_kantor')
                ->required()
                ->exists('kantors', 'id'),

            Forms\Components\TextInput::make('nama_lokasi')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('latitude')
                ->numeric()
                ->required()
                ->reactive(),

            Forms\Components\TextInput::make('longitude')
                ->numeric()
                ->required()
                ->reactive(),

            // Tambahkan komponen peta
            Forms\Components\View::make('filament.forms.components.leaflet-map'),

            Forms\Components\TextInput::make('radius')
                ->numeric()
                ->suffix('meter')
                ->default(0)
                ->required(),

            Forms\Components\Select::make('status')
                ->options([
                    'aktif' => 'Aktif',
                    'tidak aktif' => 'Tidak Aktif',
                ])
                ->default('aktif')
                ->required(),

            Forms\Components\Textarea::make('keterangan')
                ->maxLength(65535)
                ->columnSpanFull(),
        ]);
}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kantor.nama_kantor')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('nama_lokasi')->searchable(),
                Tables\Columns\TextColumn::make('latitude')->searchable(),
                Tables\Columns\TextColumn::make('longitude')->searchable(),
                Tables\Columns\TextColumn::make('radius')->suffix('m')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'aktif' => 'success',
                        'tidak aktif' => 'danger',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kantor')
                    ->relationship('kantor', 'nama_kantor'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'aktif' => 'Aktif',
                        'tidak aktif' => 'Tidak Aktif',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListLokasis::route('/'),
            'create' => Pages\CreateLokasi::route('/create'),
            'edit' => Pages\EditLokasi::route('/{record}/edit'),
        ];
    }

    // Otorisasi: Hanya Superadmin yang bisa mengelola Lokasi
    public static function canCreate(): bool { return auth()->user()->role === 'superadmin'; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()->role === 'superadmin'; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()->role === 'superadmin'; }
    public static function getEloquentQuery(): Builder { return parent::getEloquentQuery(); }
}