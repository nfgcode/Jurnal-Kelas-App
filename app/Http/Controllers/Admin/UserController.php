<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\User;
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
            'sort' => ['nullable', 'in:nama,nip_nis,peran,kelas,status,aktif'],
            'dir' => ['nullable', 'in:asc,desc'],
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
            ->when($filters['q'] ?? null, function ($query, $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('nip', 'like', "%{$q}%")
                        ->orWhere('nis', 'like', "%{$q}%")
                        // A student's own class, or a class a teacher is timetabled in.
                        ->orWhereHas('kelas', fn ($k) => $k->where('nama_kelas', 'like', "%{$q}%"))
                        ->orWhereHas('jadwals.kelas', fn ($k) => $k->where('nama_kelas', 'like', "%{$q}%"));
                });
            })
            ->when(
                $filters['sort'] ?? null,
                fn ($query, $sort) => $this->terapkanUrutan($query, $sort, ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc'),
                // Default: most-recently-active first, then by name.
                fn ($query) => $query->orderByRaw('last_active_at IS NULL, last_active_at DESC')->orderBy('name')
            )
            ->paginate(18)
            ->withQueryString();

        $jumlahPerRole = [
            'admin' => User::where('role', 'admin')->count(),
            'guru' => User::where('role', 'guru')->count(),
            'siswa' => User::where('role', 'siswa')->count(),
        ];

        return view('admin.users.index', [
            'users' => $users,
            'jumlahPerRole' => $jumlahPerRole,
            'statistik' => [
                'total' => array_sum($jumlahPerRole),
                'guruAktif' => User::where('role', 'guru')->where('status', 'aktif')->count(),
                'guruPending' => User::where('role', 'guru')->where('status', 'pending')->count(),
                'siswaAktif' => User::where('role', 'siswa')->where('status', 'aktif')->count(),
                'nonaktif' => User::where('status', 'nonaktif')->count(),
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
            'kelasList' => Kelas::orderBy('nama_kelas')->get(),
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $validated['password'] = Hash::make($validated['password']);

        User::create($this->normalize($validated));

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
            $jadwals = $user->jadwals()->with(['kelas', 'mataPelajaran'])->get();
        } else {
            $jadwals = collect();
        }

        $rekapPresensi = $user->isSiswa()
            ? $user->presensis()
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
            'kelasList' => Kelas::orderBy('nama_kelas')->get(),
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate($this->rules($user));

        // Only change the password when a new one was actually supplied.
        if (filled($validated['password'] ?? null)) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Don't let an admin demote themselves and lose access mid-session.
        if ($user->is($request->user()) && $validated['role'] !== 'admin') {
            return back()
                ->withInput()
                ->withErrors(['role' => 'Anda tidak dapat mengubah peran akun Anda sendiri.']);
        }

        $user->update($this->normalize($validated));

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
     * Apply a whitelisted column sort. NIP/NIS collapse to one column via
     * COALESCE; class sorts by the student's class name through a subquery so no
     * join is needed. $dir is already constrained to asc|desc by the caller.
     */
    private function terapkanUrutan($query, string $sort, string $dir)
    {
        return match ($sort) {
            'nama' => $query->orderBy('name', $dir),
            'nip_nis' => $query->orderByRaw('COALESCE(nip, nis) ' . ($dir === 'desc' ? 'desc' : 'asc')),
            'peran' => $query->orderBy('role', $dir),
            'kelas' => $query->orderBy(Kelas::select('nama_kelas')->whereColumn('kelas.id', 'users.kelas_id'), $dir),
            'status' => $query->orderBy('status', $dir),
            'aktif' => $query->orderBy('last_active_at', $dir),
            default => $query,
        };
    }

    /**
     * Validation rules shared by store and update.
     *
     * On update the password becomes optional and the email uniqueness
     * check ignores the record being edited.
     */
    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'guru', 'siswa'])],
            'status' => ['required', Rule::in(['aktif', 'nonaktif', 'pending'])],
            'is_ketua_kelas' => ['nullable', 'boolean'],
            // nip and nis double as login identifiers, so they must be unique.
            'nip' => ['nullable', 'required_if:role,guru', 'string', 'max:255', Rule::unique('users')->ignore($user)],
            'nis' => ['nullable', 'required_if:role,siswa', 'string', 'max:255', Rule::unique('users')->ignore($user)],
            'kelas_id' => ['nullable', 'required_if:role,siswa', 'exists:kelas,id'],
        ];
    }

    /**
     * Clear role-specific fields that don't apply to the chosen role,
     * so a guru never keeps a stale nis and vice versa.
     */
    private function normalize(array $data): array
    {
        if ($data['role'] !== 'guru') {
            $data['nip'] = null;
        }

        if ($data['role'] !== 'siswa') {
            $data['nis'] = null;
            $data['kelas_id'] = null;
        }

        // Only a student can chair a class, and only one per class does.
        $data['is_ketua_kelas'] = $data['role'] === 'siswa' && ! empty($data['is_ketua_kelas']);

        return $data;
    }
}
