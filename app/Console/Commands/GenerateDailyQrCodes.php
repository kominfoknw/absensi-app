<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Kantor;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GenerateDailyQrCodes extends Command
{
    protected $signature = 'qr:generate-daily';
    protected $description = 'Generate new QR codes for all offices daily (masuk and pulang).';

    public function handle()
    {
        $this->info('Generating daily QR codes (Masuk & Pulang)...');

        $kantors = Kantor::all();
        $count = 0;

        foreach ($kantors as $kantor) {
            // Generate QR Code Masuk
            $kantor->qr_code_secret_masuk = (string) Str::uuid();
            $kantor->qr_code_masuk_generated_at = Carbon::now();

            // Generate QR Code Pulang
            $kantor->qr_code_secret_pulang = (string) Str::uuid();
            $kantor->qr_code_pulang_generated_at = Carbon::now();

            $kantor->save();
            $count++;
            $this->line("Generated new QR (Masuk & Pulang) for Kantor: {$kantor->nama_kantor}");
        }

        $this->info("Finished generating {$count} new QR codes.");
        return Command::SUCCESS;
    }
}