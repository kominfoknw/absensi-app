<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BeritaResource\Pages;
use App\Filament\Resources\BeritaResource\RelationManagers;
use App\Models\Berita; // Pastikan model Berita diimpor
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\FileUpload; // Untuk upload gambar
use Filament\Forms\Components\RichEditor; // Untuk konten yang lebih kaya
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;

class BeritaResource extends Resource
{
    protected static ?string $model = Berita::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper'; // Icon untuk navigasi
    protected static ?string $navigationGroup = 'Konten'; // Grup navigasi (opsional)
    protected static ?string $navigationLabel = 'Berita'; // Label di navigasi
    protected static ?string $pluralModelLabel = 'Berita'; // Label jamak

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->role === 'superadmin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Berita')
                    ->schema([
                        TextInput::make('judul')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Masukkan judul berita')
                            ->columnSpanFull(), // Mengambil lebar penuh

                        RichEditor::make('konten') // Menggunakan RichEditor untuk input teks kaya
                            ->required()
                            ->maxLength(65535) // Ukuran maksimal TEXT
                            ->placeholder('Tulis konten berita di sini...')
                            ->toolbarButtons([ // Tombol-tombol yang ingin ditampilkan
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'undo',
                            ])
                            ->columnSpanFull(),

                        FileUpload::make('gambar_url')
                            ->label('Gambar Utama')
                            ->image() // Hanya menerima file gambar
                            ->directory('berita-gambar') // Direktori penyimpanan di storage/app/public
                            ->columnSpanFull()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']) // Tipe file yang diizinkan
                            ->maxSize(2048) // Ukuran maksimal 2MB
                            ->helperText('Unggah gambar utama untuk berita (max 2MB, JPG, PNG, WEBP).'),
                    ])->columns(2), // Atur layout section menjadi 2 kolom
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')
                    ->searchable()
                    ->sortable(),
                ImageColumn::make('gambar_url')
                    ->label('Gambar')
                    ->defaultImageUrl(url('/images/default-news.png')) // Gambar default jika kosong (opsional)
                    ->circular(), // Bentuk gambar lingkaran
                TextColumn::make('konten')
                    ->limit(50) // Batasi tampilan konten
                    ->tooltip('Lihat konten lengkap')
                    ->html(), // Render HTML jika konten berisi tag HTML (dari RichEditor)
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true) // Sembunyikan secara default
                    ->label('Dibuat Pada'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true) // Sembunyikan secara default
                    ->label('Diperbarui Pada'),
            ])
            ->filters([
                // Filter berdasarkan judul atau tanggal
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->placeholder('Dari tanggal'),
                        Forms\Components\DatePicker::make('created_until')
                            ->placeholder('Sampai tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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
            'index' => Pages\ListBeritas::route('/'),
            'create' => Pages\CreateBerita::route('/create'),
            'edit' => Pages\EditBerita::route('/{record}/edit'),
        ];
    }
}