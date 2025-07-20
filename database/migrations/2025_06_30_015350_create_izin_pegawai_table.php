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
        Schema::create('izin_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('User yang mengajukan/merekam izin');
            $table->foreignId('pegawai_id')->constrained('pegawais')->onDelete('cascade')->comment('Pegawai yang diajukan izinnya');
            $table->foreignId('kantor_id')->constrained('kantors')->onDelete('cascade')->comment('Kantor tempat pegawai berada');
            $table->string('nama_izin');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('file')->nullable()->comment('Path file pendukung');
            $table->enum('status', ['pending', 'diterima', 'ditolak'])->default('pending'); // Default status pending
            $table->text('keterangan')->nullable();
            $table->timestamps(); // created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('izin_pegawai');
    }
};