<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class Phase9AccessibilityAndPolishTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $this->actingAs($user);

        return $user;
    }

    public function test_breadcrumbs_have_aria_current_and_accessible_markup(): void
    {
        $this->admin();

        $response = $this->get(route('admin.transisi.index'));
        $response->assertStatus(200);

        // Verifikasi elemen nav breadcrumb dengan atribut aria-label dan aria-current
        $response->assertSee('<nav class="breadcrumb-nav mb-2" aria-label="Breadcrumb">', false);
        $response->assertSee('aria-current="page"', false);
    }

    public function test_legacy_banner_explains_separate_atomic_transactions_accurately(): void
    {
        $this->admin();

        $response = $this->get(route('admin.transisi.index'));
        $response->assertStatus(200);

        // Verifikasi koreksi akurasi: checkpoint audit terpisah per tahap
        $response->assertSee('transaksi atomik dan checkpoint audit terpisah per tahap', false);
    }

    public function test_landing_tahun_ajaran_has_wcag_heading_hierarchy_and_labels(): void
    {
        $this->admin();

        $response = $this->get(route('admin.tahun-ajaran.index'));
        $response->assertStatus(200);

        // Heading levels
        $response->assertSee('<h2 class="h4 mb-0 fw-bold">Pusat Periode Akademik</h2>', false);
        $response->assertSee('<h3 class="h5 fw-bold mb-1">Alur Transisi & Siklus Akademik</h3>', false);
        $response->assertSee('<h4 class="h6 fw-bold mb-2">Kelulusan Kelas XII</h4>', false);

        // Responsive grid and KPI classes
        $response->assertSee('grid-row grid-row--4', false);
        $response->assertSee('year-calendar-grid', false);
    }
}
