<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Support\Halaman;
use App\Support\Urutan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users, filterable by role and name/email.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'role' => ['nullable', Rule::in(['admin', 'guru', 'siswa'])],
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['nullable', 'exists:mata_pelajaran,id'],
            'status' => ['nullable', Rule::in(['aktif', 'nonaktif', 'pending'])],
            'q' => ['nullable', 'string', 'max:255'],
            // sort/dir are deliberately not validated here: Urutan whitelists the
            // column against this screen's map and narrows the direction, so an
            // unknown one falls back to the default order instead of bouncing the
            // reader with a validation error — the behaviour every other table has.
        ]);

        $users = User::query()
            // A guru's subjects and classes are derived from the timetable
            // rather than stored, so the rows need it loaded.
            ->with(['kelas', 'jadwals.mataPelajaran', 'jadwals.kelas'])
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['kelas_id'] ?? null, fn ($query, $id) => $query->where('kelas_id', $id))
            // Match teachers of the subject (they teach it) AND students whose
            // class studies it (their kelas has a jadwal for it).
            ->when($filters['mata_pelajaran_id'] ?? null, fn ($query, $id) => $query->where(fn ($w) => $w
                ->whereHas('jadwals', fn ($j) => $j->where('mata_pelajaran_id', $id))
                ->orWhereHas('kelas.jadwals', fn ($j) => $j->where('mata_pelajaran_id', $id))))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->cari($q));

        // Default: most-recently-active first, then by name. Plain DESC already
        // sorts NULLs last on both MySQL and SQLite, and unlike the old
        // `last_active_at IS NULL` expression it lets users_last_active_at_index
        // provide the order.
        Urutan::terapkan($users, $request, self::petaUrutan(), fn ($query) => $query
            ->orderByDesc('last_active_at')->orderBy('name'));

        $users = $users->paginate(Halaman::perHalaman())->withQueryString();

        // One grouped pass over users covers every headline count below, instead
        // of ~7 separate COUNT queries. Summed in PHP so it stays driver-portable.
        $rekap = User::selectRaw('role, status, COUNT(*) as total')
            ->groupBy('role', 'status')
            ->get();

        $perRoleStatus = fn (string $role, ?string $status = null) => (int) $rekap
            ->where('role', $role)
            ->when($status !== null, fn ($c) => $c->where('status', $status))
            ->sum('total');

        $jumlahPerRole = [
            'admin' => $perRoleStatus('admin'),
            'guru' => $perRoleStatus('guru'),
            'siswa' => $perRoleStatus('siswa'),
        ];

        return view('admin.users.index', [
            'users' => $users,
            'jumlahPerRole' => $jumlahPerRole,
            'statistik' => [
                'total' => (int) $rekap->sum('total'),
                'guruAktif' => $perRoleStatus('guru', 'aktif'),
                'guruPending' => $perRoleStatus('guru', 'pending'),
                'siswaAktif' => $perRoleStatus('siswa', 'aktif'),
                'nonaktif' => (int) $rekap->where('status', 'nonaktif')->sum('total'),
                'kelas' => Kelas::count(),
            ],
            'kelasList' => Kelas::orderBy('nama_kelas')->get(),
            'mapelList' => MataPelajaran::orderBy('nama')->get(),
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        return view('admin.users.create', [
            'kelasList' => Kelas::with('waliKelas')->orderBy('nama_kelas')->get(),
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(UserRequest $request)
    {
        $data = $request->normalizedData();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        if ($user->role === 'guru') {
            $this->syncPerwalian($user, $request->input('kelas_wali', []));
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load('kelas');

        // Extra context depending on the role being viewed.
        if ($user->isGuru()) {
            $user->loadCount(['jadwals', 'jurnals']);
            $user->load('kelasWali');
            $jadwals = $user->jadwals()->with(['kelas', 'mataPelajaran'])->get();
        } else {
            $jadwals = collect();
        }

        $rekapPresensi = $user->isSiswa()
            ? $user->presensiHarian()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
            : collect();

        return view('admin.users.show', compact('user', 'jadwals', 'rekapPresensi'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        return view('admin.users.edit', [
            'user' => $user,
            'kelasList' => Kelas::with('waliKelas')->orderBy('nama_kelas')->get(),
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UserRequest $request, User $user)
    {
        $data = $request->normalizedData();

        // Only change the password when a new one was actually supplied.
        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Don't let an admin demote themselves and lose access mid-session.
        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return back()
                ->withInput()
                ->withErrors(['role' => 'Anda tidak dapat mengubah peran akun Anda sendiri.']);
        }

        $user->update($data);

        // Sync homeroom classes when the account is (still) a guru. When the
        // role just changed away from guru, the User model's updated() event
        // has already released them, so skipping here keeps them released.
        if ($user->role === 'guru') {
            $this->syncPerwalian($user, $request->input('kelas_wali', []));
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }

    /**
     * Make this guru the wali of exactly $kelasIds: assign the chosen classes
     * and release any class it was wali of but no longer chosen. Both the user
     * form and the Kelas form write the same kelas.wali_kelas_id, so they stay
     * two views of one fact. An empty selection releases all of this guru's
     * classes.
     *
     * @param  array<int|string>  $kelasIds
     */
    private function syncPerwalian(User $user, array $kelasIds): void
    {
        Kelas::whereIn('id', $kelasIds)->update(['wali_kelas_id' => $user->id]);

        Kelas::where('wali_kelas_id', $user->id)
            ->when($kelasIds, fn ($query) => $query->whereNotIn('id', $kelasIds))
            ->update(['wali_kelas_id' => null]);
    }

    /**
     * Apply a whitelisted column sort. NIP/NIS collapse to one column via
     * COALESCE; class sorts by the student's class name through a subquery so no
     * join is needed. $dir is already constrained to asc|desc by the caller.
     */
    /**
     * Sortable columns of the user table. The keys are the whitelist
     * {@see Urutan} matches `?sort=` against; `dir` is already narrowed to
     * asc|desc there, so it is safe inside orderByRaw.
     *
     * @return array<string, callable>
     */
    private static function petaUrutan(): array
    {
        return [
            'nama' => fn ($q, $dir) => $q->orderBy('name', $dir),
            'nip_nis' => fn ($q, $dir) => $q->orderByRaw("COALESCE(nip, nis) {$dir}"),
            'peran' => fn ($q, $dir) => $q->orderBy('role', $dir),
            'kelas' => fn ($q, $dir) => $q->orderBy(
                Kelas::select('nama_kelas')->whereColumn('kelas.id', 'users.kelas_id'), $dir
            ),
            'status' => fn ($q, $dir) => $q->orderBy('status', $dir),
            'aktif' => fn ($q, $dir) => $q->orderBy('last_active_at', $dir),
        ];
    }
}
