<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\LoginResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Token authentication for the JSON API (Laravel Sanctum). Mirrors the web
 * login, resolving NIP/NIS/email via the shared {@see LoginResolver}.
 */
class AuthController extends Controller
{
    /**
     * Exchange credentials for a personal access token.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'user' => 'required|string',
            'password' => 'required|string',
            'role' => ['nullable', Rule::in(['admin', 'guru', 'siswa'])],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = LoginResolver::resolve($credentials['user'], $credentials['role'] ?? null);

        // Generic message whether the account is missing or the password wrong,
        // so the endpoint never confirms which identifiers exist.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'user' => 'NIP/NIS atau password salah.',
            ]);
        }

        if ($user->status === 'nonaktif') {
            throw ValidationException::withMessages([
                'user' => 'Akun ini nonaktif. Hubungi admin sekolah.',
            ]);
        }

        $user->forceFill(['last_active_at' => now()])->save();

        $token = $user->createToken($credentials['device_name'] ?? 'api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('kelas')),
        ]);
    }

    /**
     * Revoke the access token used for the current request.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    /**
     * The authenticated user's own profile.
     */
    public function me(Request $request)
    {
        return new UserResource($request->user()->load('kelas'));
    }
}
