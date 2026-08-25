<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Controller;
use App\Http\Requests\AkunRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * The account endpoint, matching what {@see AkunController} does on the web.
 *
 * Same rule as there: only admin accounts are created here. A guru or siswa
 * account is created together with the person it belongs to, through the
 * stored procedure, so this endpoint cannot mint a credential pointing at
 * nobody.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'role' => ['nullable', Rule::in(['admin', 'guru', 'siswa'])],
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['aktif', 'nonaktif', 'pending'])],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $users = User::query()
            ->with(['guru', 'siswa'])
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            // The class belongs to the student record now, so filtering by it
            // means filtering the people this account is attached to.
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q
                ->whereHas('siswa', fn ($s) => $s->where('kelas_id', $id)))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari))
            ->orderBy('username')
            ->paginate(18);

        return UserResource::collection($users);
    }

    public function show(User $user)
    {
        return new UserResource($user->load(['guru', 'siswa']));
    }

    public function store(AkunRequest $request)
    {
        $data = $request->validated();

        if ($data['role'] !== 'admin') {
            return response()->json([
                'message' => 'Akun guru dan siswa dibuat bersama data orangnya, lewat endpoint guru/siswa.',
                'errors' => ['role' => ['Hanya akun admin yang bisa dibuat di sini.']],
            ], 422);
        }

        $user = User::create([
            'username' => $data['username'],
            'nama' => $data['nama'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'admin',
            'status' => $data['status'],
        ]);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(AkunRequest $request, User $user)
    {
        $data = $request->validated();

        // Don't let an admin demote themselves and lose access.
        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return response()->json([
                'message' => 'Anda tidak dapat mengubah peran akun Anda sendiri.',
            ], 422);
        }

        // The role follows the person the account is attached to.
        if ($data['role'] !== $user->role && ($user->nip || $user->nis)) {
            return response()->json([
                'message' => 'Peran akun ini mengikuti data orangnya; ubah lewat endpoint guru/siswa.',
            ], 422);
        }

        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->status = $data['status'];
        $user->role = $data['role'];
        $user->nama = $user->role === 'admin' ? $data['nama'] : null;

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return new UserResource($user->load(['guru', 'siswa']));
    }

    /**
     * Revokes access; the person stays on the register.
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Akun berhasil dihapus. Data orangnya tetap tersimpan.']);
    }
}
