<?php

namespace App\Filament\Resources\LapkinResource\Pages;

use App\Filament\Resources\LapkinResource;
use Filament\Resources\Pages\Page;
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
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Notifications\Notification;
use App\Models\Lapkin;
use App\Models\Pegawai;
use App\Models\Kantor;
use App\Models\HariLibur;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RekapLapkin extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable {
        InteractsWithTable::resetPage as tableResetPage;
    }

    protected static string $resource = LapkinResource::class;
    protected static string $view = 'filament.resources.lapkin-resource.pages.rekap-lapkin';

    public ?int $bulan = null;
    public ?int $tahun = null;
    public ?int $kantor_id = null;
    public ?int $pegawai_id = null;

    public bool $showTable = false;
    public bool $showPrintButton = false;

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

        // Inisialisasi kantor_id untuk operator
        if ($user->role === 'operator') {
            $this->kantor_id = $user->kantor_id;
            $this->form->fill(['kantor_id' => $user->kantor_id]);
        }
        // Inisialisasi awal untuk superadmin, pegawai_id tetap null sampai kantor dipilih
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
                    ->placeholder('Pilih Kantor') // Placeholder untuk Superadmin
                    ->visible(fn () => $user->role === 'superadmin')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->kantor_id = $state;
                        $this->pegawai_id = null; // Reset pegawai_id saat kantor berubah
                        $this->form->fill(['pegawai_id' => null]);
                    }),
            ]),

            Select::make('pegawai_id')
                ->label('Pilih Pegawai')
                ->options(function (\Filament\Forms\Get $get) use ($user) {
                    $kantorId = $get('kantor_id');
                    $pegawaiOptions = collect();

                    // Logika untuk Superadmin: Pegawai hanya muncul setelah kantor dipilih
                    if ($user->role === 'superadmin') {
                        if (!empty($kantorId)) { // Pastikan kantor_id tidak kosong
                            $pegawaiOptions = Pegawai::where('kantor_id', $kantorId)->pluck('nama', 'id');
                        }
                        // Jika kantorId kosong, pegawaiOptions tetap kosong, jadi tidak ada yang muncul
                    }
                    // Logika untuk Operator: Pegawai langsung muncul berdasarkan kantornya
                    elseif ($user->role === 'operator') {
                        $pegawaiOptions = Pegawai::where('kantor_id', $user->kantor_id)->pluck('nama', 'id');
                    }

                    return $pegawaiOptions;
                })
                ->searchable()
                ->preload()
                // Required hanya jika daftar pegawai tidak kosong (yaitu setelah kantor dipilih untuk superadmin)
                ->required(fn (\Filament\Forms\Get $get) => 
                    ($user->role === 'superadmin' && !empty($get('kantor_id'))) || // Superadmin & kantor sudah dipilih
                    $user->role === 'operator' // Operator selalu required
                )
                ->placeholder(fn () => 
                    $user->role === 'superadmin' && empty($this->kantor_id) ? 'Pilih kantor terlebih dahulu' : 'Pilih Pegawai'
                )
                // Disable jika superadmin belum memilih kantor
                ->disabled(fn () => $user->role === 'superadmin' && empty($this->kantor_id))
                ->live()
                ->afterStateUpdated(fn ($state) => $this->pegawai_id = $state),

            Actions::make([
                FormAction::make('tampilkan')
                    ->label('Tampilkan Laporan')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->action('loadData'),
                FormAction::make('cetakLaporan')
                    ->label('Cetak Laporan')
                    ->color('success')
                    ->icon('heroicon-o-printer')
                    ->action(function () {
                        if (!$this->bulan || !$this->tahun || !$this->pegawai_id) {
                            Notification::make()
                                ->title('Peringatan!')
                                ->body('Bulan, Tahun, dan Pegawai harus dipilih untuk mencetak laporan.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $printUrl = route('lapkin.print', [
                            'month' => $this->bulan,
                            'year' => $this->tahun,
                            'pegawai_id' => $this->pegawai_id,
                            'kantor_id' => $this->kantor_id,
                        ]);

                        return redirect()->to($printUrl);
                    })
                    ->visible(fn () => (bool) $this->pegawai_id),
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
            $this->showPrintButton = false;
            return;
        }

        $this->showTable = true;
        $this->showPrintButton = true;
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
                    ->toggleable(isToggledHiddenByDefault: $user->role === 'operator'),
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
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ])
            ->paginated(false);
    }
}