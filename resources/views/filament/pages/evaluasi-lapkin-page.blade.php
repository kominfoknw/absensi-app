<x-filament::page>
    <form wire:submit.prevent="loadData">
        {{ $this->form }}
    </form>

    {{-- Tampilan Tabel Laporan --}}
    @if ($showTable)
        <div class="mt-8">
            {{ $this->table }}
        </div>
    @endif
</x-filament::page>