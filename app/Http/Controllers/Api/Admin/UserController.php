<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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
            ->with('kelas')
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->where('kelas_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari))
            ->orderBy('name')
            ->paginate(18);

        return UserResource::collection($users);
    }

    public function show(User $user)
    {
        return new UserResource($user->load('kelas'));
    }

    public function store(UserRequest $request)
    {
        $data = $request->normalizedData();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->normalizedData();

        // Only change the password when a new one was actually supplied.
        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Don't let an admin demote themselves and lose access.
        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return response()->json([
                'message' => 'Anda tidak dapat mengubah peran akun Anda sendiri.',
            ], 422);
        }

        $user->update($data);

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Pengguna berhasil dihapus.']);
    }
}
