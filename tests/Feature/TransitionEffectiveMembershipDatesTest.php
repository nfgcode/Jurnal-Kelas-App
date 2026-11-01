<?php

namespace Tests\Feature;

use App\Models\{Jurusan, Kelas, RiwayatKelasSiswa, Siswa, TahunAjaran};
use App\Support\TransisiAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransitionEffectiveMembershipDatesTest extends TestCase
{
    use RefreshDatabase;

    private function roster(string $grade = 'X'): array
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-06-15'));
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true, 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']);
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']);
        Jurusan::create(['kode' => 'TKJ', 'nama' => 'TKJ', 'aktif' => true]);
        $source = Kelas::create(['nama_kelas' => "$grade TKJ 1", 'tingkat' => $grade, 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => '2026/2027']);
        $student = Siswa::create(['nis' => '00123', 'nama' => 'Test', 'status' => 'aktif', 'kelas_id' => $source->id]);
        $member = RiwayatKelasSiswa::create(['siswa_nis' => $student->nis, 'kelas_id' => $source->id, 'tahun_ajaran_kode' => '2026/2027', 'tingkat' => $grade, 'status' => 'aktif', 'aktif' => true, 'tanggal_mulai' => '2026-08-10', 'catatan' => 'Verified intake']);
        TransisiAkademik::siapkanKelasTujuan('2027/2028', '2026/2027');
        return [$student, $member];
    }

    public static function uncertainDates(): array
    {
        return [
            'missing destination start' => ['target', 'mulai', null],
            'missing destination end' => ['target', 'selesai', null],
            'reversed destination' => ['target', 'selesai', '2027-06-01'],
            'overlapping periods' => ['target', 'mulai', '2027-06-20'],
            'calendar gap' => ['target', 'mulai', '2027-07-05'],
            'missing source bounds' => ['source', 'mulai', null],
            'expired destination' => ['clock', '', '2028-07-01'],
            'late transition cannot extend source history' => ['clock', '', '2027-07-02'],
            'missing membership' => ['delete', '', null],
            'undated membership' => ['member', 'tanggal_mulai', null],
            'early legacy membership' => ['member', 'tanggal_mulai', '2026-06-30'],
            'future source membership' => ['member', 'tanggal_mulai', '2027-07-01'],
            'already closed active membership' => ['member', 'tanggal_selesai', '2027-06-01'],
            'wrong membership year' => ['member', 'tahun_ajaran_kode', '2027/2028'],
            'wrong membership grade' => ['member', 'tingkat', 'XI'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('uncertainDates')]
    public function test_uncertain_dates_are_rejected_without_rewriting_history(string $kind, string $field, ?string $value): void
    {
        [$student, $member] = $this->roster();
        if ($kind === 'target' || $kind === 'source') {
            TahunAjaran::whereKey($kind === 'target' ? '2027/2028' : '2026/2027')->update([$field => $value]);
        } elseif ($kind === 'clock') {
            $this->travelTo(\Illuminate\Support\Carbon::parse($value));
        } elseif ($kind === 'delete') {
            $member->delete();
        } else {
            $member->update([$field => $value]);
        }
        $before = RiwayatKelasSiswa::all()->toArray();
        try {
            TransisiAkademik::eksekusi('2026/2027', '2027/2028');
            $this->fail('Uncertain effective membership dates accepted');
        } catch (\DomainException|\Illuminate\Validation\ValidationException $e) {
            $this->assertSame($before, RiwayatKelasSiswa::all()->toArray());
            $this->assertSame($student->kelas_id, $student->fresh()->kelas_id);
            $this->assertDatabaseCount('transisi_tahun_ajaran', 0);
        }
    }

    public function test_overlapping_saved_history_is_not_silently_overwritten(): void
    {
        [$student, $member] = $this->roster();
        $historical = $member->replicate();
        $historical->fill(['aktif' => false, 'tanggal_mulai' => '2027-07-01', 'tanggal_selesai' => '2027-07-05'])->save();
        $before = RiwayatKelasSiswa::all()->toArray();
        try {
            TransisiAkademik::eksekusi('2026/2027', '2027/2028');
            $this->fail('Overlapping history accepted');
        } catch (\DomainException $e) {
            $this->assertSame($before, RiwayatKelasSiswa::all()->toArray());
            $this->assertDatabaseCount('transisi_tahun_ajaran', 0);
        }
    }

    public function test_retention_uses_same_inclusive_boundary(): void
    {
        [$student, $member] = $this->roster();
        $dest = Kelas::where('tahun_ajaran_kode', '2027/2028')->where('tingkat', 'X')->firstOrFail();
        TransisiAkademik::eksekusi('2026/2027', '2027/2028', ['pengecualian' => [$student->nis => ['aksi' => 'tinggal_kelas', 'kelas_id' => $dest->id]]]);
        $this->assertSame('2027-07-01', $student->riwayatKelas()->where('aktif', true)->firstOrFail()->tanggal_mulai->toDateString());
        $this->assertSame('2027-06-30', $member->fresh()->tanggal_selesai->toDateString());
    }

    public function test_start_day_transition_supports_dedicated_activation(): void
    {
        $this->roster();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-07-01'));
        TransisiAkademik::eksekusi('2026/2027', '2027/2028');
        app(\App\Services\AktivasiTahunAjaran::class)->aktifkan('2027/2028');
        $this->assertTrue(TahunAjaran::find('2027/2028')->aktif);
    }

    public function test_planned_promotion_uses_destination_start_without_fabricating_a_gap(): void
    {
        [$student, $member] = $this->roster();
        TransisiAkademik::eksekusi('2026/2027', '2027/2028');
        $this->assertDatabaseHas('riwayat_kelas_siswa', ['siswa_nis' => $student->nis, 'tahun_ajaran_kode' => '2027/2028', 'tanggal_mulai' => '2027-07-01', 'aktif' => true]);
        $this->assertSame('2027-06-30', $member->fresh()->tanggal_selesai->toDateString());
        $this->assertSame('2026-08-10', $member->fresh()->tanggal_mulai->toDateString());
        $this->assertSame('Verified intake', $member->fresh()->catatan);
        $this->assertDatabaseCount('riwayat_kelas_siswa', 2);
        $this->assertFalse(TahunAjaran::find('2027/2028')->aktif);
    }
}
