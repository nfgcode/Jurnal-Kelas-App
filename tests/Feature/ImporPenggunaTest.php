<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\User;
use App\Support\ImporPengguna;
use App\Support\XlsxExport;
use App\Support\XlsxReader;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Bulk account creation from a spreadsheet: the template an admin downloads, the
 * reader that takes it back, and the two-step preview → commit that stops a file
 * with typos in it from creating half a class.
 */
class ImporPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    /**
     * Write rows to a real .xlsx on disk and hand it back as an upload, so the
     * test exercises the actual writer/reader pair rather than a stub.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private function berkas(string $jenis, array $rows, ?array $header = null): UploadedFile
    {
        $header ??= array_column(ImporPengguna::kolom($jenis), 'judul');

        // XlsxExport builds the file inside a download response; the response's
        // own temp file is exactly the workbook we want to upload back.
        $respons = XlsxExport::download('impor.xlsx', $header, $rows);
        $path = $respons->getFile()->getPathname();

        $salinan = tempnam(sys_get_temp_dir(), 'impor').'.xlsx';
        copy($path, $salinan);

        return new UploadedFile($salinan, 'impor.xlsx', null, null, true);
    }

    public function test_the_template_downloads_as_a_real_xlsx_with_the_expected_columns(): void
    {
        $respons = $this->actingAs($this->admin)
            ->get(route('admin.impor.template', ['jenis' => 'siswa']))
            ->assertOk()
            ->assertDownload();

        $respons->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        // The file the admin gets must be readable by the importer that receives
        // it back — the two halves are defined by one column list.
        $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($tmp, $respons->streamedContent());

        $baris = XlsxReader::baca($tmp);

        $this->assertSame(
            array_column(ImporPengguna::kolom('siswa'), 'judul'),
            $baris[0]
        );

        unlink($tmp);
    }

    public function test_the_template_is_offered_for_guru_too(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.impor.template', ['jenis' => 'guru']))
            ->assertOk()
            ->assertDownload();
    }

    public function test_the_import_screen_and_template_are_admin_only(): void
    {
        $guru = User::where('role', 'guru')->firstOrFail();

        $this->actingAs($guru)->get(route('admin.impor.index'))->assertForbidden();
        $this->actingAs($guru)->get(route('admin.impor.template', ['jenis' => 'siswa']))->assertForbidden();
    }

    public function test_a_valid_student_file_previews_then_creates_the_accounts(): void
    {
        $kelas = Kelas::firstOrFail();

        $berkas = $this->berkas('siswa', [
            ['Siswa Impor Satu', 'siswa.impor1@test.app', 'IMP001', $kelas->nama_kelas, 'ya', 'rahasia123', 'aktif'],
            ['Siswa Impor Dua', 'siswa.impor2@test.app', 'IMP002', $kelas->nama_kelas, '', 'rahasia123', 'aktif'],
        ]);

        $pratinjau = $this->actingAs($this->admin)
            ->post(route('admin.impor.pratinjau'), ['jenis' => 'siswa', 'berkas' => $berkas])
            ->assertOk();

        // Nothing is written until the admin confirms.
        $this->assertDatabaseMissing('users', ['email' => 'siswa.impor1@test.app']);

        $hasil = $pratinjau->viewData('hasil');
        $this->assertSame(2, $hasil['ringkas']['baru']);
        $this->assertSame(0, $hasil['ringkas']['gagal']);

        $this->actingAs($this->admin)
            ->post(route('admin.impor.simpan'), [
                'jenis' => 'siswa',
                'berkas' => $pratinjau->viewData('berkas'),
            ])
            ->assertRedirect(route('admin.users.index', ['role' => 'siswa']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'siswa.impor1@test.app',
            'role' => 'siswa',
            'nis' => 'IMP001',
            'kelas_id' => $kelas->id,
            'is_ketua_kelas' => true,
        ]);
        $this->assertDatabaseHas('users', ['email' => 'siswa.impor2@test.app', 'is_ketua_kelas' => false]);
    }

    public function test_rows_with_problems_are_reported_and_never_written(): void
    {
        $adaEmail = User::where('role', 'siswa')->firstOrFail()->email;

        $berkas = $this->berkas('siswa', [
            // Unknown class, bad email, duplicate email, missing password.
            ['Kelas Salah', 'kelas.salah@test.app', 'IMP010', 'XII TIDAK ADA', '', 'rahasia123', 'aktif'],
            ['Email Rusak', 'bukan-email', 'IMP011', Kelas::value('nama_kelas'), '', 'rahasia123', 'aktif'],
            ['Email Kembar', $adaEmail, 'IMP012', Kelas::value('nama_kelas'), '', 'rahasia123', 'aktif'],
            ['Tanpa Sandi', 'tanpa.sandi@test.app', 'IMP013', Kelas::value('nama_kelas'), '', '', 'aktif'],
        ]);

        $pratinjau = $this->actingAs($this->admin)
            ->post(route('admin.impor.pratinjau'), ['jenis' => 'siswa', 'berkas' => $berkas])
            ->assertOk();

        $hasil = $pratinjau->viewData('hasil');

        $this->assertSame(4, $hasil['ringkas']['gagal']);
        $this->assertSame(0, $hasil['ringkas']['baru']);

        // Committing anyway writes nothing, because every row is bad.
        $this->actingAs($this->admin)->post(route('admin.impor.simpan'), [
            'jenis' => 'siswa',
            'berkas' => $pratinjau->viewData('berkas'),
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'kelas.salah@test.app']);
        $this->assertDatabaseMissing('users', ['nis' => 'IMP013']);
    }

    /**
     * A blank Password column is the common case: the admin sets one default on
     * the form instead of typing the same string a hundred times.
     */
    public function test_the_form_password_fills_in_for_rows_that_leave_it_blank(): void
    {
        $kelas = Kelas::firstOrFail();

        $berkas = $this->berkas('siswa', [
            ['Pakai Bawaan', 'pakai.bawaan@test.app', 'IMP020', $kelas->nama_kelas, '', '', 'aktif'],
        ]);

        $pratinjau = $this->actingAs($this->admin)
            ->post(route('admin.impor.pratinjau'), [
                'jenis' => 'siswa',
                'berkas' => $berkas,
                'password_bawaan' => 'sandibawaan',
            ])
            ->assertOk();

        $this->assertSame(1, $pratinjau->viewData('hasil')['ringkas']['baru']);

        $this->actingAs($this->admin)->post(route('admin.impor.simpan'), [
            'jenis' => 'siswa',
            'berkas' => $pratinjau->viewData('berkas'),
            'password_bawaan' => 'sandibawaan',
        ]);

        $siswa = User::where('email', 'pakai.bawaan@test.app')->firstOrFail();
        $this->assertTrue(password_verify('sandibawaan', $siswa->password));
    }

    /**
     * Overwriting an existing account is opt-in: without the checkbox a clashing
     * email is an error, not a silent update.
     */
    public function test_existing_accounts_are_only_overwritten_when_asked(): void
    {
        $ada = User::where('role', 'guru')->firstOrFail();
        $namaLama = $ada->name;

        $rows = [['Nama Baru Sekali', $ada->email, $ada->nip ?? 'IMP030', '', 'rahasia123', 'aktif']];

        $tanpa = $this->actingAs($this->admin)
            ->post(route('admin.impor.pratinjau'), ['jenis' => 'guru', 'berkas' => $this->berkas('guru', $rows)])
            ->assertOk();

        $this->assertSame(1, $tanpa->viewData('hasil')['ringkas']['gagal']);

        $dengan = $this->actingAs($this->admin)
            ->post(route('admin.impor.pratinjau'), [
                'jenis' => 'guru',
                'berkas' => $this->berkas('guru', $rows),
                'perbarui' => '1',
            ])
            ->assertOk();

        $this->assertSame(1, $dengan->viewData('hasil')['ringkas']['perbarui']);

        $this->actingAs($this->admin)->post(route('admin.impor.simpan'), [
            'jenis' => 'guru',
            'berkas' => $dengan->viewData('berkas'),
            'perbarui' => '1',
        ]);

        $this->assertSame('Nama Baru Sekali', $ada->fresh()->name);
        $this->assertNotSame($namaLama, $ada->fresh()->name);
    }

    /**
     * The unique values a school actually collides on are NIS/NIP, and the
     * database would only catch the second write — after the first had landed.
     */
    public function test_a_duplicate_nis_inside_the_same_file_is_caught(): void
    {
        $kelas = Kelas::firstOrFail();

        $berkas = $this->berkas('siswa', [
            ['Kembar Satu', 'kembar1@test.app', 'SAMA1', $kelas->nama_kelas, '', 'rahasia123', 'aktif'],
            ['Kembar Dua', 'kembar2@test.app', 'SAMA1', $kelas->nama_kelas, '', 'rahasia123', 'aktif'],
        ]);

        $hasil = $this->actingAs($this->admin)
            ->post(route('admin.impor.pratinjau'), ['jenis' => 'siswa', 'berkas' => $berkas])
            ->assertOk()
            ->viewData('hasil');

        $this->assertSame(1, $hasil['ringkas']['baru']);
        $this->assertSame(1, $hasil['ringkas']['gagal']);
    }

    /**
     * A file that is not the template is a wrong file, not a list of bad rows —
     * it must bounce with an explanation rather than importing nonsense.
     */
    public function test_a_file_with_the_wrong_headers_is_rejected_outright(): void
    {
        $berkas = $this->berkas('siswa', [['a', 'b', 'c']], ['Kolom A', 'Kolom B', 'Kolom C']);

        $this->actingAs($this->admin)
            ->post(route('admin.impor.pratinjau'), ['jenis' => 'siswa', 'berkas' => $berkas])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    /**
     * The stored filename is the only thing the confirm step trusts from the
     * browser, so anything but a ULID must not reach the filesystem.
     */
    public function test_the_confirm_step_refuses_a_crafted_filename(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.impor.simpan'), [
                'jenis' => 'siswa',
                'berkas' => '../../../.env',
            ])
            ->assertSessionHasErrors('berkas');
    }
}
