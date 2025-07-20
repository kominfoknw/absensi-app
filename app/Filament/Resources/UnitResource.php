<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
use App\Models\Kantor;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Unit';

    public static function canAccess(): bool
{
    return auth()->check() && (auth()->user()->role === 'superadmin' || auth()->user()->role === 'operator');
}

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('kantor_id')
                    ->label('Kantor')
                    ->options(fn () => auth()->user()->role === 'superadmin' ? Kantor::pluck('nama_kantor', 'id') : Kantor::where('id', auth()->user()->kantor_id)->pluck('nama_kantor', 'id'))
                    ->disabled(fn () => auth()->user()->role === 'operator') // Operator tidak bisa mengubah kantor
                    ->required()
                    ->exists('kantors', 'id'),
                Forms\Components\Select::make('user_id')
                    ->label('Operator Penanggung Jawab')
                    ->options(User::where('role', 'operator')->pluck('name', 'id'))
                    ->nullable()
                    ->helperText('Pilih operator yang bertanggung jawab atas unit ini.'),
                Forms\Components\TextInput::make('nama_unit')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'aktif' => 'Aktif',
                        'tidak aktif' => 'Tidak Aktif',
                    ])
                    ->default('aktif')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kantor.nama_kantor')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('operator.name')->label('Operator')->default('N/A')->sortable(),
                Tables\Columns\TextColumn::make('nama_unit')->searchable(),
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
                    ->relationship('kantor', 'nama_kantor')
                    ->hidden(fn () => auth()->user()->role === 'operator'),
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
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }

    // Otorisasi:
    // Superadmin: Bisa mengelola semua unit
    // Operator: Bisa mengelola unit di kantornya
    // Pegawai: Tidak bisa mengakses
    public static function canCreate(): bool { return auth()->user()->role === 'superadmin' || auth()->user()->role === 'operator'; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (auth()->user()->role === 'superadmin') return true;
        if (auth()->user()->role === 'operator') return $record->kantor_id === auth()->user()->kantor_id;
        return false;
    }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (auth()->user()->role === 'superadmin') return true;
        if (auth()->user()->role === 'operator') return $record->kantor_id === auth()->user()->kantor_id;
        return false;
    }
    public static function getEloquentQuery(): Builder
    {
        if (auth()->user()->role === 'superadmin') return parent::getEloquentQuery();
        if (auth()->user()->role === 'operator') return parent::getEloquentQuery()->where('kantor_id', auth()->user()->kantor_id);
        return parent::getEloquentQuery()->where('id', null); // Pegawai tidak bisa melihat
    }
}