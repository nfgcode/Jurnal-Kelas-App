<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            $table->string('siswa_nis', 20)->unique();
            $table->string('tahun_lulus', 9)->index();
            $table->date('tanggal_lulus')->nullable();
            $table->foreignId('kelas_terakhir_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('siswa_nis')->references('nis')->on('siswa')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('tahun_lulus')->references('kode')->on('tahun_ajaran')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni');
    }
};
