<?php

namespace Tests\Feature;

use App\Models\{Jurusan, Kelas, RiwayatKelasSiswa, Siswa, TahunAjaran};
use App\Services\AktivasiTahunAjaran;
use App\Support\TransisiAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransitionReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function roster(string $grade = 'X'): array
    {
        $this->travelTo(Carbon::parse('2027-06-15'));
        TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true, 'mulai' => '2026-07-01', 'selesai' => '2027-06-30']);
        TahunAjaran::create(['kode' => '2027/2028', 'aktif' => false, 'mulai' => '2027-07-01', 'selesai' => '2028-06-30']);
        Jurusan::create(['kode' => 'TKJ', 'nama' => 'TKJ', 'aktif' => true]);
        $classes = [];
        foreach (['2026/2027', '2027/2028'] as $year) {
            foreach (['X', 'XI', 'XII'] as $level) {
                $classes[$year][$level] = Kelas::create(['nama_kelas' => "$level TKJ 1", 'tingkat' => $level, 'jurusan_kode' => 'TKJ', 'paralel' => 1, 'tahun_ajaran_kode' => $year]);
            }
        }
        $student = Siswa::create(['nis' => '00123', 'nama' => 'Test', 'status' => 'aktif', 'kelas_id' => $classes['2026/2027'][$grade]->id]);
        $member = RiwayatKelasSiswa::create(['siswa_nis' => $student->nis, 'kelas_id' => $student->kelas_id, 'tahun_ajaran_kode' => '2026/2027', 'tingkat' => $grade, 'status' => 'aktif', 'aktif' => true, 'tanggal_mulai' => '2026-08-10', 'catatan' => 'Verified intake']);
        return [$student, $member, $classes['2027/2028'][$grade]];
    }

    public function test_execution_rejects_overlap_even_when_today_masks_destination_start(): void
    {
        [$student, $member] = $this->roster();
        TahunAjaran::whereKey('2027/2028')->update(['mulai' => '2027-06-20']);
        $this->travelTo(Carbon::parse('2027-07-01'));
        $before = $member->fresh()->toArray();
        try {
            TransisiAkademik::eksekusi('2026/2027', '2027/2028');
            $this->fail('Overlapping late transition accepted');
        } catch (\DomainException $e) {
            $this->assertSame($before, $member->fresh()->toArray());
            $this->assertSame($student->kelas_id, $student->fresh()->kelas_id);
            $this->assertDatabaseCount('riwayat_kelas_siswa', 1);
            $this->assertDatabaseCount('transisi_tahun_ajaran', 0);
        }
    }

    public static function previewCases(): array
    {
        return [
            'missing dates' => ['dates', false],
            'missing history' => ['missing', false],
            'undated history' => ['undated', false],
            'overlapping history' => ['history', false],
            'calendar gap' => ['gap', false],
            'late' => ['late', false],
            'masked overlap' => ['overlap', false],
            'valid' => ['valid', true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('previewCases')]
    public function test_preview_matches_date_history_validation_without_writes_or_locks(string $case, bool $ready): void
    {
        [$student, $member] = $this->roster();
        if ($case === 'dates') {
            TahunAjaran::whereKey('2027/2028')->update(['mulai' => null]);
        } elseif ($case === 'missing') {
            $member->delete();
        } elseif ($case === 'undated') {
            $member->update(['tanggal_mulai' => null]);
        } elseif ($case === 'history') {
            $member->replicate()->fill(['aktif' => false, 'tanggal_selesai' => '2027-06-01'])->save();
        } elseif ($case === 'gap') {
            TahunAjaran::whereKey('2027/2028')->update(['mulai' => '2027-07-05']);
        } elseif ($case === 'late' || $case === 'overlap') {
            $this->travelTo(Carbon::parse($case === 'late' ? '2027-07-02' : '2027-07-01'));
            if ($case === 'overlap') {
                TahunAjaran::whereKey('2027/2028')->update(['mulai' => '2027-06-20']);
            }
        }
        $before = RiwayatKelasSiswa::all()->toArray();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $preview = TransisiAkademik::pratinjau('2026/2027', '2027/2028');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame($ready, $preview['siap_eksekusi']);
        $this->assertSame($ready, $preview['masalah_validasi'] === []);
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^select\b/i', $query['query']);
            $this->assertDoesNotMatchRegularExpression('/for\s+update/i', $query['query']);
        }
        $this->assertSame($before, RiwayatKelasSiswa::all()->toArray());
        if ($ready) {
            TransisiAkademik::eksekusi('2026/2027', '2027/2028');
            $this->assertSame('2027-07-01', $student->riwayatKelas()->where('aktif', true)->sole()->tanggal_mulai->toDateString());
        } else {
            try {
                TransisiAkademik::eksekusi('2026/2027', '2027/2028');
                $this->fail('Preview blocker accepted by execution');
            } catch (\DomainException $e) {
                $this->assertSame($preview['masalah_validasi'][0], $e->getMessage());
                $this->assertSame($before, RiwayatKelasSiswa::all()->toArray());
                $this->assertSame($student->kelas_id, $student->fresh()->kelas_id);
                $this->assertDatabaseCount('transisi_tahun_ajaran', 0);
            }
        }
    }

    public function test_execution_revalidates_history_changed_after_ready_preview(): void
    {
        [$student, $member] = $this->roster();
        $this->assertTrue(TransisiAkademik::pratinjau('2026/2027', '2027/2028')['siap_eksekusi']);
        $member->update(['tanggal_mulai' => null]);
        $before = $member->fresh()->toArray();
        try {
            TransisiAkademik::eksekusi('2026/2027', '2027/2028');
            $this->fail('Changed history bypassed execution revalidation');
        } catch (\DomainException $e) {
            $this->assertSame($before, $member->fresh()->toArray());
            $this->assertSame($student->kelas_id, $student->fresh()->kelas_id);
            $this->assertDatabaseCount('transisi_tahun_ajaran', 0);
        }
    }

    public static function retainedActions(): array
    {
        return [['X', 'tinggal_kelas'], ['XII', 'tunda_lulus']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('retainedActions')]
    public function test_retained_transition_allows_verified_activation_on_destination_start(string $grade, string $action): void
    {
        [$student, $member, $destination] = $this->roster($grade);
        TransisiAkademik::eksekusi('2026/2027', '2027/2028', ['pengecualian' => [$student->nis => ['aksi' => $action, 'kelas_id' => $destination->id]]]);
        $this->assertSame('tinggal_kelas', $student->riwayatKelas()->where('aktif', true)->sole()->status);
        $this->travelTo(Carbon::parse('2027-07-01'));
        app(AktivasiTahunAjaran::class)->aktifkan('2027/2028');
        $this->assertTrue(TahunAjaran::findOrFail('2027/2028')->aktif);
        $this->assertFalse(TahunAjaran::findOrFail('2026/2027')->aktif);
        $this->assertSame($destination->id, $student->fresh()->kelas_id);
        $this->assertSame('2027-06-30', $member->fresh()->tanggal_selesai->toDateString());
        $this->assertSame('2027-07-01', $student->riwayatKelas()->where('aktif', true)->sole()->tanggal_mulai->toDateString());
    }
}
