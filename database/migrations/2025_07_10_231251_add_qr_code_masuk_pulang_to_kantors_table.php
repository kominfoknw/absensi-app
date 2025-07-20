<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantors', function (Blueprint $table) {
            // Hapus kolom lama jika ada untuk diganti dengan yang spesifik (jika Anda ingin bersih)
            // $table->dropColumn('qr_code_secret');
            // $table->dropColumn('qr_code_generated_at');

            // QR Code untuk Absen Masuk
            $table->string('qr_code_secret_masuk')->nullable()->unique()->after('nama_kantor');
            $table->timestamp('qr_code_masuk_generated_at')->nullable()->after('qr_code_secret_masuk');

            // QR Code untuk Absen Pulang
            $table->string('qr_code_secret_pulang')->nullable()->unique()->after('qr_code_masuk_generated_at');
            $table->timestamp('qr_code_pulang_generated_at')->nullable()->after('qr_code_secret_pulang');
        });
    }

    public function down(): void
    {
        Schema::table('kantors', function (Blueprint $table) {
            $table->dropColumn('qr_code_secret_masuk');
            $table->dropColumn('qr_code_masuk_generated_at');
            $table->dropColumn('qr_code_secret_pulang');
            $table->dropColumn('qr_code_pulang_generated_at');

            // Jika Anda menghapus kolom lama di up(), pertimbangkan untuk menambahkannya kembali di down()
            // $table->string('qr_code_secret')->nullable()->unique();
            // $table->timestamp('qr_code_generated_at')->nullable();
        });
    }
};