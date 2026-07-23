<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AFTER UPDATE / AFTER DELETE triggers on jurnal that record a row in
 * jurnal_audit (MySQL-only). The update trigger only logs when materi actually
 * changed (null-safe <=> comparison). On SQLite the audit table stays empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_jurnal_after_update');
        DB::unprepared("
            CREATE TRIGGER trg_jurnal_after_update
            AFTER UPDATE ON jurnal
            FOR EACH ROW
            BEGIN
                IF NOT (OLD.materi <=> NEW.materi) THEN
                    INSERT INTO jurnal_audit (jurnal_id, aksi, materi_lama, materi_baru, diubah_pada)
                    VALUES (NEW.id, 'update', OLD.materi, NEW.materi, NOW());
                END IF;
            END
        ");

        DB::unprepared('DROP TRIGGER IF EXISTS trg_jurnal_after_delete');
        DB::unprepared("
            CREATE TRIGGER trg_jurnal_after_delete
            AFTER DELETE ON jurnal
            FOR EACH ROW
            BEGIN
                INSERT INTO jurnal_audit (jurnal_id, aksi, materi_lama, materi_baru, diubah_pada)
                VALUES (OLD.id, 'delete', OLD.materi, NULL, NOW());
            END
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_jurnal_after_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_jurnal_after_delete');
    }
};
