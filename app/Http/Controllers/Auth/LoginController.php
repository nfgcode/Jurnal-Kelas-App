<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\LoginResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LoginController extends Controller
{
    /**
     * Show the login form. The brand panel no longer carries school figures:
     * a public page has no business announcing the school's head counts.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle an authentication attempt.
     *
     * Users sign in with their NIP (guru) or NIS (siswa); an admin has neither,
     * so username or email work too. The form has one identifier field and no
     * role picker (as in Figma): the resolver picks, among the accounts the
     * identifier matches, the one the password unlocks. `role` is still
     * accepted from older clients and narrows the lookup when sent.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'user' => 'required|string',
            'password' => 'required|string',
            'role' => ['nullable', Rule::in(['admin', 'guru', 'siswa'])],
        ]);

        $user = LoginResolver::resolve($credentials['user'], $credentials['role'] ?? null, $credentials['password']);

        // Only reached with the right password, so "nonaktif" is never revealed
        // to someone merely guessing identifiers.
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
