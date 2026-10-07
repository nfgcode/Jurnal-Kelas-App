<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $event => $suffix) {
                DB::unprepared("CREATE TRIGGER siswa_jurusan_{$suffix} BEFORE {$event} ON siswa FOR EACH ROW
                    BEGIN
                        DECLARE v_jurusan VARCHAR(20) DEFAULT NULL;
                        IF NEW.kelas_id IS NOT NULL THEN
                            SELECT jurusan_kode INTO v_jurusan FROM kelas WHERE id = NEW.kelas_id FOR SHARE;
                        END IF;
                        SET NEW.jurusan_kode = v_jurusan;
                    END");
            }
            DB::unprepared('CREATE TRIGGER kelas_jurusan_update AFTER UPDATE ON kelas FOR EACH ROW
                BEGIN
                    IF NOT (OLD.jurusan_kode <=> NEW.jurusan_kode) THEN
                        UPDATE siswa SET jurusan_kode = NEW.jurusan_kode WHERE kelas_id = NEW.id;
                    END IF;
                END');
            // MySQL FK cascades do not fire siswa triggers, so detach before deleting the class.
            DB::unprepared('CREATE TRIGGER kelas_jurusan_delete BEFORE DELETE ON kelas FOR EACH ROW
                UPDATE siswa SET kelas_id = NULL, jurusan_kode = NULL WHERE kelas_id = OLD.id');
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $event => $suffix) {
                DB::unprepared("CREATE TRIGGER siswa_jurusan_{$suffix} AFTER {$event} ON siswa
                    WHEN NEW.jurusan_kode IS NOT (SELECT jurusan_kode FROM kelas WHERE id = NEW.kelas_id)
                    BEGIN
                        UPDATE siswa SET jurusan_kode = (SELECT jurusan_kode FROM kelas WHERE id = NEW.kelas_id)
                        WHERE nis = NEW.nis;
                    END");
            }
            DB::unprepared('CREATE TRIGGER kelas_jurusan_update AFTER UPDATE OF jurusan_kode ON kelas
                BEGIN
                    UPDATE siswa SET jurusan_kode = NEW.jurusan_kode WHERE kelas_id = NEW.id;
                END');
            DB::unprepared('CREATE TRIGGER kelas_jurusan_delete BEFORE DELETE ON kelas
                BEGIN
                    UPDATE siswa SET kelas_id = NULL, jurusan_kode = NULL WHERE kelas_id = OLD.id;
                END');
        }
    }

    public function down(): void
    {
        foreach (['kelas_jurusan_delete', 'kelas_jurusan_update', 'siswa_jurusan_update', 'siswa_jurusan_insert'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
