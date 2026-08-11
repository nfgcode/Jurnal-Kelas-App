<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Presensi;
use App\Models\User;
use App\Support\Periode;
use App\Support\Ringkasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The automatic-journal feature: prefill an attendance form from an earlier
 * lesson (A), the nightly backfill of empty past meetings (B), the "Otomatis"
 * status + editable/adopt + honesty attestation (C), and the "diedit setelah
 * hari-H" marker + admin filter/recap (D). Built on hand-made fixtures so each
 * case is deterministic and fast.
 */
class JurnalOtomatisTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function guru(): User
    {
        return User::factory()->create([
            'role' => 'guru',
            'nip' => '1985'.str_pad((string) ++self::$seq, 8, '0', STR_PAD_LEFT),
        ]);
    }

    private function siswa(Kelas $kelas, bool $ketua = false): User
    {
        return User::factory()->create([
            'role' => 'siswa',
            'nis' => '2026'.str_pad((string) ++self::$seq, 6, '0', STR_PAD_LEFT),
            'kelas_id' => $kelas->id,
            'is_ketua_kelas' => $ketua,
        ]);
    }

    private function mapel(string $nama): MataPelajaran
    {
        $m = new MataPelajaran;
        $m->forceFill(['nama' => $nama, 'kode' => 'K'.++self::$seq, 'kelompok' => 'wajib', 'jp_per_minggu' => 2]);
        $m->save();

        return $m;
    }

    private function kelas(?User $wali = null): Kelas
    {
        return Kelas::create([
            'nama_kelas' => 'X UJI '.++self::$seq,
            'tingkat' => 'X',
            'tahun_ajaran' => '2026/2027',
            'wali_kelas_id' => $wali?->id,
        ]);
    }

    private function jadwal(Kelas $kelas, MataPelajaran $mapel, User $guru, string $tanggal, int $jamMulai): Jadwal
    {
        return Jadwal::create([
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'hari' => Ringkasan::HARI[Carbon::parse($tanggal)->dayOfWeekIso - 1],
            'jam_ke_mulai' => $jamMulai,
            'jam_ke_selesai' => $jamMulai + 1,
            'jam_mulai' => sprintf('%02d:00', 6 + $jamMulai),
            'jam_selesai' => sprintf('%02d:30', 6 + $jamMulai),
        ]);
    }

    /** A past date that is a school day (Mon–Sat), so the backfill will consider it. */
    private function tanggalLampau(int $mundur = 3): string
    {
        $t = Carbon::today()->subDays($mundur);

        while ($t->dayOfWeekIso === 7) {
            $t = $t->subDay();
        }

        return $t->toDateString();
    }

    private function jurnal(Jadwal $jadwal, string $tanggal, array $ganti = []): Jurnal
    {
        return Jurnal::create(array_merge([
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Materi uji',
            'kehadiran_guru_status' => 'hadir',
            'guru_id' => $jadwal->guru_id,
            'diisi_oleh_id' => $jadwal->guru_id,
            'diisi_oleh_peran' => 'guru',
        ], $ganti));
    }

    // ---- A. Prefill -------------------------------------------------------

    public function test_presensi_form_prefills_from_an_earlier_meeting_the_same_day(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();

        $jadwalA = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1); // earlier
        $jadwalB = $this->jadwal($kelas, $this->mapel('Fisika'), $guru, $tanggal, 3);      // later, empty roster

        [$s1, $s2] = [$this->siswa($kelas), $this->siswa($kelas)];

        // The earlier lesson recorded s1 present, s2 sick.
        $jurnalA = $this->jurnal($jadwalA, $tanggal);
        Presensi::create(['jurnal_id' => $jurnalA->id, 'siswa_id' => $s1->id, 'status' => 'hadir']);
        Presensi::create(['jurnal_id' => $jurnalA->id, 'siswa_id' => $s2->id, 'status' => 'sakit']);

        // The later lesson has a journal but no roster yet.
        $jurnalB = $this->jurnal($jadwalB, $tanggal);

        $prefill = $this->actingAs($guru)->get(route('presensi.create', $jurnalB))
            ->assertOk()
            ->viewData('prefill');

        $this->assertNotNull($prefill, 'Form seharusnya menawarkan prefill.');
        $this->assertSame('hadir', $prefill['map'][$s1->id]);
        $this->assertSame('sakit', $prefill['map'][$s2->id]);
    }

    // ---- B. Nightly backfill ---------------------------------------------

    public function test_backfill_creates_a_system_journal_and_copies_the_roster(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();

        $jadwalA = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1); // filled sibling
        $jadwalB = $this->jadwal($kelas, $this->mapel('Fisika'), $guru, $tanggal, 3);      // the gap

        [$s1, $s2] = [$this->siswa($kelas), $this->siswa($kelas)];
        $jurnalA = $this->jurnal($jadwalA, $tanggal);
        Presensi::create(['jurnal_id' => $jurnalA->id, 'siswa_id' => $s1->id, 'status' => 'hadir']);
        Presensi::create(['jurnal_id' => $jurnalA->id, 'siswa_id' => $s2->id, 'status' => 'alpa']);

        $this->artisan('jurnal:isi-otomatis', ['--sekarang' => true, '--lookback' => 7])->assertSuccessful();

        $sistem = Jurnal::where('jadwal_id', $jadwalB->id)->whereDate('tanggal', $tanggal)->first();

        $this->assertNotNull($sistem, 'Pertemuan kosong seharusnya diisi otomatis.');
        $this->assertSame('sistem', $sistem->diisi_oleh_peran);
        $this->assertSame('tidak_hadir', $sistem->kehadiran_guru_status);
        $this->assertFalse((bool) $sistem->kehadiran_guru_ada_tugas);
        $this->assertSame('Otomatis', $sistem->statusPengisian()['label']);

        // Roster copied from the sibling meeting.
        $roster = $sistem->presensis()->pluck('status', 'siswa_id');
        $this->assertSame('hadir', $roster[$s1->id]);
        $this->assertSame('alpa', $roster[$s2->id]);
    }

    public function test_backfill_is_idempotent(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);

        $this->artisan('jurnal:isi-otomatis', ['--sekarang' => true, '--lookback' => 7])->assertSuccessful();
        $this->artisan('jurnal:isi-otomatis', ['--sekarang' => true, '--lookback' => 7])->assertSuccessful();

        $this->assertSame(1, Jurnal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', $tanggal)->count());
    }

    public function test_backfill_leaves_today_and_the_future_alone(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $hariIni = Carbon::today()->dayOfWeekIso === 7 ? Carbon::today()->addDay() : Carbon::today();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $hariIni->toDateString(), 1);

        $this->artisan('jurnal:isi-otomatis', ['--sekarang' => true, '--lookback' => 14])->assertSuccessful();

        $this->assertSame(0, Jurnal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', $hariIni->toDateString())->count());
    }

    // ---- C. Otomatis chip + editable/adopt + attestation ------------------

    public function test_editing_a_system_journal_requires_the_honesty_checkbox(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);

        $sistem = $this->jurnal($jadwal, $tanggal, [
            'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
            'kehadiran_guru_status' => 'tidak_hadir', 'kehadiran_guru_ada_tugas' => false,
        ]);

        // Without the attestation → rejected, still a system journal.
        $this->actingAs($guru)->put(route('jurnal.update', $sistem), [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Sebenarnya saya hadir',
            'kehadiran_guru' => 'hadir',
        ])->assertSessionHasErrors('pernyataan');

        $this->assertTrue($sistem->fresh()->dibuatSistem());
    }

    public function test_editing_a_system_journal_with_attestation_adopts_it(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);

        $sistem = $this->jurnal($jadwal, $tanggal, [
            'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
            'kehadiran_guru_status' => 'tidak_hadir', 'kehadiran_guru_ada_tugas' => false,
        ]);

        $this->actingAs($guru)->put(route('jurnal.update', $sistem), [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Sebenarnya saya hadir dan mengajar',
            'kehadiran_guru' => 'hadir',
            'pernyataan' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $segar = $sistem->fresh();
        $this->assertSame('guru', $segar->diisi_oleh_peran);      // adopted
        $this->assertSame($guru->id, $segar->diisi_oleh_id);
        $this->assertSame('hadir', $segar->kehadiran_guru_status);
        $this->assertFalse($segar->dibuatSistem());
        $this->assertTrue($segar->dieditSetelahHari());           // edited after the lesson day
    }

    public function test_filling_a_meeting_with_a_system_journal_redirects_to_editing_it(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);

        $sistem = $this->jurnal($jadwal, $tanggal, [
            'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
        ]);

        $this->actingAs($guru)
            ->get(route('jurnal.create', ['jadwal_id' => $jadwal->id, 'tanggal' => $tanggal]))
            ->assertRedirect(route('jurnal.edit', $sistem));
    }

    public function test_filing_a_journal_beside_a_system_one_is_refused(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);

        $sistem = $this->jurnal($jadwal, $tanggal, [
            'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
        ]);

        // The unique index would accept this pair ('sistem' vs 'guru'), so the
        // controller has to be the one that refuses a second journal.
        $this->actingAs($guru)->post('/jurnal', [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Jurnal kedua',
            'kehadiran_guru' => 'hadir',
        ])->assertRedirect(route('jurnal.edit', $sistem));

        $this->assertSame(1, Jurnal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', $tanggal)->count());
    }

    // ---- Metrics stay honest about what the automation filled --------------

    public function test_a_system_journal_never_counts_as_filled_in(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();

        $jadwalA = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);
        $jadwalB = $this->jadwal($kelas, $this->mapel('Fisika'), $guru, $tanggal, 3);

        $this->jurnal($jadwalA, $tanggal);                                   // written by the guru
        $this->jurnal($jadwalB, $tanggal, [                                  // filled by the nightly job
            'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
            'kehadiran_guru_status' => 'tidak_hadir', 'kehadiran_guru_ada_tugas' => false,
        ]);

        // The window ends today, not on $tanggal: SQLite stores `tanggal` as
        // "Y-m-d 00:00:00", so a `selesai` on the same day would sort *before* the
        // row and quietly exclude it (see JurnalOtomatisTest's other ranges).
        $periode = Periode::dari(Request::create('/', 'GET', [
            'preset' => 'custom', 'mulai' => $tanggal, 'selesai' => Carbon::today()->toDateString(),
        ]));

        // Completeness counts the guru's journal only — one of the two meetings.
        $this->assertSame(50.0, Ringkasan::kelengkapanKelas($kelas->id, $periode));
        $this->assertSame(50.0, Ringkasan::kelengkapan('kelas_id', $periode)[$kelas->id]);

        // And the automatic one is reported on its own instead of vanishing.
        $this->assertSame(1, Ringkasan::otomatis($periode));

        // A placeholder's "tidak hadir · tanpa tugas" is not an observed absence,
        // so it must not brand the teacher in the attendance rollup.
        $kehadiran = Ringkasan::kehadiranGuru(Jurnal::query(), $periode);
        $this->assertSame(1, $kehadiran['total']);
        $this->assertSame(0, $kehadiran['tanpa_tugas']);
    }

    public function test_the_recap_splits_written_automatic_and_missing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $hariIni = Carbon::today()->toDateString();

        $jadwalA = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);
        $jadwalB = $this->jadwal($kelas, $this->mapel('Fisika'), $guru, $tanggal, 3);
        $this->jadwal($kelas, $this->mapel('Kimia'), $guru, $tanggal, 6); // never filled at all

        $this->jurnal($jadwalA, $tanggal);
        $this->jurnal($jadwalB, $tanggal, ['diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null]);

        $statistik = $this->actingAs($admin)
            ->get("/admin/laporan/jurnal?preset=custom&mulai={$tanggal}&selesai={$hariIni}")
            ->assertOk()
            ->viewData('statistik');

        $this->assertSame(1, $statistik['terisi']);
        $this->assertSame(1, $statistik['otomatis']);
        $this->assertSame(1, $statistik['belum']);
        // The three buckets must account for every scheduled meeting.
        $this->assertSame(3, $statistik['terisi'] + $statistik['otomatis'] + $statistik['belum']);
    }

    public function test_a_backfilled_journal_is_not_reported_as_a_late_teacher(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau(10); // long enough ago to be "late" if counted
        $hariIni = Carbon::today()->toDateString();

        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);
        $this->jurnal($jadwal, $tanggal, ['diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null]);

        $statistik = $this->actingAs($admin)
            ->get("/admin/laporan/jurnal?preset=custom&mulai={$tanggal}&selesai={$hariIni}")
            ->assertOk()
            ->viewData('statistik');

        $this->assertSame(0, $statistik['telat'], 'Jurnal otomatis selalu "telat" secara teknis; itu bukan guru yang telat.');
    }

    // ---- API parity (same rules as the web flow) --------------------------

    public function test_api_refuses_to_file_a_journal_beside_a_system_one(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);

        $sistem = $this->jurnal($jadwal, $tanggal, [
            'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
        ]);

        $this->actingAs($guru)->postJson('/api/jurnal', [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Lewat API',
            'kehadiran_guru_status' => 'hadir',
        ])
            ->assertStatus(422)
            ->assertJsonPath('jurnal_id', $sistem->id)
            // The pointer must be usable: these routes bind on the public id.
            ->assertJsonPath('jurnal_public_id', $sistem->public_id);

        $this->assertSame(1, Jurnal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', $tanggal)->count());
    }

    public function test_api_editing_a_system_journal_requires_the_attestation(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);

        $sistem = $this->jurnal($jadwal, $tanggal, [
            'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
            'kehadiran_guru_status' => 'tidak_hadir', 'kehadiran_guru_ada_tugas' => false,
        ]);

        $this->actingAs($guru)->putJson("/api/jurnal/{$sistem->public_id}", [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Sebenarnya saya hadir',
            'kehadiran_guru_status' => 'hadir',
        ])->assertStatus(422)->assertJsonValidationErrors('pernyataan');

        $this->assertTrue($sistem->fresh()->dibuatSistem());
    }

    public function test_api_editing_a_system_journal_with_attestation_adopts_it(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);

        $sistem = $this->jurnal($jadwal, $tanggal, [
            'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
            'kehadiran_guru_status' => 'tidak_hadir', 'kehadiran_guru_ada_tugas' => false,
        ]);

        $this->actingAs($guru)->putJson("/api/jurnal/{$sistem->public_id}", [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Sebenarnya saya hadir dan mengajar',
            'kehadiran_guru_status' => 'hadir',
            'pernyataan' => true,
        ])->assertOk();

        $segar = $sistem->fresh();
        $this->assertSame('guru', $segar->diisi_oleh_peran);
        $this->assertSame($guru->id, $segar->diisi_oleh_id);
        $this->assertFalse($segar->dibuatSistem());
        // The marker is set by the model event, so the API path carries it too.
        $this->assertTrue($segar->dieditSetelahHari());
    }

    public function test_api_editing_an_ordinary_journal_needs_no_attestation(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);
        $jurnal = $this->jurnal($jadwal, $tanggal);

        $this->actingAs($guru)->putJson("/api/jurnal/{$jurnal->public_id}", [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Diperbarui biasa',
            'kehadiran_guru_status' => 'hadir',
        ])->assertOk();

        $this->assertSame('Diperbarui biasa', $jurnal->fresh()->materi);
    }

    // ---- D. "Diedit setelah hari-H" marker + admin filter -----------------

    public function test_editing_after_the_lesson_day_flags_and_badges_the_journal(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);
        $jurnal = $this->jurnal($jadwal, $tanggal);

        $this->assertFalse($jurnal->dieditSetelahHari());

        $this->actingAs($guru)->put(route('jurnal.update', $jurnal), [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $tanggal,
            'materi' => 'Materi diperbaiki belakangan',
            'kehadiran_guru' => 'hadir',
        ])->assertRedirect();

        $this->assertTrue($jurnal->fresh()->dieditSetelahHari());

        $this->actingAs($guru)->get(route('jurnal.show', $jurnal))
            ->assertSee('Diedit setelah hari-H');
    }

    public function test_admin_can_filter_journals_edited_after_the_day(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau(3);

        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);
        $jadwal2 = $this->jadwal($kelas, $this->mapel('Fisika'), $guru, $tanggal, 3);

        // One flagged as edited-after-the-day, one not.
        $this->jurnal($jadwal, $tanggal, ['materi' => 'JURNALDIEDIT', 'diedit_setelah_hari' => true]);
        $this->jurnal($jadwal2, $tanggal, ['materi' => 'JURNALBIASA']);

        // An explicit custom range so the fixtures fall inside the window whatever
        // day the suite runs, and off the boundary that trips SQLite's date store.
        $rentang = "preset=custom&mulai={$tanggal}&selesai=".Carbon::today()->toDateString();

        $this->actingAs($admin)->get("/admin/laporan/jurnal?{$rentang}&edit_lewat_hari=1")
            ->assertOk()
            ->assertSee('JURNALDIEDIT')
            ->assertDontSee('JURNALBIASA');

        $this->actingAs($admin)->get("/admin/laporan/jurnal?{$rentang}")
            ->assertOk()
            ->assertSee('JURNALDIEDIT')
            ->assertSee('JURNALBIASA');
    }
}
