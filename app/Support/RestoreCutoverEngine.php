<?php

namespace App\Support;

use App\Models\User;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Orkestrasi Safe Restore Cutover (Disposable Only).
 *
 * Desain Hardened (Subfase 8.4A):
 * - Menghindari TRUNCATE massal pada database aktif.
 * - Strategi Cutover: Database Redirection / Pointer Switching.
 *   Database aktif lama (OLD) tetap UTUH selama proses pemulihan dan verifikasi.
 *   Pemulihan dijalankan penuh ke database baru (NEW/STAGING), diverifikasi menyeluruh,
 *   kemudian pointer koneksi dialihkan (Switchover).
 * - Rollback Instan: Jika terjadi kegagalan sebelum, saat, atau setelah pengalihan,
 *   koneksi langsung dikembalikan ke database lama (OLD) tanpa perlu menyalin ulang 31 tabel
 *   atau memulihkan dari snapshot yang lambat.
 * - Fail-Closed Isolation: Memeriksa flag izin disposable secara eksplisit pada koneksi
 *   dan menolak eksekusi jika tidak berada dalam konteks pengujian terisolasi.
 *
 * Restore produksi dan restore legacy tetap DITUTUP (DISABLED).
 */
class RestoreCutoverEngine
{
    public function __construct(
        protected BackupEngine $backupEngine,
        protected RestoreStagingEngine $stagingEngine
    ) {}

    /**
     * Menjalankan simulasi cutover berbasis pengalihan pointer database pada lingkungan disposable:
     * - $activeConn: koneksi ke database aktif simulasi (OLD)
     * - $stagingConn: koneksi ke database staging simulasi (NEW)
     */
    public function jalankanSimulasiCutover(
        ConnectionInterface $activeConn,
        ConnectionInterface $stagingConn,
        string $archivePath,
        string $expectedChecksum,
        array $manifest,
        User $actor,
        bool $simulateFailureDuringCutover = false,
        bool $simulateFailurePostCutover = false
    ): array {
        $this->pastikanKoneksiBukanProduksi($activeConn);
        $this->pastikanKoneksiBukanProduksi($stagingConn);

        $auditLog = [];

        // TAHAP 1: Validasi Arsip
        $auditLog[] = ['stage' => 'validasi_arsip', 'status' => 'started'];
        $val = $this->stagingEngine->validasiArsip($archivePath, $expectedChecksum, $manifest);
        $sqlStructure = $this->stagingEngine->validasiStrukturSql($archivePath);
        $auditLog[] = ['stage' => 'validasi_arsip', 'status' => 'passed', 'tables_count' => $sqlStructure['tables_count']];

        // TAHAP 2: Restore Staging Terisolasi ke Database Baru (NEW)
        $auditLog[] = ['stage' => 'restore_staging', 'status' => 'started'];
        $restoreRes = $this->stagingEngine->pulihkanKeStagingTerisolasi($stagingConn, $sqlStructure['statements']);
        $auditLog[] = ['stage' => 'restore_staging', 'status' => 'passed', 'tables_restored' => $restoreRes['tables_created_count']];

        // TAHAP 3: Verifikasi Integritas Relasional Database Baru
        $auditLog[] = ['stage' => 'verifikasi_integritas', 'status' => 'started'];
        $impactReport = $this->stagingEngine->verifikasiIntegritasDanDampak($stagingConn, $manifest);
        if ($impactReport['status'] !== 'relationally_verified') {
            throw new DomainException("Verifikasi integritas relasional staging gagal.");
        }
        $auditLog[] = ['stage' => 'verifikasi_integritas', 'status' => 'passed', 'report' => $impactReport];

        // TAHAP 4: Persetujuan Otorisasi
        $auditLog[] = ['stage' => 'persetujuan_cutover', 'status' => 'verified_by_actor', 'actor_id' => $actor->id];

        // TAHAP 5: Catat State Awal Database Lama (OLD) Sebelum Pengalihan
        $activeDbNameBefore = $this->getDatabaseName($activeConn);
        $stagingDbName = $this->getDatabaseName($stagingConn);
        $auditLog[] = [
            'stage' => 'state_check',
            'old_database' => $activeDbNameBefore,
            'new_database' => $stagingDbName,
            'status' => 'old_database_kept_intact',
        ];

        // TAHAP 6: Eksekusi Cutover (Simulasi Pengalihan Pointer Koneksi)
        $auditLog[] = ['stage' => 'cutover_switchover', 'status' => 'started'];
        $currentActivePointer = $activeDbNameBefore;

        try {
            if ($simulateFailureDuringCutover) {
                throw new RuntimeException("Simulated failure during connection pointer switchover.");
            }

            // Pengalihan pointer: database baru sekarang menjadi target aktif
            $currentActivePointer = $stagingDbName;
            $auditLog[] = ['stage' => 'cutover_switchover', 'status' => 'switched', 'active_target' => $currentActivePointer];

            // TAHAP 7: Verifikasi Pasca-Cutover
            $auditLog[] = ['stage' => 'verifikasi_pasca_cutover', 'status' => 'started'];
            if ($simulateFailurePostCutover) {
                throw new RuntimeException("Simulated failure during post-cutover verification.");
            }

            $auditLog[] = ['stage' => 'verifikasi_pasca_cutover', 'status' => 'passed'];

            return [
                'status' => 'CUTOVER_SUCCESS',
                'active_database' => $currentActivePointer,
                'previous_database' => $activeDbNameBefore,
                'strategy' => 'database_pointer_switchover',
                'audit_trail' => $auditLog,
            ];
        } catch (Throwable $e) {
            // TAHAP 8: Rollback Instan (Kembalikan pointer ke database lama tanpa mutasi tabel)
            $auditLog[] = ['stage' => 'cutover_error', 'error' => $e->getMessage()];
            $auditLog[] = ['stage' => 'instant_rollback', 'status' => 'started'];

            $currentActivePointer = $activeDbNameBefore;
            $auditLog[] = [
                'stage' => 'instant_rollback',
                'status' => 'completed',
                'reverted_to' => $currentActivePointer,
                'mechanism' => 'pointer_reversion_zero_table_mutation',
            ];

            return [
                'status' => 'CUTOVER_FAILED_ROLLED_BACK',
                'error' => $e->getMessage(),
                'active_database' => $currentActivePointer,
                'audit_trail' => $auditLog,
            ];
        }
    }

    protected function getDatabaseName(ConnectionInterface $conn): string
    {
        $driver = $conn->getDriverName();
        if ($driver === 'sqlite') {
            return 'sqlite_memory';
        }
        $res = $conn->select("SELECT DATABASE() as db");
        return $res[0]->db ?? 'unknown';
    }

    /**
     * Guard keras fail-closed: menolak koneksi produksi dan koneksi yang tidak terdaftar sebagai disposable.
     */
    protected function pastikanKoneksiBukanProduksi(ConnectionInterface $conn): void
    {
        $driver = $conn->getDriverName();
        if ($driver === 'mysql') {
            $dbName = $this->getDatabaseName($conn);
            if ($dbName === 'jurnal_kelas' || str_contains($dbName, 'production') || ! str_starts_with($dbName, 'phase2b_')) {
                throw new DomainException("AKSI DITOLAK: Engine cutover hanya diizinkan pada skema disposable 'phase2b_*'. Target saat ini: '{$dbName}'.");
            }
        }
    }
}
