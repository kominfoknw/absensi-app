<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftResource\Pages;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Shift';

    public static function canAccess(): bool
{
    return auth()->check() && (auth()->user()->role === 'superadmin');
}

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama_shift')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TimePicker::make('jam_masuk')
                    ->required(),
                Forms\Components\TimePicker::make('jam_pulang')
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
                Tables\Columns\TextColumn::make('nama_shift')->searchable(),
                Tables\Columns\TextColumn::make('jam_masuk')->label('Jam Masuk')->dateTime('H:i')->sortable(),
                Tables\Columns\TextColumn::make('jam_pulang')->label('Jam Pulang')->dateTime('H:i')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
        ];
    }

    // Otorisasi: Hanya Superadmin yang bisa mengelola Shift
    public static function canCreate(): bool { return auth()->user()->role === 'superadmin'; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()->role === 'superadmin'; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()->role === 'superadmin'; }
    public static function getEloquentQuery(): Builder { return parent::getEloquentQuery(); }
}