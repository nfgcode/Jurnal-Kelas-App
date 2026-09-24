<?php

namespace App\Support;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Resolves a login identifier to an account the same way for every entry point.
 *
 * Four identifiers are accepted: username, email, and — because they are what
 * a school hands out and what people remember — the NIP of a teacher or the NIS
 * of a student. Each column is checked separately so a NULL nip/nis can never
 * be matched, and an optional role narrows the lookup.
 *
 * The login form no longer asks for a role (the Figma login has one identifier
 * field), so the same digits may match more than one account — a guru's NIP
 * equal to a siswa's NIS, say. Given the password, the account it unlocks is
 * the one returned; the others are never touched.
 *
 * Shared by the web {@see LoginController} and the
 * API {@see AuthController} so both behave identically.
 */
class LoginResolver
{
    public static function resolve(string $identifier, ?string $role = null, ?string $password = null): ?User
    {
        $kandidat = User::query()
            ->when($role, fn ($query, $role) => $query->where('role', $role))
            ->where(function ($query) use ($identifier) {
                $query->where('username', $identifier)
                    ->orWhere('nip', $identifier)
                    ->orWhere('nis', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->orderBy('id')
            ->get();

        if ($password === null) {
            return $kandidat->first();
        }

        return $kandidat->first(fn (User $user) => Hash::check($password, $user->password));
    }
}
