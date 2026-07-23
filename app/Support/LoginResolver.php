<?php

namespace App\Support;

use App\Models\User;

/**
 * Resolves a login identifier to an account the same way for every entry point.
 *
 * Users sign in with their NIP (guru) or NIS (siswa); an admin has neither, so
 * the email address is accepted instead. Each column is checked separately so a
 * NULL nip/nis can never be matched, and an optional role narrows the lookup so
 * the same digits can never resolve to an account of another role.
 *
 * Shared by the web {@see \App\Http\Controllers\Auth\LoginController} and the
 * API {@see \App\Http\Controllers\Api\AuthController} so both behave identically.
 */
class LoginResolver
{
    public static function resolve(string $identifier, ?string $role = null): ?User
    {
        return User::query()
            ->when($role, fn ($query, $role) => $query->where('role', $role))
            ->where(function ($query) use ($identifier) {
                $query->where('nip', $identifier)
                    ->orWhere('nis', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->first();
    }
}
