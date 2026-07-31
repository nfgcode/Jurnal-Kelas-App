<?php

namespace Tests\Feature;

use App\Support\Ikon;
use Tests\TestCase;

/**
 * Icons are inline SVG from {@see Ikon} instead of Bootstrap Icons' webfont, so
 * the app makes no network request for them and nothing renders as an invisible
 * box when a font fails to load.
 *
 * The failure mode this guards is quiet: ask for a name that is not in the map
 * and the icon renders as nothing at all — no error, no warning, just a blank
 * space nobody notices until a teacher asks why a button looks broken.
 */
class IkonTest extends TestCase
{
    /** @return array<string> */
    private function ikonDipakai(): array
    {
        $nama = [];

        // <x-ikon nama="search" /> and :nama="'mortarboard-fill'"
        foreach ($this->berkas(resource_path('views'), 'blade.php') as $isi) {
            preg_match_all('/<x-ikon[^>]*?:?nama="([^"]+)"/', $isi, $cocok);
            $nama = array_merge($nama, $cocok[1]);
        }

        // Names chosen in PHP, e.g. 'ikon' => 'bi-tools'
        foreach ($this->berkas(app_path(), 'php') as $isi) {
            preg_match_all("/'(bi-[a-z0-9-]+)'/", $isi, $cocok);
            $nama = array_merge($nama, $cocok[1]);
        }

        // A Blade expression such as `$x ? 'a' : 'b'` yields the whole ternary;
        // pick the quoted literals out of it and drop anything still dynamic.
        $rata = [];
        foreach ($nama as $satu) {
            // Array subscripts first: in `$item['ikon']` the quoted part is a key,
            // not an icon name, and counting it would fail the check on a string
            // that never reaches Ikon::svg().
            $satu = preg_replace("/\[\s*'[^']*'\s*\]/", '', $satu);

            if (preg_match_all("/'([a-z0-9-]+)'/", $satu, $literal)) {
                $rata = array_merge($rata, $literal[1]);
            } elseif (preg_match('/^(bi-)?[a-z0-9-]+$/', $satu)) {
                $rata[] = $satu;
            }
        }

        return array_values(array_unique($rata));
    }

    /** @return array<string> */
    private function berkas(string $dir, string $akhiran): array
    {
        $isi = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($iterator as $berkas) {
            if ($berkas->isFile() && str_ends_with($berkas->getFilename(), $akhiran)) {
                $isi[] = file_get_contents($berkas->getPathname());
            }
        }

        return $isi;
    }

    public function test_every_icon_the_app_renders_exists(): void
    {
        $kurang = array_values(array_filter(
            $this->ikonDipakai(),
            fn ($nama) => ! Ikon::ada($nama),
        ));

        $this->assertSame([], $kurang, 'Ikon dipakai tapi belum ada di App\Support\Ikon — '
            .'salin jalur SVG-nya dari node_modules/bootstrap-icons/icons/<nama>.svg, '
            .'jika tidak ikon ini tampil kosong: '.implode(', ', $kurang));
    }

    public function test_an_icon_renders_as_inline_svg_sized_to_the_text(): void
    {
        $svg = Ikon::svg('search');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('width="1em"', $svg);
        $this->assertStringContainsString('fill="currentColor"', $svg);
        // Decorative: it must not be announced by a screen reader.
        $this->assertStringContainsString('aria-hidden="true"', $svg);
    }

    public function test_the_bi_prefix_is_accepted_because_stored_values_still_carry_it(): void
    {
        $this->assertTrue(Ikon::ada('bi-tools'));
        $this->assertSame(Ikon::svg('tools'), Ikon::svg('bi-tools'));
    }

    /** A missing decoration must never take a page down. */
    public function test_an_unknown_name_renders_nothing_instead_of_failing(): void
    {
        $this->assertFalse(Ikon::ada('tidak-ada-ikon-ini'));
        $this->assertSame('', Ikon::svg('tidak-ada-ikon-ini'));
    }

    public function test_the_font_is_gone_from_the_build_input(): void
    {
        $scss = file_get_contents(resource_path('sass/app.scss'));
        $js = file_get_contents(resource_path('js/app.js'));

        // The whole point of inlining: no icon stylesheet, no webfont download.
        $this->assertStringNotContainsString('bootstrap-icons', $scss);
        $this->assertStringNotContainsString('bootstrap-icons', $js);
        $this->assertFileDoesNotExist(resource_path('sass/_ikon.scss'));
    }
}
