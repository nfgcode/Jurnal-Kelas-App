<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LoginController extends Controller
{
    /**
     * Show the login form, with the school figures the brand panel displays.
     */
    public function showLoginForm()
    {
        return view('auth.login', [
            'ringkasan' => [
                'Siswa aktif' => User::where('role', 'siswa')->where('status', 'aktif')->count(),
                'Guru pengajar' => User::where('role', 'guru')->count(),
                'Jurnal tercatat' => Jurnal::count(),
                'Rombel' => Kelas::count(),
            ],
        ]);
    }

    /**
     * Handle an authentication attempt.
     *
     * Users sign in with their NIP (guru) or NIS (siswa). An admin has
     * neither, so the email address is accepted instead. The role tab on the
     * form narrows the lookup so the same digits can never resolve to an
     * account of another role.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'user' => 'required|string',
            'password' => 'required|string',
            'role' => ['nullable', Rule::in(['admin', 'guru', 'siswa'])],
        ]);

        $user = $this->resolveUser($credentials['user'], $credentials['role'] ?? null);

        if ($user?->status === 'nonaktif') {
            return back()->withErrors([
                'user' => 'Akun ini nonaktif. Hubungi admin sekolah.',
            ])->onlyInput('user');
        }

        if ($user && Auth::attempt(
            ['email' => $user->email, 'password' => $credentials['password']],
            $request->boolean('remember'),
        )) {
            $request->session()->regenerate();

            $user->forceFill(['last_active_at' => now()])->save();

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'user' => 'NIP/NIS atau password salah.',
        ])->onlyInput('user');
    }

    /**
     * Find the account matching a login identifier.
     *
     * Each column is checked separately so a NULL nip/nis can never be
     * matched, and so an admin can still sign in with their email.
     */
    private function resolveUser(string $identifier, ?string $role = null): ?User
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

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
