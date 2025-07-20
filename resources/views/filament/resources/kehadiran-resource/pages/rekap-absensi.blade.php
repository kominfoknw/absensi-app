<x-filament-panels::page>
    <form wire:submit.prevent="loadData">
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-8">
            Tampilkan Rekap
        </x-filament::button>
    </form>

    {{-- Tombol Cetak Rekap hanya muncul jika data sudah dimuat --}}
    @if ($showExportButton)
        <div class="mt-6">
            <x-filament::button
                tag="a"
                href="{{ route('rekap.export.pdf', [
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'kantor_id' => $kantor_id,
                ]) }}"
                target="_blank"
                icon="heroicon-o-arrow-down-tray"
                color="success"
            >
                Cetak Rekap
            </x-filament::button>
        </div>
    @endif

    {{-- Tampilkan tabel rekap hanya jika filter sudah diterapkan DAN ada data rekap --}}
    @if ($this->filtersApplied && $this->rekaps->isNotEmpty())
        @php
            $daysInMonth = \Carbon\Carbon::create($tahun, $bulan, 1)->daysInMonth;
            $liburDates = \App\Models\HariLibur::whereMonth('tanggal', $bulan)
                ->whereYear('tanggal', $tahun)
                ->pluck('tanggal')
                ->map(fn($date) => \Carbon\Carbon::parse($date)->day)
                ->toArray();
        @endphp

        <div class="overflow-x-auto mt-6"> {{-- Gunakan overflow-x-auto untuk tabel lebar --}}
            <table class="table-auto border-collapse border w-full text-xs">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                        <th class="border p-2">Nama Pegawai</th>
                        @for ($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $tanggalHeader = \Carbon\Carbon::create($tahun, $bulan, $day);
                                $isWeekendHeader = in_array($tanggalHeader->dayOfWeek, [\Carbon\Carbon::SATURDAY, \Carbon\Carbon::SUNDAY]);
                                $isHolidayHeader = in_array($day, $liburDates);
                            @endphp
                            <th class="border p-1 text-center {{ ($isWeekendHeader || $isHolidayHeader) ? 'text-red-500 font-bold bg-red-100 dark:bg-red-900' : '' }}">
                                {{ $day }}
                            </th>
                        @endfor
                        <th class="border p-2">Hadir</th>
                        <th class="border p-2">Izin</th>
                        <th class="border p-2">Alpa</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- THIS IS THE FOREACH LOOP THAT WAS LIKELY CAUSING THE ISSUE IF NOT PROPERLY CLOSED --}}
                    @foreach ($this->rekaps as $rekap)
                        <tr>
                            {{-- Gunakan nama_lengkap dari pegawai, karena model User tidak punya 'nama' langsung --}}
                            <td class="border p-2 whitespace-nowrap">{{ $rekap['pegawai']->nama_lengkap ?? $rekap['pegawai']->nama }}</td>
                            @for ($day = 1; $day <= $daysInMonth; $day++)
                                @php
                                    $record = $rekap['records']->get($day); // Dapatkan record kehadiran untuk hari ini
                                    $tanggalCell = \Carbon\Carbon::create($tahun, $bulan, $day);
                                    $isWeekendCell = in_array($tanggalCell->dayOfWeek, [\Carbon\Carbon::SATURDAY, \Carbon\Carbon::SUNDAY]);
                                    $isHolidayCell = in_array($day, $liburDates);

                                    $cellClasses = '';
                                    if ($isWeekendCell || $isHolidayCell) {
                                        $cellClasses = 'bg-red-50 dark:bg-red-900'; // Warna latar belakang untuk libur
                                    }
                                @endphp
                                <td class="border p-1 text-center {{ $cellClasses }}">
                                    @if ($isWeekendCell || $isHolidayCell)
                                        <span class="text-red-400 font-semibold italic">L</span> {{-- Libur --}}
                                    @elseif ($record)
                                        @if ($record->status == 'hadir' || $record->status == 'tugas_luar')
                                            <div class="text-green-600 font-medium">{{ \Carbon\Carbon::parse($record->jam_masuk)->format('H:i') }}</div>
                                            <div class="text-blue-600">{{ $record->jam_pulang ? \Carbon\Carbon::parse($record->jam_pulang)->format('H:i') : '-' }}</div>
                                        @elseif ($record->status == 'izin' || $record->status == 'sakit') {{-- Tambahkan 'sakit' jika ada --}}
                                            <span class="text-blue-500 font-bold">I</span> {{-- Izin/Sakit --}}
                                        @else
                                            <span class="text-red-500 font-bold">A</span> {{-- Alpa/Status lain yang tidak dikenali --}}
                                        @endif
                                    @else
                                        {{-- Jika tidak ada record dan bukan libur/weekend, itu Alpa --}}
                                        <span class="text-red-500 font-bold">A</span>
                                    @endif
                                </td>
                            @endfor {{-- THIS IS THE CLOSING FOR THE INNER @for loop --}}
                            <td class="border p-2 text-center text-green-600 font-bold">{{ $rekap['hadir'] }}</td>
                            <td class="border p-2 text-center text-blue-600 font-bold">{{ $rekap['izin'] }}</td>
                            <td class="border p-2 text-center text-red-600 font-bold">{{ $rekap['alpa'] }}</td>
                        </tr>
                    @endforeach {{-- THIS IS THE CLOSING FOR THE OUTER @foreach loop --}}
                </tbody>
            </table>

            <div class="mt-4">
                {{ $this->rekaps->links() }}
            </div>
        </div>
    @else
        {{-- Pesan yang muncul jika belum ada data atau data kosong --}}
        <div class="mt-8 p-4 bg-gray-100 dark:bg-gray-700 rounded-lg text-center text-gray-600 dark:text-gray-300">
            @if ($this->filtersApplied && $this->rekaps->isEmpty())
                Tidak ada data rekap absensi yang ditemukan untuk filter yang dipilih.
            @else
                Pilih bulan dan tahun, lalu klik "Tampilkan Rekap" untuk melihat absensi.
            @endif
        </div>
    @endif {{-- THIS IS THE CLOSING FOR THE MAIN @if ($this->filtersApplied && $this->rekaps->isNotEmpty()) --}}
</x-filament-panels::page>