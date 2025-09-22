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

        .border-bottom-none {
            border-bottom: none !important;
        }

        .border-top-none {
            border-top: none !important;
        }

        .border-vertical-none {
            border-left: none !important;
            border-right: none !important;
        }

        .border-horizontal-none {
            border-top: none !important;
            border-bottom: none !important;
        }

        .border-left-none {
            border-left: none !important;
        }

        .border-right-none {
            border-right: none !important;
        }

        .check {
            font-family: DejaVu Sans, sans-serif;
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
                        <td style="text-align: center; vertical-align: middle">{{ $lapkin->kualitas_hasil }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr>
                <td colspan="6" style="text-align: right; font-weight: bold;">Total Nilai Kinerja</td>
                <td style="font-weight: bold;text-align: center">{{ $totalNilaiKualitasHasil }}</td>
            </tr>
            <tr>
                <td colspan="6" style="text-align: right; font-weight: bold;">Jumlah hari kerja bulan ini</td>
                <td style="font-weight: bold;text-align: center">{{ $jumlahHariKerja }}</td>
            </tr>

            {{-- BARIS KETERANGAN --}}
            <tr>
                <td colspan="4" style="text-align: center">*) Penilaian Atasan dalam Angka semua Output Kinerja Hari Kerja</td>
                <td colspan="3" class="border-bottom-none" style="text-align: center;">Konawe, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</td>
            </tr>
            <tr>
                <td style="text-align: center;width: 7%">Nilai</td>
                <td colspan="3" style="text-align: center">Kriteria</td>
                <td colspan="3" class="border-top-none border-bottom-none" style="text-align: center;">Pegawai Yang Melaporkan</td>
            </tr>
            <tr>
                <td style="text-align: center; vertical-align: middle">110 - < 120</td>
                <td style="line-height: 1.5" colspan="3">Hasil Kerja Sempurna, tidak ada kesalahan, tidak ada revisi dan pelayanan di atas standar yang ditentukan. Menciptakan ide baru dalam peningkatan kinerja yang memberi manfaat bagi organisasi atau negara</td>
                <td colspan="3" rowspan="6" class="border-top-none" style="text-align: center; vertical-align: middle;">
                    <b><u>{{ $namaPegawai }}</u></b><br/>
                    NIP. {{ $nip }}
                </td>
            </tr>
            <tr>
                <td style="text-align: center; vertical-align: middle">90 - < 110</td>
                <td style="line-height: 1.5" colspan="3">Hasil kerja mempunyai 1 atau 2 kesalahan kecil, tidak ada kesalahan besar, revisi dan pelayanan cukup memenuhi standar yang ditentukan</td>
            </tr>
            <tr>
                <td style="text-align: center; vertical-align: middle">70 - < 90</td>
                <td style="line-height: 1.5" colspan="3">Hasil kerja mempunyai 3 atau 4 kesalahan kecil, tidak ada kesalahan besar, revisi dan pelayanan cukup memenuhi standar yang ditentukan</td>
            </tr>
            <tr>
                <td style="text-align: center; vertical-align: middle">50 - < 70</td>
                <td style="line-height: 1.5" colspan="3">Hasil kerja mempunyai 5 kesalahan kecil, dan ada kesalahan besar, revisi dan pelayanan cukup memenuhi standar yang ditentukan</td>
            </tr>
            <tr>
                <td style="text-align: center; vertical-align: middle">< 50</td>
                <td style="line-height: 1.5" colspan="3">Hasil kerja mempunyai 5 kesalahan kecil, dan ada kesalahan besar, kurang memuaskan, revisi dan pelayanan dibawah standar yang ditentukan</td>
            </tr>
            <tr>
                <td style="text-align: center; vertical-align: middle">0</td>
                <td style="line-height: 1.5" colspan="3">Jika tidak ada laporan kinerja/Output kinerja pada hari kerja dimaksud</td>
            </tr>
            {{-- AKHIR BARIS KETERANGAN --}}

        </tbody>
    </table>

    <table style="margin-top: 1px;">
        <tbody>
            <tr>
                <td colspan="5" style="text-align: center">Hasil Penilaian Kualitas Kinerja Bulan ini</td>
            </tr>
            <tr>
                <td rowspan="2" class="border-right-none" style="width: 20%;text-align:right; vertical-align: middle;padding: 10px">Rumus Perhitungan</td>
                <td rowspan="2" class="border-vertical-none" style="width: 2%; vertical-align: middle; text-align: center">:</td>
                <td class="border-vertical-none border-bottom-none" style="text-align: right; vertical-align: bottom"><u>Total Nilai Kinerja (Kolom 7)</u></td>
                <td rowspan="2" class="border-vertical-none" style="width: 2%; vertical-align: middle; text-align: center">=</td>
                <td rowspan="2" class="border-left-none" style="vertical-align: middle">Nilai Kinerja</td>
            </tr>
            <tr>
                <td class="border-vertical-none border-top-none" style="text-align: right; vertical-align: top">Jumlah Hari Kerja Bulan ini</td>
            </tr>
            <tr>
                <td rowspan="2" class="border-right-none" style="width: 20%;text-align:right; vertical-align: middle;padding: 10px">Hasil Perhitungan Kinerja</td>
                <td rowspan="2" class="border-vertical-none" style="width: 2%; vertical-align: middle; text-align: center">:</td>
                <td class="border-vertical-none border-bottom-none" style="text-align: right; vertical-align: bottom"><u>&nbsp;{{ $totalNilaiKualitasHasil }}&nbsp;</u></td>
                <td rowspan="2" class="border-vertical-none" style="width: 2%; vertical-align: middle; text-align: center">=</td>
                <td rowspan="2" class="border-left-none" style="vertical-align: middle">{{ $hasilPerhitunganKinerja }}</td>
            </tr>
            <tr>
                <td class="border-vertical-none border-top-none" style="text-align: right; vertical-align: top">{{ $jumlahHariKerja }}</td>
            </tr>
        </tbody>
    </table>
    <table style="margin-top: 1px;">
        <tbody>
            <tr>
                <td colspan="6" style="text-align: center">
                    Kriteria Nilai (Cheklist salah satu sesuai hasil perhitungan di atas (<span class="check">&#x2714;</span>)**)
                </td>
            </tr>
            <tr>
                <td style="text-align: center">Sangat Baik (  )**</td>
                <td style="text-align: center">Baik (  )**</td>
                <td style="text-align: center">Cukup (  )**</td>
                <td style="text-align: center">Kurang (  )**</td>
                <td style="text-align: center; width: 19%">Sangat Kurang (  )**</td>
                <td style="text-align: center; width: 20%">Tidak Ada Laporan (  )**</td>
            </tr>
            <tr>
                <td style="height: 15px"></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="border-right-none border-bottom-none" style="width: 20%" colspan="4"></td>
                <td class="border-left-none border-bottom-none" colspan="2" style="text-align: center">
                    Pejabat Penilai Kinerja/Atasan Langsung<br/>
                    {{ $jabatanAtasan }}
                </td>
            </tr>
            <tr>
                <td class="border-horizontal-none border-right-none" colspan="4"></td>
                <td class="border-horizontal-none border-left-none" colspan="2" style="height: 100px"></td>
            </tr>
            <tr>
                <td class="border-top-none border-right-none" colspan="4"></td>
                <td class="border-top-none border-left-none" colspan="2" style="text-align: center">
                    <strong><u>{{ $namaAtasan }}</u></strong><br/>
                    NIP. {{ $nipAtasan }}
                </td>
            </tr>
        </tbody>
    </table>

</body>
</html>
