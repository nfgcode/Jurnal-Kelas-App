<?php

namespace Tests;

use App\Models\Guru;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Views reference @vite; tests shouldn't depend on built front-end assets.
        $this->withoutVite();
    }

    /**
     * Create a teacher and the account they sign in with, and hand back the
     * account.
     *
     * Tests almost always want the account — `actingAs()` takes one — while the
     * person is one hop away as `$akun->guru`. Making a teacher is two rows now,
     * and every test that forgets the second one fails on a foreign key rather
     * than on the thing it was checking, so the pair is made here once.
     *
     * @param  array<string, mixed>  $orang
     * @param  array<string, mixed>  $akun
     */
    protected function buatGuru(array $orang = [], array $akun = []): User
    {
        $guru = Guru::factory()->create($orang);

        return User::factory()->guru($guru)->create($akun);
    }

    /**
     * Create a student and their account; returns the account.
     *
     * @param  array<string, mixed>  $orang
     * @param  array<string, mixed>  $akun
     */
    protected function buatSiswa(array $orang = [], array $akun = []): User
    {
        $siswa = Siswa::factory()->create($orang);

        return User::factory()->siswa($siswa)->create($akun);
    }

    /**
     * The account belonging to an existing teacher, by NIP or model.
     */
    protected function akunGuru(Guru|string $guru): User
    {
        return User::where('nip', $guru instanceof Guru ? $guru->nip : $guru)->firstOrFail();
    }

    /**
     * The account belonging to an existing student, by NIS or model.
     */
    protected function akunSiswa(Siswa|string $siswa): User
    {
        return User::where('nis', $siswa instanceof Siswa ? $siswa->nis : $siswa)->firstOrFail();
    }

    /**
     * An account of some student in the given class — the class chair when
     * `$ketua` is true, a rank-and-file student when it is false, either when
     * it is null.
     */
    protected function akunSiswaKelas(int $kelasId, ?bool $ketua = null): User
    {
        return User::whereHas('siswa', fn ($s) => $s
            ->where('kelas_id', $kelasId)
            ->when($ketua !== null, fn ($q) => $q->ketua($ketua)))
            ->firstOrFail();
    }

    /**
     * A class, with the school year (and jurusan) it references brought into
     * existence first.
     *
     * `kelas.tahun_ajaran_kode` is a non-null foreign key now, so a bare
     * `Kelas::create()` in a test fails on the constraint rather than on
     * whatever the test was actually checking.
     *
     * @param  array<string, mixed>  $atribut
     */
    protected function buatKelas(array $atribut = []): Kelas
    {
        $tahun = $atribut['tahun_ajaran_kode'] ?? '2026/2027';
        TahunAjaran::firstOrCreate(['kode' => $tahun], ['aktif' => true]);

        if (! empty($atribut['jurusan_kode'])) {
            Jurusan::firstOrCreate(
                ['kode' => $atribut['jurusan_kode']],
                ['nama' => $atribut['jurusan_kode']],
            );
        }

        return Kelas::create($atribut + [
            'nama_kelas' => 'X UJI',
            'tingkat' => 'X',
            'tahun_ajaran_kode' => $tahun,
        ]);
    }
}
