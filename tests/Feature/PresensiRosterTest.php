<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\PresensiHarian;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may file a class's daily attendance, and the admin-only audit trail of who
 * did.
 *
 * The rule the whole feature rests on: one roll call per class per day, filed by
 * that class's ketua kelas and nobody else (admin corrects). A guru — including
 * the wali kelas — reads it and exports it, but never writes it.
 */
class PresensiRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    /**
     * A class with a wali, its ketua, a teacher who teaches it, and an outsider.
     *
     * @return array<string, mixed>
     */
    private function skenario(): array
    {
        $kelas = Kelas::whereNotNull('wali_kelas_id')
            ->whereHas('siswa', fn ($q) => $q->where('is_ketua_kelas', true))
            ->firstOrFail();

        return [
            'kelas' => $kelas,
            'wali' => User::findOrFail($kelas->wali_kelas_id),
            'pengajar' => User::findOrFail($kelas->jadwals()->value('guru_id')),
            'ketua' => User::where('role', 'siswa')->where('kelas_id', $kelas->id)
                ->where('is_ketua_kelas', true)->firstOrFail(),
            'siswaBiasa' => User::where('role', 'siswa')->where('kelas_id', $kelas->id)
                ->where('is_ketua_kelas', false)->firstOrFail(),
            'admin' => User::where('role', 'admin')->firstOrFail(),
        ];
    }

    /** The roster payload the form posts, marking everyone present. */
    private function payload(Kelas $kelas): array
    {
        $roster = $kelas->siswa()->pluck('id');
        $payload = ['tanggal' => now()->toDateString(), 'presensi' => []];

        foreach ($roster as $i => $id) {
            $payload['presensi'][$i] = ['siswa_id' => $id, 'status' => 'hadir'];
        }

        return $payload;
    }

    public function test_the_ketua_kelas_may_file_their_own_class_attendance(): void
    {
        $s = $this->skenario();

        $this->assertTrue($s['ketua']->can('isiPresensiHarian', $s['kelas']));

        $this->actingAs($s['ketua'])
            ->get(route('presensi-harian.edit', $s['kelas']))
            ->assertOk();
    }

    public function test_a_guru_teaching_the_class_may_read_but_not_file_attendance(): void
    {
        $s = $this->skenario();

        $this->assertTrue($s['pengajar']->can('lihatPresensiHarian', $s['kelas']));
        $this->assertFalse($s['pengajar']->can('isiPresensiHarian', $s['kelas']));

        $this->actingAs($s['pengajar'])
            ->get(route('presensi-harian.edit', $s['kelas']))
            ->assertForbidden();
    }

    public function test_the_wali_kelas_may_read_but_not_file_attendance(): void
    {
        $s = $this->skenario();

        $this->assertTrue($s['wali']->can('lihatPresensiHarian', $s['kelas']));
        $this->assertFalse($s['wali']->can('isiPresensiHarian', $s['kelas']));
    }

    public function test_a_regular_student_may_not_file_their_classs_attendance(): void
    {
        $s = $this->skenario();

        $this->assertFalse($s['siswaBiasa']->can('isiPresensiHarian', $s['kelas']));

        $this->actingAs($s['siswaBiasa'])
            ->post(route('presensi-harian.store', $s['kelas']), $this->payload($s['kelas']))
            ->assertForbidden();
    }

    public function test_a_ketua_may_not_file_another_classs_attendance(): void
    {
        $s = $this->skenario();
        $lain = Kelas::whereKeyNot($s['kelas']->id)->firstOrFail();

        $this->assertFalse($s['ketua']->can('isiPresensiHarian', $lain));

        $this->actingAs($s['ketua'])
            ->post(route('presensi-harian.store', $lain), $this->payload($lain))
            ->assertForbidden();
    }

    public function test_filing_attendance_stores_one_row_per_student_and_an_audit_entry(): void
    {
        $s = $this->skenario();
        $roster = $s['kelas']->siswa()->pluck('id');

        $this->actingAs($s['ketua'])
            ->post(route('presensi-harian.store', $s['kelas']), $this->payload($s['kelas']))
            ->assertRedirect(route('presensi-harian.show', [$s['kelas'], 'tanggal' => now()->toDateString()]));

        $this->assertDatabaseHas('presensi_harian', [
            'kelas_id' => $s['kelas']->id,
            'tanggal' => now()->toDateString(),
            'siswa_id' => $roster->first(),
            'status' => 'hadir',
            'diisi_oleh_id' => $s['ketua']->id,
        ]);

        $this->assertDatabaseHas('presensi_harian_log', [
            'kelas_id' => $s['kelas']->id,
            'diedit_oleh_id' => $s['ketua']->id,
            'jumlah_siswa' => $roster->count(),
        ]);
    }

    /**
     * "Once that day" means the second save replaces the first rather than
     * adding a parallel roster — the whole point of the unique index.
     */
    public function test_filing_twice_in_a_day_replaces_rather_than_duplicates(): void
    {
        $s = $this->skenario();
        $roster = $s['kelas']->siswa()->pluck('id');

        $this->actingAs($s['ketua'])->post(route('presensi-harian.store', $s['kelas']), $this->payload($s['kelas']));

        $ubah = $this->payload($s['kelas']);
        $ubah['presensi'][0]['status'] = 'alpa';

        $this->actingAs($s['ketua'])->post(route('presensi-harian.store', $s['kelas']), $ubah);

        $this->assertSame($roster->count(), PresensiHarian::where('kelas_id', $s['kelas']->id)
            ->whereDate('tanggal', now()->toDateString())->count());

        $this->assertDatabaseHas('presensi_harian', [
            'kelas_id' => $s['kelas']->id,
            'siswa_id' => $roster->first(),
            'status' => 'alpa',
        ]);

        // The second save is recorded as a correction, not as a first filing.
        $this->assertDatabaseHas('presensi_harian_log', [
            'kelas_id' => $s['kelas']->id,
            'koreksi' => true,
        ]);
    }

    /**
     * A roll call describes the day it was taken on. A ketua filing yesterday's
     * attendance today would be reconstructing it, so only admin may.
     */
    public function test_a_ketua_may_not_file_attendance_for_a_past_date(): void
    {
        $s = $this->skenario();
        $kemarin = now()->subDay()->toDateString();

        $this->actingAs($s['ketua'])
            ->get(route('presensi-harian.edit', [$s['kelas'], 'tanggal' => $kemarin]))
            ->assertRedirect(route('presensi-harian.show', [$s['kelas'], 'tanggal' => $kemarin]));

        $payload = $this->payload($s['kelas']);
        $payload['tanggal'] = $kemarin;

        $this->actingAs($s['ketua'])
            ->post(route('presensi-harian.store', [$s['kelas'], 'tanggal' => $kemarin]), $payload)
            ->assertSessionHas('error');
    }

    public function test_an_admin_may_correct_a_past_date(): void
    {
        $s = $this->skenario();
        $kemarin = now()->subDay()->toDateString();

        $this->actingAs($s['admin'])
            ->get(route('presensi-harian.edit', [$s['kelas'], 'tanggal' => $kemarin]))
            ->assertOk();
    }

    /**
     * Attendance may only be recorded for students actually in the class, so a
     * crafted siswa_id from another class is rejected.
     */
    public function test_a_student_from_another_class_is_rejected(): void
    {
        $s = $this->skenario();
        $luar = User::where('role', 'siswa')->where('kelas_id', '!=', $s['kelas']->id)->firstOrFail();

        $this->actingAs($s['ketua'])
            ->post(route('presensi-harian.store', $s['kelas']), [
                'tanggal' => now()->toDateString(),
                'presensi' => [['siswa_id' => $luar->id, 'status' => 'hadir']],
            ])
            ->assertSessionHasErrors('presensi.0.siswa_id');

        $this->assertDatabaseMissing('presensi_harian', [
            'kelas_id' => $s['kelas']->id,
            'siswa_id' => $luar->id,
        ]);
    }

    public function test_presensi_log_page_is_admin_only(): void
    {
        $s = $this->skenario();

        $this->actingAs($s['admin'])->get('/admin/presensi-log')->assertOk();
        $this->actingAs($s['pengajar'])->get('/admin/presensi-log')->assertForbidden();
        $this->actingAs($s['ketua'])->get('/admin/presensi-log')->assertForbidden();
    }
}
