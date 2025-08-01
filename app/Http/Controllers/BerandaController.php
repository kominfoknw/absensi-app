<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Kehadiran;
use App\Models\Lapkin;
use App\Models\Kantor;
use App\Models\Unit;
use App\Models\HariLibur;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; // Pastikan ini diimpor jika menggunakan DB facade

class BerandaController extends Controller
{
    public function index()
{
    // Tanggal untuk bulan sebelumnya
    $endDate = Carbon::now()->startOfMonth()->subDay(); // Akhir bulan lalu
    $startDate = $endDate->copy()->startOfMonth();      // Awal bulan lalu

    // --- 1. Hitung Total Hari Kerja Efektif Bulan Lalu ---
    $totalHariKerjaBulanLalu = 0;
    $hariLiburBulanLalu = HariLibur::whereBetween('tanggal', [$startDate, $endDate])
                                    ->pluck('tanggal')
                                    ->map(fn($tgl) => Carbon::parse($tgl)->toDateString())
                                    ->toArray();

    $currentDate = $startDate->copy();
    while ($currentDate->lessThanOrEqualTo($endDate)) {
        if ($currentDate->isWeekday() && !in_array($currentDate->toDateString(), $hariLiburBulanLalu)) {
            $totalHariKerjaBulanLalu++;
        }
        $currentDate->addDay();
    }

    if ($totalHariKerjaBulanLalu == 0) {
        $totalHariKerjaBulanLalu = 1;
    }

    // --- 2. Perhitungan Performa Pegawai Terbaik (Top 3) ---
    $allPegawai = Pegawai::with('kantor')->get();
    $pegawaiPerformance = [];

    foreach ($allPegawai as $pegawai) {
        $kehadiranBulanLaluCount = $pegawai->kehadiran()
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->whereIn('status', ['hadir', 'tugas_luar'])
            ->count();

        $persentaseKehadiran = 0;
        if ($totalHariKerjaBulanLalu > 0) {
            $persentaseKehadiran = ($kehadiranBulanLaluCount / $totalHariKerjaBulanLalu) * 100;
        }

        $lapkinBulanLalu = $pegawai->lapkins()
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get();

        $rataRataKualitasLapkin = 0;
        if ($lapkinBulanLalu->isNotEmpty()) {
            $rataRataKualitasLapkin = $lapkinBulanLalu->avg('kualitas_hasil');
        }

        $totalScore = ($persentaseKehadiran * 0.7) + ($rataRataKualitasLapkin * 0.3);

        // Hanya masukkan pegawai yang punya data kehadiran atau lapkin
        if ($kehadiranBulanLaluCount > 0 || $lapkinBulanLalu->isNotEmpty()) {
            $pegawaiPerformance[] = [
                'pegawai' => $pegawai,
                'persentase_kehadiran' => round($persentaseKehadiran, 2),
                'rata_rata_kualitas_lapkin' => round($rataRataKualitasLapkin, 2),
                'total_score' => round($totalScore, 2),
            ];
        }
    }

    $top3Pegawai = collect($pegawaiPerformance)
                   ->sortByDesc('total_score')
                   ->take(3);

    // --- 3. Grafik Kehadiran per Kantor ---
    $kantorKehadiranData = [];
    $kantorLabels = [];

    $kantorList = Kantor::all();
    foreach ($kantorList as $kantor) {
        $totalPegawaiKantorIni = Pegawai::where('kantor_id', $kantor->id)->count();

        if ($totalPegawaiKantorIni > 0) {
            $hadirOrTugasLuarCount = Kehadiran::whereHas('pegawai', function ($query) use ($kantor) {
                    $query->where('kantor_id', $kantor->id);
                })
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->whereIn('status', ['hadir', 'tugas_luar'])
                ->count();

            $persentaseKehadiranKantor = ($hadirOrTugasLuarCount / ($totalPegawaiKantorIni * $totalHariKerjaBulanLalu)) * 100;
            $kantorKehadiranData[] = round($persentaseKehadiranKantor, 2);
        } else {
            $kantorKehadiranData[] = 0;
        }

        $kantorLabels[] = $kantor->nama_kantor;
    }

    // --- 4. Statistik Umum ---
    $statistikData = [
        'total_pegawai' => Pegawai::count(),
        'total_kantor' => Kantor::count(),
        'total_unit' => Unit::count(),
        'total_hari_libur_bln_lalu' => HariLibur::whereBetween('tanggal', [$startDate, $endDate])->count(),
    ];

    // Kirim ke view
    return view('beranda', compact(
        'top3Pegawai',
        'kantorLabels',
        'kantorKehadiranData',
        'statistikData'
    ));
}

}