<?php

use App\Support\PendaftaranPengguna;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registering a person is now two inserts across two tables — the person row in
 * `guru`/`siswa`, and the login row in `users`. Half of that landing is worse
 * than none of it: a teacher with no account cannot sign in, and an account
 * with no teacher is a credential pointing at nobody.
 *
 * So the pair is done inside a stored procedure with an EXIT HANDLER that rolls
 * back and re-raises, which also gives the duplicate checks somewhere to live
 * that the database enforces regardless of who is writing — the app, an import
 * script, or an admin poking at phpMyAdmin.
 *
 * MySQL-only, like the rest of the stored objects here. {@see PendaftaranPengguna}
 * holds the portable transaction the SQLite test database runs instead, and
 * translates the SIGNAL messages below into validation errors either way.
 */
return new class extends Migration
{
    /**
     * Text parameters must be declared with the same charset and collation as
     * the columns they are compared against. A routine parameter otherwise
     * inherits the *server* default (utf8mb4_0900_ai_ci on MySQL 8), and
     * `nip = TRIM(p_nip)` against a utf8mb4_unicode_ci column fails outright
     * with "illegal mix of collations" rather than merely sorting oddly.
     */
    private string $kolasi = '';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $charset = DB::connection()->getConfig('charset') ?: 'utf8mb4';
        $collation = DB::connection()->getConfig('collation') ?: 'utf8mb4_unicode_ci';
        $this->kolasi = " CHARACTER SET {$charset} COLLATE {$collation}";

        $this->fungsi();
        $this->trigger();
        $this->prosedur();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['sp_tambah_guru', 'sp_tambah_siswa'] as $sp) {
            DB::unprepared("DROP PROCEDURE IF EXISTS {$sp}");
        }

        foreach ([
            'trg_guru_before_insert', 'trg_guru_before_update',
            'trg_siswa_before_insert', 'trg_siswa_before_update',
            'trg_users_before_insert', 'trg_users_before_update',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        foreach (['fn_nama_duplikat_guru', 'fn_nama_duplikat_siswa'] as $fn) {
            DB::unprepared("DROP FUNCTION IF EXISTS {$fn}");
        }
    }

    /**
     * Duplicate-name counters. Split from the procedures so the *form* can ask
     * the same question before submitting ("2 siswa lain di kelas ini bernama
     * sama — lanjutkan?") and get an answer computed the one way.
     *
     * A student is compared within their class and a teacher school-wide,
     * because those are the scopes where a repeated name is evidence of a
     * double entry rather than a coincidence.
     */
    private function fungsi(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS fn_nama_duplikat_guru');
        DB::unprepared("
            CREATE FUNCTION fn_nama_duplikat_guru(p_nama VARCHAR(255){$this->kolasi})
                RETURNS INT
                READS SQL DATA
            BEGIN
                DECLARE v_jumlah INT;
                SELECT COUNT(*) INTO v_jumlah FROM guru WHERE nama = TRIM(p_nama);
                RETURN IFNULL(v_jumlah, 0);
            END
        ");

        DB::unprepared('DROP FUNCTION IF EXISTS fn_nama_duplikat_siswa');
        DB::unprepared("
            CREATE FUNCTION fn_nama_duplikat_siswa(p_nama VARCHAR(255){$this->kolasi}, p_kelas_id BIGINT)
                RETURNS INT
                READS SQL DATA
            BEGIN
                DECLARE v_jumlah INT;
                SELECT COUNT(*) INTO v_jumlah
                    FROM siswa
                    WHERE nama = TRIM(p_nama)
                      AND (kelas_id <=> p_kelas_id);
                RETURN IFNULL(v_jumlah, 0);
            END
        ");
    }

    /**
     * Invariants that must hold whoever does the writing.
     *
     * The person triggers normalise the name and refuse an empty one or a
     * too-short identifier — a blank NIS is the shape a bad CSV import takes.
     *
     * The account triggers enforce the rule the split exists to express: a guru
     * account points at a `guru` row, a siswa account at a `siswa` row, and an
     * admin at neither and so carries their own name. Without this the three
     * tables would drift back into the mess they replaced, one mislabelled row
     * at a time.
     */
    private function trigger(): void
    {
        foreach (['insert' => 'BEFORE INSERT', 'update' => 'BEFORE UPDATE'] as $nama => $waktu) {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_guru_before_{$nama}");
            DB::unprepared("
                CREATE TRIGGER trg_guru_before_{$nama}
                {$waktu} ON guru
                FOR EACH ROW
                BEGIN
                    SET NEW.nama = TRIM(NEW.nama);
                    SET NEW.nip  = TRIM(NEW.nip);

                    IF NEW.nama IS NULL OR NEW.nama = '' THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NAMA_KOSONG';
                    END IF;

                    IF CHAR_LENGTH(NEW.nip) < 4 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NIP_TIDAK_VALID';
                    END IF;
                END
            ");

            DB::unprepared("DROP TRIGGER IF EXISTS trg_siswa_before_{$nama}");
            DB::unprepared("
                CREATE TRIGGER trg_siswa_before_{$nama}
                {$waktu} ON siswa
                FOR EACH ROW
                BEGIN
                    SET NEW.nama = TRIM(NEW.nama);
                    SET NEW.nis  = TRIM(NEW.nis);

                    IF NEW.nama IS NULL OR NEW.nama = '' THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NAMA_KOSONG';
                    END IF;

                    IF CHAR_LENGTH(NEW.nis) < 4 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NIS_TIDAK_VALID';
                    END IF;
                END
            ");

            DB::unprepared("DROP TRIGGER IF EXISTS trg_users_before_{$nama}");
            DB::unprepared("
                CREATE TRIGGER trg_users_before_{$nama}
                {$waktu} ON users
                FOR EACH ROW
                BEGIN
                    IF NEW.role = 'guru' AND (NEW.nip IS NULL OR NEW.nis IS NOT NULL) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AKUN_GURU_BUTUH_NIP';
                    END IF;

                    IF NEW.role = 'siswa' AND (NEW.nis IS NULL OR NEW.nip IS NOT NULL) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AKUN_SISWA_BUTUH_NIS';
                    END IF;

                    IF NEW.role = 'admin' AND (NEW.nip IS NOT NULL OR NEW.nis IS NOT NULL) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AKUN_ADMIN_TANPA_NIP_NIS';
                    END IF;

                    -- The name lives in exactly one place: on the person row for
                    -- guru and siswa, on the account for an admin.
                    IF NEW.role = 'admin' AND (NEW.nama IS NULL OR TRIM(NEW.nama) = '') THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AKUN_ADMIN_BUTUH_NAMA';
                    END IF;

                    IF NEW.role <> 'admin' AND NEW.nama IS NOT NULL THEN
                        SET NEW.nama = NULL;
                    END IF;
                END
            ");
        }
    }

    /**
     * The registrations themselves. Each one checks first and inserts second,
     * so the caller gets 'NIS_SUDAH_ADA' rather than a raw 1062 duplicate-key
     * error, and both inserts commit together or neither does.
     *
     * `p_izinkan_nama_sama` exists because a repeated name is suspicious, not
     * impossible — two students really can be called Muhammad Rizki. The
     * default answer is to refuse; the admin can override once they have looked.
     */
    private function prosedur(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_tambah_guru');
        DB::unprepared("
            CREATE PROCEDURE sp_tambah_guru(
                IN p_nip VARCHAR(20){$this->kolasi},
                IN p_nama VARCHAR(255){$this->kolasi},
                IN p_jenis_kelamin VARCHAR(1){$this->kolasi},
                IN p_no_hp VARCHAR(20){$this->kolasi},
                IN p_alamat TEXT{$this->kolasi},
                IN p_username VARCHAR(255){$this->kolasi},
                IN p_email VARCHAR(255){$this->kolasi},
                IN p_password VARCHAR(255){$this->kolasi},
                IN p_izinkan_nama_sama TINYINT
            )
            BEGIN
                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    RESIGNAL;
                END;

                IF EXISTS (SELECT 1 FROM guru WHERE nip = TRIM(p_nip)) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NIP_SUDAH_ADA';
                END IF;

                IF EXISTS (SELECT 1 FROM users WHERE username = p_username) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'USERNAME_SUDAH_ADA';
                END IF;

                IF EXISTS (SELECT 1 FROM users WHERE email = p_email) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'EMAIL_SUDAH_ADA';
                END IF;

                IF p_izinkan_nama_sama = 0 AND fn_nama_duplikat_guru(p_nama) > 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NAMA_SUDAH_ADA';
                END IF;

                START TRANSACTION;
                    INSERT INTO guru (nip, nama, jenis_kelamin, no_hp, alamat, status, created_at, updated_at)
                    VALUES (TRIM(p_nip), p_nama, p_jenis_kelamin, p_no_hp, p_alamat, 'aktif', NOW(), NOW());

                    INSERT INTO users (username, nama, email, role, status, nip, password, created_at, updated_at)
                    VALUES (p_username, NULL, p_email, 'guru', 'aktif', TRIM(p_nip), p_password, NOW(), NOW());
                COMMIT;
            END
        ");

        DB::unprepared('DROP PROCEDURE IF EXISTS sp_tambah_siswa');
        DB::unprepared("
            CREATE PROCEDURE sp_tambah_siswa(
                IN p_nis VARCHAR(20){$this->kolasi},
                IN p_nisn VARCHAR(20){$this->kolasi},
                IN p_nama VARCHAR(255){$this->kolasi},
                IN p_jenis_kelamin VARCHAR(1){$this->kolasi},
                IN p_kelas_id BIGINT,
                IN p_no_hp VARCHAR(20){$this->kolasi},
                IN p_alamat TEXT{$this->kolasi},
                IN p_username VARCHAR(255){$this->kolasi},
                IN p_email VARCHAR(255){$this->kolasi},
                IN p_password VARCHAR(255){$this->kolasi},
                IN p_izinkan_nama_sama TINYINT
            )
            BEGIN
                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    RESIGNAL;
                END;

                IF EXISTS (SELECT 1 FROM siswa WHERE nis = TRIM(p_nis)) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NIS_SUDAH_ADA';
                END IF;

                IF p_nisn IS NOT NULL AND EXISTS (SELECT 1 FROM siswa WHERE nisn = TRIM(p_nisn)) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NISN_SUDAH_ADA';
                END IF;

                IF EXISTS (SELECT 1 FROM users WHERE username = p_username) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'USERNAME_SUDAH_ADA';
                END IF;

                IF EXISTS (SELECT 1 FROM users WHERE email = p_email) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'EMAIL_SUDAH_ADA';
                END IF;

                IF p_izinkan_nama_sama = 0 AND fn_nama_duplikat_siswa(p_nama, p_kelas_id) > 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NAMA_SUDAH_ADA';
                END IF;

                START TRANSACTION;
                    INSERT INTO siswa (nis, nisn, nama, jenis_kelamin, kelas_id, no_hp, alamat, status, created_at, updated_at)
                    VALUES (TRIM(p_nis), NULLIF(TRIM(IFNULL(p_nisn, '')), ''), p_nama, p_jenis_kelamin, p_kelas_id, p_no_hp, p_alamat, 'aktif', NOW(), NOW());

                    INSERT INTO users (username, nama, email, role, status, nis, password, created_at, updated_at)
                    VALUES (p_username, NULL, p_email, 'siswa', 'aktif', TRIM(p_nis), p_password, NOW(), NOW());
                COMMIT;
            END
        ");
    }
};
