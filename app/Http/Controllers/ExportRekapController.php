<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Kehadiran;
use App\Models\Pegawai;
use App\Models\Kantor;
use App\Models\HariLibur;
use Carbon\Carbon;
use PDF;

class ExportRekapController extends Controller
{
    public function export(Request $request)
{
    $bulan = $request->get('bulan', now()->month);
    $tahun = $request->get('tahun', now()->year);

    // Ambil user login
    $user = auth()->user();

    // Default kantor_id dari request
    $kantorId = $request->get('kantor_id');

    // Kalau user operator → pakai kantor_id user
    if ($user->role === 'operator') {
        $kantorId = $user->kantor_id;
    }

    $tanggalMulai = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
    $tanggalAkhir = $tanggalMulai->copy()->endOfMonth();
    $namaBulan = strtoupper($tanggalMulai->translatedFormat('F'));
    $namaTahun = $tanggalMulai->year;

    $hariLibur = HariLibur::whereBetween('tanggal', [$tanggalMulai, $tanggalAkhir])
        ->pluck('tanggal')
        ->map(fn($tgl) => Carbon::parse($tgl)->toDateString())
        ->toArray();

    $pegawaiQuery = Pegawai::query()->with('user');

    if ($kantorId) {
        $pegawaiQuery->where('kantor_id', $kantorId);
        $namaKantor = strtoupper(optional(Kantor::find($kantorId))->nama_kantor ?? 'SEMUA KANTOR');
    } else {
        $namaKantor = 'SEMUA KANTOR';
    }

    $pegawaiList = $pegawaiQuery->get();
    $rekaps = collect();

    foreach ($pegawaiList as $pegawai) {
        $dataPerTanggal = collect();
        $izin = 0;
        $hadir = 0;
        $alpa = 0;

        for ($day = 1; $day <= $tanggalAkhir->day; $day++) {
            $tanggal = Carbon::create($tahun, $bulan, $day);
            $tanggalStr = $tanggal->toDateString();

            $isLibur = in_array($tanggalStr, $hariLibur);
            $isWeekend = in_array($tanggal->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);

            $kehadiran = Kehadiran::where('pegawai_id', $pegawai->id)
                ->whereDate('tanggal', $tanggalStr)
                ->first();

            if ($kehadiran) {
                $dataPerTanggal->put($day, $kehadiran);
                match ($kehadiran->status) {
                    'izin' => $izin++,
                    'hadir' => $hadir++,
                    default => $alpa++,
                };
            } elseif (!$isWeekend && !$isLibur) {
                $alpa++;
                $dataPerTanggal->put($day, (object)['status' => 'alpa']);
            } elseif ($isLibur) {
                $dataPerTanggal->put($day, (object)['status' => 'libur']);
            } else {
                $dataPerTanggal->put($day, null);
            }
        }

        $rekaps->push([
            'pegawai' => $pegawai,
            'records' => $dataPerTanggal,
            'izin' => $izin,
            'hadir' => $hadir,
            'alpa' => $alpa,
        ]);
    }

    $pdf = PDF::loadView('rekap.pdf', compact(
        'rekaps',
        'tanggalMulai',
        'tanggalAkhir',
        'namaBulan',
        'namaTahun',
        'namaKantor',
        'bulan',
        'tahun'
    ))->setPaper('legal', 'landscape');

    return $pdf->stream('rekap-kehadiran.pdf');
}

}
