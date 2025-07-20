<x-filament::page>
    <form wire:submit.prevent="loadData">
        {{ $this->form }} {{-- Ini akan merender semua input dan kedua tombol (Tampilkan & Cetak) --}}
    </form>

    {{-- Hapus bagian ini karena tombol "Cetak Laporan" sudah ada di dalam form PHP --}}
    {{-- @if ($showPrintButton)
        <div class="mt-6">
            <x-filament::button
                tag="a"
                href="{{ route('lapkin.print', [
                    'month' => $bulan,
                    'year' => $tahun,
                    'pegawai_id' => $pegawai_id,
                    'kantor_id' => $kantor_id,
                ]) }}"
                target="_blank"
                icon="heroicon-o-printer"
                color="success"
            >
                Cetak Laporan
            </x-filament::button>
        </div>
    @endif --}}

    {{-- Tampilan Tabel Laporan --}}
    @if ($showTable)
        <div class="mt-8">
            {{ $this->table }} {{-- Ini akan merender tabel dari metode table() di kelas RekapLapkin --}}
        </div>
    @endif
</x-filament::page>