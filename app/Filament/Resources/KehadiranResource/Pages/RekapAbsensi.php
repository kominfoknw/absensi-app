<?php

namespace App\Filament\Resources\KehadiranResource\Pages;

use Filament\Resources\Pages\Page;
use Filament\Forms;
use Illuminate\Pagination\LengthAwarePaginator;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use App\Models\Pegawai;
use App\Models\Kehadiran;
use App\Models\Kantor;
use App\Models\HariLibur;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Livewire\WithPagination;

class RekapAbsensi extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use WithPagination;

    protected static string $resource = \App\Filament\Resources\KehadiranResource::class;
    protected static string $view = 'filament.resources.kehadiran-resource.pages.rekap-absensi';

    public ?int $bulan = null;
    public ?int $tahun = null;
    public ?int $kantor_id = null;

    public bool $showExportButton = false;
    public bool $filtersApplied = false; // <<< Default FALSE

    public function mount(): void
    {
        $user = Auth::user();

        Log::info('[RekapAbsensi Mount] User Role: ' . $user->role . ', User ID: ' . $user->id);

        $defaultBulan = now()->month;
        $defaultTahun = now()->year;
        $defaultKantorId = null; // Default untuk superadmin

        if ($user->role !== 'superadmin') {
            // Untuk operator, ambil kantor_id langsung dari model User
            $kantorIdOperator = $user->kantor_id ?? null;

            Log::info('[RekapAbsensi Mount] Operator - Kantor ID dari User (langsung): ' . ($kantorIdOperator ?? 'NULL'));

            $this->kantor_id = $kantorIdOperator;

            // Untuk operator, kita tetap ingin data muncul otomatis
            $this->filtersApplied = true; // <<< Operator langsung terapkan filter
            $this->showExportButton = true;

        } else {
            // Untuk superadmin, kita ingin mereka memilih filter dulu
            $this->filtersApplied = false; // <<< Superadmin TIDAK langsung terapkan filter
            $this->showExportButton = false;
        }

        $this->form->fill([
            'bulan' => $defaultBulan,
            'tahun' => $defaultTahun,
            'kantor_id' => $defaultKantorId, // Akan null untuk superadmin, atau ID kantor operator
        ]);

        Log::info('[RekapAbsensi Mount] Form Filled - Bulan: ' . $this->bulan . ', Tahun: ' . $this->tahun . ', Kantor ID (Property): ' . ($this->kantor_id ?? 'NULL'));

        // Panggil loadData HANYA jika filtersApplied true (yaitu untuk operator)
        if ($this->filtersApplied) {
            $this->loadData();
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Grid::make(3)->schema([
                Select::make('bulan')
                    ->label('Bulan')
                    ->options(array_combine(
                        range(1, 12),
                        array_map(fn($m) => Carbon::create(null, $m)->translatedFormat('F'), range(1, 12))
                    ))
                    ->required(),

                Select::make('tahun')
                    ->label('Tahun')
                    ->options(collect(range(now()->year - 5, now()->year + 1))->mapWithKeys(fn ($y) => [$y => $y]))
                    ->required(),

                Select::make('kantor_id')
                    ->label('Kantor')
                    ->options(Kantor::pluck('nama_kantor', 'id'))
                    ->visible(fn () => auth()->user()->role === 'superadmin')
                    ->nullable()
                    ->placeholder('Semua Kantor'),
            ]),
        ];
    }

    public function loadData(): void
    {
        $this->validate();

        Log::info('[RekapAbsensi LoadData] Filters Applied set to true. Current Kantor ID: ' . ($this->kantor_id ?? 'NULL'));
        $this->filtersApplied = true;
        $this->showExportButton = true;
        $this->resetPage();
    }

    public function getRekapsProperty(): LengthAwarePaginator
    {
        // Ini adalah kunci: Jika filtersApplied FALSE, kembalikan paginator kosong.
        // Ini akan terjadi untuk superadmin saat pertama kali mount.
        if (!$this->filtersApplied) {
            Log::info('[RekapAbsensi getRekapsProperty] Filters not applied. Returning empty paginator.');
            return new LengthAwarePaginator([], 0, 10);
        }

        $user = Auth::user();

        Log::info('[RekapAbsensi getRekapsProperty] User Role: ' . $user->role);
        Log::info('[RekapAbsensi getRekapsProperty] Component Kantor ID: ' . ($this->kantor_id ?? 'NULL'));


        $pegawaiQuery = Pegawai::query();

        if ($user->role === 'superadmin') {
            if ($this->kantor_id) {
                $pegawaiQuery->where('kantor_id', $this->kantor_id);
                Log::info('[RekapAbsensi getRekapsProperty] Superadmin filtering by Kantor ID: ' . $this->kantor_id);
            } else {
                Log::info('[RekapAbsensi getRekapsProperty] Superadmin viewing all offices (Kantor ID is NULL).');
            }
        } else { // Ini adalah operator
            $kantorIdOperator = $user->kantor_id ?? null;

            Log::info('[RekapAbsensi getRekapsProperty] Operator - Calculated Kantor ID from User: ' . ($kantorIdOperator ?? 'NULL'));

            if ($kantorIdOperator) {
                $pegawaiQuery->where('kantor_id', $kantorIdOperator);
                Log::info('[RekapAbsensi getRekapsProperty] Operator filtering by Kantor ID: ' . $kantorIdOperator);
            } else {
                Log::warning('[RekapAbsensi getRekapsProperty] Operator has no valid Kantor ID in User model. Returning empty data.');
                return new LengthAwarePaginator([], 0, 10);
            }
        }

        $pegawaiPaginated = $pegawaiQuery->paginate(10);
        Log::info('[RekapAbsensi getRekapsProperty] Pegawai Count after filter: ' . $pegawaiPaginated->total());

        $tanggalAwal = Carbon::create($this->tahun, $this->bulan, 1);
        $daysInMonth = $tanggalAwal->daysInMonth;

        $tanggalLibur = HariLibur::whereMonth('tanggal', $this->bulan)
            ->whereYear('tanggal', $this->tahun)
            ->pluck('tanggal')
            ->map(fn($tgl) => Carbon::parse($tgl)->day)
            ->toArray();

        $rekaps = $pegawaiPaginated->getCollection()->map(function ($pegawai) use ($tanggalLibur, $daysInMonth) {
            $dataPerTanggal = collect();
            $izin = 0;
            $hadir = 0;
            $alpa = 0;
            $totalHariKerja = 0;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $tanggal = Carbon::create($this->tahun, $this->bulan, $day);
                $tanggalStr = $tanggal->toDateString();
                $isWeekend = in_array($tanggal->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
                $isLibur = in_array($day, $tanggalLibur);

                if (!$isWeekend && !$isLibur) {
                    $totalHariKerja++;
                }

                $kehadiran = Kehadiran::where('pegawai_id', $pegawai->id)
                    ->whereDate('tanggal', $tanggalStr)
                    ->first();

                if ($kehadiran) {
                    $dataPerTanggal->put($day, $kehadiran);
                    match ($kehadiran->status) {
                        'izin' => $izin++,
                        'hadir', 'tugas_luar' => $hadir++,
                        default => $alpa++,
                    };
                } else {
                    if (!$isWeekend && !$isLibur) {
                        $alpa++;
                    }
                    $dataPerTanggal->put($day, null);
                }
            }

            return [
                'pegawai' => $pegawai,
                'records' => $dataPerTanggal,
                'izin' => $izin,
                'hadir' => $hadir,
                'alpa' => $alpa,
                'total_hari_kerja' => $totalHariKerja,
            ];
        });

        return new LengthAwarePaginator(
            $rekaps,
            $pegawaiPaginated->total(),
            $pegawaiPaginated->perPage(),
            $pegawaiPaginated->currentPage(),
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}