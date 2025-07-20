<?php

namespace App\Filament\Resources\LapkinResource\Pages;

use App\Filament\Resources\LapkinResource;
use Filament\Resources\Pages\Page; // <-- PENTING: Ganti dari ListRecords menjadi Page
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Card;   // <-- TAMBAHKAN BARIS INI
use Filament\Forms\Components\Actions\Action as FormAction; // Alias untuk menghindari konflik nama
use Filament\Tables\Actions\Action as TableAction; // Alias untuk menghindari konflik nama
use Filament\Notifications\Notification;
use App\Models\Lapkin;
use App\Models\Pegawai;
use App\Models\Kantor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ListLapkins extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = LapkinResource::class;
    protected static string $view = 'filament.resources.lapkin-resource.pages.list-lapkins'; // Nama view kustom

    // Properti untuk menyimpan nilai filter
    public ?array $filterData = [];
    public $month;
    public $year;
    public $kantor_id;
    public $pegawai_id;

    // Properti untuk mengontrol tampilan tabel
    public bool $showTable = false;

    // Inisialisasi awal filter
    public function mount(): void
    {
        $this->form->fill([
            'month' => now()->month,
            'year' => now()->year,
            'kantor_id' => auth()->user()->role === 'operator' ? auth()->user()->kantor_id : null,
            'pegawai_id' => null, // Default kosong
        ]);
        $this->month = now()->month;
        $this->year = now()->year;
        $this->kantor_id = auth()->user()->role === 'operator' ? auth()->user()->kantor_id : null;
        $this->pegawai_id = null;
    }

    // Definisi form filter
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Select::make('month')
                            ->label('Bulan')
                            ->options(
                                collect(range(1, 12))->mapWithKeys(fn ($month) => [
                                    $month => Carbon::create()->month($month)->translatedFormat('F')
                                ])->toArray()
                            )
                            ->default(now()->month)
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->month = $state),
                        Select::make('year')
                            ->label('Tahun')
                            ->options(
                                collect(range(Carbon::now()->year - 5, Carbon::now()->year + 1))->mapWithKeys(fn ($year) => [
                                    $year => $year
                                ])->toArray()
                            )
                            ->default(now()->year)
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->year = $state),
                        Select::make('kantor_id')
                            ->label('Pilih Kantor')
                            ->options(
                                fn () => auth()->user()->role === 'superadmin'
                                    ? Kantor::pluck('nama_kantor', 'id')
                                    : Kantor::where('id', auth()->user()->kantor_id)->pluck('nama_kantor', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->hidden(fn (): bool => auth()->user()->role !== 'superadmin')
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->kantor_id = $state;
                                $this->pegawai_id = null; // Reset pegawai_id saat kantor_id berubah
                                $this->form->fill(['pegawai_id' => null]);
                            }),
                        Select::make('pegawai_id')
                            ->label('Pilih Pegawai')
                            ->options(function (Forms\Get $get) {
                                $user = auth()->user();
                                $kantorId = $get('kantor_id');
                                $pegawaiOptions = collect();

                                if ($user->role === 'superadmin') {
                                    if ($kantorId) {
                                        $pegawaiOptions = Pegawai::where('kantor_id', $kantorId)->pluck('nama', 'id');
                                    } else {
                                        $pegawaiOptions = Pegawai::pluck('nama', 'id');
                                    }
                                } elseif ($user->role === 'operator') {
                                    $pegawaiOptions = Pegawai::where('kantor_id', $user->kantor_id)->pluck('nama', 'id');
                                }

                                return $pegawaiOptions;
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->pegawai_id = $state),
                    ])->columns(2),
                Forms\Components\Actions::make([
                    FormAction::make('tampilkan')
                        ->label('Tampilkan Laporan')
                        ->icon('heroicon-o-eye')
                        ->color('primary')
                        ->action(function () {
                            // Validasi form sebelum menampilkan tabel
                            $data = $this->form->getState();
                            if (!$data['month'] || !$data['year'] || !$data['pegawai_id']) {
                                Notification::make()
                                    ->title('Peringatan!')
                                    ->body('Bulan, Tahun, dan Pegawai harus dipilih.')
                                    ->warning()
                                    ->send();
                                $this->showTable = false;
                                return;
                            }
                            $this->showTable = true;
                            // Trigger table refresh
                            $this->getTable()->deselectAllRecords(); // Bersihkan seleksi jika ada
                            $this->getTable()->getLivewire()->call('resetTable'); // Reset pagination/search
                        }),
                    FormAction::make('cetakLaporan')
                        ->label('Cetak Laporan')
                        ->color('success')
                        ->icon('heroicon-o-printer')
                        ->action(function () {
                            $data = $this->form->getState();
                            $month = $data['month'] ?? now()->month;
                            $year = $data['year'] ?? now()->year;
                            $pegawaiId = $data['pegawai_id'] ?? null;
                            $kantorId = $data['kantor_id'] ?? null;

                            if (!$pegawaiId) {
                                Notification::make()
                                    ->title('Peringatan!')
                                    ->body('Silakan pilih pegawai terlebih dahulu untuk mencetak laporan.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $printUrl = route('lapkin.print', [
                                'month' => $month,
                                'year' => $year,
                                'pegawai_id' => $pegawaiId,
                                'kantor_id' => $kantorId
                            ]);

                            return redirect()->to($printUrl);
                        })
                        ->visible(fn () => (bool) $this->pegawai_id), // Tombol cetak hanya aktif jika pegawai_id sudah dipilih
                ])->fullWidth(),
            ]);
    }

    // Definisi tabel (mirip dengan metode table() di Resource)
    public function table(Table $table): Table
    {
        return $table
            ->query(Lapkin::query()
                ->when($this->month, fn (Builder $query) => $query->whereMonth('tanggal', $this->month))
                ->when($this->year, fn (Builder $query) => $query->whereYear('tanggal', $this->year))
                ->when($this->pegawai_id, fn (Builder $query) => $query->where('pegawai_id', $this->pegawai_id))
                ->when($this->kantor_id && auth()->user()->role === 'superadmin', fn (Builder $query) => $query->where('kantor_id', $this->kantor_id))
                ->when(auth()->user()->role === 'operator', fn (Builder $query) => $query->whereHas('pegawai', fn ($q) => $q->where('kantor_id', auth()->user()->kantor_id)))
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
                    ->toggleable(isToggledHiddenByDefault: auth()->user()->role === 'operator'),
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
                TextColumn::make('tempat')
                    ->label('Tempat')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('target')
                    ->label('Target')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('output')
                    ->label('Output')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('lampiran')
                    ->label('Lampiran')
                    ->icon(fn (?string $state): string => $state ? 'heroicon-o-paper-clip' : 'heroicon-o-x-circle')
                    ->color(fn (?string $state): string => $state ? 'success' : 'danger')
                    ->url(fn (Lapkin $record): ?string => $record->lampiran ? asset('storage/' . $record->lampiran) : null)
                    ->openUrlInNewTab()
                    ->tooltip(fn (?string $state): string => $state ? 'Klik untuk melihat lampiran' : 'Tidak ada lampiran'),
                TextColumn::make('kualitas_hasil')
                    ->label('Kualitas Hasil')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                // Filter di sini tidak diperlukan karena sudah ada di form
            ])
            ->actions([
                // Tidak ada aksi edit/delete langsung di tabel laporan
            ])
            ->bulkActions([
                // Tidak ada bulk actions di tabel laporan
            ]);
    }
}