<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
// Pastikan use statement ini benar untuk kolom tabel
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn; // <--- PASTIKAN INI ADA DAN BENAR

// Pastikan use statement ini benar untuk komponen form
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Card;

// Pastikan use statement ini benar untuk action tabel
use Filament\Tables\Actions\Action as TableAction; // <--- Tambahkan alias untuk menghindari konflik dengan FormAction

use Filament\Notifications\Notification;
use App\Models\Lapkin;
use App\Models\Pegawai;
use App\Models\Kantor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EvaluasiLapkinPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable {
        InteractsWithTable::resetPage as tableResetPage;
    }

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string $view = 'filament.pages.evaluasi-lapkin-page';
    protected static ?string $navigationLabel = 'Evaluasi Laporan Kinerja';
    protected static ?string $title = 'Evaluasi Laporan Kinerja';
    protected static ?int $navigationSort = 2;

    public ?int $bulan = null;
    public ?int $tahun = null;
    public ?int $kantor_id = null;
    public ?int $pegawai_id = null;

    public bool $showTable = false;

    public function mount(): void
    {
        $user = auth()->user();

        $this->form->fill([
            'bulan' => now()->month,
            'tahun' => now()->year,
            'pegawai_id' => null,
            'kantor_id' => null,
        ]);

        $this->bulan = now()->month;
        $this->tahun = now()->year;
        $this->pegawai_id = null;

        if ($user->role === 'operator') {
            $this->kantor_id = $user->kantor_id;
            $this->form->fill(['kantor_id' => $user->kantor_id]);
        }
    }

    protected function getFormSchema(): array
    {
        $user = Auth::user();

        return [
            Grid::make(3)->schema([
                Select::make('bulan')
                    ->label('Bulan')
                    ->options(array_combine(
                        range(1, 12),
                        array_map(fn($m) => Carbon::create(null, $m)->translatedFormat('F'), range(1, 12))
                    ))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->bulan = $state),

                Select::make('tahun')
                    ->label('Tahun')
                    ->options(collect(range(now()->year - 5, now()->year + 1))->mapWithKeys(fn ($y) => [$y => $y]))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->tahun = $state),

                Select::make('kantor_id')
                    ->label('Kantor')
                    ->options(Kantor::pluck('nama_kantor', 'id'))
                    ->placeholder('Pilih Kantor')
                    ->visible(fn () => $user->role === 'superadmin')
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->kantor_id = $state;
                        $this->pegawai_id = null;
                        $this->form->fill(['pegawai_id' => null]);
                    }),
            ]),

            Select::make('pegawai_id')
                ->label('Pilih Pegawai')
                ->options(function (\Filament\Forms\Get $get) use ($user) {
                    $pegawaiOptions = collect();
                    $kantorId = $get('kantor_id');

                    if ($user->role === 'superadmin') {
                        if (!empty($kantorId)) {
                            $pegawaiOptions = Pegawai::where('kantor_id', $kantorId)->pluck('nama', 'id');
                        }
                    } elseif ($user->role === 'operator') {
                        $pegawaiOptions = Pegawai::where('kantor_id', $user->kantor_id)->pluck('nama', 'id');
                    } elseif ($user->role === 'pegawai') { // <-- FOKUS DI SINI
                        // Menggunakan relasi bawahanPegawai() dari user yang login
                        if ($user->bawahanPegawai->count() > 0) {
                            $pegawaiOptions = $user->bawahanPegawai->pluck('nama', 'id');
                        }
                    }
                    return $pegawaiOptions;
                })
                ->searchable()
                ->preload()
                ->required(fn (\Filament\Forms\Get $get) =>
                    ($user->role === 'superadmin' && !empty($get('kantor_id'))) ||
                    $user->role === 'operator' ||
                    $user->role === 'pegawai'
                )
                ->placeholder(fn () =>
                    $user->role === 'superadmin' && empty($this->kantor_id) ? 'Pilih kantor terlebih dahulu' : 'Pilih Pegawai'
                )
                ->disabled(fn () => $user->role === 'superadmin' && empty($this->kantor_id))
                ->live()
                ->afterStateUpdated(fn ($state) => $this->pegawai_id = $state),

            Actions::make([
                FormAction::make('tampilkan')
                    ->label('Tampilkan Evaluasi')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->action('loadData'),
            ])->fullWidth(),
        ];
    }

    public function loadData(): void
    {
        $data = $this->form->getState();
        if (!$data['bulan'] || !$data['tahun'] || !$data['pegawai_id']) {
            Notification::make()
                ->title('Peringatan!')
                ->body('Bulan, Tahun, dan Pegawai harus dipilih.')
                ->warning()
                ->send();
            $this->showTable = false;
            return;
        }

        $this->showTable = true;
        $this->tableResetPage();
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->query(Lapkin::query()
                ->when($this->bulan, fn (Builder $query) => $query->whereMonth('tanggal', $this->bulan))
                ->when($this->tahun, fn (Builder $query) => $query->whereYear('tanggal', $this->tahun))
                ->when($this->pegawai_id, fn (Builder $query) => $query->where('pegawai_id', $this->pegawai_id))
                ->when($this->kantor_id && $user->role === 'superadmin', fn (Builder $query) => $query->where('kantor_id', $this->kantor_id))
                ->when($user->role === 'operator', fn (Builder $query) => $query->whereHas('pegawai', fn ($q) => $q->where('kantor_id', $user->kantor_id)))
                ->when($user->role === 'pegawai', function (Builder $query) use ($user) {
                    // Gunakan bawahanPegawai dari user
                    if ($user->bawahanPegawai->count() > 0) {
                        $bawahanIds = $user->bawahanPegawai->pluck('id')->toArray();
                        $query->whereIn('pegawai_id', $bawahanIds);
                    } else {
                        $query->whereNull('id'); // Jika tidak ada bawahan, tidak tampilkan apa-apa
                    }
                })
            )
            ->columns([
                TextColumn::make('pegawai.nama')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kantor.nama_kantor')
                    ->label('Kantor')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: $user->role === 'operator' || $user->role === 'pegawai'),
                TextColumn::make('hari')
                    ->label('Hari')
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('nama_kegiatan')
                    ->label('Nama Kegiatan')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('kualitas_hasil')
                    ->label('Nilai Kinerja')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->suffix(' Pts')
                    ->sortable(),
                IconColumn::make('lampiran')
                    ->label('Lampiran')
                    ->icon(fn (?string $state): string => $state ? 'heroicon-o-paper-clip' : 'heroicon-o-x-circle')
                    ->color(fn (?string $state): string => $state ? 'success' : 'danger')
                    ->url(fn (Lapkin $record): ?string => $record->lampiran ? asset('storage/' . $record->lampiran) : null)
                    ->openUrlInNewTab()
                    ->tooltip(fn (?string $state): string => $state ? 'Klik untuk melihat lampiran' : 'Tidak ada lampiran'),
            ])
            ->actions([
                TableAction::make('nilaiKinerja')
                    ->label('Nilai Kinerja')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->visible(function (Lapkin $record) use ($user): bool {
                        if ($user->role === 'superadmin') {
                            return true;
                        }
                        if ($user->role === 'operator' && $record->kantor_id === $user->kantor_id) {
                            return true;
                        }
                        if ($user->role === 'pegawai') {
                            // Cek apakah user yang login memiliki role 'pegawai'
                            // DAN apakah atasan_id dari pegawai yang punya Lapkin
                            // cocok dengan ID user yang sedang login.
                            return $record->pegawai->atasan_id === $user->id; // <-- PERBAIKAN DI BARIS INI
                        }
                        return false;
                    })
                    ->form([
                        Card::make()
                            ->schema([
                                TextInput::make('kualitas_hasil')
                                    ->label('Nilai Kualitas Hasil (0-100)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->required()
                                    ->default(fn (Lapkin $record) => $record->kualitas_hasil ?? 0),
                            ])
                    ])
                    ->action(function (Lapkin $record, array $data): void {
                        $record->kualitas_hasil = $data['kualitas_hasil'];
                        $record->save();

                        Notification::make()
                            ->title('Nilai Kinerja Berhasil Disimpan')
                            ->body('Nilai kinerja untuk Lapkin pegawai ' . $record->pegawai->nama . ' pada tanggal ' . $record->tanggal->format('d M Y') . ' berhasil diperbarui.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                //
            ])
            ->paginated(false);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return in_array($user->role, ['superadmin', 'operator', 'pegawai']);
    }
}