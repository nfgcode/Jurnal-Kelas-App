<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the fields the high-fidelity screens display but the original
     * schema never modelled: room/capacity, subject grouping, lesson-period
     * (JP) numbering and teacher attendance. Account lifecycle and the ketua
     * kelas flag now belong to `users` and `siswa` respectively, and are
     * declared where those tables are created.
     */
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->unsignedSmallInteger('kapasitas')->default(36)->after('jurusan');
        });

        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->enum('kelompok', ['wajib', 'peminatan', 'muatan_lokal', 'kejuruan'])
                ->default('wajib')
                ->after('kode');
            $table->unsignedTinyInteger('jp_per_minggu')->default(2)->after('kelompok');
        });

        Schema::table('jadwal', function (Blueprint $table) {
            // JP = jam pelajaran. The screens address periods by number
            // ("JP 1-2"), not by wall-clock time.
            $table->unsignedTinyInteger('jam_ke_mulai')->default(1)->after('hari');
            $table->unsignedTinyInteger('jam_ke_selesai')->default(2)->after('jam_ke_mulai');
        });

        Schema::table('jurnal', function (Blueprint $table) {
            $table->text('tugas')->nullable()->after('materi');

            // Teacher attendance for this meeting — a fact independent of the
            // student roster. Split across three columns because the guru and
            // the ketua kelas report different things about the same absence:
            // the guru records whether work was left behind, the student
            // records the stated reason.
            $table->enum('kehadiran_guru_status', ['hadir', 'tidak_hadir'])->default('hadir')->after('catatan');
            $table->enum('kehadiran_guru_alasan', ['sakit', 'izin', 'alpa'])->nullable()->after('kehadiran_guru_status');
            $table->boolean('kehadiran_guru_ada_tugas')->nullable()->after('kehadiran_guru_alasan');
            $table->text('kehadiran_guru_keterangan')->nullable()->after('kehadiran_guru_ada_tugas');

            // Null means the assigned guru filled it themselves.
            $table->foreignId('diisi_oleh_id')->nullable()->after('guru_nip')->constrained('users')->nullOnDelete();
        });

        // The hi-fi journal form asks for Materi and Tugas, not Kegiatan, so
        // the column can no longer be required.
        Schema::table('jurnal', function (Blueprint $table) {
            $table->text('kegiatan')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->dropForeign(['diisi_oleh_id']);
            $table->dropColumn([
                'tugas',
                'kehadiran_guru_status',
                'kehadiran_guru_alasan',
                'kehadiran_guru_ada_tugas',
                'kehadiran_guru_keterangan',
                'diisi_oleh_id',
            ]);
        });

        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropColumn(['jam_ke_mulai', 'jam_ke_selesai']);
        });

        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->dropColumn(['kelompok', 'jp_per_minggu']);
        });

        Schema::table('kelas', function (Blueprint $table) {
            $table->dropColumn(['kapasitas']);
        });
    }
};
