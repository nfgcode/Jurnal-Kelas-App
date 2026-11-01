<?php

namespace Tests\Feature;

use App\Models\{Kelas, RiwayatKelasSiswa, Siswa, TahunAjaran, User};
use App\Support\{PendaftaranPengguna, SiklusHidupSiswa};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Phase2AReviewGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transition_query_failure_never_discloses_private_bindings(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $source = $this->buatKelas();
        $source->tahunAjaran->update(['mulai' => '2026-07-01', 'selesai' => '2027-06-30']);
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']);
        $student = Siswa::create(['nis' => '00999', 'nama' => 'Original', 'status' => 'aktif', 'kelas_id' => $source->id]);
        RiwayatKelasSiswa::create(['siswa_nis' => $student->nis, 'kelas_id' => $source->id, 'tahun_ajaran_kode' => '2026/2027', 'tingkat' => $source->tingkat, 'status' => 'aktif', 'aktif' => true, 'tanggal_mulai' => '2026-07-01']);
        \App\Support\TransisiAkademik::siapkanKelasTujuan('2027/2028', '2026/2027');
        $preview = \App\Support\TransisiAkademik::pratinjau('2026/2027', '2027/2028');
        $private = 'PRIVATE-CATATAN-991';
        \Illuminate\Support\Facades\DB::listen(function ($query) use ($private) {
            if (str_starts_with($query->sql, 'insert into "riwayat_kelas_siswa"')) {
                throw new \Illuminate\Database\QueryException('sqlite', 'insert into riwayat_kelas_siswa (catatan) values (?)', [$private], new \RuntimeException('SQL rejected'));
            }
        });
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.transisi.eksekusi'), [
            'tahun_asal' => '2026/2027', 'tahun_tujuan' => '2027/2028', 'konfirmasi' => 1,
            'snapshot' => $preview['snapshot'], 'pengecualian' => ['00999' => ['aksi' => 'promosi', 'catatan' => $private]],
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('error', 'Eksekusi transisi gagal. Tidak ada perubahan disimpan; hubungi administrator.');
        $this->assertStringNotContainsString($private, json_encode(session()->all()));
        $this->assertStringNotContainsString($private, (string) @file_get_contents(storage_path('logs/laravel.log')));
        $this->assertDatabaseHas('siswa', ['nis' => $student->nis, 'kelas_id' => $source->id]);
        // Query listener belongs to this test's isolated Laravel application.
    }

    public static function lifecycleWriters(): array
    {
        return ['correction' => ['correction'], 'registration' => ['registration'], 'import' => ['import']];
    }

    #[DataProvider('lifecycleWriters')]
    public function test_lifecycle_writer_locks_singleton_before_any_student_or_account_query(string $writer): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $class = $this->buatKelas();
        $class->tahunAjaran->update(['mulai' => '2026-07-01', 'selesai' => '2027-06-30']);
        $student = Siswa::create(['nis' => '00123', 'nama' => 'Original', 'status' => 'aktif', 'kelas_id' => $class->id]);
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        try {
            if ($writer === 'correction') {
                app(SiklusHidupSiswa::class)->perbarui($student, ['nama' => 'Updated']);
            } elseif ($writer === 'registration') {
                app(PendaftaranPengguna::class)->siswa(['nis' => '00999', 'nama' => 'Intake', 'kelas_id' => $class->id,
                    'email' => 'intake@example.test', 'username' => 'intake', 'password' => 'password123']);
            } else {
                \App\Support\ImporPengguna::simpan([['error' => [], 'aksi' => 'baru', 'data' => [
                    'nis' => '00999', 'nama' => 'Import', 'email' => 'import@example.test', 'status' => 'aktif',
                    '_kelas_id' => $class->id, '_username' => 'import', '_password' => 'password123',
                ]]], 'siswa');
            }
            $queries = \Illuminate\Support\Facades\DB::getQueryLog();
            $this->assertStringContainsString('academic_operation_lock', $queries[0]['query']);
        } finally {
            \Illuminate\Support\Facades\DB::disableQueryLog();
        }
    }

    public static function invalidMemberships(): array
    {
        return [
            'no history' => [null],
            'wrong year' => [['tahun_ajaran_kode' => '2026/2027']],
            'wrong grade' => [['tingkat' => 'XII']],
            'wrong class' => [['kelas_id' => 999]],
            'unknown date' => [['tanggal_mulai' => null]],
            'legacy execution before period' => [['tanggal_mulai' => '2026-10-30']],
            'after period' => [['tanggal_mulai' => '2028-07-01']],
            'closed active history' => [['tanggal_selesai' => '2027-08-01']],
            'nonactive status' => [['status' => 'pindah']],
        ];
    }

    #[DataProvider('invalidMemberships')]
    public function test_activation_requires_verified_membership(?array $override): void
    {
        $this->travelTo(now()->setDate(2027, 7, 2));
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true]);
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']);
        $class = $this->buatKelas(['tahun_ajaran_kode' => '2027/2028']);
        $student = Siswa::create(['nis' => '00999', 'nama' => 'Placed', 'status' => 'aktif', 'kelas_id' => $class->id]);
        if ($override !== null) {
            $member = RiwayatKelasSiswa::daftarkan($student, $class, '2027/2028', '2027-07-01');
            // Corrupt legacy rows bypass model guards deliberately; FK class remains real.
            if (isset($override['kelas_id'])) {
                $override['kelas_id'] = $this->buatKelas(['nama_kelas' => 'XI OTHER', 'tingkat' => 'XI', 'tahun_ajaran_kode' => '2027/2028'])->id;
            }
            $member->update($override);
        }
        try {
            app(\App\Services\AktivasiTahunAjaran::class)->aktifkan('2027/2028');
            $this->fail('Unverified activation accepted');
        } catch (ValidationException|\DomainException $e) {
            $this->assertTrue(TahunAjaran::find('2026/2027')->aktif);
            $this->assertFalse(TahunAjaran::find('2027/2028')->aktif);
        }
        $this->assertDatabaseCount('admin_audit_log', 0);
    }

    public function test_activation_accepts_verified_target_period_evidence_without_rewriting_history(): void
    {
        $this->travelTo(now()->setDate(2027, 7, 2));
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true]);
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']);
        $class = $this->buatKelas(['tahun_ajaran_kode' => '2027/2028']);
        $student = Siswa::create(['nis' => '00999', 'nama' => 'Placed', 'status' => 'aktif', 'kelas_id' => $class->id]);
        $member = RiwayatKelasSiswa::daftarkan($student, $class, '2027/2028', '2027-07-01');
        $before = $member->fresh()->getAttributes();
        app(\App\Services\AktivasiTahunAjaran::class)->aktifkan('2027/2028');
        $this->assertTrue(TahunAjaran::find('2027/2028')->aktif);
        $this->assertSame($before, $member->fresh()->getAttributes());
    }

    public static function invalidPeriods(): array
    {
        return [
            'expired' => ['2025-07-01', '2026-06-30'],
            'future' => ['2027-07-01', '2028-06-30'],
            'undated' => [null, null],
            'missing start' => [null, '2027-06-30'],
            'missing end' => ['2026-07-01', null],
            'reversed' => ['2027-06-30', '2026-07-01'],
        ];
    }

    #[DataProvider('invalidPeriods')]
    public function test_registration_rejects_unverified_dated_membership_atomically(?string $start, ?string $end): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $class = $this->buatKelas();
        $class->tahunAjaran->update(['mulai' => $start, 'selesai' => $end]);
        try {
            app(PendaftaranPengguna::class)->siswa(['nis' => '00999', 'nama' => 'Intake', 'kelas_id' => $class->id,
                'email' => 'intake@example.test', 'username' => 'intake', 'password' => 'password123']);
            $this->fail('Unverified registration accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kelas_id', $e->errors());
        }
        $this->assertDatabaseCount('siswa', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('riwayat_kelas_siswa', 0);
    }

    #[DataProvider('invalidPeriods')]
    public function test_correction_rejects_unverified_destination_atomically(?string $start, ?string $end): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30));
        $source = $this->buatKelas();
        $source->tahunAjaran->update(['mulai' => '2026-07-01', 'selesai' => '2027-06-30']);
        $student = Siswa::create(['nis' => '00999', 'nama' => 'Original', 'status' => 'aktif', 'kelas_id' => $source->id]);
        RiwayatKelasSiswa::daftarkan($student, $source, '2026/2027', '2026-07-01');
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false, 'mulai' => $start, 'selesai' => $end]);
        $dest = $this->buatKelas(['nama_kelas' => 'XI DEST', 'tingkat' => 'XI', 'tahun_ajaran_kode' => '2027/2028']);
        try {
            app(SiklusHidupSiswa::class)->perbarui($student, ['nama' => 'Changed', 'kelas_id' => $dest->id, 'alasan' => 'Correction']);
            $this->fail('Unverified correction accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kelas_id', $e->errors());
        }
        $this->assertDatabaseHas('siswa', ['nis' => '00999', 'nama' => 'Original', 'kelas_id' => $source->id]);
        $this->assertDatabaseCount('riwayat_kelas_siswa', 1);
        $this->assertDatabaseHas('riwayat_kelas_siswa', ['siswa_nis' => '00999', 'aktif' => true, 'tanggal_selesai' => null]);
    }
}
