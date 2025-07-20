<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lapkin;
use App\Models\Pegawai;
use App\Models\HariLibur;
use App\Models\Kantor;
use PDF; // Import facade PDF
use Carbon\Carbon;

class PdfController extends Controller
{
    public function printLapkin(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:' . (Carbon::now()->year + 5), // Batas tahun bisa disesuaikan
            'pegawai_id' => 'required|exists:pegawais,id',
            'kantor_id' => 'sometimes|nullable|exists:kantors,id', // Opsional, hanya untuk superadmin
        ]);

        $month = $request->input('month');
        $year = $request->input('year');
        $pegawaiId = $request->input('pegawai_id');
        $kantorId = $request->input('kantor_id'); // Kantor ID dari filter jika ada

        $pegawai = Pegawai::with(['user', 'kantor', 'unit'])->find($pegawaiId);

        if (!$pegawai) {
            abort(404, 'Pegawai tidak ditemukan.');
        }

        // Ambil data Lapkin
        $lapkins = Lapkin::where('pegawai_id', $pegawaiId)
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->orderBy('tanggal', 'asc')
            ->get();

        // Data Header
        $namaBulan = Carbon::create($year, $month, 1)->translatedFormat('F');
        $nip = $pegawai->nip ?? '-';
        $namaPegawai = $pegawai->nama ?? '-';
        $jabatan = $pegawai->jabatan ?? '-';
        $pangkat = $pegawai->pangkat ?? '-'; // Asumsi langsung di pegawai
        $golongan = $pegawai->golongan ?? '-'; // Asumsi langsung di pegawai
        $kelasJabatan = $pegawai->kelas_jabatan ?? '-'; // Asumsi langsung di pegawai
        $namaUnit = $pegawai->unit->nama_unit ?? '-';
        $namaKantor = $pegawai->kantor->nama_kantor ?? '-';

        // Logika Perhitungan Kinerja
        $totalNilaiKualitasHasil = $lapkins->sum('kualitas_hasil');

        // Hitung jumlah hari kerja dalam bulan ini
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $totalDaysInMonth = $endDate->day;
        $workDays = 0;
        $hariLiburDates = HariLibur::whereYear('tanggal', $year)
                                    ->whereMonth('tanggal', $month)
                                    ->pluck('tanggal')
                                    ->map(fn($date) => Carbon::parse($date)->toDateString())
                                    ->toArray();

        for ($i = 1; $i <= $totalDaysInMonth; $i++) {
            $currentDate = Carbon::create($year, $month, $i);
            // Cek apakah bukan Sabtu atau Minggu DAN bukan hari libur
            if (!$currentDate->isWeekend() && !in_array($currentDate->toDateString(), $hariLiburDates)) {
                $workDays++;
            }
        }

        $hasilPerhitunganKinerja = ($workDays > 0) ? ($totalNilaiKualitasHasil / $workDays) : 0;

        // Group lapkins by week
        $lapkinsGroupedByWeek = [];
        foreach ($lapkins as $lapkin) {
            $weekNumber = Carbon::parse($lapkin->tanggal)->weekOfMonth;
            $lapkinsGroupedByWeek[$weekNumber][] = $lapkin;
        }

        // Urutkan Lapkin berdasarkan tanggal untuk penomoran yang benar di minggu
        foreach ($lapkinsGroupedByWeek as $weekNum => $weekLapkins) {
            usort($lapkinsGroupedByWeek[$weekNum], function ($a, $b) {
                return Carbon::parse($a->tanggal)->timestamp - Carbon::parse($b->tanggal)->timestamp;
            });
        }

        $data = [
            'namaBulan' => $namaBulan,
            'tahun' => $year,
            'nip' => $nip,
            'namaPegawai' => $namaPegawai,
            'jabatan' => $jabatan,
            'pangkat' => $pangkat,
            'golongan' => $golongan,
            'kelasJabatan' => $kelasJabatan,
            'namaUnit' => $namaUnit,
            'namaKantor' => $namaKantor,
            'lapkinsGroupedByWeek' => $lapkinsGroupedByWeek,
            'totalNilaiKualitasHasil' => $totalNilaiKualitasHasil,
            'jumlahHariKerja' => $workDays,
            'hasilPerhitunganKinerja' => number_format($hasilPerhitunganKinerja, 2), // Format 2 desimal
        ];

        $pdf = PDF::loadView('pdf.lapkin_report', $data)
            ->setPaper('legal', 'landscape'); // Set paper legal dan orientasi landscape

        return $pdf->stream("Laporan_Lapkin_{$namaPegawai}_{$namaBulan}_{$year}.pdf");
    }
}