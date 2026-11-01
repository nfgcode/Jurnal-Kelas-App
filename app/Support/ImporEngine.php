<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Engine Impor Multi-Mode Terpadu:
 * 1. tambah: Hanya menambah record baru; menolak/melewati duplikat identitas.
 * 2. perbarui: Memperbarui record yang cocok identitas (NIP/NIS/Kode).
 * 3. ganti_keseluruhan: Safe synchronization per kategori.
 *    - Menambah baris baru dan memperbarui baris yang ada.
 *    - Record database yang tidak ada dalam berkas dievaluasi:
 *      * Jika memiliki relasi historis (presensi, jurnal, riwayat kelas), di-deaktivasi (status = 'nonaktif').
 *      * BUKAN TRUNCATE, BUKAN DELETE ALL, dan BUKAN DISABLE FK.
 *    - Kategori tanpa mekanisme deaktivasi status ditolak dari mode ganti_keseluruhan.
 */
class ImporEngine
{
    public const MODE_TAMBAH = 'tambah';
    public const MODE_PERBARUI = 'perbarui';
    public const MODE_GANTI = 'ganti_keseluruhan';

    public const KATEGORI_SISWA = 'siswa';
    public const KATEGORI_GURU = 'guru';
    public const KATEGORI_KELAS = 'kelas';
    public const KATEGORI_MAPEL = 'mata_pelajaran';

    /**
     * Kategori yang mendukung mode 'ganti_keseluruhan'.
     * Kategori harus memiliki kolom status (aktif/nonaktif) untuk safe synchronization.
     */
    public const KATEGORI_MENDUKUNG_GANTI = [
        self::KATEGORI_SISWA,
        self::KATEGORI_GURU,
    ];

    /**
     * Maksimal baris yang diizinkan dalam satu berkas impor.
     */
    public const MAX_BARIS = 1000;

    /**
     * Memeriksa berkas dan menghasilkan preview terklasifikasi:
     * - baru: Baris baru yang belum ada di database.
     * - diperbarui: Baris yang cocok dengan identitas database.
     * - tidak_berubah: Data identik dengan data di database.
     * - dinonaktifkan: Record database yang akan dinonaktifkan (hanya pada mode ganti_keseluruhan).
     * - konflik: Baris yang mengalami bentrok validasi atau duplikasi.
     */
    public static function periksa(
        string $filePath,
        string $kategori,
        string $mode = self::MODE_TAMBAH,
        ?string $passwordBawaan = null,
        array $manualExclusions = []
    ): array {
        if (! in_array($mode, [self::MODE_TAMBAH, self::MODE_PERBARUI, self::MODE_GANTI], true)) {
            throw new InvalidArgumentException("Mode impor tidak dikenal: {$mode}");
        }

        if ($mode === self::MODE_GANTI && ! in_array($kategori, self::KATEGORI_MENDUKUNG_GANTI, true)) {
            throw new DomainException("Kategori '{$kategori}' tidak mendukung mode ganti keseluruhan karena tidak memiliki mekanisme penonaktifan aman.");
        }

        // Baca berkas XLSX atau CSV menggunakan XlsxReader
        $rawRows = XlsxReader::baca($filePath);
        if (empty($rawRows)) {
            return [
                'fatal' => 'Berkas kosong atau tidak dapat dibaca.',
                'baris' => [],
                'ringkas' => [],
            ];
        }

        $header = array_shift($rawRows);
        $kolomDef = ImporPengguna::kolom($kategori);
        $headerPeta = self::petakanHeader($header, $kolomDef);

        if ($headerPeta === null) {
            return [
                'fatal' => 'Header berkas tidak sesuai template.',
                'baris' => [],
                'ringkas' => [],
            ];
        }

        if (count($rawRows) > self::MAX_BARIS) {
            return [
                'fatal' => 'Jumlah baris melebihi batas maksimum ' . self::MAX_BARIS . ' baris.',
                'baris' => [],
                'ringkas' => [],
            ];
        }

        $kelasPeta = Kelas::pluck('id', 'nama_kelas')->toArray();
        $mapelPeta = MataPelajaran::pluck('id', 'nama')->toArray();

        $baris = [];
        $terlihat = ['email' => [], 'identitas' => []];
        $fileIdentities = [];

        foreach ($rawRows as $idx => $r) {
            $nomor = $idx + 2;
            $data = [];
            foreach ($headerPeta as $key => $colIndex) {
                $val = $r[$colIndex] ?? '';
                $data[$key] = is_string($val) ? trim($val) : (string) $val;
            }

            // Simpan nilai asli tanpa mutasi tanda kutip di memori internal database
            // Sanitasi kutip anti-formula hanya diterapkan saat merender view atau mengekspor CSV/XLSX
            $identitasKey = $kategori === self::KATEGORI_GURU ? 'nip' : 'nis';
            $idVal = $data[$identitasKey] ?? '';
            if ($idVal !== '') {
                $fileIdentities[] = $idVal;
            }

            $isExcluded = in_array((string) $nomor, array_map('strval', $manualExclusions), true);

            $item = self::evaluasiBaris(
                $nomor,
                $data,
                $kategori,
                $mode,
                $kelasPeta,
                $mapelPeta,
                $terlihat,
                $passwordBawaan,
                $isExcluded
            );

            $baris[] = $item;
        }

        // Evaluasi record yang akan dinonaktifkan pada mode ganti_keseluruhan
        $akanDinonaktifkan = [];
        if ($mode === self::MODE_GANTI) {
            $akanDinonaktifkan = self::hitungDeaktivasi($kategori, $fileIdentities);
        }

        $ringkas = [
            'total' => count($baris),
            'baru' => count(array_filter($baris, fn ($b) => $b['status_klasifikasi'] === 'baru' && ! $b['dikecualikan'])),
            'diperbarui' => count(array_filter($baris, fn ($b) => $b['status_klasifikasi'] === 'diperbarui' && ! $b['dikecualikan'])),
            'tidak_berubah' => count(array_filter($baris, fn ($b) => $b['status_klasifikasi'] === 'tidak_berubah' && ! $b['dikecualikan'])),
            'dikecualikan' => count(array_filter($baris, fn ($b) => $b['dikecualikan'])),
            'konflik' => count(array_filter($baris, fn ($b) => ! empty($b['error']) && ! $b['dikecualikan'])),
            'dinonaktifkan' => count($akanDinonaktifkan),
        ];

        // Buat fingerprint snapshot untuk perlindungan stale confirmation
        $fingerprint = hash('sha256', json_encode([
            'file_hash' => hash_file('sha256', $filePath),
            'mode' => $mode,
            'kategori' => $kategori,
            'ringkas' => $ringkas,
            'manual_exclusions' => $manualExclusions,
        ], JSON_THROW_ON_ERROR));

        return [
            'fatal' => null,
            'kategori' => $kategori,
            'mode' => $mode,
            'baris' => $baris,
            'akan_dinonaktifkan' => $akanDinonaktifkan,
            'ringkas' => $ringkas,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * Mengeksekusi impor secara atomik dengan proteksi locking dan verifikasi fingerprint.
     */
    public static function commit(
        string $filePath,
        string $kategori,
        string $mode,
        string $expectedFingerprint,
        array $manualExclusions,
        ?string $passwordBawaan,
        User $actor
    ): array {
        return DB::transaction(function () use ($filePath, $kategori, $mode, $expectedFingerprint, $manualExclusions, $passwordBawaan, $actor) {
            // Re-evaluasi preview untuk validasi snapshot terkini
            $preview = self::periksa($filePath, $kategori, $mode, $passwordBawaan, $manualExclusions);
            if ($preview['fatal'] !== null) {
                throw new DomainException("Validasi ulang gagal: " . $preview['fatal']);
            }

            if ($preview['fingerprint'] !== $expectedFingerprint) {
                throw new DomainException("workflow_confirmation_stale");
            }

            $kelasPeta = Kelas::pluck('id', 'nama_kelas')->toArray();
            $mapelPeta = MataPelajaran::pluck('id', 'nama')->toArray();
            $identitasKey = $kategori === self::KATEGORI_GURU ? 'nip' : 'nis';

            $totalBaru = 0;
            $totalDiperbarui = 0;
            $totalDilewati = 0;
            $processedIdentities = [];

            foreach ($preview['baris'] as $b) {
                if ($b['dikecualikan'] || ! empty($b['error'])) {
                    $totalDilewati++;
                    continue;
                }

                $data = $b['data'];
                $idVal = $data[$identitasKey];
                $processedIdentities[] = $idVal;

                if ($kategori === self::KATEGORI_SISWA) {
                    $res = self::simpanBarisSiswa($data, $b['status_klasifikasi'], $kelasPeta, $passwordBawaan);
                } else {
                    $res = self::simpanBarisGuru($data, $b['status_klasifikasi'], $passwordBawaan);
                }

                if ($res === 'baru') {
                    $totalBaru++;
                } elseif ($res === 'diperbarui') {
                    $totalDiperbarui++;
                } else {
                    $totalDilewati++;
                }
            }

            // Pada mode ganti_keseluruhan, lakukan deaktivasi record yang tidak ada dalam file
            $totalDinonaktifkan = 0;
            if ($mode === self::MODE_GANTI) {
                $totalDinonaktifkan = self::eksekusiDeaktivasi($kategori, $processedIdentities);
            }

            // Catat audit lengkap
            AuditAdmin::catat(
                aksi: "impor.{$kategori}.{$mode}",
                tipeEntitas: 'impor',
                entitasId: $kategori,
                deskripsi: "Impor {$kategori} mode {$mode}: {$totalBaru} baru, {$totalDiperbarui} diperbarui, {$totalDinonaktifkan} dinonaktifkan, {$totalDilewati} dilewati.",
                metadata: [
                    'mode' => $mode,
                    'kategori' => $kategori,
                    'baru' => $totalBaru,
                    'diperbarui' => $totalDiperbarui,
                    'dinonaktifkan' => $totalDinonaktifkan,
                    'dilewati' => $totalDilewati,
                    'exclusions' => $manualExclusions,
                ],
                status: 'berhasil',
                jumlah: $totalBaru + $totalDiperbarui
            );

            return [
                'status' => 'berhasil',
                'baru' => $totalBaru,
                'diperbarui' => $totalDiperbarui,
                'dinonaktifkan' => $totalDinonaktifkan,
                'dilewati' => $totalDilewati,
            ];
        });
    }

    /**
     * Evaluasi satu baris dengan klasifikasi status yang ketat.
     */
    private static function evaluasiBaris(
        int $nomor,
        array $data,
        string $kategori,
        string $mode,
        array $kelasPeta,
        array $mapelPeta,
        array &$terlihat,
        ?string $passwordBawaan,
        bool $isExcluded
    ): array {
        $error = [];
        $identitasKey = $kategori === self::KATEGORI_GURU ? 'nip' : 'nis';
        $labelIdentitas = strtoupper($identitasKey);

        $idVal = $data[$identitasKey] ?? '';
        $emailVal = $data['email'] ?? '';

        if ($idVal === '') {
            $error[] = "{$labelIdentitas} wajib diisi.";
        }
        if ($emailVal === '') {
            $error[] = "Email wajib diisi.";
        }

        // Cek duplikasi di internal berkas
        if ($idVal !== '') {
            if (isset($terlihat['identitas'][$idVal])) {
                $error[] = "{$labelIdentitas} {$idVal} ganda di baris {$terlihat['identitas'][$idVal]} dan {$nomor}.";
            } else {
                $terlihat['identitas'][$idVal] = $nomor;
            }
        }

        if ($emailVal !== '') {
            if (isset($terlihat['email'][$emailVal])) {
                $error[] = "Email {$emailVal} ganda di baris {$terlihat['email'][$emailVal]} dan {$nomor}.";
            } else {
                $terlihat['email'][$emailVal] = $nomor;
            }
        }

        // Cek keberadaan di database
        $userById = $idVal !== '' ? User::where($identitasKey, $idVal)->first() : null;
        $userByEmail = $emailVal !== '' ? User::where('email', $emailVal)->first() : null;

        if ($userByEmail && (! $userById || $userById->id !== $userByEmail->id)) {
            $error[] = "Email {$emailVal} sudah dipakai oleh pengguna lain.";
        }

        // Evaluasi kelas untuk siswa
        if ($kategori === self::KATEGORI_SISWA) {
            $namaKelas = $data['kelas'] ?? '';
            if ($namaKelas === '') {
                $error[] = "Kelas wajib diisi.";
            } elseif (! isset($kelasPeta[$namaKelas])) {
                $error[] = "Kelas '{$namaKelas}' tidak terdaftar.";
            }
        }

        // Klasifikasi status: baru, diperbarui, tidak_berubah, konflik
        $klasifikasi = 'baru';
        if ($userById) {
            if ($mode === self::MODE_TAMBAH) {
                $error[] = "{$labelIdentitas} {$idVal} sudah terdaftar di database (Mode Tambah Baru menolak duplikat).";
                $klasifikasi = 'konflik';
            } else {
                $klasifikasi = 'diperbarui';
            }
        } else {
            if ($mode === self::MODE_PERBARUI) {
                $error[] = "{$labelIdentitas} {$idVal} tidak ditemukan di database (Mode Perbarui hanya mengubah data yang cocok).";
                $klasifikasi = 'konflik';
            } else {
                $klasifikasi = 'baru';
            }
        }

        if (! empty($error)) {
            $klasifikasi = 'konflik';
        }

        return [
            'nomor' => $nomor,
            'data' => $data,
            'error' => $error,
            'status_klasifikasi' => $klasifikasi,
            'dikecualikan' => $isExcluded,
        ];
    }

    private static function hitungDeaktivasi(string $kategori, array $fileIdentities): array
    {
        $identitasKey = $kategori === self::KATEGORI_GURU ? 'nip' : 'nis';

        $query = User::where('role', $kategori)->where('status', '!=', 'nonaktif');
        if (! empty($fileIdentities)) {
            $query->whereNotIn($identitasKey, $fileIdentities);
        }

        return $query->get(['id', 'name', 'email', $identitasKey, 'status'])->toArray();
    }

    private static function eksekusiDeaktivasi(string $kategori, array $keepIdentities): int
    {
        $identitasKey = $kategori === self::KATEGORI_GURU ? 'nip' : 'nis';

        $targets = User::where('role', $kategori)
            ->where('status', '!=', 'nonaktif')
            ->whereNotIn($identitasKey, $keepIdentities)
            ->get();

        $count = 0;
        foreach ($targets as $u) {
            $u->update(['status' => 'nonaktif']);

            if ($kategori === self::KATEGORI_SISWA) {
                Siswa::where('nis', $u->nis)->update(['status' => 'nonaktif']);
            } elseif ($kategori === self::KATEGORI_GURU) {
                Guru::where('nip', $u->nip)->update(['status' => 'nonaktif']);
            }
            $count++;
        }

        return $count;
    }

    private static function simpanBarisSiswa(array $data, string $klasifikasi, array $kelasPeta, ?string $passwordBawaan): string
    {
        $kelasId = $kelasPeta[$data['kelas']] ?? null;
        $status = in_array($data['status'] ?? '', ['aktif', 'nonaktif', 'pending'], true) ? $data['status'] : 'aktif';

        $user = User::where('nis', $data['nis'])->first();

        if ($user) {
            // Update data yang ada
            $user->update([
                'nama' => $data['nama'],
                'email' => $data['email'],
                'status' => $status,
            ]);

            Siswa::where('nis', $data['nis'])->update([
                'nama' => $data['nama'],
                'kelas_id' => $kelasId,
                'status' => $status,
            ]);

            return 'diperbarui';
        }

        // Tambah baru
        // Larang password bersama default: jika tidak diisi secara spesifik pada baris, buat password acak kuat aman
        $password = ! empty($data['password']) ? $data['password'] : Str::random(32);
        $username = $data['nis'];

        $siswa = Siswa::create([
            'nis' => $data['nis'],
            'nama' => $data['nama'],
            'kelas_id' => $kelasId,
            'status' => $status,
        ]);

        $user = User::create([
            'nama' => $data['nama'],
            'username' => $username,
            'email' => $data['email'],
            'password' => Hash::make($password),
            'role' => 'siswa',
            'nis' => $data['nis'],
            'status' => $status,
        ]);

        $siswa->update(['user_id' => $user->id]);

        return 'baru';
    }

    private static function simpanBarisGuru(array $data, string $klasifikasi, ?string $passwordBawaan): string
    {
        $status = in_array($data['status'] ?? '', ['aktif', 'nonaktif', 'pending'], true) ? $data['status'] : 'aktif';
        $user = User::where('nip', $data['nip'])->first();

        if ($user) {
            $user->update([
                'nama' => $data['nama'],
                'email' => $data['email'],
                'status' => $status,
            ]);

            Guru::where('nip', $data['nip'])->update([
                'nama' => $data['nama'],
                'status' => $status,
                'no_hp' => $data['no_hp'] ?? null,
                'alamat' => $data['alamat'] ?? null,
            ]);

            return 'diperbarui';
        }

        // Larang password bersama default: jika tidak diisi secara spesifik pada baris, buat password acak kuat aman
        $password = ! empty($data['password']) ? $data['password'] : Str::random(32);
        $username = $data['nip'];
        $user = User::create([
            'nama' => $data['nama'],
            'username' => $username,
            'email' => $data['email'],
            'password' => Hash::make($password),
            'role' => 'guru',
            'nip' => $data['nip'],
            'status' => $status,
        ]);

        Guru::create([
            'user_id' => $user->id,
            'nip' => $data['nip'],
            'nama' => $data['nama'],
            'status' => $status,
            'jenis_kelamin' => $data['jenis_kelamin'] ?? 'L',
            'no_hp' => $data['no_hp'] ?? null,
            'alamat' => $data['alamat'] ?? null,
        ]);

        return 'baru';
    }

    private static function petakanHeader(array $header, array $kolomDef): ?array
    {
        $judulKeKey = [];
        foreach ($kolomDef as $k => $def) {
            $judulKeKey[mb_strtolower(trim($def['judul']))] = $k;
        }

        $peta = [];
        foreach ($header as $colIdx => $title) {
            $t = mb_strtolower(trim((string) $title));
            if (isset($judulKeKey[$t])) {
                $peta[$judulKeKey[$t]] = $colIdx;
            }
        }

        // Pastikan kolom wajib ada
        foreach ($kolomDef as $k => $def) {
            if ($def['wajib'] && ! isset($peta[$k])) {
                return null;
            }
        }

        return $peta;
    }
}
