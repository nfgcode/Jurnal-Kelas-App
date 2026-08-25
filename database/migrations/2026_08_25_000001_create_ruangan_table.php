<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rooms were free text on two tables: `kelas.ruang` and `jadwal.ruang`.
     * Nothing stopped "Lab RPL 2", "lab rpl 2" and "LabRPL2" from being three
     * different rooms, nobody could ask how many seats a room has, and a room
     * being renamed meant editing every timetable row that mentioned it.
     *
     * Keyed by the code painted on the door (`R-101`, `LAB-RPL-1`) for the same
     * reason `siswa` is keyed by NIS: it is the identifier people already use.
     */
    public function up(): void
    {
        Schema::create('ruangan', function (Blueprint $table) {
            $table->string('kode', 20)->primary();
            $table->string('nama');
            $table->enum('jenis', [
                'kelas', 'laboratorium', 'bengkel', 'aula',
                'perpustakaan', 'olahraga', 'lainnya',
            ])->default('kelas');
            $table->unsignedSmallInteger('kapasitas')->default(36);
            $table->string('gedung')->nullable();
            $table->unsignedTinyInteger('lantai')->nullable();
            $table->text('keterangan')->nullable();
            $table->enum('status', ['aktif', 'perbaikan', 'nonaktif'])->default('aktif');
            $table->timestamps();

            $table->index('jenis', 'ruangan_jenis_index');
            $table->index('status', 'ruangan_status_index');
        });

        // Both references are nullable and release the room rather than delete
        // the class or the timetable slot: demolishing a room does not cancel
        // the lesson, it leaves it needing a new location.
        Schema::table('kelas', function (Blueprint $table) {
            $table->string('ruangan_kode', 20)->nullable()->after('jurusan');
            $table->foreign('ruangan_kode')->references('kode')->on('ruangan')->nullOnDelete();
        });

        Schema::table('jadwal', function (Blueprint $table) {
            $table->string('ruangan_kode', 20)->nullable()->after('jam_selesai');
            $table->foreign('ruangan_kode')->references('kode')->on('ruangan')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropForeign(['ruangan_kode']);
            $table->dropColumn('ruangan_kode');
        });

        Schema::table('kelas', function (Blueprint $table) {
            $table->dropForeign(['ruangan_kode']);
            $table->dropColumn('ruangan_kode');
        });

        Schema::dropIfExists('ruangan');
    }
};
