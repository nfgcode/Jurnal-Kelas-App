<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AkunRequest;
use App\Models\User;
use App\Support\Halaman;
use App\Support\Urutan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Login accounts, and nothing else.
 *
 * This page answers "who can sign in, as what, and when did they last do it".
 * Who those people *are* — names, NIS/NIP, classes, subjects — is the business
 * of /admin/siswa and /admin/guru. Splitting them means an admin resetting a
 * password can no longer accidentally rename a student, and a teacher's record
 * survives their account being revoked.
 *
 * Only admin accounts can be created here: a guru or siswa account is created
 * together with the person it belongs to, in one transaction, because an
 * account pointing at nobody has nothing to authorise.
 */
class AkunController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'role' => ['nullable', Rule::in(['admin', 'guru', 'siswa'])],
            'status' => ['nullable', Rule::in(['aktif', 'nonaktif', 'pending'])],
            'tertaut' => ['nullable', 'in:ya,tidak'],
            'q' => ['nullable', 'string', 'max:255'],
            // sort/dir are deliberately not validated: Urutan whitelists the
            // column against this screen's map, so an unknown one falls back to
            // the default order instead of bouncing the reader with an error.
        ]);

        $akun = User::query()
            // The name is read through these; without eager loading the list
            // costs a query per row.
            ->with(['guru', 'siswa.kelas'])
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['tertaut'] ?? null, fn ($q, $t) => $t === 'tidak'
                ? $q->whereNull('nip')->whereNull('nis')
                : $q->where(fn ($w) => $w->whereNotNull('nip')->orWhereNotNull('nis')))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari));

        // Default: most-recently-active first. Plain DESC already sorts NULLs
        // last on both MySQL and SQLite, and lets users_last_active_at_index
        // provide the order.
        Urutan::terapkan($akun, $request, [
            'username' => fn ($q, $dir) => $q->orderBy('username', $dir),
            'email' => fn ($q, $dir) => $q->orderBy('email', $dir),
            'peran' => fn ($q, $dir) => $q->orderBy('role', $dir),
            'nip_nis' => fn ($q, $dir) => $q->orderByRaw("COALESCE(nip, nis) {$dir}"),
            'status' => fn ($q, $dir) => $q->orderBy('status', $dir),
            'aktif' => fn ($q, $dir) => $q->orderBy('last_active_at', $dir),
        ], fn ($q) => $q->orderByDesc('last_active_at')->orderBy('username'));

        // One grouped pass covers every headline count, instead of ~6 separate
        // COUNTs. Summed in PHP so it stays driver-portable.
        $rekap = User::selectRaw('role, status, COUNT(*) as total')->groupBy('role', 'status')->get();

        $hitung = fn (?string $role = null, ?string $status = null) => (int) $rekap
            ->when($role !== null, fn ($c) => $c->where('role', $role))
            ->when($status !== null, fn ($c) => $c->where('status', $status))
            ->sum('total');

        return view('admin.akun.index', [
            'akun' => $akun->paginate(Halaman::perHalaman())->withQueryString(),
            'filters' => $filters,
            'jumlahPerRole' => [
                'admin' => $hitung('admin'),
                'guru' => $hitung('guru'),
                'siswa' => $hitung('siswa'),
            ],
            'statistik' => [
                'total' => $hitung(),
                'aktif' => $hitung(null, 'aktif'),
                'nonaktif' => $hitung(null, 'nonaktif'),
                'pending' => $hitung(null, 'pending'),
                'belumPernahMasuk' => User::whereNull('last_active_at')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.akun.create');
    }

    /**
     * Admin accounts only — see the class docblock.
     */
    public function store(AkunRequest $request)
    {
        $data = $request->validated();

        if ($data['role'] !== 'admin') {
            return back()->withInput()->withErrors([
                'role' => 'Akun guru dan siswa dibuat dari halaman Data Guru / Data Siswa, bersama data orangnya.',
            ]);
        }

        User::create([
            'username' => $data['username'],
            'nama' => $data['nama'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'admin',
            'status' => $data['status'],
        ]);

        return redirect()->route('admin.akun.index')
            ->with('success', 'Akun admin berhasil ditambahkan.');
    }

    public function show(User $akun)
    {
        $akun->load(['guru.mataPelajaran', 'guru.kelasWali', 'siswa.kelas']);

        return view('admin.akun.show', compact('akun'));
    }

    public function edit(User $akun)
    {
        $akun->load(['guru', 'siswa']);

        return view('admin.akun.edit', compact('akun'));
    }

    public function update(AkunRequest $request, User $akun)
    {
        $data = $request->validated();

        // Don't let an admin demote themselves and lose access mid-session.
        if ($akun->is($request->user()) && $data['role'] !== 'admin') {
            return back()->withInput()
                ->withErrors(['role' => 'Anda tidak dapat mengubah peran akun Anda sendiri.']);
        }

        // The role follows the person the account is attached to; changing it
        // freely would leave a guru account pointing at a student row, which
        // the database trigger refuses anyway. Better to say so here.
        if ($data['role'] !== $akun->role && ($akun->nip || $akun->nis)) {
            return back()->withInput()->withErrors([
                'role' => 'Peran akun ini mengikuti data orangnya. Ubah dari halaman Data Guru / Data Siswa.',
            ]);
        }

        $akun->username = $data['username'];
        $akun->email = $data['email'];
        $akun->status = $data['status'];
        $akun->role = $data['role'];
        $akun->nama = $akun->role === 'admin' ? $data['nama'] : null;

        if (filled($data['password'] ?? null)) {
            $akun->password = Hash::make($data['password']);
        }

        $akun->save();

        return redirect()->route('admin.akun.index')
            ->with('success', 'Akun berhasil diperbarui.');
    }

    /**
     * Deleting an account revokes access; it does not remove the person.
     *
     * That asymmetry is the point of the split — the student stays on the
     * roster and keeps their attendance history, they just can no longer sign
     * in. Removing the person is done from their own page.
     */
    public function destroy(Request $request, User $akun)
    {
        if ($akun->is($request->user())) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $milik = $akun->guru?->nama ?? $akun->siswa?->nama;
        $akun->delete();

        return redirect()->route('admin.akun.index')->with('success', $milik
            ? "Akun dihapus. Data {$milik} tetap tersimpan dan bisa diberi akun baru."
            : 'Akun berhasil dihapus.');
    }
}
