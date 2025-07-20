<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Checkbox; // <-- Import Checkbox
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;

class Login extends BaseLogin
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getLoginFormComponent(), // <-- Tetap panggil ini karena ada kustomisasi
                // Definisi komponen password secara langsung
                TextInput::make('password') // <-- Nama field untuk password
                    ->label(__('filament-panels::pages/auth/login.form.password.label')) // Label bawaan Filament
                    ->password() // Ini akan membuat input type="password"
                    ->required(),
                // Definisi komponen remember me secara langsung
                Checkbox::make('remember') // <-- Nama field untuk remember me
                    ->label(__('filament-panels::pages/auth/login.form.remember.label')), // Label bawaan Filament
            ])
            ->statePath('data'); // Data form akan disimpan di array 'data'
    }

    protected function getLoginFormComponent(): TextInput
    {
        return TextInput::make('login_identifier_input') // Nama komponen yang sangat generik
            ->label(__('Email atau NIP')) // Label kustom Anda
            ->required()
            ->autocomplete()
            ->autofocus()
            ->type('text') // Paksa tipe HTML menjadi 'text'
            ->extraInputAttributes(['formnovalidate' => true]) // Matikan validasi HTML5 browser
            ->rules([
                'required',
                'string',
                'min:3',
                Rule::exists('users', 'email'), // Validasi keberadaan di kolom 'email' database
            ])
            ->statePath('email'); // Petakan input ke kunci 'email' di data form
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $loginInput = $data['email']; // NIP atau Email yang diinput pengguna
        $password = $data['password'];
        $remember = $data['remember'];

        // 1. Coba autentikasi pengguna menggunakan kolom 'email' (tempat NIP dan email berada).
        if (!Auth::attempt(['email' => $loginInput, 'password' => $password], $remember)) {
            throw ValidationException::withMessages([
                'data.email' => __('filament-panels::pages/auth/login.messages.failed'),
            ]);
        }

        // 2. Dapatkan pengguna yang baru saja berhasil login.
        $user = Auth::user();

        // 3. Logika khusus berdasarkan peran untuk Superadmin.
        if ($user->role === 'superadmin') {
            if (!filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'data.email' => __('Hanya Superadmin yang dapat login menggunakan email.'),
                ]);
            }
        }

        // 4. Jika semua validasi lolos dan autentikasi berhasil, kembalikan respons login Filament.
        return app(LoginResponse::class);
    }
}