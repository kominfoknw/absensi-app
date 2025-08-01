<x-filament-panels::page>
    <div class="fi-form">
        {{ $this->getForm() }}
    </div>

    @if(!$this->filtersApplied)
        <div class="fi-ta-content-wrapper rounded-xl border border-gray-200 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10 p-6 text-center text-gray-600 dark:text-gray-400">
            <p>Silakan pilih bulan dan tahun, lalu klik "Tampilkan" untuk melihat rekap perhitungan.</p>
        </div>
    @else
        <div class="fi-ta-content-wrapper rounded-xl border border-gray-200 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
            <div class="overflow-x-auto">
                <table class="fi-ta-table w-full divide-y divide-gray-200 dark:divide-white/10">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">Nama Pegawai</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">Hasil Kinerja (60%)</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">Hasil Disiplin (40%)</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">Pengurangan</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">Total Terima</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse($this->recordsData as $pegawai)
                            @php
                                $basicTunjangan = $pegawai->basic_tunjangan ?? 0;
                                $bulanIni = $this->data['bulan'];
                                $tahunIni = $this->data['tahun'];

                                $startDate = \Carbon\Carbon::createFromDate($tahunIni, $bulanIni, 1);
                                $endDate = $startDate->copy()->endOfMonth();
                                $jumlahHariKerjaBulanIni = 0;
                                $workingDates = [];

                                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                                    if ($date->isWeekday()) {
                                        $isHariLibur = \App\Models\HariLibur::whereDate('tanggal', $date->toDateString())->exists();
                                        if (!$isHariLibur) {
                                            $jumlahHariKerjaBulanIni++;
                                            $workingDates[] = $date->toDateString();
                                        }
                                    }
                                }

                                $totalKualitasHasil = $pegawai->lapkins()
                                    ->whereMonth('tanggal', $bulanIni)
                                    ->whereYear('tanggal', $tahunIni)
                                    ->sum('kualitas_hasil');
                                    
                                $bobotKinerja = $totalKualitasHasil/$jumlahHariKerjaBulanIni;

                                $persentaseKinerja = 0;
                                if ($bobotKinerja >= 110) {
                                    $persentaseKinerja = 1.00;
                                } elseif ($bobotKinerja >= 90) {
                                    $persentaseKinerja = 1.00;
                                } elseif ($bobotKinerja >= 70) {
                                    $persentaseKinerja = 0.75;
                                } elseif ($bobotKinerja >= 50) {
                                    $persentaseKinerja = 0.50;
                                } elseif ($bobotKinerja >= 1) {
                                    $persentaseKinerja = 0.25;
                                } else {
                                    $persentaseKinerja = 0.00;
                                }
                                
                                

                                $hasilKinerja = 0;
                                if ($jumlahHariKerjaBulanIni > 0) {
                                    $hasilKinerja = $persentaseKinerja * 0.60 * $basicTunjangan;
                                }

                                $hasilDisiplin = 0.40 * $basicTunjangan;

                                $alphaCount = 0;
                                $telatCount = 0;
                                $pulangCepatCount = 0;

                                $kehadiranPegawaiBulanIni = $pegawai->kehadiran()
                                    ->whereMonth('tanggal', $bulanIni)
                                    ->whereYear('tanggal', $tahunIni)
                                    ->get()
                                    ->keyBy(fn($item) => $item->tanggal->toDateString());

                                foreach ($workingDates as $dateString) {
                                    if (!isset($kehadiranPegawaiBulanIni[$dateString])) {
                                        $alphaCount++;
                                    }
                                }

                                $getMinutes = function($timeString) {
                                    if (empty($timeString)) return 0;
                                    try {
                                        [$h, $m, $s] = array_map('intval', explode(':', $timeString));
                                        return ($h * 60) + $m;
                                    } catch (\Throwable $th) {
                                        return 0;
                                    }
                                };

                                foreach ($kehadiranPegawaiBulanIni as $kehadiran) {
                                    $telatMenit = $getMinutes($kehadiran->telat);
                                    $pulangCepatMenit = $getMinutes($kehadiran->pulang_cepat);

                                    if ($telatMenit >= 91) {
                                        $telatCount++;
                                    }
                                    if ($pulangCepatMenit >= 91) {
                                        $pulangCepatCount++;
                                    }
                                }

                                $persentasePengurangan = ($alphaCount * 0.03) + ($telatCount * 0.015) + ($pulangCepatCount * 0.0155);
                                $penguranganRupiah = $persentasePengurangan * $basicTunjangan;

                                $totalTerima = $hasilKinerja + ($hasilDisiplin - $penguranganRupiah);
                            @endphp
                            <tr class="fi-ta-row @if($loop->even) bg-gray-50/50 dark:bg-white/5 @endif">
                                <td class="fi-ta-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                                    {{ $pegawai->nama }}
                                    <div class="text-xs text-gray-500">{{ $pegawai->nip }}</div>
                                </td>
                                <td class="fi-ta-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">{{ 'Rp ' . number_format($hasilKinerja, 0, ',', '.'), }}</td>
                                <td class="fi-ta-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">{{ 'Rp ' . number_format($hasilDisiplin, 0, ',', '.') }}</td>
                                <td class="fi-ta-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">{{ 'Rp ' . number_format($penguranganRupiah, 0, ',', '.') }}</td>
                                <td class="fi-ta-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">{{ 'Rp ' . number_format($totalTerima, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr class="fi-ta-row">
                                <td colspan="5" class="fi-ta-cell px-3 py-3.5 text-center text-gray-500">
                                    Tidak ada data pegawai ditemukan untuk kriteria yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>