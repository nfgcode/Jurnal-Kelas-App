<?php

namespace Database\Factories;

use App\Models\Guru;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guru>
 */
class GuruFactory extends Factory
{
    protected $model = Guru::class;

    /** Keeps generated NIPs unique across a test without relying on faker's pool. */
    private static int $urutan = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // 18 digits, the shape a real NIP has, and long enough that the
            // BEFORE INSERT trigger's minimum-length check passes.
            'nip' => '1985'.str_pad((string) ++self::$urutan, 14, '0', STR_PAD_LEFT),
            'nama' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'status' => 'aktif',
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['status' => 'nonaktif']);
    }
}
