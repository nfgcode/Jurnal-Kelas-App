<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropForeign(['jurusan_kode']);
            $table->foreign('jurusan_kode')->references('kode')->on('jurusan')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->string('jurusan_kode', 20)->nullable();
            $table->index('jurusan_kode', 'siswa_jurusan_kode_index');
            $table->foreign('jurusan_kode')->references('kode')->on('jurusan')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::table('jurnal', function (Blueprint $table) {
            $table->string('siswa_nis', 20)->nullable();
            $table->index('siswa_nis', 'jurnal_siswa_nis_index');
            $table->foreign('siswa_nis')->references('nis')->on('siswa')->restrictOnDelete();
        });

        DB::table('siswa')->update([
            'jurusan_kode' => DB::raw('(SELECT jurusan_kode FROM kelas WHERE kelas.id = siswa.kelas_id)'),
        ]);
        DB::table('jurnal')->update([
            'siswa_nis' => DB::raw("(SELECT nis FROM users WHERE users.id = jurnal.diisi_oleh_id AND users.role = 'siswa')"),
        ]);
    }

    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->dropForeign(['siswa_nis']);
            $table->dropIndex('jurnal_siswa_nis_index');
            $table->dropColumn('siswa_nis');
        });
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropForeign(['jurusan_kode']);
            $table->dropIndex('siswa_jurusan_kode_index');
            $table->dropColumn('jurusan_kode');
        });
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropForeign(['jurusan_kode']);
            $table->foreign('jurusan_kode')->references('kode')->on('jurusan')->nullOnDelete();
        });
    }
};
