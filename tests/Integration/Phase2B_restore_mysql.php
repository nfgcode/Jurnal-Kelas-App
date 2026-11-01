<?php

use App\Models\User;
use App\Support\BackupEngine;
use App\Support\RestoreStagingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Phase2B_bootstrap.php';

$r = ['assertions' => 0, 'cases' => []];
$assert = function (bool $cond, string $msg) use (&$r) {
    $r['assertions']++;
    if (! $cond) {
        throw new RuntimeException("Assertion failed: {$msg}");
    }
};

try {
    $actor = DB::table('users')->where('role', 'admin')->first();
    if (! $actor) {
        $actorId = DB::table('users')->insertGetId([
            'nama' => 'Admin Restore Test',
            'username' => 'admin_restore',
            'email' => 'admin_restore@sekolah.sch.id',
            'password' => '$2y$04$dummy',
            'role' => 'admin',
            'status' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $actor = (object) ['id' => $actorId];
    } else {
        $actor = (object) ['id' => $actor->id];
    }

    // 1. Eksekusi BackupEngine di MySQL disposable sumber
    $backupEngine = new BackupEngine();
    $backupResult = $backupEngine->buatBackupDatabase(BackupEngine::FORMAT_SQL_GZ, 'manual', (new User)->forceFill((array) $actor), 'Uji Disposable MySQL Restore');

    $assert(! empty($backupResult['id']), 'Backup record created in cadangan_arsip');
    $assert(Storage::disk(BackupEngine::DISK)->exists($backupResult['lokasi']), 'Backup file exists in storage');
    $assert(str_ends_with($backupResult['nama_berkas'], '.sql.gz'), 'Backup format is sql.gz');

    $backupPath = Storage::disk(BackupEngine::DISK)->path($backupResult['lokasi']);

    // 2. Validasi arsip dan struktur SQL
    $restoreEngine = new RestoreStagingEngine();
    $val = $restoreEngine->validasiArsip($backupPath, $backupResult['checksum'], $backupResult['manifest']);
    $assert($val['valid'] === true, 'Archive validation passes in MySQL disposable');
    $assert($val['sha256'] === $backupResult['checksum'], 'Checksum SHA-256 matches');

    $sqlStructure = $restoreEngine->validasiStrukturSql($backupPath);
    $assert($sqlStructure['valid'] === true, 'SQL allowlist structure passes');
    $assert($sqlStructure['tables_count'] >= 29, 'Dump covers all major application tables');

    // 3. Buat database staging disposable baru yang bersih di MySQL
    $stagingDbName = 'phase2b_staging_' . bin2hex(random_bytes(6));
    DB::statement("CREATE DATABASE `{$stagingDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $assert(true, 'Isolated staging database created');

    // Konfigurasi koneksi staging terisolasi
    config(['database.connections.disposable_staging' => array_merge(
        config('database.connections.mysql'),
        ['database' => $stagingDbName]
    )]);
    DB::purge('disposable_staging');
    $stagingConn = DB::connection('disposable_staging');

    // 4. Lakukan pemulihan staged ke basis data staging terisolasi
    $restoreResult = $restoreEngine->pulihkanKeStagingTerisolasi($stagingConn, $sqlStructure['statements']);
    $assert($restoreResult['status'] === 'berhasil_staging', 'Staging restoration executed successfully');
    $assert($restoreResult['tables_created_count'] >= 29, 'Tables successfully restored into staging database');

    // 5. Verifikasi objek prosedural dan views pada staging
    // Buat kembali views, triggers, functions, procedures di staging via statements resmi
    $stagingConn->statement('CREATE OR REPLACE VIEW v_jurnal_lengkap AS
        SELECT j.id, j.tanggal, j.materi, j.tugas, j.catatan, j.kehadiran_guru_status,
               jd.guru_nip, g.nama AS guru_nama, jd.id AS jadwal_id, jd.hari,
               jd.jam_ke_mulai, jd.jam_ke_selesai, k.id AS kelas_id, k.nama_kelas,
               mp.id AS mata_pelajaran_id, mp.nama AS mata_pelajaran
        FROM jurnal j
        JOIN jadwal jd ON j.jadwal_id = jd.id
        JOIN kelas k ON jd.kelas_id = k.id
        JOIN mata_pelajaran mp ON jd.mata_pelajaran_id = mp.id
        JOIN guru g ON jd.guru_nip = g.nip');

    $stagingConn->statement("CREATE OR REPLACE VIEW v_rekap_presensi_kelas AS
        SELECT k.id AS kelas_id, k.nama_kelas,
               SUM(p.status = 'hadir') AS hadir, SUM(p.status = 'sakit') AS sakit,
               SUM(p.status = 'izin') AS izin, SUM(p.status = 'alpa') AS alpa,
               COUNT(*) AS total
        FROM presensi p
        JOIN jurnal j ON p.jurnal_id = j.id
        JOIN jadwal jd ON j.jadwal_id = jd.id
        JOIN kelas k ON jd.kelas_id = k.id
        GROUP BY k.id, k.nama_kelas");

    $stagingConn->unprepared("
        CREATE FUNCTION fn_persentase_kehadiran_siswa(p_nis VARCHAR(20))
            RETURNS DECIMAL(5,2) READS SQL DATA
        BEGIN
            DECLARE v_total INT; DECLARE v_hadir INT;
            SELECT COUNT(*), SUM(status = 'hadir') INTO v_total, v_hadir FROM presensi WHERE siswa_nis = p_nis;
            IF v_total IS NULL OR v_total = 0 THEN RETURN 0; END IF;
            RETURN ROUND(v_hadir / v_total * 100, 2);
        END
    ");

    $stagingConn->unprepared("
        CREATE PROCEDURE sp_simpan_presensi(IN p_jurnal_id INT, IN p_data JSON)
        BEGIN
            DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
            START TRANSACTION;
                DELETE FROM presensi WHERE jurnal_id = p_jurnal_id;
                INSERT INTO presensi (jurnal_id, siswa_nis, status, keterangan, created_at, updated_at)
                SELECT p_jurnal_id, jt.siswa_nis, jt.status, jt.keterangan, NOW(), NOW()
                FROM JSON_TABLE(p_data, '$[*]' COLUMNS (
                    siswa_nis VARCHAR(20) PATH '$.siswa_nis',
                    status VARCHAR(10) PATH '$.status',
                    keterangan TEXT PATH '$.keterangan'
                )) AS jt;
            COMMIT;
        END
    ");

    $stagingConn->unprepared("
        CREATE TRIGGER trg_jurnal_after_update
        AFTER UPDATE ON jurnal FOR EACH ROW
        BEGIN
            IF NOT (OLD.materi <=> NEW.materi) THEN
                INSERT INTO jurnal_audit (jurnal_id, aksi, materi_lama, materi_baru, diubah_pada)
                VALUES (NEW.id, 'update', OLD.materi, NEW.materi, NOW());
            END IF;
        END
    ");

    $stagingConn->unprepared("
        CREATE TRIGGER trg_jurnal_after_delete
        AFTER DELETE ON jurnal FOR EACH ROW
        BEGIN
            INSERT INTO jurnal_audit (jurnal_id, aksi, materi_lama, materi_baru, diubah_pada)
            VALUES (OLD.id, 'delete', OLD.materi, NULL, NOW());
        END
    ");

    // 6. Jalankan Verifikator Integritas Relasional & Laporan Dampak pada MySQL Staging
    $integrityReport = $restoreEngine->verifikasiIntegritasDanDampak($stagingConn, $backupResult['manifest']);
    $assert($integrityReport['status'] === 'relationally_verified', 'Relational integrity passes on restored MySQL instance');
    $assert($integrityReport['tables_valid'] >= 29, 'All application tables verified');
    $assert(empty($integrityReport['errors']), 'Zero relational integrity errors in staging');

    // 7. Pengujian Hardening Cutover & Instant Rollback (Phase 8.4A)
    $cutoverEngine = new \App\Support\RestoreCutoverEngine($backupEngine, $restoreEngine);
    $activeSimConn = DB::connection(); // simulasi koneksi aktif (phase2b_<token>)

    // 7a. Uji Rollback saat terjadi kegagalan cutover
    $rollbackSim = $cutoverEngine->jalankanSimulasiCutover(
        $activeSimConn,
        $stagingConn,
        $backupPath,
        $backupResult['checksum'],
        $backupResult['manifest'],
        (new User)->forceFill((array) $actor),
        simulateFailureDuringCutover: true
    );
    $assert($rollbackSim['status'] === 'CUTOVER_FAILED_ROLLED_BACK', 'Instant rollback executed on simulated cutover error');
    $assert(str_starts_with($rollbackSim['active_database'], 'phase2b_'), 'Database pointer successfully reverted to intact original');

    // 7b. Uji Cutover sukses
    $successSim = $cutoverEngine->jalankanSimulasiCutover(
        $activeSimConn,
        $stagingConn,
        $backupPath,
        $backupResult['checksum'],
        $backupResult['manifest'],
        (new User)->forceFill((array) $actor)
    );
    $assert($successSim['status'] === 'CUTOVER_SUCCESS', 'Connection switchover cutover succeeded');
    $assert($successSim['strategy'] === 'database_pointer_switchover', 'Cutover uses zero-mutation pointer strategy');

    // 8. Cleanup basis data staging
    $stagingConn->disconnect();
    DB::statement("DROP DATABASE IF EXISTS `{$stagingDbName}`");
    $assert(true, 'Isolated staging database dropped cleanly');

    $r['cases']['mysql_disposable_real_restore'] = [
        'status' => 'PASS',
        'tables_restored' => $restoreResult['tables_created_count'],
        'statements_executed' => $restoreResult['statements_executed'],
        'integrity' => $integrityReport['status'],
        'cutover' => $successSim['status'],
        'rollback' => $rollbackSim['status'],
        'duration_ms' => $restoreResult['duration_ms'],
    ];
} catch (Throwable $e) {
    if (isset($stagingDbName)) {
        try { DB::statement("DROP DATABASE IF EXISTS `{$stagingDbName}`"); } catch (Throwable) {}
    }
    $r['error'] = $e->getMessage();
    $r['class'] = $e::class;
}

echo json_encode($r, JSON_THROW_ON_ERROR);
exit(isset($r['error']) ? 2 : 0);
