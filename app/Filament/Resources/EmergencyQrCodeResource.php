<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmergencyQrCodeResource\Pages;
use App\Models\Kantor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class EmergencyQrCodeResource extends Resource
{
    protected static ?string $model = Kantor::class;
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Absen Darurat QR';
    protected static ?string $pluralModelLabel = 'QR Code Absen Darurat';
    protected static ?int $navigationSort = 5;

    // --- Kontrol Akses ---
    public static function canViewAny(): bool
    {
        return auth()->user()->role === 'superadmin' || (auth()->user()->role === 'operator' && auth()->user()->kantor_id !== null);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        if (auth()->user()->role === 'operator') {
            $query->where('id', auth()->user()->kantor_id);
        }
        return $query;
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === 'superadmin';
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->role === 'superadmin';
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->role === 'superadmin';
    }

    public static function canEdit(Model $record): bool
    {
        if (auth()->user()->role === 'superadmin') {
            return true;
        }
        return auth()->user()->role === 'operator' && auth()->user()->kantor_id === $record->id;
    }
    // --- Akhir Kontrol Akses ---


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama_kantor')
                    ->label('Nama Kantor')
                    ->required()
                    ->maxLength(255)
                    ->disabled(auth()->user()->role !== 'superadmin'),

                Forms\Components\Fieldset::make('QR Code Absen Masuk')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('qr_code_secret_masuk')
                            ->label('QR Secret Masuk')
                            ->unique(ignoreRecord: true)
                            ->nullable()
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Digenerate otomatis setiap hari.'),
                        Forms\Components\View::make('qr_code_display_masuk')
                            ->label('QR Code Masuk')
                            ->hidden(fn (?Kantor $record) => $record === null || $record->qr_code_secret_masuk === null)
                            ->view('filament.resources.emergency-qr-code-resource.forms.components.qr-code-display', [
                                'secret_field' => 'qr_code_secret_masuk',
                            ]),
                    ]),

                Forms\Components\Fieldset::make('QR Code Absen Pulang')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('qr_code_secret_pulang')
                            ->label('QR Secret Pulang')
                            ->unique(ignoreRecord: true)
                            ->nullable()
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Digenerate otomatis setiap hari.'),
                        Forms\Components\View::make('qr_code_display_pulang')
                            ->label('QR Code Pulang')
                            ->hidden(fn (?Kantor $record) => $record === null || $record->qr_code_secret_pulang === null)
                            ->view('filament.resources.emergency-qr-code-resource.forms.components.qr-code-display', [
                                'secret_field' => 'qr_code_secret_pulang',
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_kantor')
                    ->label('Nama Kantor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('qr_code_secret_masuk')
                    ->label('Secret Masuk')
                    ->copyable()
                    ->tooltip('Klik untuk menyalin')
                    ->placeholder('Belum digenerate')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('qr_code_secret_pulang')
                    ->label('Secret Pulang')
                    ->copyable()
                    ->tooltip('Klik untuk menyalin')
                    ->placeholder('Belum digenerate')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('qr_code_masuk_generated_at')
                    ->label('Masuk Digenerate')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('qr_code_pulang_generated_at')
                    ->label('Pulang Digenerate')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generateQrMasuk')
                    ->label('Generate/Refresh QR Masuk')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (Kantor $record) {
                        $record->qr_code_secret_masuk = (string) Str::uuid();
                        $record->qr_code_masuk_generated_at = Carbon::now();
                        $record->save();
                        Notification::make()
                            ->title('QR Code Masuk berhasil di-generate/refresh!')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Kantor $record) => auth()->user()->role === 'superadmin' || (auth()->user()->role === 'operator' && auth()->user()->kantor_id === $record->id)),

                Tables\Actions\Action::make('viewQrMasuk')
                    ->label('Lihat QR Masuk')
                    ->icon('heroicon-o-qr-code')
                    ->color('primary')
                    ->modalContent(function (Kantor $record) {
                        return self::renderQrCodeModalContent($record->qr_code_secret_masuk, $record->nama_kantor, 'Masuk');
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->visible(fn (Kantor $record) => $record->qr_code_secret_masuk !== null),

                Tables\Actions\Action::make('generateQrPulang')
                    ->label('Generate/Refresh QR Pulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Kantor $record) {
                        $record->qr_code_secret_pulang = (string) Str::uuid();
                        $record->qr_code_pulang_generated_at = Carbon::now();
                        $record->save();
                        Notification::make()
                            ->title('QR Code Pulang berhasil di-generate/refresh!')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Kantor $record) => auth()->user()->role === 'superadmin' || (auth()->user()->role === 'operator' && auth()->user()->kantor_id === $record->id)),

                Tables\Actions\Action::make('viewQrPulang')
                    ->label('Lihat QR Pulang')
                    ->icon('heroicon-o-qr-code')
                    ->color('danger')
                    ->modalContent(function (Kantor $record) {
                        return self::renderQrCodeModalContent($record->qr_code_secret_pulang, $record->nama_kantor, 'Pulang');
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->visible(fn (Kantor $record) => $record->qr_code_secret_pulang !== null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->role === 'superadmin'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmergencyQrCodes::route('/'),
            'create' => Pages\CreateEmergencyQrCode::route('/create'),
            'edit' => Pages\EditEmergencyQrCode::route('/{record}/edit'),
        ];
    }

    // --- Helper function untuk render konten modal QR Code ---
    private static function renderQrCodeModalContent(?string $qrCodeSecret, string $namaKantor, string $jenisAbsen): HtmlString
    {
        if (empty($qrCodeSecret)) {
            Log::info('QR Code Modal: Secret is empty for ' . $namaKantor . ' - ' . $jenisAbsen);
            return new HtmlString('<p class="text-center p-4">QR Code '.$jenisAbsen.' belum digenerate. Klik "Generate/Refresh QR '.$jenisAbsen.'" terlebih dahulu.</p>');
        }

        $svgDataUri = ''; // Ganti nama variabel untuk lebih jelas
        $errorMessage = '';

        try {
            Log::info('QR Code Modal: Attempting to generate QR for secret: ' . $qrCodeSecret . ' for ' . $namaKantor . ' - ' . $jenisAbsen);

            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_MARKUP_SVG, // Ini tetap untuk menghasilkan SVG
                'eccLevel'   => QRCode::ECC_L,
                'scale'      => 8,
                // Tambahkan opsi berikut agar output langsung Data URI jika itu yang diinginkan
                // Defaultnya seharusnya menghasilkan SVG string, namun log Anda menunjukkan sudah Data URI
                // Untuk memastikan outputnya adalah Data URI, Anda bisa coba opsi ini
                'outputBase64' => true, // <-- Ini penting! Pastikan outputnya Base64 encoded Data URI
            ]);
            $qrcode = new QRCode($options);
            // Langsung gunakan hasil render sebagai Data URI
            $svgDataUri = $qrcode->render($qrCodeSecret);

            // Log debugging baru
            Log::info('QR Code Modal Debug FINAL: Length of svgDataUri: ' . strlen($svgDataUri));
            if (!empty($svgDataUri)) {
                Log::info('QR Code Modal Debug FINAL: First 200 characters of svgDataUri: ' . substr($svgDataUri, 0, 200));
                Log::info('QR Code Modal Debug FINAL: Last 200 characters of svgDataUri: ' . substr($svgDataUri, -200));
            } else {
                Log::warning('QR Code Modal Debug FINAL: svgDataUri is empty after rendering.');
                $errorMessage = 'QR Code rendering produced an empty result.';
            }

            // Periksa apakah hasil render dimulai dengan "data:image/svg+xml;base64,"
            // Karena log Anda menunjukkan bahwa itu memang dimulai dengan itu.
            if (!Str::startsWith($svgDataUri, 'data:image/svg+xml;base64,')) {
                 $errorMessage = 'QR Code tidak dalam format Data URI Base64 yang diharapkan.';
                 Log::error("Filament Modal QR Display Error: {$errorMessage} for secret: {$qrCodeSecret}. Output starts with: " . substr($svgDataUri, 0, 50));
            }


        } catch (\Throwable $e) {
            $errorMessage = 'Terjadi error saat membuat QR Code: ' . $e->getMessage();
            Log::error('Filament Modal QR Code Rendering Exception: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in file ' . $e->getFile() . ' for secret: ' . $qrCodeSecret);

            Notification::make()
                ->title('Gagal menampilkan QR Code')
                ->danger()
                ->body('Terjadi kesalahan saat membuat QR Code. Silakan periksa log server untuk detail: ' . $e->getMessage())
                ->send();
        }

        // Jika ada error atau Data URI kosong
        if ($errorMessage || empty($svgDataUri)) {
            return new HtmlString('<p class="text-center p-4 text-red-500">' . ($errorMessage ?: 'QR Code tidak dapat ditampilkan karena masalah internal.') . '</p>');
        }

        // Langsung gunakan $svgDataUri sebagai src karena sudah Base64 encoded Data URI
        return new HtmlString('
            <div class="flex flex-col items-center justify-center p-4 space-y-4">
                <h3 class="text-xl font-bold">QR Code Absen '.$jenisAbsen.' untuk ' . $namaKantor . '</h3>
                <div class="p-4 bg-white rounded-lg shadow-lg">
                    <img src="' . $svgDataUri . '" alt="QR Code Absen '.$jenisAbsen.'" style="width: 256px; height: 256px;">
                </div>
                <p class="text-sm text-gray-600">Secret: <code class="bg-gray-100 p-1 rounded">' . $qrCodeSecret . '</code></p>
                <p class="text-xs text-gray-500 text-center">Scan QR Code ini untuk absen darurat '.$jenisAbsen.'.</p>
            </div>
        ');
    }
}