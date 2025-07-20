<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TugasLuarResource\Pages;
use App\Filament\Resources\TugasLuarResource\RelationManagers;
use App\Models\TugasLuar;
use App\Models\Pegawai;
use App\Models\Kantor;
use App\Models\User;
use App\Models\Kehadiran; // <-- PENTING: Impor model Kehadiran
use App\Enums\TugasLuarStatus;
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
use Filament\Tables\Actions\Action; // <-- PENTING: Impor Action
use Filament\Notifications\Notification; // <-- PENTING: Impor Notification
use Carbon\Carbon; // <-- PENTING: Impor Carbon

class TugasLuarResource extends Resource
{
    protected static ?string $model = TugasLuar::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $modelLabel = 'Tugas Luar Pegawai';
    protected static ?string $pluralModelLabel = 'Tugas Luar Pegawai';

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
                        Hidden::make('user_id')
                            ->default(auth()->id())
                            ->dehydrated(true),

                        Select::make('kantor_id')
                            ->label('Kantor')
                            ->options(
                                fn () => auth()->user()->role === 'superadmin'
                                    ? Kantor::pluck('nama_kantor', 'id')
                                    : Kantor::where('id', auth()->user()->kantor_id)->pluck('nama_kantor', 'id')
                            )
                            ->searchable()
                            ->required()
                            ->live()
                            ->disabled(fn (): bool => auth()->user()->role === 'operator')
                            ->default(fn () => auth()->user()->role === 'operator' ? auth()->user()->kantor_id : null),

                        Select::make('pegawai_id')
                            ->label('Pegawai')
                            ->options(function (Forms\Get $get) {
                                $kantorId = $get('kantor_id');
                                if ($kantorId) {
                                    return Pegawai::where('kantor_id', $kantorId)->pluck('nama', 'id');
                                }
                                return collect();
                            })
                            ->searchable()
                            ->required()
                            ->exists('pegawais', 'id')
                            ->rules([
                                function (Forms\Get $get, ?Model $record) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                        $pegawaiId = $value;
                                        $tanggalMulai = $get('tanggal_mulai');
                                        $tanggalSelesai = $get('tanggal_selesai');

                                        if (!$pegawaiId || !$tanggalMulai || !$tanggalSelesai) {
                                            return;
                                        }

                                        $query = TugasLuar::where('pegawai_id', $pegawaiId)
                                            ->where(function ($q) use ($tanggalMulai, $tanggalSelesai) {
                                                $q->whereBetween('tanggal_mulai', [$tanggalMulai, $tanggalSelesai])
                                                  ->orWhereBetween('tanggal_selesai', [$tanggalMulai, $tanggalSelesai])
                                                  ->orWhere(function ($subQ) use ($tanggalMulai, $tanggalSelesai) {
                                                      $subQ->where('tanggal_mulai', '<=', $tanggalMulai)
                                                           ->where('tanggal_selesai', '>=', $tanggalSalah);
                                                  });
                                            });

                                        if ($record) {
                                            $query->where('id', '!=', $record->id);
                                        }

                                        if ($query->exists()) {
                                            $fail('Pegawai ini sudah memiliki tugas luar pada rentang tanggal tersebut.');
                                        }
                                    };
                                },
                            ]),

                        TextInput::make('nama_tugas')
                            ->label('Nama Tugas')
                            ->required()
                            ->maxLength(255),

                        DatePicker::make('tanggal_mulai')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->native(false)
                            ->live(),
                        DatePicker::make('tanggal_selesai')
                            ->label('Tanggal Selesai')
                            ->required()
                            ->native(false)
                            ->afterOrEqual('tanggal_mulai')
                            ->live(),

                        FileUpload::make('file')
                            ->label('File Pendukung (opsional)')
                            ->disk('public')
                            ->directory('tugas_luar_files')
                            ->nullable(),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3)
                            ->columnSpan('full')
                            ->nullable(),

                        Select::make('status')
                            ->label('Status Tugas Luar')
                            ->options(TugasLuarStatus::class)
                            ->default(TugasLuarStatus::Pending)
                            ->required()
                            ->disabled(fn (?Model $record) => $record && $record->status !== TugasLuarStatus::Pending && auth()->user()->role !== 'superadmin'),
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
                    ->toggleable(isToggledHiddenByDefault: auth()->user()->role === 'operator'),
                TextColumn::make('nama_tugas')
                    ->label('Nama Tugas')
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
                    ->url(fn (TugasLuar $record): ?string => $record->file ? asset('storage/' . $record->file) : null)
                    ->openUrlInNewTab(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => TugasLuarStatus::Pending->value,
                        'success' => TugasLuarStatus::Diterima->value,
                        'danger' => TugasLuarStatus::Ditolak->value,
                    ]),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')
                    ->label('Dibuat Oleh')
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
                    ->visible(fn (): bool => auth()->user()->role === 'superadmin'),
                SelectFilter::make('status')
                    ->label('Filter Berdasarkan Status')
                    ->options(TugasLuarStatus::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                // --- Aksi Verifikasi BARU ---
                Action::make('verifyTugasLuar')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    // Aksi ini hanya terlihat jika status tugas luar masih Pending
                    // dan pengguna memiliki hak akses untuk mengedit record ini
                    ->visible(fn (TugasLuar $record): bool =>
                        $record->status === TugasLuarStatus::Pending && static::canEdit($record)
                    )
                    ->form([
                        Select::make('status')
                            ->label('Ubah Status Tugas Luar')
                            ->options([
                                TugasLuarStatus::Diterima->value => 'Diterima',
                                TugasLuarStatus::Ditolak->value => 'Ditolak',
                            ])
                            ->default(TugasLuarStatus::Diterima->value) // Default pilihan ke Diterima
                            ->required(),
                    ])
                    ->action(function (array $data, TugasLuar $record): void {
                        $newStatus = $data['status'];

                        // Update status tugas luar
                        $record->status = $newStatus;
                        $record->save();

                        // Jika status diterima, catat kehadiran
                        if ($newStatus === TugasLuarStatus::Diterima->value) {
                            $startDate = Carbon::parse($record->tanggal_mulai);
                            $endDate = Carbon::parse($record->tanggal_selesai);

                            // Loop melalui setiap hari dalam rentang tugas luar
                            for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
                                // Cek apakah sudah ada catatan kehadiran untuk pegawai dan tanggal ini
                                $existingKehadiran = Kehadiran::where('pegawai_id', $record->pegawai_id)
                                    ->where('tanggal', $date->toDateString())
                                    ->first();

                                if ($existingKehadiran) {
                                    // Jika ada, update statusnya menjadi 'hadir' (untuk tugas luar)
                                    $existingKehadiran->status = 'hadir';
                                    $existingKehadiran->save();

                                    Notification::make()
                                        ->title('Catatan Kehadiran Diperbarui')
                                        ->body("Kehadiran {$record->pegawai->nama} pada tanggal {$date->format('d M Y')} diupdate menjadi 'hadir' (Tugas Luar).")
                                        ->success()
                                        ->send();
                                } else {
                                    // Jika belum ada, buat catatan kehadiran baru
                                    Kehadiran::create([
                                        'user_id' => $record->user_id, // User yang membuat tugas luar
                                        'pegawai_id' => $record->pegawai_id,
                                        // Asumsi pegawai memiliki shift_id. Sesuaikan jika relasi shift_id ada di model Pegawai
                                        'shift_id' => $record->pegawai->shift_id ?? null, // Atur ke null jika tidak ada atau tidak relevan
                                        'tanggal' => $date->toDateString(),
                                        'jam_masuk' => '00:00:00', // Sesuai permintaan
                                        'lat_masuk' => 0.0, // Sesuai permintaan
                                        'long_masuk' => 0.0, // Sesuai permintaan
                                        'telat' => 0, // Sesuai permintaan
                                        'jam_pulang' => '00:00:00', // Sesuai permintaan
                                        'lat_pulang' => 0.0, // Sesuai permintaan
                                        'long_pulang' => 0.0, // Sesuai permintaan
                                        'pulang_cepat' => 0, // Sesuai permintaan
                                        'status' => 'hadir', // Sesuai permintaan (diasumsikan hadir karena tugas luar)
                                        // created_at dan updated_at akan otomatis terisi
                                    ]);

                                    Notification::make()
                                        ->title('Kehadiran Tercatat')
                                        ->body("Kehadiran {$record->pegawai->nama} pada tanggal {$date->format('d M Y')} berhasil dicatat sebagai 'hadir' (Tugas Luar).")
                                        ->success()
                                        ->send();
                                }
                            }
                            Notification::make()
                                ->title('Tugas Luar Diterima dan Kehadiran Dicatat')
                                ->body('Tugas luar berhasil diterima dan catatan kehadiran telah dibuat untuk seluruh periode tugas luar.')
                                ->success()
                                ->send();
                        } else {
                            // Jika status ditolak
                            Notification::make()
                                ->title('Tugas Luar Ditolak')
                                ->body('Status tugas luar berhasil diubah menjadi Ditolak.')
                                ->warning()
                                ->send();
                        }
                    }),
                // --- Akhir Aksi Verifikasi BARU ---
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
            'index' => Pages\ListTugasLuars::route('/'),
            'create' => Pages\CreateTugasLuar::route('/create'),
            'edit' => Pages\EditTugasLuar::route('/{record}/edit'),
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