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
        Schema::create('kehadirans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Mengacu ke tabel users untuk user yang login (pegawai)
            $table->foreignId('pegawai_id')->constrained('pegawais')->onDelete('cascade'); // Mengacu ke tabel users untuk user yang login (pegawai)
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null'); // Mengacu ke tabel shifts
            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->boolean('telat')->default(false); // true jika telat, false jika tidak
            $table->string('lat_masuk')->nullable();
            $table->string('long_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->boolean('pulang_cepat')->default(false); // true jika pulang cepat, false jika tidak
            $table->string('lat_pulang')->nullable();
            $table->string('long_pulang')->nullable();
            $table->enum('status', ['hadir', 'alpha', 'izin', 'sakit'])->default('alpha'); // Status kehadiran
            $table->text('keterangan')->nullable();
            $table->timestamps();

            // Menambahkan unique constraint agar satu user hanya bisa memiliki satu record kehadiran per tanggal
            $table->unique(['user_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kehadirans');
    }
};