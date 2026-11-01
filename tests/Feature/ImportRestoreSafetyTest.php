<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\CadanganController;
use App\Support\CadanganData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportRestoreSafetyTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private function preview(): \Illuminate\Testing\TestResponse
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $csv = "Nama Lengkap,Email,NIP,Wali Kelas,Password,Status\nGuru Safety,safety@example.test,198001012020129999,,,aktif\n";
        return $this->actingAs($admin)->post(route('admin.impor.pratinjau'), [
            'jenis' => 'guru', 'password_bawaan' => 'SecretDefault-123',
            'berkas' => \Illuminate\Http\UploadedFile::fake()->createWithContent('roster.csv', $csv),
        ])->assertOk();
    }

    public function test_preview_secret_is_not_returned_in_html_or_plaintext_session(): void
    {
        $response = $this->preview();
        $response->assertDontSee('SecretDefault-123')->assertDontSee('name="password_bawaan"', false);
        $this->assertStringNotContainsString('SecretDefault-123', json_encode($response->viewData('hasil')));
        $this->assertStringNotContainsString('SecretDefault-123', json_encode(session()->all()));
    }

    public function test_confirmation_uses_immutable_server_options_and_consumes_preview(): void
    {
        $response = $this->preview();
        $payload = ['jenis' => 'guru', 'berkas' => $response->viewData('berkas'), 'password_bawaan' => 'TamperedSecret'];
        $this->post(route('admin.impor.simpan'), $payload)->assertRedirect(route('admin.guru.index'));
        $user = \App\Models\User::where('email', 'safety@example.test')->firstOrFail();
        $this->assertTrue(password_verify('SecretDefault-123', $user->password));
        $this->post(route('admin.impor.simpan'), $payload)->assertSessionHas('error');
    }

    public function test_unpreviewed_file_cannot_be_confirmed(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $name = (string) \Illuminate\Support\Str::ulid().'.csv';
        \Illuminate\Support\Facades\Storage::disk('local')->put('impor/'.$name, 'private');
        $this->actingAs($admin)->post(route('admin.impor.simpan'), ['jenis' => 'guru', 'berkas' => $name])
            ->assertSessionHas('error');
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists('impor/'.$name));
    }

    public function test_initial_import_form_never_repopulates_password(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->withSession(['_old_input' => ['password_bawaan' => 'SecretDefault-123']]);
        $this->actingAs($admin)->get(route('admin.impor.index'))->assertOk()->assertDontSee('SecretDefault-123')
            ->assertSee('type="password" name="password_bawaan"', false);
    }

    public function test_malformed_upload_does_not_flash_or_log_payload_or_password(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        \Illuminate\Support\Facades\Log::spy();
        $response = $this->actingAs($admin)->post(route('admin.impor.pratinjau'), [
            'jenis' => 'guru', 'password_bawaan' => 'SecretDefault-123',
            'berkas' => \Illuminate\Http\UploadedFile::fake()->createWithContent('roster.csv', 'SECRET-payload-not-headers'),
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertStringNotContainsString('SecretDefault-123', json_encode(session()->all()));
        $this->assertStringNotContainsString('SECRET-payload-not-headers', json_encode(session()->all()));
        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('error');
    }

    public function test_import_write_error_is_generic_without_query_details_in_session_or_audit(): void
    {
        $response = $this->preview();
        DB::statement("CREATE TRIGGER fail_import BEFORE INSERT ON guru BEGIN SELECT RAISE(ABORT, 'SQL-SECRET-MARKER'); END");
        $this->post(route('admin.impor.simpan'), ['jenis' => 'guru', 'berkas' => $response->viewData('berkas')])
            ->assertRedirect(route('admin.impor.index'))->assertSessionHas('error');
        $this->assertStringNotContainsString('SQL-SECRET-MARKER', json_encode(session()->all()));
        $this->assertStringNotContainsString('SecretDefault-123', json_encode(session()->all()));
        $this->assertStringNotContainsString('SQL-SECRET-MARKER', json_encode(DB::table('admin_audit_log')->get()));
        $this->assertDatabaseMissing('users', ['email' => 'safety@example.test']);
    }

    public function test_validation_never_flashes_default_password(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.impor.pratinjau'), ['jenis' => 'invalid', 'password_bawaan' => 'SecretDefault-123'])
            ->assertSessionHasErrors('jenis');
        $this->assertStringNotContainsString('SecretDefault-123', json_encode(session()->all()));
    }

    public function test_export_rejects_empty_unknown_mixed_and_ambiguous_selections(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        foreach (['', '?tabel=', '?tabel[]=unknown', '?tabel[]=users&tabel[]=unknown', '?semua=1&tabel[]=users'] as $query) {
            foreach (['json', 'xlsx'] as $format) {
                $this->actingAs($admin)->get('/admin/cadangan/'.$format.$query)->assertRedirect()->assertSessionHasErrors();
                session()->forget('errors');
            }
        }
    }

    public function test_service_export_rejects_empty_selection_before_opening_file(): void
    {
        $path = sys_get_temp_dir().'/must-not-create.json';
        try {
            (new CadanganData)->tulisJson($path, []);
            $this->fail('Empty selection accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_expired_changed_wrong_type_and_wrong_owner_previews_are_rejected(): void
    {
        foreach (['expired', 'changed', 'type', 'owner', 'session'] as $case) {
            $response = $this->preview();
            $name = $response->viewData('berkas');
            $payload = ['jenis' => 'guru', 'berkas' => $name];
            if ($case === 'expired') { $this->travel(7)->hours(); }
            if ($case === 'changed') { \Illuminate\Support\Facades\Storage::disk('local')->append('impor/'.$name, 'changed'); }
            if ($case === 'type') { $payload['jenis'] = 'siswa'; }
            if ($case === 'owner') { $this->actingAs(\App\Models\User::factory()->create(['role' => 'admin'])); }
            if ($case === 'session') { session()->forget('impor_owner'); }
            $this->post(route('admin.impor.simpan'), $payload)->assertSessionHas('error');
            $this->assertDatabaseMissing('users', ['email' => 'safety@example.test']);
            $this->travelBack();
        }
    }

    public function test_gzip_payload_restore_is_blocked_without_database_queries(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('bomb.json.gz', gzencode(str_repeat('x', 100000)));
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $this->actingAs($admin)->post('/admin/cadangan/pulihkan', ['berkas' => $file, 'mode' => 'ganti', 'konfirmasi' => '1'])->assertStatus(503);
        // Authentication activity middleware may update last_active_at, never backup data.
        $this->assertSame([], array_values(array_filter($queries, fn ($sql) => ! str_starts_with($sql, 'update "users" set "last_active_at"'))));
    }

    public function test_backup_download_is_private_and_non_cacheable(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/admin/cadangan/json?tabel[]=users')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_restore_has_no_production_configuration_bypass(): void
    {
        config(['app.env' => 'production', 'restore.enabled' => true]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pemulihan dinonaktifkan');
        (new CadanganData)->pulihkan(['tabel' => []], 'gabung');
    }

    public function test_direct_restore_is_unconditionally_disabled_before_database_access(): void
    {
        DB::listen(fn () => $this->fail('Restore must not issue database queries.'));
        Schema::shouldReceive('hasTable')->never();
        Schema::shouldReceive('withoutForeignKeyConstraints')->never();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pemulihan dinonaktifkan');
        (new CadanganData)->pulihkan(['tabel' => ['users' => []]], 'ganti');
    }

    public function test_web_restore_blocks_before_validation_parsing_or_service_calls(): void
    {
        $service = $this->mock(CadanganData::class);
        $service->shouldNotReceive('pulihkan');
        DB::listen(fn () => $this->fail('Restore must not issue database queries.'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Pemulihan dinonaktifkan');
        (new CadanganController)->pulihkan(Request::create('/restore', 'POST'), $service);
    }
}
