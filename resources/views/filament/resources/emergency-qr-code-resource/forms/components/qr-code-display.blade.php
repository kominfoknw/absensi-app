@php
    use chillerlan\QRCode\QRCode;
    use chillerlan\QRCode\QROptions;
    use Illuminate\Support\Str; // Pastikan ini di-import jika belum

    $qrCodeSecret = $getRecord()->{$secret_field};
    $jenisAbsen = str_contains($secret_field, 'masuk') ? 'Masuk' : 'Pulang';

    $svg = ''; // Inisialisasi variabel $svg

    if ($qrCodeSecret) {
        try {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_MARKUP_SVG,
                'eccLevel'   => QRCode::ECC_L,
                'scale'      => 8,
            ]);
            $qrcode = new QRCode($options);
            $renderedSvg = $qrcode->render($qrCodeSecret); // Simpan hasil render ke variabel sementara

            // Opsional: Periksa apakah outputnya benar-benar SVG
            if (!is_string($renderedSvg) || !Str::startsWith(trim($renderedSvg), '<svg')) {
                \Log::error("Filament QR Display Error: Rendered QR Code is not valid SVG for secret: {$qrCodeSecret}");
                $svg = '<p class="text-center text-red-500">Gagal merender QR Code: Output tidak valid.</p>';
            } else {
                $svg = $renderedSvg;
            }

        } catch (\Throwable $e) { // Tangkap semua jenis error dan exception
            // Log error ke Laravel logs
            \Log::error('Filament QR Code Rendering Exception: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in file ' . $e->getFile() . ' for secret: ' . $qrCodeSecret);

            // Tampilkan pesan error di UI Filament
            $svg = '<p class="text-center text-red-500">Terjadi error saat membuat QR Code. Pesan: ' . $e->getMessage() . '</p>';
        }
    } else {
        $svg = '<p class="text-center text-gray-500">QR Code '.$jenisAbsen.' belum digenerate. Silakan klik "Generate/Refresh QR '.$jenisAbsen.'" dari halaman daftar.</p>';
    }
@endphp

<div class="p-4 flex flex-col items-center justify-center space-y-4">
    @if ($qrCodeSecret && $svg && !Str::contains($svg, 'Gagal merender') && !Str::contains($svg, 'Terjadi error'))
        <div class="p-4 bg-white rounded-lg shadow-lg">
            {!! $svg !!}
        </div>
        <p class="text-sm text-gray-600">Secret: <code class="bg-gray-100 p-1 rounded">{{ $qrCodeSecret }}</code></p>
        <p class="text-xs text-gray-500">Scan QR Code ini untuk absen darurat {{ strtolower($jenisAbsen) }}.</p>
    @else
        {!! $svg !!}
    @endif
</div>