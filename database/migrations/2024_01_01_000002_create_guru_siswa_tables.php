<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two tables that hold *people*, keyed by the identifier the school
     * already issues them: NIP for a teacher, NIS for a student.
     *
     * These are natural primary keys on purpose. A NIS is not an internal
     * detail the way an auto-increment id is — it is printed on the report
     * card, typed into the attendance sheet and quoted between staff. Using it
     * as the key means a roster row names the student it belongs to, and no
     * join is needed to find out which one.
     *
     * `users` is finished off here rather than in its own migration because the
     * account can only point at a person once the person tables exist.
     */
    public function up(): void
    {
        Schema::create('guru', function (Blueprint $table) {
            $table->string('nip', 20)->primary();
            $table->string('nama');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();

            $table->index('nama', 'guru_nama_index');
            $table->index('status', 'guru_status_index');
        });

        Schema::create('siswa', function (Blueprint $table) {
            $table->string('nis', 20)->primary();
            // NISN is the national number; NIS is the school's own. Kept apart
            // because a transfer student arrives with one and not the other.
            $table->string('nisn', 20)->nullable()->unique();
            $table->string('nama');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            // A class has one ketua kelas; they are the student allowed to fill
            // the class journal on the teacher's behalf.
            $table->boolean('is_ketua_kelas')->default(false);
            $table->string('no_hp', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->enum('status', ['aktif', 'nonaktif', 'lulus'])->default('aktif');
            $table->timestamps();

            $table->index('nama', 'siswa_nama_index');
            $table->index(['kelas_id', 'nama'], 'siswa_kelas_nama_index');
            $table->index('status', 'siswa_status_index');
        });

        Schema::table('users', function (Blueprint $table) {
            // Only an admin carries their name on the account: they are not a
            // teacher or a student, so no person row describes them. For every
            // other account this stays NULL and the name is read through the
            // relation — one source of truth per person, which is the whole
            // point of the split.
            $table->string('nama')->nullable()->after('username');
            $table->enum('role', ['admin', 'guru', 'siswa'])->default('siswa')->after('email');
            $table->enum('status', ['aktif', 'nonaktif', 'pending'])->default('aktif')->after('role');
            $table->string('nip', 20)->nullable()->unique()->after('status');
            $table->string('nis', 20)->nullable()->unique()->after('nip');
            $table->timestamp('last_active_at')->nullable()->after('nis');

            // One account per person, at most. Deleting the person deletes the
            // login with them — an account for a student who no longer exists
            // has nothing left to authorise.
            $table->foreign('nip')->references('nip')->on('guru')->cascadeOnDelete();
            $table->foreign('nis')->references('nis')->on('siswa')->cascadeOnDelete();
        });

        Schema::table('kelas', function (Blueprint $table) {
            $table->foreign('wali_kelas_nip')->references('nip')->on('guru')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropForeign(['wali_kelas_nip']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['nip']);
            $table->dropForeign(['nis']);
            $table->dropColumn(['nama', 'role', 'status', 'nip', 'nis', 'last_active_at']);
        });

        Schema::dropIfExists('siswa');
        Schema::dropIfExists('guru');
    }
};
