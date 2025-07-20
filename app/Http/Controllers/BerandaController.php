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
        $endDate = Carbon::now()->startOfMonth()->subDay(); // Contoh: Jika hari ini 11 Juli 2025, ini akan menjadi 30 Juni 2025
        $startDate = $endDate->copy()->startOfMonth();      // Ini akan menjadi 1 Juni 2025

        // --- 1. Hitung Total Hari Kerja Efektif Bulan Lalu (mengabaikan akhir pekan & hari libur) ---
        $totalHariKerjaBulanLalu = 0;
        // Ambil semua tanggal hari libur nasional di bulan lalu
        $hariLiburBulanLalu = HariLibur::whereBetween('tanggal', [$startDate, $endDate])
                                        ->pluck('tanggal')
                                        ->map(fn($tgl) => Carbon::parse($tgl)->toDateString())
                                        ->toArray();

        $currentDate = $startDate->copy();
        while ($currentDate->lessThanOrEqualTo($endDate)) {
            // Jika hari ini adalah hari kerja (Senin-Jumat) DAN bukan hari libur nasional
            if ($currentDate->isWeekday() && !in_array($currentDate->toDateString(), $hariLiburBulanLalu)) {
                $totalHariKerjaBulanLalu++;
            }
            $currentDate->addDay();
        }

        // Hindari pembagian dengan nol jika tidak ada hari kerja di bulan lalu (kasus ekstrem)
        if ($totalHariKerjaBulanLalu == 0) {
            $totalHariKerjaBulanLalu = 1; // Set minimal 1 untuk menghindari error, meskipun persentase akan 0
        }

        // --- 2. Perhitungan Performa Pegawai Terbaik (Top 3) ---
        $allPegawai = Pegawai::with(['kantor'])->get(); // Eager load kantor untuk tampilan
        $pegawaiPerformance = [];

        foreach ($allPegawai as $pegawai) {
            // Hitung Kehadiran individu
            $kehadiranBulanLaluCount = $pegawai->kehadiran()
                                             ->whereBetween('tanggal', [$startDate, $endDate])
                                             ->whereIn('status', ['hadir', 'tugas_luar'])
                                             ->count();

            $persentaseKehadiran = 0;
            if ($totalHariKerjaBulanLalu > 0) {
                $persentaseKehadiran = ($kehadiranBulanLaluCount / $totalHariKerjaBulanLalu) * 100;
            }

            // Hitung Kualitas Hasil Lapkin individu
            $lapkinBulanLalu = $pegawai->lapkins()
                                     ->whereBetween('tanggal', [$startDate, $endDate])
                                     ->get();

            $rataRataKualitasLapkin = 0;
            if ($lapkinBulanLalu->isNotEmpty()) {
                $rataRataKualitasLapkin = $lapkinBulanLalu->avg('kualitas_hasil');
            }

            // Hitung Total Score (Bobot bisa disesuaikan)
            $totalScore = ($persentaseKehadiran * 0.7) + ($rataRataKualitasLapkin * 0.3); // Bobot 70% kehadiran, 30% lapkin

            $pegawaiPerformance[] = [
                'pegawai' => $pegawai,
                'persentase_kehadiran' => round($persentaseKehadiran, 2),
                'rata_rata_kualitas_lapkin' => round($rataRataKualitasLapkin, 2),
                'total_score' => round($totalScore, 2),
            ];
        }

        // Urutkan dan ambil 3 pegawai teratas
        $top3Pegawai = collect($pegawaiPerformance)
                       ->sortByDesc('total_score')
                       ->take(3);

        // --- 3. Data untuk Grafik Kehadiran per Kantor Bulan Lalu ---
        $kantorKehadiranData = [];
        $kantorLabels = [];

        $kantorList = Kantor::all();
        foreach ($kantorList as $kantor) {
            $totalPegawaiKantorIni = Pegawai::where('kantor_id', $kantor->id)->count();

            // Hanya hitung jika ada pegawai di kantor ini untuk menghindari pembagian nol
            if ($totalPegawaiKantorIni > 0) {
                $hadirOrTugasLuarCount = Kehadiran::whereHas('pegawai', function($query) use ($kantor) {
                                                    $query->where('kantor_id', $kantor->id);
                                                })
                                                ->whereBetween('tanggal', [$startDate, $endDate])
                                                ->whereIn('status', ['hadir', 'tugas_luar'])
                                                ->count();

                $persentaseKehadiranKantor = 0;
                if ($totalHariKerjaBulanLalu > 0) {
                    // Formula: (Jumlah Kehadiran / (Jumlah Pegawai di Kantor * Total Hari Kerja Efektif)) * 100
                    $persentaseKehadiranKantor = ($hadirOrTugasLuarCount / ($totalPegawaiKantorIni * $totalHariKerjaBulanLalu)) * 100;
                }
                $kantorKehadiranData[] = round($persentaseKehadiranKantor, 2);
                $kantorLabels[] = $kantor->nama_kantor;
            } else {
                // Jika tidak ada pegawai di kantor, tetap tambahkan kantor dengan 0% kehadiran
                $kantorKehadiranData[] = 0;
                $kantorLabels[] = $kantor->nama_kantor;
            }
        }

        // --- 4. Data Statistik Umum ---
        $totalPegawai = Pegawai::count();
        $totalKantor = Kantor::count();
        $totalUnit = Unit::count();
        $totalHariLibur = HariLibur::whereBetween('tanggal', [$startDate, $endDate])->count(); // Total hari libur di bulan sebelumnya

        $statistikData = [
            'total_pegawai' => $totalPegawai,
            'total_kantor' => $totalKantor,
            'total_unit' => $totalUnit,
            'total_hari_libur_bln_lalu' => $totalHariLibur,
        ];

        // --- Debugging (aktifkan jika perlu) ---
        // dd($kantorLabels, $kantorKehadiranData, $statistikData);

        // Kirim semua data ke view
        return view('beranda', compact(
            'top3Pegawai',
            'kantorLabels',
            'kantorKehadiranData',
            'statistikData'
        ));
    }
}