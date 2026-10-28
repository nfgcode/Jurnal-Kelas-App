<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transisi_tahun_ajaran', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_asal', 9);
            $table->string('tahun_tujuan', 9);
            $table->enum('status', ['persiapan', 'diproses', 'selesai', 'gagal'])->default('persiapan');
            $table->unsignedInteger('jumlah_siswa_dipromosikan')->default(0);
            $table->unsignedInteger('jumlah_siswa_lulus')->default(0);
            $table->unsignedInteger('jumlah_siswa_tinggal')->default(0);
            $table->text('catatan')->nullable();
            $table->foreignId('diproses_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_pada')->nullable();
            $table->timestamps();

            $table->foreign('tahun_asal')->references('kode')->on('tahun_ajaran')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('tahun_tujuan')->references('kode')->on('tahun_ajaran')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->unique(['tahun_asal', 'tahun_tujuan'], 'transisi_tahun_asal_tujuan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transisi_tahun_ajaran');
    }
};
