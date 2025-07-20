<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Http\UploadedFile;
use App\Models\Kehadiran;
use App\Models\Lapkin;
use App\Models\Kantor;
use App\Models\Pegawai;

class ImportExportData extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $slug = 'data-import-export';
    protected static ?string $navigationGroup = 'Admin Tools';
    protected static ?string $title = 'Import/Export Data';

    protected static string $view = 'filament.pages.import-export-data';

    public array $importLapkinData = [];
    public array $importAttendanceData = [];
    public array $downloadTemplateData = [];

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->role === 'superadmin';
    }

    public function rules(): array
    {
        return [
            'importLapkinData.lapkin_file' => 'required|file|mimes:xlsx,xls',
            'importAttendanceData.attendance_file' => 'required|file|mimes:xlsx,xls',
            'downloadTemplateData.kantor_id' => 'required|exists:kantors,id',
            'downloadTemplateData.pegawai_id' => 'required|exists:pegawais,id',
            'downloadTemplateData.tanggal_mulai' => 'required|date',
            'downloadTemplateData.tanggal_selesai' => 'required|date|after_or_equal:downloadTemplateData.tanggal_mulai',
        ];
    }

    /**
     * In Filament Pages, we must NOT override render().
     * Instead, pass data to the view using getViewData().
     */
    public function getViewData(): array
    {
        return [
            'kantors' => Kantor::pluck('nama_kantor', 'id')->toArray(),
            'pegawais' => Pegawai::pluck('nama', 'id')->toArray(),
        ];
    }

    public function importLapkin(): void
    {
        $this->validateOnly('importLapkinData');

        /** @var UploadedFile $file */
        $file = $this->importLapkinData['lapkin_file'];

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $header = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, true, false)[0];

            DB::beginTransaction();
            $importedCount = 0;

            for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
                $rowData = $sheet->rangeToArray('A' . $row . ':' . $sheet->getHighestColumn() . $row, null, true, false)[0];
                $data = array_combine($header, $rowData);

                if (
                    empty($data['user_id']) ||
                    empty($data['pegawai_id']) ||
                    empty($data['kantor_id']) ||
                    empty($data['hari']) ||
                    empty($data['tanggal']) ||
                    empty($data['nama_kegiatan'])
                ) {
                    Notification::make()
                        ->title("Baris $row dilewati: Data tidak lengkap.")
                        ->danger()
                        ->send();
                    continue;
                }

                Lapkin::create([
                    'user_id' => $data['user_id'],
                    'pegawai_id' => $data['pegawai_id'],
                    'kantor_id' => $data['kantor_id'],
                    'hari' => $data['hari'],
                    'tanggal' => Carbon::parse($data['tanggal'])->format('Y-m-d'),
                    'nama_kegiatan' => $data['nama_kegiatan'],
                    'tempat' => $data['tempat'] ?? null,
                    'target' => $data['target'] ?? null,
                    'output' => $data['output'] ?? null,
                    'kualitas_hasil' => $data['kualitas_hasil'] ?? null,
                ]);

                $importedCount++;
            }

            DB::commit();

            Notification::make()
                ->title('Import Lapkin berhasil!')
                ->body("$importedCount data berhasil diimport.")
                ->success()
                ->send();

            $this->importLapkinData['lapkin_file'] = null;

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Gagal mengimport Lapkin!')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function importAttendance(): void
    {
        $this->validateOnly('importAttendanceData');

        /** @var UploadedFile $file */
        $file = $this->importAttendanceData['attendance_file'];

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $header = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, true, false)[0];

            DB::beginTransaction();
            $importedCount = 0;

            for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
                $rowData = $sheet->rangeToArray('A' . $row . ':' . $sheet->getHighestColumn() . $row, null, true, false)[0];
                $data = array_combine($header, $rowData);

                if (
                    empty($data['user_id']) ||
                    empty($data['pegawai_id']) ||
                    empty($data['shift_id']) ||
                    empty($data['tanggal']) ||
                    empty($data['jam_masuk']) ||
                    empty($data['status'])
                ) {
                    Notification::make()
                        ->title("Baris $row dilewati: Data tidak lengkap.")
                        ->danger()
                        ->send();
                    continue;
                }

                Kehadiran::create([
                    'user_id' => $data['user_id'],
                    'pegawai_id' => $data['pegawai_id'],
                    'shift_id' => $data['shift_id'],
                    'tanggal' => Carbon::parse($data['tanggal'])->format('Y-m-d'),
                    'jam_masuk' => $data['jam_masuk'],
                    'jam_pulang' => $data['jam_pulang'] ?? null,
                    'status' => $data['status'],
                ]);

                $importedCount++;
            }

            DB::commit();

            Notification::make()
                ->title('Import Kehadiran berhasil!')
                ->body("$importedCount data berhasil diimport.")
                ->success()
                ->send();

            $this->importAttendanceData['attendance_file'] = null;

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Gagal mengimport Kehadiran!')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function downloadLapkinTemplate()
    {
        $this->validateOnly('downloadTemplateData');

        $data = $this->downloadTemplateData;

        $kantorId = $data['kantor_id'];
        $pegawaiId = $data['pegawai_id'];
        $tanggalMulai = Carbon::parse($data['tanggal_mulai']);
        $tanggalSelesai = Carbon::parse($data['tanggal_selesai']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lapkin Template');

        $headers = [
            'user_id', 'pegawai_id', 'kantor_id',
            'hari', 'tanggal', 'nama_kegiatan',
            'tempat', 'target', 'output', 'kualitas_hasil'
        ];

        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        $currentDate = $tanggalMulai->copy();
        while ($currentDate->lte($tanggalSelesai)) {
            $sheet->setCellValue("A$row", Pegawai::find($pegawaiId)?->user_id ?? '');
            $sheet->setCellValue("B$row", $pegawaiId);
            $sheet->setCellValue("C$row", $kantorId);
            $sheet->setCellValue("D$row", $currentDate->isoFormat('dddd'));
            $sheet->setCellValue("E$row", $currentDate->format('Y-m-d'));
            $row++;
            $currentDate->addDay();
        }

        $fileName = "template_lapkin_{$tanggalMulai->format('Ymd')}_to_{$tanggalSelesai->format('Ymd')}.xlsx";
        $tempPath = "temp/$fileName";

        (new Xlsx($spreadsheet))->save(Storage::disk('local')->path($tempPath));

        return response()->download(Storage::disk('local')->path($tempPath))->deleteFileAfterSend();
    }

    public function downloadAttendanceTemplate()
    {
        $this->validateOnly('downloadTemplateData');

        $data = $this->downloadTemplateData;

        $kantorId = $data['kantor_id'];
        $pegawaiId = $data['pegawai_id'];
        $tanggalMulai = Carbon::parse($data['tanggal_mulai']);
        $tanggalSelesai = Carbon::parse($data['tanggal_selesai']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kehadiran Template');

        $headers = [
            'user_id', 'pegawai_id', 'shift_id',
            'tanggal', 'jam_masuk', 'jam_pulang', 'status'
        ];

        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        $currentDate = $tanggalMulai->copy();
        while ($currentDate->lte($tanggalSelesai)) {
            $sheet->setCellValue("A$row", Pegawai::find($pegawaiId)?->user_id ?? '');
            $sheet->setCellValue("B$row", $pegawaiId);
            $sheet->setCellValue("D$row", $currentDate->format('Y-m-d'));
            $row++;
            $currentDate->addDay();
        }

        $fileName = "template_kehadiran_{$tanggalMulai->format('Ymd')}_to_{$tanggalSelesai->format('Ymd')}.xlsx";
        $tempPath = "temp/$fileName";

        (new Xlsx($spreadsheet))->save(Storage::disk('local')->path($tempPath));

        return response()->download(Storage::disk('local')->path($tempPath))->deleteFileAfterSend();
    }
}
