<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * x-barchart's date labels. A long period (a year of weekly bars) used to clip
 * every label to its few-pixel column, so "14/7" read as "1:".
 */
class BarchartTest extends TestCase
{
    /** @return array<int, array{key: string, label: string, value: int}> */
    private function seri(int $jumlah): array
    {
        return array_map(fn ($i) => ['key' => "k{$i}", 'label' => ($i + 1).'/7', 'value' => $i], range(0, $jumlah - 1));
    }

    /** @return array{tampil: int, sembunyi: int} */
    private function hitungLabel(string $html): array
    {
        return [
            'tampil' => preg_match_all('/class="barchart__tick"/', $html),
            'sembunyi' => preg_match_all('/class="barchart__tick barchart__tick--sembunyi"/', $html),
        ];
    }

    public function test_a_short_series_labels_every_bar(): void
    {
        $html = Blade::render('<x-barchart :series="$s" />', ['s' => $this->seri(14)]);

        $this->assertSame(['tampil' => 14, 'sembunyi' => 0], $this->hitungLabel($html));
    }

    public function test_a_long_series_labels_about_eight_bars_on_odd_positions(): void
    {
        $html = Blade::render('<x-barchart :series="$s" />', ['s' => $this->seri(41)]);
        $hitung = $this->hitungLabel($html);

        $this->assertSame(41, $hitung['tampil'] + $hitung['sembunyi']);
        $this->assertSame(7, $hitung['tampil']); // every 6th bar: 1, 7, 13, ... 37
        $this->assertStringContainsString('barchart--padat', $html);
        // The labels shown are whole, and sit on odd children so the phone rule
        // hiding even-numbered ticks never hides them.
        $this->assertStringContainsString('>1/7</span>', $html);
        $this->assertStringContainsString('>7/7</span>', $html);
    }
}
