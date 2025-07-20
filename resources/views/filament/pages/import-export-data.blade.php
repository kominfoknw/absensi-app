<x-filament-panels::page>
    <div class="fi-page-content-wrapper flex-auto p-4 md:p-6 lg:p-8">
        <div class="fi-page-content mx-auto w-full max-w-7xl space-y-8">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Import Data Lapkin --}}
                <x-filament::section>
                    <x-slot name="heading">Import Data Lapkin</x-slot>
                    <form wire:submit.prevent="importLapkin" class="space-y-4">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">File Excel Lapkin</span>
                            <input
                                type="file"
                                wire:model="importLapkinData.lapkin_file"
                                class="filament-input mt-1 block w-full"
                                accept=".xlsx,.xls"
                            >
                        </label>
                        @error('importLapkinData.lapkin_file')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                        <x-filament::button type="submit">
                            Import Lapkin
                        </x-filament::button>
                    </form>
                </x-filament::section>

                {{-- Import Data Kehadiran --}}
                <x-filament::section>
                    <x-slot name="heading">Import Data Kehadiran</x-slot>
                    <form wire:submit.prevent="importAttendance" class="space-y-4">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">File Excel Kehadiran</span>
                            <input
                                type="file"
                                wire:model="importAttendanceData.attendance_file"
                                class="filament-input mt-1 block w-full"
                                accept=".xlsx,.xls"
                            >
                        </label>
                        @error('importAttendanceData.attendance_file')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                        <x-filament::button type="submit">
                            Import Kehadiran
                        </x-filament::button>
                    </form>
                </x-filament::section>
            </div>

            {{-- Download Template Excel --}}
            <x-filament::section>
                <x-slot name="heading">Download Template Excel</x-slot>

                {{-- Form Download Template Lapkin --}}
                <form wire:submit.prevent="downloadLapkinTemplate" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Kantor</label>
                            <select
                                wire:model="downloadTemplateData.kantor_id"
                                class="filament-input mt-1 block w-full"
                            >
                                <option value="">-- Pilih Kantor --</option>
                                @foreach($kantors as $id => $nama)
                                    <option value="{{ $id }}">{{ $nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pegawai</label>
                            <select
                                wire:model="downloadTemplateData.pegawai_id"
                                class="filament-input mt-1 block w-full"
                            >
                                <option value="">-- Pilih Pegawai --</option>
                                @foreach($pegawais as $id => $nama)
                                    <option value="{{ $id }}">{{ $nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tanggal Mulai</label>
                            <input
                                type="date"
                                wire:model="downloadTemplateData.tanggal_mulai"
                                class="filament-input mt-1 block w-full"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tanggal Selesai</label>
                            <input
                                type="date"
                                wire:model="downloadTemplateData.tanggal_selesai"
                                class="filament-input mt-1 block w-full"
                            >
                        </div>
                    </div>
                    <x-filament::button type="submit" color="success">
                        Download Template Lapkin
                    </x-filament::button>
                </form>

                {{-- Form Download Template Kehadiran --}}
                <form wire:submit.prevent="downloadAttendanceTemplate" class="space-y-4 mt-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Kantor</label>
                            <select
                                wire:model="downloadTemplateData.kantor_id"
                                class="filament-input mt-1 block w-full"
                            >
                                <option value="">-- Pilih Kantor --</option>
                                @foreach($kantors as $id => $nama)
                                    <option value="{{ $id }}">{{ $nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pegawai</label>
                            <select
                                wire:model="downloadTemplateData.pegawai_id"
                                class="filament-input mt-1 block w-full"
                            >
                                <option value="">-- Pilih Pegawai --</option>
                                @foreach($pegawais as $id => $nama)
                                    <option value="{{ $id }}">{{ $nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tanggal Mulai</label>
                            <input
                                type="date"
                                wire:model="downloadTemplateData.tanggal_mulai"
                                class="filament-input mt-1 block w-full"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tanggal Selesai</label>
                            <input
                                type="date"
                                wire:model="downloadTemplateData.tanggal_selesai"
                                class="filament-input mt-1 block w-full"
                            >
                        </div>
                    </div>
                    <x-filament::button type="submit" color="success">
                        Download Template Kehadiran
                    </x-filament::button>
                </form>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
