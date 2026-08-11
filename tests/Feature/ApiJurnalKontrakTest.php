<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Support\Ringkasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The API must hand back the identifier its own URLs are addressed by.
 *
 * A journal's route key is its public id ({@see Jurnal::getRouteKeyName}), and
 * that applies to the API as much as the web — `/api/jurnal/{numeric id}` 404s.
 * The resource used to expose only the numeric id, so a client could read a
 * journal and still have no way to build the URL that updates it. These tests
 * lock the identifier in place, including a full read-then-write round trip.
 */
class ApiJurnalKontrakTest extends TestCase
{
    use RefreshDatabase;

    private function jurnalUji(): array
    {
        $guru = User::factory()->create(['role' => 'guru', 'nip' => '198500000001']);

        $kelas = Kelas::create([
            'nama_kelas' => 'X UJI API',
            'tingkat' => 'X',
            'tahun_ajaran' => '2026/2027',
        ]);

        $mapel = new MataPelajaran;
        $mapel->forceFill(['nama' => 'Matematika', 'kode' => 'MTKAPI', 'kelompok' => 'wajib', 'jp_per_minggu' => 2]);
        $mapel->save();

        $tanggal = Carbon::today()->toDateString();

        $jadwal = Jadwal::create([
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'hari' => Ringkasan::HARI[Carbon::parse($tanggal)->dayOfWeekIso - 1] ?? 'Senin',
            'jam_ke_mulai' => 1,
            'jam_ke_selesai' => 2,
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
        ]);

        $jurnal = Jurnal::create([
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Materi awal',
            'kehadiran_guru_status' => 'hadir',
            'guru_id' => $guru->id,
            'diisi_oleh_id' => $guru->id,
            'diisi_oleh_peran' => 'guru',
        ]);

        return [$guru, $jadwal, $jurnal, $tanggal];
    }

    public function test_the_api_exposes_the_public_id_it_binds_on(): void
    {
        [$guru, , $jurnal] = $this->jurnalUji();

        $this->actingAs($guru)->getJson("/api/jurnal/{$jurnal->public_id}")
            ->assertOk()
            ->assertJsonPath('data.public_id', $jurnal->public_id)
            ->assertJsonPath('data.id', $jurnal->id);

        $this->actingAs($guru)->getJson('/api/jurnal')
            ->assertOk()
            ->assertJsonPath('data.0.public_id', $jurnal->public_id);
    }

    public function test_a_client_can_update_using_only_what_the_api_returned(): void
    {
        [$guru, $jadwal, $jurnal, $tanggal] = $this->jurnalUji();

        // Read it back the way a real client would, then write using the id the
        // response carried — no out-of-band knowledge of the numeric key.
        $dibaca = $this->actingAs($guru)->getJson("/api/jurnal/{$jurnal->public_id}")
            ->assertOk()
            ->json('data');

        $this->actingAs($guru)->putJson("/api/jurnal/{$dibaca['public_id']}", [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Materi diperbarui lewat API',
            'kehadiran_guru_status' => 'hadir',
        ])->assertOk();

        $this->assertSame('Materi diperbarui lewat API', $jurnal->fresh()->materi);
    }

    public function test_the_numeric_id_is_not_a_url_key(): void
    {
        [$guru, , $jurnal] = $this->jurnalUji();

        // Guards the trap itself: if binding ever changes, this test says so
        // rather than leaving clients to discover a 404.
        $this->actingAs($guru)->getJson("/api/jurnal/{$jurnal->id}")->assertNotFound();
    }
}
