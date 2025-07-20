<?php

namespace App\Filament\Resources\RekapPerhitunganResource\Pages;

use App\Filament\Resources\RekapPerhitunganResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use App\Models\Pegawai;
use App\Models\Kantor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification; // Import Notification untuk pesan error

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;

class ListRekapPerhitungans extends ListRecords implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = RekapPerhitunganResource::class;

    protected static ?string $title = 'Rekap Perhitungan Pegawai';

    // Properti Livewire untuk menyimpan seluruh state form
    public array $data = [];

    // Mengontrol apakah tabel rekap sudah harus ditampilkan atau belum
    public bool $filtersApplied = false;

    // Mengontrol visibilitas tombol "Cetak PDF"
    public bool $showExportButton = false;

    // Implementasi method getForm() yang diperlukan oleh HasForms
    public function getForm(string $name = 'form'): ?Form
    {
        return $this->makeForm()
            ->schema($this->getFormSchema())
            ->statePath('data'); // State form akan terikat ke properti $this->data
    }

    public function mount(): void
    {
        parent::mount();

        $user = Auth::user();
        $isSuperAdminOrAdmin = ($user->role === 'superadmin' || $user->role === 'admin');

        $defaultBulan = Carbon::now()->month;
        $defaultTahun = Carbon::now()->year;
        $defaultKantorId = null;

        if (!$isSuperAdminOrAdmin) {
            // Jika operator, filter sudah dianggap diterapkan dari awal
            $defaultKantorId = $user->kantor_id ?? null;
            $this->filtersApplied = true;
            $this->showExportButton = true; // Tombol cetak muncul langsung untuk operator
        } else {
            // Jika superadmin/admin, filter belum diterapkan di awal
            $this->filtersApplied = false;
            $this->showExportButton = false; // Tombol cetak disembunyikan di awal
        }

        // Inisialisasi properti $data dengan nilai default
        $this->data = [
            'bulan' => $defaultBulan,
            'tahun' => $defaultTahun,
            'kantor_id' => $defaultKantorId,
        ];

        // Panggil loadData HANYA jika filtersApplied true (yaitu untuk operator)
        if ($this->filtersApplied) {
            $this->loadData();
        }
    }

    protected function getFormSchema(): array
    {
        $user = Auth::user();
        // Superadmin atau Admin bisa melihat filter kantor
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
                                ->hidden(!$canSeeKantorFilter) // Sembunyikan jika bukan superadmin/admin
                                ->nullable()
                                ->placeholder('Semua Kantor'),
                        ]),
                    Actions::make([
                        Action::make('tampilkan')
                            ->label('Tampilkan')
                            ->submit('loadData') // Menggunakan submit untuk validasi form Livewire
                            ->button()
                            ->color('primary'),
                        Action::make('cetak_pdf')
                            ->label('Cetak PDF')
                            ->action('exportPdf') // Panggil metode Livewire exportPdf
                            ->button()
                            ->color('info')
                            ->visible($this->showExportButton), // Hanya terlihat jika $showExportButton true
                    ])->alignEnd(),
                ])->columns(1),
        ];
    }

    public function loadData(): void
    {
        // Validasi form sebelum menampilkan data
        try {
            $this->form->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Error Validasi')
                ->body('Pastikan Bulan dan Tahun sudah dipilih.')
                ->danger()
                ->send();
            return; // Hentikan eksekusi jika validasi gagal
        }

        $this->filtersApplied = true; // Set true agar tabel ditampilkan
        $this->showExportButton = true; // Set true agar tombol cetak muncul

        // Memicu refresh data pada tabel (jika menggunakan custom Livewire render)
        // Jika Anda hanya mengandalkan getTableContent, ini mungkin tidak diperlukan
        $this->dispatch('refreshTable');
    }

    // Metode baru untuk menangani ekspor PDF
    public function exportPdf(): \Illuminate\Http\RedirectResponse
    {
        // Validasi form lagi sebelum mengarahkan ke PDF
        try {
            $this->form->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
             Notification::make()
                ->title('Error Validasi')
                ->body('Pilih Bulan dan Tahun sebelum mencetak PDF.')
                ->danger()
                ->send();
            return redirect()->back(); // Tetap di halaman yang sama
        }

        // Siapkan parameter query untuk rute PDF
        $queryParams = [
            'bulan' => $this->data['bulan'],
            'tahun' => $this->data['tahun'],
        ];

        if (isset($this->data['kantor_id']) && $this->data['kantor_id']) {
            $queryParams['kantor_id'] = $this->data['kantor_id'];
        }

        // Redirect ke rute bernama untuk generasi PDF
        return redirect()->route('rekap-perhitungan.export-pdf', $queryParams);
    }


    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Jika filter belum diterapkan, kembalikan query yang tidak akan menghasilkan hasil
        if (!$this->filtersApplied) {
            return Pegawai::query()->whereRaw('1 = 0');
        }

        $user = Auth::user();
        $isSuperAdminOrAdmin = ($user->role === 'superadmin' || $user->role === 'admin');

        $query = Pegawai::query();

        if (!$isSuperAdminOrAdmin) {
            $operatorKantorId = $user->kantor_id ?? null;
            if ($operatorKantorId) {
                $query->where('kantor_id', $operatorKantorId);
            } else {
                return $query->whereRaw('1 = 0'); // Operator tanpa kantor_id tidak melihat data
            }
        } elseif (isset($this->data['kantor_id']) && $this->data['kantor_id']) {
            $query->where('kantor_id', $this->data['kantor_id']);
        }

        return $query;
    }

    protected function getTableContent(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.pages.rekap-perhitungan', [
            'records' => $this->getRecords()->get(),
            'selectedMonth' => $this->data['bulan'],
            'selectedYear' => $this->data['tahun'],
            'form' => $this->getForm(),
            'filtersApplied' => $this->filtersApplied,
            // $showExportButton tidak perlu dikirim karena visibilitasnya diatur di getFormSchema
        ]);
    }

    // Pastikan pagination tidak aktif untuk rekap ini
    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    // Kolom tabel akan diatur di Blade secara manual
    protected function getTableColumns(): array
    {
        return [];
    }

    // Aksi tabel kosong
    protected function getTableActions(): array
    {
        return [];
    }

    // Filter tabel kosong (karena kita menggunakan form terpisah)
    protected function getTableFilters(): array
    {
        return [];
    }

    // Aksi massal tabel kosong
    protected function getTableBulkActions(): array
    {
        return [];
    }
}