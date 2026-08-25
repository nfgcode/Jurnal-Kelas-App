<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Accounts.
 *
 * The default is an admin, because that is the only role whose account stands
 * alone — a guru or siswa account needs a person row to point at, so use
 * {@see Guru()} / {@see Siswa()}, which create one.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'admin',
            'status' => 'aktif',
        ];
    }

    /**
     * An account belonging to a (newly created) teacher.
     */
    public function guru(?Guru $guru = null): static
    {
        return $this->state(function () use ($guru) {
            $guru ??= Guru::factory()->create();

            return ['role' => 'guru', 'nip' => $guru->nip, 'nis' => null, 'nama' => null];
        });
    }

    /**
     * An account belonging to a (newly created) student.
     */
    public function siswa(?Siswa $siswa = null): static
    {
        return $this->state(function () use ($siswa) {
            $siswa ??= Siswa::factory()->create();

            return ['role' => 'siswa', 'nis' => $siswa->nis, 'nip' => null, 'nama' => null];
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
