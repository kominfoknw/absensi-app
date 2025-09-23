<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IzinPegawaiResource\Pages;
use App\Filament\Resources\IzinPegawaiResource\RelationManagers;
use App\Models\IzinPegawai;
use App\Models\Pegawai;
use App\Models\Kantor;
use App\Models\User;
use App\Models\Kehadiran; // Import model Kehadiran
use App\Enums\IzinStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Validation\Rule;
use Filament\Tables\Actions\Action; // Import Action
use Filament\Notifications\Notification; // Import Notification
use Carbon\Carbon; // Import Carbon

class IzinPegawaiResource extends Resource
{
    protected static ?string $model = IzinPegawai::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Izin Pegawai';
    protected static ?string $modelLabel = 'Izin Pegawai';

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
                        // Hidden field untuk menyimpan user_id yang sedang login
                        Hidden::make('user_id')
                            ->default(auth()->id())
                            ->dehydrated(true), // Pastikan nilai ini disimpan

                        Select::make('kantor_id')
                            ->label('Kantor')
                            ->options(
                                fn () => auth()->user()->role === 'superadmin'
                                    ? Kantor::pluck('nama_kantor', 'id')
                                    : Kantor::where('id', auth()->user()->kantor_id)->pluck('nama_kantor', 'id')
                            )
                            ->searchable()
                            ->required()
                            ->live() // Penting agar perubahan ini memicu update di field pegawai_id
                            ->disabled(fn (): bool => auth()->user()->role === 'operator') // Operator tidak bisa memilih kantor lain
                            ->default(fn () => auth()->user()->role === 'operator' ? auth()->user()->kantor_id : null),

                        Select::make('pegawai_id')
                            ->label('Pegawai')
                            ->options(function (Forms\Get $get) {
                                $kantorId = $get('kantor_id');
                                if ($kantorId) {
                                    return Pegawai::where('kantor_id', $kantorId)->pluck('nama', 'id');
                                }
                                return collect(); // Kosongkan jika kantor belum dipilih
                            })
                            ->searchable()
                            ->required()
                            ->exists('pegawais', 'id') // Memastikan pegawai ada
                            // Aturan validasi unik tumpang tindih tanggal ditempatkan langsung pada field ini
                            ->rules([
                                function (Forms\Get $get, ?Model $record) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                        $pegawaiId = $value; // Nilai dari pegawai_id yang sedang divalidasi
                                        $tanggalMulai = $get('tanggal_mulai');
                                        $tanggalSelesai = $get('tanggal_selesai');

                                        // Jika salah satu dari field ini kosong, abaikan validasi tumpang tindih
                                        if (!$pegawaiId || !$tanggalMulai || !$tanggalSelesai) {
                                            return;
                                        }

                                        // Query untuk mencari izin yang tumpang tindih
                                        $query = IzinPegawai::where('pegawai_id', $pegawaiId)
                                            ->where(function ($q) use ($tanggalMulai, $tanggalSelesai) {
                                                $q->whereBetween('tanggal_mulai', [$tanggalMulai, $tanggalSelesai]) // Izin lain dimulai di antara rentang
                                                  ->orWhereBetween('tanggal_selesai', [$tanggalMulai, $tanggalSelesai]) // Izin lain berakhir di antara rentang
                                                  ->orWhere(function ($subQ) use ($tanggalMulai, $tanggalSelesai) { // Izin lain mencakup seluruh rentang
                                                      $subQ->where('tanggal_mulai', '<=', $tanggalMulai)
                                                           ->where('tanggal_selesai', '>=', $tanggalSelesai);
                                                  });
                                            });

                                        // Saat mengedit, kecualikan record saat ini dari pengecekan
                                        if ($record) {
                                            $query->where('id', '!=', $record->id);
                                        }

                                        if ($query->exists()) {
                                            $fail('Pegawai ini sudah memiliki izin pada rentang tanggal tersebut.');
                                        }
                                    };
                                },
                            ]),

                        TextInput::make('nama_izin')
                            ->label('Nama Izin')
                            ->required()
                            ->maxLength(255),

                        DatePicker::make('tanggal_mulai')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->native(false)
                            ->live(), // Jadikan live agar validasi di pegawai_id bisa bereaksi
                        DatePicker::make('tanggal_selesai')
                            ->label('Tanggal Selesai')
                            ->required()
                            ->native(false)
                            ->afterOrEqual('tanggal_mulai')
                            ->live(), // Jadikan live agar validasi di pegawai_id bisa bereaksi

                        FileUpload::make('file')
                            ->label('File Pendukung (opsional)')
                            ->disk('public')
                            ->directory('izin_files') // Folder di storage/app/public/izin_files
                            ->nullable(),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3)
                            ->columnSpan('full')
                            ->nullable(),

                      
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pegawai.nama')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kantor.nama_kantor')
                    ->label('Kantor')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: auth()->user()->role === 'operator'), // Sembunyikan untuk operator
                TextColumn::make('nama_izin')
                    ->label('Nama Izin')
                    ->searchable(),
                TextColumn::make('tanggal_mulai')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),
                TextColumn::make('tanggal_selesai')
                    ->label('Selesai')
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('file')
                    ->label('File')
                    ->icon(fn (?string $state): string => $state ? 'heroicon-o-document' : 'heroicon-o-x-circle')
                    ->color(fn (?string $state): string => $state ? 'success' : 'danger')
                    ->url(fn (IzinPegawai $record): ?string => $record->file ? asset('storage/' . $record->file) : null)
                    ->openUrlInNewTab(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => IzinStatus::Pending->value,
                        'success' => IzinStatus::Diterima->value,
                        'danger' => IzinStatus::Ditolak->value,
                    ]),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')
                    ->label('Diajukan Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kantor_id')
                    ->label('Filter Berdasarkan Kantor')
                    ->options(
                        fn () => auth()->user()->role === 'superadmin'
                            ? Kantor::pluck('nama_kantor', 'id')
                            : Kantor::where('id', auth()->user()->kantor_id)->pluck('nama_kantor', 'id')
                    )
                    ->visible(fn (): bool => auth()->user()->role === 'superadmin'), // Hanya superadmin yang bisa memfilter semua kantor
                SelectFilter::make('status')
                    ->label('Filter Berdasarkan Status')
                    ->options(IzinStatus::class), // Menggunakan Enum untuk filter
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Action::make('verifyIzin')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->visible(fn (IzinPegawai $record): bool => $record->status === IzinStatus::Pending && static::canEdit($record))
                    ->form([
                        Select::make('status')
                            ->label('Ubah Status Izin')
                            ->options([
                                IzinStatus::Diterima->value => 'Diterima',
                                IzinStatus::Ditolak->value => 'Ditolak',
                            ])
                            ->default(IzinStatus::Diterima)
                            ->required(),
                    ])
                    ->action(function (array $data, IzinPegawai $record): void {
                        $newStatus = $data['status'];

                        $record->status = $newStatus;
                        $record->save();

                        if ($newStatus === IzinStatus::Diterima) {
                            $startDate = Carbon::parse($record->tanggal_mulai);
                            $endDate = Carbon::parse($record->tanggal_selesai);

                            for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
                                $existingKehadiran = Kehadiran::where('pegawai_id', $record->pegawai_id)
                                    ->where('tanggal', $date->toDateString())
                                    ->first();

                                if ($existingKehadiran) {
                                    $existingKehadiran->status = 'izin';
                                    $existingKehadiran->save();

                                    Notification::make()
                                        ->title('Catatan Kehadiran Diperbarui')
                                        ->body("Kehadiran {$record->pegawai->nama} pada tanggal {$date->format('d M Y')} diupdate menjadi 'izin'.")
                                        ->success()
                                        ->send();
                                } else {
                                    // PASTIKAN pegawai_id DIMASUKKAN DI SINI
                                    Kehadiran::create([
                                        'user_id' => $record->user_id,
                                        'pegawai_id' => $record->pegawai_id, // <--- TAMBAHKAN BARIS INI
                                        'shift_id' => $record->pegawai->shift_id,
                                        'tanggal' => $date->toDateString(),
                                        'jam_masuk' => '00:00:00',
                                        'lat_masuk' => 0.0,
                                        'long_masuk' => 0.0,
                                        'telat' => null,
                                        'jam_pulang' => '00:00:00',
                                        'lat_pulang' => 0.0,
                                        'long_pulang' => 0.0,
                                        'pulang_cepat' => false,
                                        'status' => 'izin',
                                    ]);

                                    Notification::make()
                                        ->title('Kehadiran Tercatat')
                                        ->body("Kehadiran {$record->pegawai->nama} pada tanggal {$date->format('d M Y')} berhasil dicatat sebagai 'izin'.")
                                        ->success()
                                        ->send();
                                }
                            }
                            Notification::make()
                                ->title('Izin Diterima dan Kehadiran Dicatat')
                                ->body('Izin berhasil diterima dan catatan kehadiran telah dibuat untuk seluruh periode izin.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Izin Ditolak')
                                ->body('Status izin berhasil diubah menjadi Ditolak.')
                                ->warning()
                                ->send();
                        }
                    }),
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
            'index' => Pages\ListIzinPegawais::route('/'),
            'create' => Pages\CreateIzinPegawai::route('/create'),
            'edit' => Pages\EditIzinPegawai::route('/{record}/edit'),
        ];
    }

    // --- LOGIKA HAK AKSES ---
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->check() && auth()->user()->role === 'operator') {
            $query->whereHas('pegawai', function (Builder $pegawaiQuery) {
                $pegawaiQuery->where('kantor_id', auth()->user()->kantor_id);
            });
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