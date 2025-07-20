<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: sans-serif;
            font-size: 10pt;
        }
        h1, h2 {
            text-align: center;
            margin: 0;
        }
        h1 { font-size: 14pt; font-weight: bold; }
        h2 { font-size: 12pt; margin-bottom: 20px; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        th, td {
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
        }
        footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-style: italic;
            font-size: 9pt;
        }
        .merah {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <h1>REKAPITULASI KEHADIRAN BULAN {{ $namaBulan }} TAHUN {{ $namaTahun }}</h1>
    <h2>{{ $namaKantor }}</h2>

    <table>
        <thead>
            <tr>
                <th>Nama</th>
                @php
                    $days = range(1, $tanggalAkhir->day);
                @endphp
                @foreach ($days as $day)
                    @php
                        $tanggal = \Carbon\Carbon::create($tahun, $bulan, $day);
                        $isWeekend = in_array($tanggal->dayOfWeek, [\Carbon\Carbon::SATURDAY, \Carbon\Carbon::SUNDAY]);
                    @endphp
                    <th style="{{ $isWeekend ? 'color:red;' : '' }}">{{ $day }}</th>
                @endforeach
                <th>Hadir</th>
                <th>Izin</th>
                <th>Alpa</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rekaps as $rekap)
                <tr>
                    <td>{{ $rekap['pegawai']->nama }}</td>
                    @foreach ($days as $day)
                        @php
                            $tanggal = \Carbon\Carbon::create($tahun, $bulan, $day);
                            $isWeekend = in_array($tanggal->dayOfWeek, [\Carbon\Carbon::SATURDAY, \Carbon\Carbon::SUNDAY]);
                            $record = $rekap['records']->get($day);
                        @endphp
                        <td>
                            @if ($record?->status === 'hadir')
                                <div>{{ \Carbon\Carbon::parse($record->jam_masuk)->format('H:i:s') ?? '-' }}</div>
                                <div>{{ \Carbon\Carbon::parse($record->jam_pulang)->format('H:i:s') ?? '-' }}</div>
                            @elseif ($record?->status === 'izin')
                                <span>I</span>
                            @elseif ($record?->status === 'alpa')
                                <span style="color:red;">A</span>
                            @elseif ($record?->status === 'libur')
                                <span class="merah">L</span>
                            @elseif ($isWeekend)
                                <span class="merah">L</span>
                            @else
                                <span style="color:red;">-</span>
                            @endif
                        </td>
                    @endforeach
                    <td>{{ $rekap['hadir'] }}</td>
                    <td>{{ $rekap['izin'] }}</td>
                    <td>{{ $rekap['alpa'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <footer>
        Rekapitulasi ini dicetak melalui sistem elektronik ekerja mobile Pemerintah Kabupaten Konawe
    </footer>
</body>
</html>
