<?php

namespace Tests\Feature;

use App\Models\{AdminAuditLog, Alumni, RiwayatKelasSiswa, Siswa, User};
use App\Support\{ImporPengguna, SiklusHidupSiswa};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudentLifecycleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $kelas = $this->buatKelas();
        $kelas->tahunAjaran->update(['mulai' => '2026-07-01', 'selesai' => '2027-06-30']);
        $user = $this->buatSiswa(['nis' => '00123', 'kelas_id' => $kelas->id]);
        RiwayatKelasSiswa::daftarkan($user->siswa, $kelas, $kelas->tahun_ajaran_kode, '2026-07-01');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        return $user;
    }

    private function payload(User $user, array $extra = []): array
    {
        return $extra + ['nama' => 'Profil baru', 'kelas_id' => $user->siswa->kelas_id,
            'status' => $user->siswa->status, 'email' => $user->email, 'username' => $user->username];
    }

    public function test_reasoned_correction_closes_membership_opens_today_and_audits_snapshots(): void
    {
        $user = $this->student();
        $source = $user->siswa->kelas_id;
        $dest = $this->buatKelas(['nama_kelas' => 'XI UJI', 'tingkat' => 'XI']);
        $this->put(route('admin.siswa.update', $user->nis), $this->payload($user, ['kelas_id' => $dest->id, 'alasan' => 'Koreksi penempatan']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('riwayat_kelas_siswa', ['siswa_nis' => '00123', 'kelas_id' => $source, 'aktif' => false, 'status' => 'pindah', 'tanggal_selesai' => '2026-10-30 00:00:00']);
        $this->assertDatabaseHas('riwayat_kelas_siswa', ['siswa_nis' => '00123', 'kelas_id' => $dest->id, 'aktif' => true, 'tanggal_mulai' => '2026-10-30 00:00:00', 'catatan' => 'Koreksi penempatan']);
        $log = AdminAuditLog::where('aksi', 'siswa.lifecycle')->firstOrFail();
        $this->assertSame($source, $log->metadata['sebelum']['kelas_id']);
        $this->assertSame($dest->id, $log->metadata['sesudah']['kelas_id']);
        $this->assertStringNotContainsString('Koreksi penempatan', json_encode($log->metadata));
    }

    public function test_graduation_creates_verified_alumni_profile_preserves_password_and_reversal_is_rejected(): void
    {
        $user = $this->student();
        $hash = $user->password;
        $source = $user->siswa->kelas_id;
        $this->put(route('admin.siswa.update', $user->nis), $this->payload($user, ['status' => 'lulus', 'alasan' => 'Keputusan kelulusan']))->assertSessionHasNoErrors();
        $alumni = Alumni::where('siswa_nis', $user->nis)->firstOrFail();
        $this->assertSame('2026/2027', $alumni->tahun_lulus);
        $this->assertSame('2026-10-30', $alumni->tanggal_lulus->toDateString());
        $this->assertSame($source, $alumni->kelas_terakhir_id);
        $this->assertNull($user->siswa->fresh()->kelas_id);
        $this->assertSame(0, RiwayatKelasSiswa::where('aktif', true)->count());
        $user->unsetRelations();
        $this->put(route('admin.siswa.update', $user->nis), $this->payload($user))->assertSessionHasNoErrors();
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame($alumni->getAttributes(), $alumni->fresh()->getAttributes());
        $this->put(route('admin.siswa.update', $user->nis), $this->payload($user, ['status' => 'aktif', 'kelas_id' => $source, 'alasan' => 'Batal']))->assertSessionHasErrors('status');
        $this->assertDatabaseHas('siswa', ['nis' => $user->nis, 'status' => 'lulus', 'kelas_id' => null]);
        $this->assertDatabaseCount('alumni', 1);
    }

    public function test_future_membership_cannot_be_closed_before_start(): void
    {
        $user = $this->student();
        RiwayatKelasSiswa::query()->update(['tanggal_mulai' => '2026-11-01']);
        $this->put(route('admin.siswa.update', $user->nis), $this->payload($user, ['status' => 'nonaktif', 'alasan' => 'Koreksi']))->assertSessionHasErrors('kelas_id');
        $this->assertDatabaseHas('siswa', ['nis' => $user->nis, 'status' => 'aktif']);
    }

    public function test_audit_failure_rolls_back_profile_membership_and_account(): void
    {
        $user = $this->student();
        AdminAuditLog::creating(function ($log) { if ($log->aksi === 'siswa.lifecycle') { throw new \RuntimeException('audit unavailable'); } });
        try {
            app(SiklusHidupSiswa::class)->perbarui($user->siswa, ['nama' => 'Tidak tersimpan', 'status' => 'nonaktif', 'alasan' => 'Koreksi']);
            $this->fail('Missing critical audit allowed');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('audit', $e->getMessage());
        } finally {
            AdminAuditLog::flushEventListeners();
        }
        $this->assertDatabaseHas('siswa', ['nis' => $user->nis, 'status' => 'aktif', 'nama' => $user->siswa->nama]);
        $this->assertDatabaseHas('riwayat_kelas_siswa', ['siswa_nis' => $user->nis, 'aktif' => true, 'tanggal_selesai' => null]);
    }

    private function importRow(User $user, array $extra = []): array
    {
        return ['error' => [], 'aksi' => 'perbarui', 'data' => $extra + [
            'nis' => $user->nis, 'nama' => 'Profil impor', 'email' => $user->email, 'status' => 'aktif',
            '_kelas_id' => $user->siswa->kelas_id, '_username' => $user->username,
            '_user_id' => $user->id, 'password' => '', '_password' => 'fallback123',
        ]];
    }

    public function test_import_profile_preserves_password_despite_fallback_and_explicit_password_still_resets(): void
    {
        $user = $this->student();
        $hash = $user->password;
        ImporPengguna::simpan([$this->importRow($user)], 'siswa');
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame('Profil impor', $user->siswa->fresh()->nama);
        ImporPengguna::simpan([$this->importRow($user, ['password' => 'explicit123', '_password' => 'explicit123'])], 'siswa');
        $this->assertTrue(password_verify('explicit123', $user->fresh()->password));
    }

    public function test_import_cannot_reassign_cross_role_account_even_bypassing_preview(): void
    {
        $user = $this->student();
        $admin = User::factory()->create(['role' => 'admin']);
        try {
            ImporPengguna::simpan([$this->importRow($user, ['email' => $admin->email, '_user_id' => $admin->id])], 'siswa');
            $this->fail('Account reassignment accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertDatabaseCount('siswa', 1);
    }

    public function test_import_blocks_academic_changes_even_with_forged_reason_and_user_pointer(): void
    {
        $user = $this->student();
        $dest = $this->buatKelas(['nama_kelas' => 'XI UJI', 'tingkat' => 'XI']);
        try {
            ImporPengguna::simpan([$this->importRow($user, ['_kelas_id' => $dest->id, 'alasan' => 'Untrusted'])], 'siswa');
            $this->fail('Generic import academic correction accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kelas_id', $e->errors());
        }
        $this->assertSame($user->siswa->kelas_id, $user->siswa->fresh()->kelas_id);
        $this->assertDatabaseCount('riwayat_kelas_siswa', 1);
    }

    public function test_preview_checks_identity_before_email_and_existing_profile_needs_no_fallback(): void
    {
        $user = $this->student();
        $path = tempnam(sys_get_temp_dir(), 'lifecycle').'.csv';
        $write = function (array $row) use ($path) {
            $f = fopen($path, 'w');
            fputcsv($f, ['Nama Lengkap', 'Email', 'NIS', 'Kelas', 'Password', 'Status'], ',', '"', '');
            fputcsv($f, $row, ',', '"', ''); fclose($f);
        };
        $write(['Profil', 'changed@example.test', $user->nis, $user->siswa->kelas->nama_kelas, '', 'aktif']);
        $result = ImporPengguna::periksa($path, 'siswa', null, true);
        $this->assertSame([], $result['baris'][0]['error']);
        $this->assertSame('perbarui', $result['baris'][0]['aksi']);
        $this->assertSame($user->id, $result['baris'][0]['data']['_user_id']);
        $admin = User::factory()->create(['role' => 'admin']);
        $write(['Profil', $admin->email, 'NEW001', $user->siswa->kelas->nama_kelas, 'password123', 'aktif']);
        $result = ImporPengguna::periksa($path, 'siswa', null, true);
        $this->assertNotEmpty($result['baris'][0]['error']);
        unlink($path);
    }

    public function test_registration_tracks_membership_today_and_form_offers_reason(): void
    {
        $user = $this->student();
        $this->get(route('admin.siswa.edit', $user->nis))->assertOk()->assertSee('name="alasan"', false);
        $created = app(\App\Support\PendaftaranPengguna::class)->siswa([
            'nis' => '00999', 'nama' => 'Pendaftaran', 'kelas_id' => $user->siswa->kelas_id,
            'email' => 'new@example.test', 'username' => 'new.student', 'password' => 'password123',
        ]);
        $this->assertDatabaseHas('riwayat_kelas_siswa', ['siswa_nis' => $created->nis, 'aktif' => true, 'tanggal_mulai' => '2026-10-30 00:00:00']);
    }

    public function test_backfill_nonactive_uses_supported_pindah_enum(): void
    {
        $user = $this->student();
        RiwayatKelasSiswa::query()->delete();
        $user->siswa->update(['status' => 'nonaktif']);
        \App\Support\BackfillRiwayatKelas::jalankan(false);
        $this->assertDatabaseHas('riwayat_kelas_siswa', ['siswa_nis' => $user->nis, 'aktif' => false, 'status' => 'pindah', 'tanggal_mulai' => null]);
    }

    public function test_destination_period_outside_today_is_rejected_atomically(): void
    {
        $user = $this->student();
        // 2A.6 unique active invariant: future fixtures must explicitly remain inactive.
        \App\Models\TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);
        $dest = $this->buatKelas(['nama_kelas' => 'XI FUTURE', 'tingkat' => 'XI', 'tahun_ajaran_kode' => '2027/2028']);
        $dest->tahunAjaran->update(['mulai' => '2027-07-01', 'selesai' => '2028-06-30']);
        $this->put(route('admin.siswa.update', $user->nis), $this->payload($user, ['kelas_id' => $dest->id, 'alasan' => 'Koreksi']))->assertSessionHasErrors('kelas_id');
        $this->assertDatabaseCount('riwayat_kelas_siswa', 1);
        $this->assertDatabaseHas('riwayat_kelas_siswa', ['siswa_nis' => $user->nis, 'aktif' => true]);
    }

    public function test_bulk_transition_refuses_existing_alumni_instead_of_insert_ignore_repair(): void
    {
        $user = $this->student();
        $class = $user->siswa->kelas;
        $class->update(['tingkat' => 'XII']);
        // 2A.6: destination preparation cannot implicitly replace the active source.
        \App\Models\TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false]);
        $dest = $this->buatKelas(['nama_kelas' => 'X NEW', 'tahun_ajaran_kode' => '2027/2028']);
        Alumni::create(['siswa_nis' => $user->nis, 'tahun_lulus' => '2026/2027', 'tanggal_lulus' => '2026-09-01', 'kelas_terakhir_id' => $class->id]);
        try {
            \App\Support\TransisiAkademik::eksekusi('2026/2027', '2027/2028');
            $this->fail('Inconsistent alumni silently ignored');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        $this->assertDatabaseHas('siswa', ['nis' => $user->nis, 'status' => 'aktif']);
        $this->assertDatabaseHas('riwayat_kelas_siswa', ['siswa_nis' => $user->nis, 'aktif' => true]);
        $this->assertDatabaseHas('alumni', ['siswa_nis' => $user->nis, 'tanggal_lulus' => '2026-09-01 00:00:00']);
    }

    public function test_nonactivation_closes_access_and_retains_reason_without_erasing_history(): void
    {
        $user = $this->student();
        $this->put(route('admin.siswa.update', $user->nis), $this->payload($user, ['status' => 'nonaktif', 'alasan' => 'Keluar sekolah']))->assertSessionHasNoErrors();
        $this->assertSame('nonaktif', $user->fresh()->status);
        $this->assertDatabaseHas('riwayat_kelas_siswa', ['siswa_nis' => $user->nis, 'aktif' => false, 'status' => 'pindah', 'catatan' => 'Keluar sekolah']);
    }

    public function test_shared_service_deactivates_account_without_resetting_password(): void
    {
        $user = $this->student();
        app(SiklusHidupSiswa::class)->perbarui($user->siswa, ['status' => 'nonaktif', 'alasan' => 'Keluar']);
        $this->assertSame('nonaktif', $user->fresh()->status);
        $this->assertSame($user->password, $user->fresh()->password);
    }

    public function test_profile_update_does_not_reactivate_pending_account(): void
    {
        $user = $this->student();
        $user->update(['status' => 'pending']);
        $this->put(route('admin.siswa.update', $user->nis), $this->payload($user))->assertSessionHasNoErrors();
        $this->assertSame('pending', $user->fresh()->status);
    }

    public function test_import_rejects_same_role_different_identity_and_guru_cross_role(): void
    {
        $user = $this->student();
        $other = $this->buatSiswa(['nis' => '00456', 'kelas_id' => $user->siswa->kelas_id]);
        foreach ([['siswa', $this->importRow($user, ['email' => $other->email, '_user_id' => null])],
            ['guru', ['error' => [], 'aksi' => 'baru', 'data' => ['nip' => '12345678', 'nama' => 'Guru', 'email' => $user->email, 'status' => 'aktif', '_password' => 'password123', '_username' => 'guru', '_user_id' => null]]]] as [$jenis, $row]) {
            try {
                ImporPengguna::simpan([$row], $jenis);
                $this->fail('Cross-identity import accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('email', $e->errors());
            }
        }
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertDatabaseCount('guru', 0);
    }

    public function test_graduation_without_verified_period_dates_is_rejected(): void
    {
        $user = $this->student();
        $user->siswa->kelas->tahunAjaran->update(['mulai' => null, 'selesai' => null]);
        $this->put(route('admin.siswa.update', $user->nis), $this->payload($user, ['status' => 'lulus', 'alasan' => 'Lulus']))->assertSessionHasErrors('status');
        $this->assertDatabaseCount('alumni', 0);
        $this->assertDatabaseHas('siswa', ['nis' => $user->nis, 'status' => 'aktif']);
    }

    public function test_alumni_profile_import_with_blank_class_and_status_preserves_graduation(): void
    {
        $user = $this->student();
        app(SiklusHidupSiswa::class)->perbarui($user->siswa, ['status' => 'lulus', 'alasan' => 'Lulus']);
        $user->unsetRelations();
        $path = tempnam(sys_get_temp_dir(), 'alumni').'.csv';
        $f = fopen($path, 'w');
        fputcsv($f, ['Nama Lengkap', 'Email', 'NIS', 'Kelas', 'Password', 'Status'], ',', '"', '');
        fputcsv($f, ['Profil alumni', $user->email, $user->nis, '', '', ''], ',', '"', ''); fclose($f);
        $result = ImporPengguna::periksa($path, 'siswa', null, true);
        unlink($path);
        $this->assertSame([], $result['baris'][0]['error']);
        ImporPengguna::simpan($result['baris'], 'siswa');
        $this->assertDatabaseHas('siswa', ['nis' => $user->nis, 'nama' => 'Profil alumni', 'status' => 'lulus', 'kelas_id' => null]);
        $this->assertDatabaseCount('alumni', 1);
        $this->assertSame($user->password, $user->fresh()->password);
    }

    public function test_individual_class_correction_requires_reason_without_partial_profile_update(): void
    {
        $user = $this->student();
        $dest = $this->buatKelas(['nama_kelas' => 'XI UJI', 'tingkat' => 'XI']);
        $before = $user->siswa->getAttributes();
        $this->put(route('admin.siswa.update', $user->nis), $this->payload($user, ['kelas_id' => $dest->id]))
            ->assertSessionHasErrors('alasan');
        $this->assertSame($before, $user->siswa->fresh()->getAttributes());
        $this->assertDatabaseCount('riwayat_kelas_siswa', 1);
    }
}
