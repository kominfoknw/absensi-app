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
        Schema::table('lapkins', function (Blueprint $table) {
            // Ubah tipe kolom dari string menjadi integer
            // default(0) agar nilai awal menjadi 0
            // Setelah diubah, tambahkan nullable(true) jika memungkinkan untuk kasus awal
            $table->integer('kualitas_hasil')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lapkins', function (Blueprint $table) {
            // Kembalikan ke tipe string jika diperlukan untuk rollback
            $table->string('kualitas_hasil')->nullable()->change();
        });
    }
};