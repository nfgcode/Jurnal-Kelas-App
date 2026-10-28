<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_kelas_siswa', function (Blueprint $table) {
            $table->id();
            $table->string('siswa_nis', 20);
            $table->unsignedBigInteger('kelas_id')->nullable();
            $table->string('tahun_ajaran_kode', 9);
            $table->enum('tingkat', ['X', 'XI', 'XII'])->nullable();
            $table->enum('status', ['aktif', 'lulus', 'pindah', 'tinggal_kelas'])->default('aktif');
            $table->boolean('aktif')->default(true);
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('siswa_nis')->references('nis')->on('siswa')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('kelas_id')->references('id')->on('kelas')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('tahun_ajaran_kode')->references('kode')->on('tahun_ajaran')
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->string('siswa_nis_aktif', 20)->virtualAs("CASE WHEN aktif = 1 THEN siswa_nis ELSE NULL END")->nullable();
            $table->unique('siswa_nis_aktif', 'riwayat_siswa_aktif_unique');

            $table->index(['siswa_nis', 'aktif'], 'riwayat_siswa_aktif_index');
            $table->index(['kelas_id', 'aktif'], 'riwayat_kelas_aktif_index');
            $table->index(['siswa_nis', 'tahun_ajaran_kode'], 'riwayat_siswa_tahun_index');
            $table->index(['kelas_id', 'tahun_ajaran_kode'], 'riwayat_kelas_tahun_index');
            $table->index(['tahun_ajaran_kode', 'tingkat'], 'riwayat_tahun_tingkat_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_kelas_siswa');
    }
};
