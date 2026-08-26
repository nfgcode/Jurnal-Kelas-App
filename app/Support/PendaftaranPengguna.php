<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Registering a person and their login as one indivisible act.
 *
 * On MySQL the work is handed to `sp_tambah_guru` / `sp_tambah_siswa`, which
 * hold the transaction and the duplicate checks inside the database, so an
 * import script or a hand-written INSERT is bound by the same rules as the
 * form. Everywhere else — the SQLite test database — the identical sequence
 * runs here in PHP.
 *
 * Both paths raise the same short codes ('NIS_SUDAH_ADA', 'NAMA_SUDAH_ADA', …)
 * and both are turned into field-level validation errors by the one translator
 * below, so the two implementations cannot drift apart in what the admin is
 * actually told.
 */
class PendaftaranPengguna extends ProsedurTersimpan
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const PESAN = [
        'NIP_SUDAH_ADA' => ['nip', 'NIP ini sudah terdaftar atas nama guru lain.'],
        'NIS_SUDAH_ADA' => ['nis', 'NIS ini sudah terdaftar atas nama siswa lain.'],
        'NISN_SUDAH_ADA' => ['nisn', 'NISN ini sudah terdaftar atas nama siswa lain.'],
        'USERNAME_SUDAH_ADA' => ['username', 'Username ini sudah dipakai akun lain.'],
        'EMAIL_SUDAH_ADA' => ['email', 'Email ini sudah dipakai akun lain.'],
        'NAMA_SUDAH_ADA' => ['nama', 'Sudah ada orang dengan nama yang sama. Periksa dulu — centang "izinkan nama sama" bila memang orang yang berbeda.'],
        'NAMA_KOSONG' => ['nama', 'Nama wajib diisi.'],
        'NIP_TIDAK_VALID' => ['nip', 'NIP terlalu pendek (minimal 4 karakter).'],
        'NIS_TIDAK_VALID' => ['nis', 'NIS terlalu pendek (minimal 4 karakter).'],
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function guru(array $data): Guru
    {
        $this->jalankan(
            mysql: fn () => DB::statement('CALL sp_tambah_guru(?,?,?,?,?,?,?,?,?)', [
                trim((string) $data['nip']),
                trim((string) $data['nama']),
                $data['jenis_kelamin'] ?? null,
                $data['no_hp'] ?? null,
                $data['alamat'] ?? null,
                $data['username'],
                $data['email'],
                Hash::make($data['password']),
                empty($data['izinkan_nama_sama']) ? 0 : 1,
            ]),
            portabel: fn () => $this->guruPortabel($data),
        );

        return Guru::findOrFail(trim((string) $data['nip']));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function siswa(array $data): Siswa
    {
        $this->jalankan(
            mysql: fn () => DB::statement('CALL sp_tambah_siswa(?,?,?,?,?,?,?,?,?,?,?)', [
                trim((string) $data['nis']),
                $this->kosongJadiNull($data['nisn'] ?? null),
                trim((string) $data['nama']),
                $data['jenis_kelamin'] ?? null,
                $data['kelas_id'] ?? null,
                $data['no_hp'] ?? null,
                $data['alamat'] ?? null,
                $data['username'],
                $data['email'],
                Hash::make($data['password']),
                empty($data['izinkan_nama_sama']) ? 0 : 1,
            ]),
            portabel: fn () => $this->siswaPortabel($data),
        );

        return Siswa::findOrFail(trim((string) $data['nis']));
    }

    /**
     * How many people already carry this name, in the scope where a repeat is
     * evidence of a double entry: the class for a student, the school for a
     * teacher. The form calls this to warn *before* the admin submits.
     */
    public function namaKembar(string $peran, string $nama, ?int $kelasId = null): int
    {
        $nama = trim($nama);

        return $peran === 'guru'
            ? Guru::where('nama', $nama)->count()
            : Siswa::where('nama', $nama)->where('kelas_id', $kelasId)->count();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    protected function pesan(): array
    {
        return self::PESAN;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guruPortabel(array $data): void
    {
        $nip = trim((string) $data['nip']);
        $nama = trim((string) $data['nama']);

        $this->tolakBila(Guru::whereKey($nip)->exists(), 'NIP_SUDAH_ADA');
        $this->tolakBila(mb_strlen($nip) < 4, 'NIP_TIDAK_VALID');
        $this->tolakBila($nama === '', 'NAMA_KOSONG');
        $this->tolakAkunBentrok($data);
        $this->tolakBila(
            empty($data['izinkan_nama_sama']) && Guru::where('nama', $nama)->exists(),
            'NAMA_SUDAH_ADA',
        );

        DB::transaction(function () use ($data, $nip, $nama) {
            Guru::create([
                'nip' => $nip,
                'nama' => $nama,
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                'no_hp' => $data['no_hp'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'status' => 'aktif',
            ]);

            User::create([
                'username' => $data['username'],
                'nama' => null,
                'email' => $data['email'],
                'role' => 'guru',
                'status' => 'aktif',
                'nip' => $nip,
                'password' => $data['password'],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function siswaPortabel(array $data): void
    {
        $nis = trim((string) $data['nis']);
        $nisn = $this->kosongJadiNull($data['nisn'] ?? null);
        $nama = trim((string) $data['nama']);
        $kelasId = $data['kelas_id'] ?? null;

        $this->tolakBila(Siswa::whereKey($nis)->exists(), 'NIS_SUDAH_ADA');
        $this->tolakBila($nisn !== null && Siswa::where('nisn', $nisn)->exists(), 'NISN_SUDAH_ADA');
        $this->tolakBila(mb_strlen($nis) < 4, 'NIS_TIDAK_VALID');
        $this->tolakBila($nama === '', 'NAMA_KOSONG');
        $this->tolakAkunBentrok($data);
        $this->tolakBila(
            empty($data['izinkan_nama_sama'])
                && Siswa::where('nama', $nama)->where('kelas_id', $kelasId)->exists(),
            'NAMA_SUDAH_ADA',
        );

        DB::transaction(function () use ($data, $nis, $nisn, $nama, $kelasId) {
            Siswa::create([
                'nis' => $nis,
                'nisn' => $nisn,
                'nama' => $nama,
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                'kelas_id' => $kelasId,
                'no_hp' => $data['no_hp'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'status' => 'aktif',
            ]);

            User::create([
                'username' => $data['username'],
                'nama' => null,
                'email' => $data['email'],
                'role' => 'siswa',
                'status' => 'aktif',
                'nis' => $nis,
                'password' => $data['password'],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tolakAkunBentrok(array $data): void
    {
        $this->tolakBila(User::where('username', $data['username'])->exists(), 'USERNAME_SUDAH_ADA');
        $this->tolakBila(User::where('email', $data['email'])->exists(), 'EMAIL_SUDAH_ADA');
    }

    private function kosongJadiNull(mixed $nilai): ?string
    {
        $nilai = trim((string) ($nilai ?? ''));

        return $nilai === '' ? null : $nilai;
    }
}
