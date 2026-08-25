<?php

namespace Database\Factories;

use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Siswa>
 */
class SiswaFactory extends Factory
{
    protected $model = Siswa::class;

    /** Keeps generated NIS values unique across a test. */
    private static int $urutan = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nis' => '2026'.str_pad((string) ++self::$urutan, 6, '0', STR_PAD_LEFT),
            'nama' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'kelas_id' => null,
            'status' => 'aktif',
        ];
    }
}
