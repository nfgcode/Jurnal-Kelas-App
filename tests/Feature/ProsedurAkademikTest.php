<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Support\PencatatanAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The stored procedures behind adding a class, a subject and a timetable slot.
 *
 * These test the service rather than the form, because the point of putting the
 * rules in the database is that they hold for writers that never see a form —
 * a bulk import, the API, or someone typing SQL. On MySQL the service calls the
 * procedure; on the SQLite test database it runs the identical sequence in PHP,
 * and both must refuse the same things with the same message.
 */
class ProsedurAkademikTest extends TestCase
{
    use RefreshDatabase;

    private PencatatanAkademik $pencatatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pencatatan = app(PencatatanAkademik::class);
    }

    private function guru(string $nip): Guru
    {
        return Guru::factory()->create(['nip' => $nip]);
    }

    /** @param array<int, string> $nip */
    private function mapel(string $kode, array $nip = []): MataPelajaran
    {
        return $this->pencatatan->mataPelajaran(
            ['nama' => 'Mapel '.$kode, 'kode' => $kode, 'kelompok' => 'wajib', 'jp_per_minggu' => 2],
            $nip,
        );
    }

    // ---- mata pelajaran: two tables, one transaction ----------------------

    public function test_a_subject_and_its_teachers_are_written_together(): void
    {
        $a = $this->guru('198500000001');
        $b = $this->guru('198500000002');

        $mapel = $this->mapel('MTK', [$a->nip, $b->nip]);

        $this->assertSame('MTK', $mapel->kode);
        $this->assertEqualsCanonicalizing(
            [$a->nip, $b->nip],
            $mapel->guru()->pluck('guru.nip')->all(),
        );
    }

    public function test_an_unknown_teacher_rolls_the_whole_subject_back(): void
    {
        $this->guru('198500000001');

        try {
            $this->mapel('KIM', ['198500000001', '000000000000']);
            $this->fail('Sebuah NIP yang tidak terdaftar seharusnya menolak seluruh penyimpanan.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('guru_nip', $e->errors());
        }

        // Half of this landing would be worse than none: a subject nobody is
        // recorded as able to teach is one the schedule form refuses everyone for.
        $this->assertNull(MataPelajaran::where('kode', 'KIM')->first());
        $this->assertSame(0, DB::table('guru_mata_pelajaran')->count());
    }

    public function test_a_duplicate_subject_code_is_refused(): void
    {
        $this->mapel('BIN');

        $this->expectException(ValidationException::class);
        $this->mapel('BIN');
    }

    // ---- kelas: the check and the insert are one step ---------------------

    public function test_a_duplicate_rombel_is_refused_on_the_paralel_field(): void
    {
        Jurusan::create(['kode' => 'TKJ', 'nama' => 'Teknik Komputer dan Jaringan']);
        $atribut = [
            'nama_kelas' => 'XII TKJ 2',
            'tingkat' => 'XII',
            'jurusan_kode' => 'TKJ',
            'paralel' => 2,
            'kapasitas' => 36,
            'tahun_ajaran_kode' => $this->buatKelas()->tahun_ajaran_kode,
        ];

        $this->pencatatan->kelas($atribut);

        try {
            $this->pencatatan->kelas(['nama_kelas' => 'Nama lain'] + $atribut);
            $this->fail('Rombel kembar seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('paralel', $e->errors());
        }

        $this->assertSame(1, Kelas::where('tingkat', 'XII')->where('paralel', 2)->count());
    }

    public function test_a_class_cannot_name_a_school_year_that_does_not_exist(): void
    {
        $this->expectException(ValidationException::class);

        $this->pencatatan->kelas([
            'nama_kelas' => 'X UJI',
            'tingkat' => 'X',
            'paralel' => 1,
            'kapasitas' => 36,
            'tahun_ajaran_kode' => '1999/2000',
        ]);
    }

    // ---- jadwal: four refusals ---------------------------------------------

    /** @return array{0: Kelas, 1: Kelas, 2: MataPelajaran, 3: Guru} */
    private function panggung(): array
    {
        $guru = $this->guru('198500000001');
        $mapel = $this->mapel('MTK', [$guru->nip]);

        return [
            $this->buatKelas(['nama_kelas' => 'X A', 'paralel' => 1]),
            $this->buatKelas(['nama_kelas' => 'X B', 'paralel' => 2]),
            $mapel,
            $guru,
        ];
    }

    /** @param array<string, mixed> $ganti */
    private function slot(Kelas $kelas, MataPelajaran $mapel, Guru $guru, array $ganti = []): Jadwal
    {
        return $this->pencatatan->jadwal(array_merge([
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'guru_nip' => $guru->nip,
            'hari' => 'Senin',
            'jam_ke_mulai' => 1,
            'jam_ke_selesai' => 2,
        ], $ganti));
    }

    public function test_a_class_cannot_be_booked_twice_in_one_period(): void
    {
        [$a, , $mapel, $guru] = $this->panggung();

        $this->slot($a, $mapel, $guru);

        try {
            $this->slot($a, $mapel, $guru);
            $this->fail('Kelas yang sudah terisi seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kelas_id', $e->errors());
        }

        $this->assertSame(1, Jadwal::count());
    }

    public function test_a_teacher_cannot_be_in_two_classes_at_once(): void
    {
        [$a, $b, $mapel, $guru] = $this->panggung();

        $this->slot($a, $mapel, $guru);

        try {
            $this->slot($b, $mapel, $guru);
            $this->fail('Guru yang sudah mengajar di jam itu seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('guru_nip', $e->errors());
        }

        $this->assertSame(1, Jadwal::count());
    }

    public function test_an_overlapping_period_range_is_refused(): void
    {
        [$a, , $mapel, $guru] = $this->panggung();

        $this->slot($a, $mapel, $guru, ['jam_ke_mulai' => 3, 'jam_ke_selesai' => 4]);

        try {
            // JP 4-5 shares JP 4 without sharing a start, which the unique key
            // on (…, jam_ke_mulai) cannot see.
            $this->slot($a, $mapel, $guru, ['jam_ke_mulai' => 4, 'jam_ke_selesai' => 5]);
            $this->fail('Rentang JP yang tumpang-tindih seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kelas_id', $e->errors());
        }

        $this->assertSame(1, Jadwal::count());
    }

    public function test_a_teacher_cannot_be_scheduled_for_a_subject_they_do_not_hold(): void
    {
        [$a, , , $guru] = $this->panggung();
        $lain = $this->mapel('SEJ'); // nobody certified for it

        try {
            $this->slot($a, $lain, $guru);
            $this->fail('Pasangan guru-mapel yang tidak tercatat seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('guru_nip', $e->errors());
        }

        $this->assertSame(0, Jadwal::count());
    }
}
