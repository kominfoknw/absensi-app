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
        Schema::create('lapkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // User yang membuat lapkin
            $table->foreignId('pegawai_id')->constrained('pegawais')->onDelete('cascade');
            $table->foreignId('kantor_id')->constrained('kantors')->onDelete('cascade');
            $table->string('hari'); // Contoh: Senin, Selasa
            $table->date('tanggal');
            $table->string('nama_kegiatan');
            $table->string('tempat');
            $table->string('target')->nullable();
            $table->string('output')->nullable();
            $table->string('lampiran')->nullable(); // Path file lampiran
            $table->string('kualitas_hasil')->nullable(); // Contoh: Baik, Cukup, Kurang
            $table->timestamps();

            // Tambahkan unique constraint untuk mencegah lapkin ganda di tanggal yang sama oleh pegawai yang sama
            $table->unique(['pegawai_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lapkins');
    }
};