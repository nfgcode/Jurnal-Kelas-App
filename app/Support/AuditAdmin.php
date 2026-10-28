<?php

namespace App\Support;

use App\Models\AdminAuditLog;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditAdmin
{
    /**
     * Kata kunci atribut sensitif yang wajib dibersihkan dari metadata audit.
     */
    private const KATA_SENSITIF = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'secret',
        'pin',
        'kartu',
    ];

    /**
     * Catat log audit administratif non-kritis (rutin).
     *
     * Jika terjadi kegagalan pencatatan, galat dilaporkan dan proses bisnis
     * pengguna tidak dibatalkan.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function catat(
        string $aksi,
        string $tipeEntitas,
        ?string $entitasId,
        string $deskripsi,
        ?array $metadata = null,
        string $status = 'berhasil',
        int $jumlah = 1,
        ?User $user = null,
    ): ?AdminAuditLog {
        try {
            if (! Schema::hasTable('admin_audit_log')) {
                return null;
            }

            return self::simpanLog(
                aksi: $aksi,
                tipeEntitas: $tipeEntitas,
                entitasId: $entitasId,
                deskripsi: $deskripsi,
                metadata: $metadata,
                status: $status,
                jumlah: $jumlah,
                user: $user,
            );
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Catat log audit administratif untuk operasi KRITIS.
     *
     * Operasi kritis mencakup: pemulihan cadangan data, penghapusan permanen,
     * transisi tahun ajaran, dan kelulusan massal.
     *
     * Wajib berhasil: melempar RuntimeException jika gagal menyimpan ke database,
     * sehingga transaksi bisnis di sekitarnya dibatalkan dan tidak berjalan
     * tanpa jejak audit permanen.
     *
     * @param  array<string, mixed>|null  $metadata
     * @throws RuntimeException
     */
    public static function catatKritis(
        string $aksi,
        string $tipeEntitas,
        ?string $entitasId,
        string $deskripsi,
        ?array $metadata = null,
        string $status = 'berhasil',
        int $jumlah = 1,
        ?User $user = null,
    ): AdminAuditLog {
        try {
            return self::simpanLog(
                aksi: $aksi,
                tipeEntitas: $tipeEntitas,
                entitasId: $entitasId,
                deskripsi: $deskripsi,
                metadata: $metadata,
                status: $status,
                jumlah: $jumlah,
                user: $user,
            );
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException(
                "Gagal mencatat log audit kritis untuk aksi '{$aksi}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Eksekusi penyimpanan ke tabel admin_audit_log.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    private static function simpanLog(
        string $aksi,
        string $tipeEntitas,
        ?string $entitasId,
        string $deskripsi,
        ?array $metadata,
        string $status,
        int $jumlah,
        ?User $user,
    ): AdminAuditLog {
        $user ??= Auth::user();

        return AdminAuditLog::create([
            'user_id' => $user?->id,
            'nama_user' => $user?->nama ?? $user?->username ?? 'Sistem',
            'role_user' => $user?->role ?? 'admin',
            'aksi' => $aksi,
            'tipe_entitas' => $tipeEntitas,
            'entitas_id' => $entitasId,
            'status' => $status,
            'deskripsi' => $deskripsi,
            'jumlah_data' => $jumlah,
            'metadata' => self::sanitasiMetadata($metadata),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Bersihkan metadata dari atribut sensitif dan batasi ukuran payload.
     *
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    public static function sanitasiMetadata(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $bersih = [];

        foreach ($metadata as $kunci => $nilai) {
            $kunciTeks = strtolower((string) $kunci);

            // Periksa apakah kunci mengandung kata sensitif
            $sensitif = false;
            foreach (self::KATA_SENSITIF as $terlarang) {
                if (str_contains($kunciTeks, $terlarang)) {
                    $sensitif = true;
                    break;
                }
            }

            if ($sensitif) {
                continue; // Jangan masukkan kunci sensitif ke metadata
            }

            if (is_array($nilai)) {
                $bersih[$kunci] = self::sanitasiMetadata($nilai);
            } elseif (is_scalar($nilai) || $nilai === null) {
                $bersih[$kunci] = $nilai;
            } else {
                $bersih[$kunci] = (string) $nilai;
            }
        }

        return $bersih;
    }

    /**
     * Catat penambahan siswa baru.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function siswaDibuat(Siswa $siswa, array $metadata = []): ?AdminAuditLog
    {
        return self::catat(
            aksi: 'siswa.tambah',
            tipeEntitas: 'siswa',
            entitasId: $siswa->nis,
            deskripsi: "Menambahkan siswa baru: {$siswa->nama} (NIS: {$siswa->nis})",
            metadata: array_merge([
                'nis' => $siswa->nis,
                'nama' => $siswa->nama,
                'kelas_id' => $siswa->kelas_id,
                'status' => $siswa->status,
            ], $metadata),
        );
    }

    /**
     * Catat pembaruan data siswa.
     *
     * @param  array<string, mixed>  $perubahan
     */
    public static function siswaDiperbarui(Siswa $siswa, array $perubahan = []): ?AdminAuditLog
    {
        return self::catat(
            aksi: 'siswa.ubah',
            tipeEntitas: 'siswa',
            entitasId: $siswa->nis,
            deskripsi: "Memperbarui data siswa: {$siswa->nama} (NIS: {$siswa->nis})",
            metadata: array_merge([
                'nis' => $siswa->nis,
                'nama' => $siswa->nama,
            ], $perubahan),
        );
    }

    /**
     * Catat penghapusan siswa yang berhasil.
     */
    public static function siswaDihapus(string $nis, string $nama, array $metadata = []): ?AdminAuditLog
    {
        return self::catat(
            aksi: 'siswa.hapus',
            tipeEntitas: 'siswa',
            entitasId: $nis,
            deskripsi: "Menghapus siswa: {$nama} (NIS: {$nis}) beserta akunnya",
            metadata: array_merge(['nis' => $nis, 'nama' => $nama], $metadata),
        );
    }

    /**
     * Catat penolakan penghapusan siswa karena adanya riwayat.
     */
    public static function siswaHapusDitolak(string $nis, string $nama, string $alasan): ?AdminAuditLog
    {
        return self::catat(
            aksi: 'siswa.hapus_ditolak',
            tipeEntitas: 'siswa',
            entitasId: $nis,
            deskripsi: "Percobaan menghapus siswa {$nama} (NIS: {$nis}) ditolak: {$alasan}",
            metadata: ['nis' => $nis, 'nama' => $nama, 'alasan' => $alasan],
            status: 'gagal',
        );
    }

    /**
     * Catat penyelesaian impor pengguna dari spreadsheet.
     */
    public static function imporSelesai(
        string $jenis,
        int $jumlahBaru,
        int $jumlahDiperbarui,
        int $jumlahDilewati,
        string $namaBerkas,
    ): ?AdminAuditLog {
        $total = $jumlahBaru + $jumlahDiperbarui;

        return self::catat(
            aksi: "impor.{$jenis}",
            tipeEntitas: 'impor',
            entitasId: $namaBerkas,
            deskripsi: "Impor {$jenis} selesai dari {$namaBerkas}: {$jumlahBaru} baru, {$jumlahDiperbarui} diperbarui, {$jumlahDilewati} dilewati.",
            metadata: [
                'jenis' => $jenis,
                'baru' => $jumlahBaru,
                'diperbarui' => $jumlahDiperbarui,
                'dilewati' => $jumlahDilewati,
                'berkas' => $namaBerkas,
            ],
            status: 'berhasil',
            jumlah: $total,
        );
    }
}
