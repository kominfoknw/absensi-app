<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use App\Models\Pegawai;
use App\Models\Kantor;
use App\Models\HariLibur;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class RekapPerhitunganPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static string $view = 'filament.pages.rekap-perhitungan-page';
    protected static ?string $navigationLabel = 'Perhitungan TPP';
    protected static ?string $title = 'Rekap Perhitungan Pegawai';
    protected static ?int $navigationSort = 3;

    public array $data = [];
    public bool $filtersApplied = false;
    public bool $showExportButton = false;
    public $recordsData = [];

    public function mount(): void
    {
        $user = Auth::user();
        $isSuperAdminOrAdmin = ($user->role === 'superadmin' || $user->role === 'admin');

        $defaultBulan = Carbon::now()->month;
        $defaultTahun = Carbon::now()->year;
        $defaultKantorId = null;

        if (!$isSuperAdminOrAdmin) {
            $defaultKantorId = $user->kantor_id ?? null;
            $this->filtersApplied = true;
            $this->showExportButton = true;
        } else {
            $this->filtersApplied = false;
            $this->showExportButton = false;
        }

        $this->data = [
            'bulan' => $defaultBulan,
            'tahun' => $defaultTahun,
            'kantor_id' => $defaultKantorId,
        ];

        if ($this->filtersApplied) {
            $this->fetchRecords();
        }
    }

    public function getForm(string $name = 'form'): ?Form
    {
        return $this->makeForm()
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getFormSchema(): array
    {
        $user = Auth::user();
        $canSeeKantorFilter = ($user->role === 'superadmin' || $user->role === 'admin');

        return [
            Section::make('Filter Rekap Perhitungan')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('bulan')
                                ->label('Bulan')
                                ->options(collect(range(1, 12))->mapWithKeys(function ($monthNum) {
                                    return [$monthNum => Carbon::create(null, $monthNum, 1)->locale('id')->monthName];
                                }))
                                ->default(Carbon::now()->month)
                                ->required(),
                            Select::make('tahun')
                                ->label('Tahun')
                                ->options(
                                    collect(range(Carbon::now()->year - 5, Carbon::now()->year + 1))
                                        ->mapWithKeys(fn ($year) => [$year => $year])
                                )
                                ->default(Carbon::now()->year)
                                ->required(),
                            Select::make('kantor_id')
                                ->label('Kantor')
                                ->options(
                                    $canSeeKantorFilter
                                        ? Kantor::pluck('nama_kantor', 'id')
                                        : ($user->kantor_id ? Kantor::where('id', $user->kantor_id)->pluck('nama_kantor', 'id') : [])
                                )
                                ->default(function() use ($canSeeKantorFilter, $user) {
                                    if ($canSeeKantorFilter) {
                                        return null;
                                    }
                                    return $user->kantor_id ?? null;
                                })
                                ->hidden(!$canSeeKantorFilter)
                                ->nullable()
                                ->placeholder('Semua Kantor'),
                        ]),
                    Actions::make([
                        Action::make('tampilkan')
                            ->label('Tampilkan')
                            ->action('loadData') // ✅ FIX disini, pakai action()
                            ->button()
                            ->color('primary'),
                        Action::make('cetak_pdf')
                            ->label('Cetak PDF')
                            ->action('exportPdf')
                            ->button()
                            ->color('info')
                            ->visible($this->showExportButton),
                    ])->alignEnd(),
                ])->columns(1),
        ];
    }

    public function loadData(): void
    {
        // Debugging: biar kelihatan kalau function jalan
        Notification::make()
            ->title('Berhasil!')
            ->body('loadData() berhasil dijalankan.')
            ->success()
            ->send();

        try {
            $this->form->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Error Validasi')
                ->body('Pastikan Bulan dan Tahun sudah dipilih.')
                ->danger()
                ->send();
            return;
        }

        $this->filtersApplied = true;
        $this->showExportButton = true;

        $this->fetchRecords();
    }

    public function fetchRecords(): void
    {
        $user = Auth::user();
        $isSuperAdminOrAdmin = ($user->role === 'superadmin' || $user->role === 'admin');

        $query = Pegawai::query();

        if (!$isSuperAdminOrAdmin) {
            $operatorKantorId = $user->kantor_id ?? null;
            if ($operatorKantorId) {
                $query->where('kantor_id', $operatorKantorId);
            } else {
                $this->recordsData = collect();
                return;
            }
        } elseif (isset($this->data['kantor_id']) && $this->data['kantor_id']) {
            $query->where('kantor_id', $this->data['kantor_id']);
        }

        $this->recordsData = $query->get();
    }

    public function exportPdf(): \Illuminate\Http\RedirectResponse
    {
        try {
            $this->form->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Error Validasi')
                ->body('Pilih Bulan dan Tahun sebelum mencetak PDF.')
                ->danger()
                ->send();
            return redirect()->back();
        }

        $queryParams = [
            'bulan' => $this->data['bulan'],
            'tahun' => $this->data['tahun'],
        ];

        if (isset($this->data['kantor_id']) && $this->data['kantor_id']) {
            $queryParams['kantor_id'] = $this->data['kantor_id'];
        }

        return redirect()->route('rekap-perhitungan.export-pdf', $queryParams);
    }

    public static function canView(): bool
    {
        $user = auth()->user();
        return in_array($user->role, ['superadmin', 'admin', 'operator']);
    }
}
