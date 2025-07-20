<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pegawais', function (Blueprint $table) {
            // Tambahkan kolom 'jabatan'
            $table->string('jabatan')->nullable()->after('nama'); // Contoh: setelah kolom 'nama'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawais', function (Blueprint $table) {
            // Hapus kolom 'jabatan' jika migrasi di-rollback
            $table->dropColumn('jabatan');
        });
    }
};