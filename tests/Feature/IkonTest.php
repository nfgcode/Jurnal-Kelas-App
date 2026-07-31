<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Icons are shipped as a subset (resources/sass/_ikon.scss) instead of Bootstrap
 * Icons' full 2078-class sheet. The saving is real, but the failure mode is
 * quiet: use an icon on a page without adding it to the subset and it renders as
 * nothing at all — no error, no warning, just a blank space nobody notices until
 * a teacher asks why the button looks broken.
 *
 * This test closes that gap by checking the two against each other.
 */
class IkonTest extends TestCase
{
    /** @return array<string> */
    private function ikonDipakai(): array
    {
        $nama = [];

        // Literal in markup: <i class="bi bi-search"></i>
        foreach ($this->berkas(resource_path('views'), 'blade.php') as $isi) {
            preg_match_all('/bi-[a-z0-9-]+/', $isi, $cocok);
            $nama = array_merge($nama, $cocok[0]);
        }

        // Chosen in PHP: 'ikon' => 'bi-tools'
        foreach ($this->berkas(app_path(), 'php') as $isi) {
            preg_match_all("/'(bi-[a-z0-9-]+)'/", $isi, $cocok);
            $nama = array_merge($nama, $cocok[1]);
        }

        return array_values(array_unique(array_filter($nama, fn ($n) => $n !== 'bi-')));
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

    public function test_every_icon_the_app_renders_exists_in_the_subset(): void
    {
        $subset = file_get_contents(resource_path('sass/_ikon.scss'));

        $kurang = array_values(array_filter(
            $this->ikonDipakai(),
            fn ($nama) => ! str_contains($subset, ".{$nama}::before"),
        ));

        $this->assertSame([], $kurang, 'Ikon dipakai tapi belum ada di resources/sass/_ikon.scss — '
            .'tambahkan definisinya (salin baris content dari node_modules/bootstrap-icons/font/bootstrap-icons.css), '
            .'jika tidak ikon ini tampil kosong: '.implode(', ', $kurang));
    }

    public function test_the_subset_carries_no_icon_the_app_never_uses(): void
    {
        preg_match_all('/\.(bi-[a-z0-9-]+)::before/', file_get_contents(resource_path('sass/_ikon.scss')), $cocok);

        // Not a correctness problem, only weight — but it keeps the subset from
        // quietly drifting back towards the full sheet.
        $this->assertSame([], array_values(array_diff($cocok[1], $this->ikonDipakai())));
    }
}
