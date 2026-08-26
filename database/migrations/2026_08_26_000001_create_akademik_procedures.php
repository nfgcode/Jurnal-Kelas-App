<?php

use App\Support\PencatatanAkademik;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stored procedures for the three master records an admin adds by hand:
 * a class, a subject, and a timetable slot.
 *
 * Two of them do genuinely multi-step work — a subject is written together with
 * the teachers certified for it, and a slot is checked against three different
 * clashes before it lands. The third, a class, is a single INSERT that does not
 * need a transaction to be atomic; what it needs is for its check and its write
 * to be *one* step, so two admins submitting "XII TKJ 2" at the same moment
 * cannot both pass the check and both insert.
 *
 * That is the real argument for putting these in the database rather than in
 * PHP: the rule then applies to every writer that reaches the table — the form,
 * the API, a bulk import, or someone typing SQL into phpMyAdmin — and the
 * check-then-write pair stops being a race.
 *
 * MySQL-only, like the rest of the stored objects here. {@see PencatatanAkademik}
 * holds the portable transaction the SQLite test database runs instead, and
 * translates the SIGNAL messages below into validation errors either way.
 */
return new class extends Migration
{
    /**
     * Text parameters must carry the same charset and collation as the columns
     * they are compared against, or MySQL refuses the comparison outright with
     * "illegal mix of collations". See the pengguna procedures for the full story.
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

        $this->kelas();
        $this->mataPelajaran();
        $this->jadwal();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['sp_tambah_kelas', 'sp_tambah_mata_pelajaran', 'sp_tambah_jadwal'] as $sp) {
            DB::unprepared("DROP PROCEDURE IF EXISTS {$sp}");
        }
    }

    /**
     * A rombel is identified by (tahun ajaran, tingkat, jurusan, paralel).
     * `kelas_rombel_unique` already refuses a duplicate; reading the row range
     * inside the transaction is what stops two simultaneous inserts from both
     * getting past the check and one of them dying on the key instead of being
     * told what was wrong.
     */
    private function kelas(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_tambah_kelas');
        DB::unprepared("
            CREATE PROCEDURE sp_tambah_kelas(
                IN p_nama VARCHAR(255){$this->kolasi},
                IN p_tingkat VARCHAR(4){$this->kolasi},
                IN p_jurusan VARCHAR(20){$this->kolasi},
                IN p_paralel TINYINT UNSIGNED,
                IN p_ruangan VARCHAR(20){$this->kolasi},
                IN p_kapasitas SMALLINT UNSIGNED,
                IN p_tahun VARCHAR(9){$this->kolasi},
                IN p_wali VARCHAR(20){$this->kolasi},
                OUT p_id BIGINT
            )
            BEGIN
                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    RESIGNAL;
                END;

                START TRANSACTION;
                    IF NOT EXISTS (SELECT 1 FROM tahun_ajaran WHERE kode = p_tahun) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'TAHUN_AJARAN_TIDAK_ADA';
                    END IF;

                    IF p_jurusan IS NOT NULL AND NOT EXISTS (SELECT 1 FROM jurusan WHERE kode = p_jurusan) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'JURUSAN_TIDAK_ADA';
                    END IF;

                    IF p_wali IS NOT NULL AND NOT EXISTS (SELECT 1 FROM guru WHERE nip = p_wali AND status = 'aktif') THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'WALI_TIDAK_AKTIF';
                    END IF;

                    -- FOR UPDATE takes the gap lock that makes this check and the
                    -- insert below one step rather than two.
                    IF EXISTS (
                        SELECT 1 FROM kelas
                        WHERE tahun_ajaran_kode = p_tahun
                          AND tingkat = p_tingkat
                          AND (jurusan_kode <=> p_jurusan)
                          AND paralel = p_paralel
                        FOR UPDATE
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ROMBEL_SUDAH_ADA';
                    END IF;

                    INSERT INTO kelas
                        (nama_kelas, tingkat, jurusan_kode, paralel, ruangan_kode,
                         kapasitas, tahun_ajaran_kode, wali_kelas_nip, qr_token, created_at, updated_at)
                    VALUES
                        (p_nama, p_tingkat, p_jurusan, p_paralel, p_ruangan,
                         p_kapasitas, p_tahun, p_wali, UUID(), NOW(), NOW());

                    SET p_id = LAST_INSERT_ID();
                COMMIT;
            END
        ");
    }

    /**
     * A subject and the teachers certified for it, written together.
     *
     * Genuinely two tables: without the transaction a failure halfway leaves a
     * subject nobody is recorded as able to teach, which the schedule form then
     * refuses every teacher for.
     */
    private function mataPelajaran(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_tambah_mata_pelajaran');
        DB::unprepared("
            CREATE PROCEDURE sp_tambah_mata_pelajaran(
                IN p_nama VARCHAR(255){$this->kolasi},
                IN p_kode VARCHAR(20){$this->kolasi},
                IN p_kelompok VARCHAR(20){$this->kolasi},
                IN p_jp TINYINT UNSIGNED,
                IN p_deskripsi TEXT{$this->kolasi},
                IN p_guru JSON,
                OUT p_id BIGINT
            )
            BEGIN
                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    RESIGNAL;
                END;

                START TRANSACTION;
                    IF EXISTS (SELECT 1 FROM mata_pelajaran WHERE kode = TRIM(p_kode) FOR UPDATE) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'KODE_MAPEL_SUDAH_ADA';
                    END IF;

                    INSERT INTO mata_pelajaran (nama, kode, kelompok, jp_per_minggu, deskripsi, created_at, updated_at)
                    VALUES (TRIM(p_nama), TRIM(p_kode), p_kelompok, p_jp, p_deskripsi, NOW(), NOW());

                    SET p_id = LAST_INSERT_ID();

                    IF p_guru IS NOT NULL AND JSON_LENGTH(p_guru) > 0 THEN
                        -- Every NIP must exist, or the pairing would name a
                        -- teacher the school does not have.
                        IF (SELECT COUNT(*) FROM JSON_TABLE(p_guru, '$[*]' COLUMNS (
                                nip VARCHAR(20){$this->kolasi} PATH '$'
                            )) jt
                            LEFT JOIN guru g ON g.nip = jt.nip
                            WHERE g.nip IS NULL) > 0 THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GURU_TIDAK_DIKENAL';
                        END IF;

                        INSERT INTO guru_mata_pelajaran (guru_nip, mata_pelajaran_id, utama, created_at, updated_at)
                        SELECT jt.nip, p_id, 0, NOW(), NOW()
                        FROM JSON_TABLE(p_guru, '$[*]' COLUMNS (
                            nip VARCHAR(20){$this->kolasi} PATH '$'
                        )) jt;
                    END IF;
                COMMIT;
            END
        ");
    }

    /**
     * A timetable slot, refused if it puts a class, a teacher or a room in two
     * places at once — or if it pairs a teacher with a subject the school has
     * not recorded them as teaching.
     *
     * The overlap test is a range comparison, not an equality: JP 10-11 and
     * JP 11-12 share a period without sharing a start, which the unique keys on
     * (…, jam_ke_mulai) cannot see. The keys remain as the last line of defence;
     * this is what turns a clash into an answer.
     */
    private function jadwal(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sp_tambah_jadwal');
        DB::unprepared("
            CREATE PROCEDURE sp_tambah_jadwal(
                IN p_kelas BIGINT,
                IN p_mapel BIGINT,
                IN p_guru VARCHAR(20){$this->kolasi},
                IN p_hari VARCHAR(10){$this->kolasi},
                IN p_mulai TINYINT UNSIGNED,
                IN p_selesai TINYINT UNSIGNED,
                IN p_ruangan VARCHAR(20){$this->kolasi},
                OUT p_id BIGINT
            )
            BEGIN
                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    RESIGNAL;
                END;

                START TRANSACTION;
                    IF p_selesai < p_mulai THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'JP_TERBALIK';
                    END IF;

                    IF NOT EXISTS (
                        SELECT 1 FROM guru_mata_pelajaran
                        WHERE guru_nip = TRIM(p_guru) AND mata_pelajaran_id = p_mapel
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GURU_TIDAK_MENGAMPU';
                    END IF;

                    IF EXISTS (
                        SELECT 1 FROM jadwal
                        WHERE kelas_id = p_kelas AND hari = p_hari
                          AND jam_ke_mulai <= p_selesai AND jam_ke_selesai >= p_mulai
                        FOR UPDATE
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'KELAS_BENTROK';
                    END IF;

                    IF EXISTS (
                        SELECT 1 FROM jadwal
                        WHERE guru_nip = TRIM(p_guru) AND hari = p_hari
                          AND jam_ke_mulai <= p_selesai AND jam_ke_selesai >= p_mulai
                        FOR UPDATE
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'GURU_BENTROK';
                    END IF;

                    IF p_ruangan IS NOT NULL AND EXISTS (
                        SELECT 1 FROM jadwal
                        WHERE ruangan_kode = p_ruangan AND hari = p_hari
                          AND jam_ke_mulai <= p_selesai AND jam_ke_selesai >= p_mulai
                        FOR UPDATE
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'RUANGAN_BENTROK';
                    END IF;

                    INSERT INTO jadwal
                        (kelas_id, mata_pelajaran_id, guru_nip, hari, jam_ke_mulai, jam_ke_selesai, ruangan_kode, created_at, updated_at)
                    VALUES
                        (p_kelas, p_mapel, TRIM(p_guru), p_hari, p_mulai, p_selesai, p_ruangan, NOW(), NOW());

                    SET p_id = LAST_INSERT_ID();
                COMMIT;
            END
        ");
    }
};
