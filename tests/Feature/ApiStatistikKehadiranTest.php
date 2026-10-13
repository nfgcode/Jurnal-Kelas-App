<?php

namespace Tests\Feature;

use App\Models\PresensiHarian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /api/statistik/kehadiran`: the attendance percentages.
 *
 * A student asking about themselves is answered from their NIS. The endpoint
 * used to read the *account id* instead, which happens to be a small integer
 * that matches no `presensi_harian.siswa_nis`, so every student who asked about
 * their own attendance was told 0%, silently, because 0% is a plausible answer.
 *
 * The NIS values here deliberately carry a leading zero: `siswa.nis` is
 * VARCHAR(20), and an int-typed parameter anywhere on this path would drop it
 * and match nothing.
 */
class ApiStatistikKehadiranTest extends TestCase
{
    use RefreshDatabase;

    private const NIS_A = '0026001';

    private const NIS_B = '0026002';

    /**
     * One class, two students, six school days of attendance:
     * A is 3 hadir of 4 (75%), B is 1 hadir of 2 (50%), the class 4 of 6 (66.67%).
     *
     * @return array{0: User, 1: User, 2: int}
     */
    private function panggung(): array
    {
        $kelas = $this->buatKelas(['nama_kelas' => 'X STAT']);

        $a = $this->buatSiswa(['nis' => self::NIS_A, 'kelas_id' => $kelas->id]);
        $b = $this->buatSiswa(['nis' => self::NIS_B, 'kelas_id' => $kelas->id]);

        $hari = [
            [self::NIS_A, '2026-09-01', 'hadir'],
            [self::NIS_A, '2026-09-02', 'hadir'],
            [self::NIS_A, '2026-09-03', 'hadir'],
            [self::NIS_A, '2026-09-04', 'alpa'],
            [self::NIS_B, '2026-09-01', 'hadir'],
            [self::NIS_B, '2026-09-02', 'alpa'],
        ];

        foreach ($hari as [$nis, $tanggal, $status]) {
            PresensiHarian::create([
                'kelas_id' => $kelas->id,
                'tanggal' => $tanggal,
                'siswa_nis' => $nis,
                'status' => $status,
            ]);
        }

        return [$a, $b, $kelas->id];
    }

    public function test_a_student_is_answered_from_their_nis_not_their_account_id(): void
    {
        [$a, , $kelasId] = $this->panggung();

        // Guards the trap itself: if these ever coincide the test would pass for
        // the wrong reason, and the regression could come back unnoticed.
        $this->assertNotSame((string) $a->id, self::NIS_A);

        $this->actingAs($a)->getJson('/api/statistik/kehadiran')
            ->assertOk()
            ->assertJsonPath('siswa_nis', self::NIS_A)
            // Compared as floats: JSON gives back 75 for 75.0, and assertJsonPath
            // is strict about the difference.
            ->assertJsonPath('persentase_kehadiran_siswa', fn ($p) => (float) $p === 75.0)
            ->assertJsonPath('kelas_id', $kelasId)
            ->assertJsonPath('persentase_kehadiran_kelas', fn ($p) => (float) $p === 66.67);
    }

    public function test_a_student_cannot_ask_about_someone_else(): void
    {
        [$a] = $this->panggung();

        // The filter is ignored for a student: they are pinned to their own row.
        $this->actingAs($a)->getJson('/api/statistik/kehadiran?siswa_nis='.self::NIS_B)
            ->assertOk()
            ->assertJsonPath('siswa_nis', self::NIS_A)
            ->assertJsonPath('persentase_kehadiran_siswa', fn ($p) => (float) $p === 75.0);
    }

    public function test_an_admin_may_ask_by_nis(): void
    {
        $this->panggung();
        $admin = User::factory()->create();

        $this->actingAs($admin)->getJson('/api/statistik/kehadiran?siswa_nis='.self::NIS_B)
            ->assertOk()
            ->assertJsonPath('siswa_nis', self::NIS_B)
            ->assertJsonPath('persentase_kehadiran_siswa', fn ($p) => (float) $p === 50.0);
    }

    public function test_a_guru_may_ask_by_nis(): void
    {
        $this->panggung();
        $guru = $this->buatGuru();

        $this->actingAs($guru)->getJson('/api/statistik/kehadiran?siswa_nis='.self::NIS_A)
            ->assertOk()
            ->assertJsonPath('persentase_kehadiran_siswa', fn ($p) => (float) $p === 75.0);
    }

    public function test_an_unknown_nis_is_refused(): void
    {
        $this->panggung();
        $admin = User::factory()->create();

        $this->actingAs($admin)->getJson('/api/statistik/kehadiran?siswa_nis=999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('siswa_nis');
    }

    public function test_asking_for_nothing_answers_nothing_rather_than_zero(): void
    {
        $this->panggung();
        $admin = User::factory()->create();

        // Null, not 0: "no student was named" and "this student was never
        // present" are different answers and must not look alike.
        $this->actingAs($admin)->getJson('/api/statistik/kehadiran')
            ->assertOk()
            ->assertJsonPath('siswa_nis', null)
            ->assertJsonPath('persentase_kehadiran_siswa', null)
            ->assertJsonPath('persentase_kehadiran_kelas', null);
    }
}
