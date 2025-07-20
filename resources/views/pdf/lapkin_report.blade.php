<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pelaksanaan Tugas/Aktivitas Harian Jabatan</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 10pt;
            margin: 0.5in; /* 1.27 cm margin, default untuk PDF */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid black;
            padding: 4px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f2f2f2;
            text-align: center;
        }
        .header-info {
            line-height: 1.5;
            margin-bottom: 10px;
        }
        .header-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .footer-calculation {
            margin-top: 20px;
            line-height: 1.5;
        }
        .grey-bg {
            background-color: #e0e0e0; /* Light grey background */
        }
    </style>
</head>
<body>

    <div class="header-title">
        LAPORAN PELAKSANAAN TUGAS/AKTIVITAS HARIAN JABATAN
    </div>

    <div class="header-info">
        <table>
            <tr>
                <td style="width: 50%; border: none; padding: 0;">Bulan : {{ $namaBulan }} {{ $tahun }}</td>
                <td style="width: 50%; border: none; padding: 0;">NIP : {{ $nip }}</td>
            </tr>
            <tr>
                <td style="width: 50%; border: none; padding: 0;">Nama PNS : {{ $namaPegawai }}</td>
                <td style="width: 50%; border: none; padding: 0;">Jabatan : {{ $jabatan }}</td>
            </tr>
            <tr>
                <td style="width: 50%; border: none; padding: 0;">Pangkat/Gol. Ruang : {{ $pangkat }}, {{ $golongan }}</td>
                <td style="width: 50%; border: none; padding: 0;">Kelas Jabatan : {{ $kelasJabatan }}</td>
            </tr>
            <tr>
                <td style="width: 50%; border: none; padding: 0;">Unit Tugas : {{ $namaUnit }}</td>
                <td style="width: 50%; border: none; padding: 0;">Unit OPD : {{ $namaKantor }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">NO.</th>
                <th style="width: 15%;">Hari/Tanggal</th>
                <th style="width: 30%;">Uraian Kegiatan</th>
                <th style="width: 10%;">Tempat</th>
                <th style="width: 10%;">Target</th>
                <th style="width: 20%;">Hasil/Output</th>
                <th style="width: 10%;">Kualitas Hasil</th>
            </tr>
            <tr class="grey-bg">
                <th>1.</th>
                <th>2.</th>
                <th>3.</th>
                <th>4.</th>
                <th>5.</th>
                <th>6.</th>
                <th>7.</th>
            </tr>
        </thead>
        <tbody>
            @php $globalNo = 1; @endphp
            @foreach ($lapkinsGroupedByWeek as $weekNum => $weekLapkins)
                <tr>
                    <td colspan="7" style="font-weight: bold;">Minggu Ke - {{ $weekNum }}</td>
                </tr>
                @foreach ($weekLapkins as $lapkin)
                    <tr>
                        <td style="text-align: center;">{{ $globalNo++ }}.</td>
                        <td>{{ $lapkin->hari }}, {{ \Carbon\Carbon::parse($lapkin->tanggal)->format('d M Y') }}</td>
                        <td>{{ $lapkin->nama_kegiatan }}</td>
                        <td>{{ $lapkin->tempat }}</td>
                        <td>{{ $lapkin->target }}</td>
                        <td>{{ $lapkin->output }}</td>
                        <td>{{ $lapkin->kualitas_hasil }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr>
                <td colspan="6" style="text-align: right; font-weight: bold;">Total Nilai Kinerja</td>
                <td style="font-weight: bold;">{{ $totalNilaiKualitasHasil }}</td>
            </tr>
            <tr>
                <td colspan="6" style="text-align: right; font-weight: bold;">Jumlah hari kerja bulan ini</td>
                <td style="font-weight: bold;">{{ $jumlahHariKerja }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer-calculation">
        <p style="font-weight: bold;">Rumus Perhitungan: Nilai Kinerja = Total Nilai Kinerja / Jumlah hasil kerja bulan ini</p>
        <p style="font-weight: bold;">Hasil perhitungan kinerja: {{ $hasilPerhitunganKinerja }}</p>
    </div>

</body>
</html>