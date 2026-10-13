<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PresensiHarian;
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

    /**
     * These helpers hand back the *account*, because that is what the tests act
     * as. The person row behind it is one hop away as `$akun->guru` / `->siswa`,
     * and the NIP/NIS a schedule or roster needs is on the account too.
     */
    private function guru(): User
    {
        return $this->buatGuru(['nip' => '1985'.str_pad((string) ++self::$seq, 8, '0', STR_PAD_LEFT)]);
    }

    private function siswa(Kelas $kelas, bool $ketua = false): User
    {
        $akun = $this->buatSiswa([
            'nis' => '2026'.str_pad((string) ++self::$seq, 6, '0', STR_PAD_LEFT),
            'kelas_id' => $kelas->id,
        ]);

        // The chair is recorded on the class, not on the student.
        if ($ketua) {
            $kelas->update(['ketua_nis' => $akun->nis]);
        }

        return $akun;
    }

    /** The class's chair — the only student who may write its journal. */
    private function ketua(Kelas $kelas): User
    {
        return $this->siswa($kelas, true);
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
        return $this->buatKelas([
            'nama_kelas' => 'X UJI '.++self::$seq,
            'tingkat' => 'X',
            'wali_kelas_nip' => $wali?->nip,
        ]);
    }

    private function jadwal(Kelas $kelas, MataPelajaran $mapel, User $guru, string $tanggal, int $jamMulai): Jadwal
    {
        return Jadwal::create([
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'guru_nip' => $guru->nip,
            'hari' => Ringkasan::HARI[Carbon::parse($tanggal)->dayOfWeekIso - 1],
            'jam_ke_mulai' => $jamMulai,
            'jam_ke_selesai' => $jamMulai + 1,
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
            // The author is an account; the NIP identifies the person, not the login.
            'diisi_oleh_id' => User::where('nip', $jadwal->guru_nip)->value('id'),
            'diisi_oleh_peran' => 'guru',
        ], $ganti));
    }

    // ---- B. Nightly backfill ---------------------------------------------

    public function test_backfill_creates_a_system_journal(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();

        $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);
        $jadwalB = $this->jadwal($kelas, $this->mapel('Fisika'), $guru, $tanggal, 3); // the gap

        $this->artisan('jurnal:isi-otomatis', ['--sekarang' => true, '--lookback' => 7])->assertSuccessful();

        $sistem = Jurnal::where('jadwal_id', $jadwalB->id)->whereDate('tanggal', $tanggal)->first();

        $this->assertNotNull($sistem, 'Pertemuan kosong seharusnya diisi otomatis.');
        $this->assertSame('sistem', $sistem->diisi_oleh_peran);
        $this->assertSame('tidak_hadir', $sistem->kehadiran_guru_status);
        $this->assertFalse((bool) $sistem->kehadiran_guru_ada_tugas);
        $this->assertSame('Otomatis', $sistem->statusPengisian()['label']);
        // Nothing is invented for the lesson's content.
        $this->assertSame('', $sistem->materi);
        $this->assertTrue($sistem->materiKosong());
    }

    /**
     * Oct 2026: a placeholder says plainly that the lesson has not been
     * described yet, instead of showing system text as if it were the materi.
     */
    public function test_a_placeholder_shows_materi_and_tugas_as_not_filled_in(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);

        $sistem = $this->jurnal($jadwal, $tanggal, [
            'materi' => '', 'tugas' => null,
            'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
        ]);

        $this->actingAs($guru)->get(route('jurnal.show', $sistem))
            ->assertOk()
            ->assertSee(Jurnal::MATERI_BELUM)
            ->assertSee(Jurnal::TUGAS_BELUM)
            ->assertDontSee('Tidak ada tugas pada pertemuan ini.')
            ->assertDontSee('mohon lengkapi');

        // The edit form opens blank, so the guru writes the materi themselves.
        $this->actingAs($guru)->get(route('jurnal.edit', $sistem))
            ->assertOk()
            ->assertViewHas('jurnal', fn ($j) => $j->materiKosong());

        // Lists read "Belum diisi" rather than a blank cell.
        $this->actingAs($guru)->get('/jurnal')->assertOk()->assertSee('Belum diisi');
    }

    /** A journal somebody wrote with no homework still says there was none. */
    public function test_a_written_journal_without_tugas_still_says_there_was_none(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);

        $jurnal = $this->jurnal($jadwal, $tanggal, ['tugas' => null]);

        $this->actingAs($guru)->get(route('jurnal.show', $jurnal))
            ->assertOk()
            ->assertSee('Materi uji')
            ->assertSee('Tidak ada tugas pada pertemuan ini.')
            ->assertDontSee(Jurnal::TUGAS_BELUM);
    }

    /**
     * The backfill fills journals, never attendance. A meeting either had its
     * roster marked or it did not — estimating one from a neighbouring lesson
     * would fabricate exactly the record attendance exists to make trustworthy.
     */
    public function test_backfill_never_invents_student_attendance(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();

        $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);
        $this->jadwal($kelas, $this->mapel('Fisika'), $guru, $tanggal, 3);
        $this->siswa($kelas);

        $this->artisan('jurnal:isi-otomatis', ['--sekarang' => true, '--lookback' => 7])->assertSuccessful();

        $this->assertSame(0, PresensiHarian::where('kelas_id', $kelas->id)
            ->whereDate('tanggal', $tanggal)->count());
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

    // ---- C. Otomatis chip + editable/adopt ---------------------------------

    /** Oct 2026: the truthfulness checkbox is gone — the edit form shows no box. */
    public function test_the_edit_form_of_a_system_journal_asks_for_no_attestation(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $tanggal = $this->tanggalLampau();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $tanggal, 1);

        $sistem = $this->jurnal($jadwal, $tanggal, [
            'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
        ]);

        $this->actingAs($guru)->get(route('jurnal.edit', $sistem))
            ->assertOk()
            ->assertDontSee('name="pernyataan"', false)
            ->assertDontSee('dengan <strong>jujur</strong>', false);
    }

    public function test_editing_a_system_journal_adopts_it(): void
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
            'materi' => 'Guru hadir dan mengajar',
            'kehadiran_guru' => 'hadir',
        ])->assertRedirect()->assertSessionHas('success');

        $segar = $sistem->fresh();
        $this->assertSame('guru', $segar->diisi_oleh_peran);      // adopted by the teacher
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

    /**
     * Today's system placeholder is the record a guru opened by taking the roll
     * before the class wrote anything. The ketua never edits (Oct 2026), so they
     * are not sent to an edit screen — they keep "Isi Jurnal", and saving writes
     * the class's journal INTO that row instead of refusing or adding a second.
     */
    public function test_the_ketua_writes_todays_journal_into_the_system_placeholder(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $hariIni = today()->toDateString();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $hariIni, 1);
        $ketua = $this->ketua($kelas);

        $sistem = $this->jurnal($jadwal, $hariIni, [
            'materi' => '', 'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
        ]);

        $this->actingAs($ketua)
            ->get(route('jurnal.create', ['jadwal_id' => $jadwal->id]))
            ->assertOk()
            ->assertViewHas('jadwalTerisi', fn ($terisi) => ! in_array($jadwal->id, $terisi, true));

        $this->actingAs($ketua)->get('/dashboard')
            ->assertOk()
            ->assertSee(route('jurnal.create', ['jadwal_id' => $jadwal->id, 'tanggal' => $hariIni]));

        $this->actingAs($ketua)->get(route('jurnal.show', $sistem))
            ->assertOk()
            ->assertSee(route('jurnal.create', ['jadwal_id' => $jadwal->id, 'tanggal' => $hariIni]));

        $this->actingAs($ketua)->post('/jurnal', [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $hariIni,
            'materi' => 'Persamaan kuadrat',
            'kehadiran_guru' => 'hadir',
        ])->assertSessionHasNoErrors()->assertRedirect(route('jurnal.show', $sistem));

        $this->assertSame(1, Jurnal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', $hariIni)->count());

        $sistem->refresh();
        $this->assertSame('siswa', $sistem->diisi_oleh_peran);
        $this->assertSame($ketua->id, $sistem->diisi_oleh_id);
        $this->assertSame('Persamaan kuadrat', $sistem->materi);

        // Written once, the slot is the class's own: a second submit is refused.
        $this->actingAs($ketua)->post('/jurnal', [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $hariIni,
            'materi' => 'Jurnal kedua',
            'kehadiran_guru' => 'hadir',
        ])->assertSessionHasErrors('jadwal_id');

        $this->assertSame(1, Jurnal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', $hariIni)->count());
    }

    /**
     * The usual order in a classroom: the guru takes the roll at the start of
     * the lesson, the ketua writes the journal afterwards. The roster marked
     * first must end up on the class's journal, not be orphaned or duplicated.
     */
    public function test_the_ketua_writes_the_journal_after_the_guru_took_the_roll(): void
    {
        $guru = $this->guru();
        $kelas = $this->kelas();
        $hariIni = today()->toDateString();
        $jadwal = $this->jadwal($kelas, $this->mapel('Matematika'), $guru, $hariIni, 1);
        $ketua = $this->ketua($kelas);
        $siswa = $this->siswa($kelas);

        $this->actingAs($guru)
            ->post(route('presensi-jurnal.mulai'), ['jadwal_id' => $jadwal->id, 'tanggal' => $hariIni])
            ->assertRedirect();

        $jurnal = Jurnal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', $hariIni)->sole();

        $this->actingAs($guru)->post(route('presensi-jurnal.store', $jurnal), ['presensi' => [
            ['siswa_nis' => $ketua->nis, 'status' => 'hadir'],
            ['siswa_nis' => $siswa->nis, 'status' => 'sakit'],
        ]])->assertRedirect(route('jurnal.show', $jurnal));

        $this->actingAs($ketua)->post('/jurnal', [
            'jadwal_id' => $jadwal->id,
            'tanggal' => $hariIni,
            'materi' => 'Persamaan kuadrat',
            'kehadiran_guru' => 'hadir',
        ])->assertSessionHasNoErrors()->assertRedirect(route('jurnal.show', $jurnal));

        $jurnal->refresh();
        $this->assertSame('siswa', $jurnal->diisi_oleh_peran);
        $this->assertSame('Persamaan kuadrat', $jurnal->materi);
        $this->assertSame(1, Jurnal::where('jadwal_id', $jadwal->id)->whereDate('tanggal', $hariIni)->count());
        $this->assertSame(2, $jurnal->presensis()->count());
    }

    /** Only today's placeholder of the ketua's own class can be written into. */
    public function test_the_ketua_cannot_write_into_another_class_or_another_day(): void
    {
        $guru = $this->guru();
        $kelasKetua = $this->kelas();
        $kelasLain = $this->kelas();
        $hariIni = today()->toDateString();
        $ketua = $this->ketua($kelasKetua);

        $lain = $this->jurnal($this->jadwal($kelasLain, $this->mapel('Fisika'), $guru, $hariIni, 3), $hariIni, [
            'materi' => '', 'diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null,
        ]);
        $kemarin = $this->jurnal(
            $this->jadwal($kelasKetua, $this->mapel('Kimia'), $guru, $this->tanggalLampau(), 5),
            $this->tanggalLampau(),
            ['diisi_oleh_peran' => 'sistem', 'diisi_oleh_id' => null],
        );

        $this->assertFalse($ketua->can('lengkapi', $lain));
        $this->assertFalse($ketua->can('lengkapi', $kemarin));
        $this->assertFalse($guru->can('lengkapi', $lain));
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
        $admin = User::factory()->create();
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
        $admin = User::factory()->create();
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

    public function test_api_editing_a_system_journal_adopts_it(): void
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
            'materi' => 'Guru hadir dan mengajar',
            'kehadiran_guru_status' => 'hadir',
        ])->assertOk();

        $segar = $sistem->fresh();
        $this->assertSame('guru', $segar->diisi_oleh_peran);
        $this->assertSame($guru->id, $segar->diisi_oleh_id);
        $this->assertFalse($segar->dibuatSistem());
        // The marker is set by the model event, so the API path carries it too.
        $this->assertTrue($segar->dieditSetelahHari());
    }

    public function test_api_update_cannot_move_a_journal_onto_another_class(): void
    {
        $guruA = $this->guru();
        $guruB = $this->guru();
        $kelasA = $this->kelas();
        $kelasB = $this->kelas();
        $tanggal = $this->tanggalLampau();

        $jadwalA = $this->jadwal($kelasA, $this->mapel('Matematika'), $guruA, $tanggal, 1);
        $jadwalB = $this->jadwal($kelasB, $this->mapel('Fisika'), $guruB, $tanggal, 3);
        $jurnal = $this->jurnal($jadwalA, $tanggal);

        // jadwal_id is editable, so the destination slot must be re-authorized —
        // otherwise A's journal lands on another class's meeting.
        // The journal's own guru tries to move it onto another teacher's class.
        $this->actingAs($guruA)->putJson("/api/jurnal/{$jurnal->public_id}", [
            'jadwal_id' => $jadwalB->id,
            'tanggal' => $tanggal,
            'materi' => 'Dipindah diam-diam',
            'kehadiran_guru_status' => 'hadir',
        ])->assertForbidden();

        $this->assertSame($jadwalA->id, $jurnal->fresh()->jadwal_id);
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

        // Oct 2026: correcting a past day's journal is the guru's (the ketua
        // kelas only works with today's).
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
        $admin = User::factory()->create();
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
