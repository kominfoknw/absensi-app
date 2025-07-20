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
            // Menambahkan kolom atasan_id sebagai foreign key ke tabel users
            $table->foreignId('atasan_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawais', function (Blueprint $table) {
            // Menghapus foreign key dan kolom atasan_id saat rollback
            $table->dropForeign(['atasan_id']);
            $table->dropColumn('atasan_id');
        });
    }
};