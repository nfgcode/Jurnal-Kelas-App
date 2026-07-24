<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One set of account rules shared by the web Admin\UserController and the API.
 * On update the password is optional and the unique checks ignore the record
 * being edited. The admin gate is enforced by the `role:admin` route
 * middleware, so authorize() defers to it.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');

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
     * The validated payload with role-specific fields cleared when they don't
     * apply to the chosen role, so a guru never keeps a stale nis and vice
     * versa. Only a student can chair a class.
     *
     * @return array<string, mixed>
     */
    public function normalizedData(): array
    {
        $data = $this->validated();

        if ($data['role'] !== 'guru') {
            $data['nip'] = null;
        }

        if ($data['role'] !== 'siswa') {
            $data['nis'] = null;
            $data['kelas_id'] = null;
        }

        $data['is_ketua_kelas'] = $data['role'] === 'siswa' && ! empty($data['is_ketua_kelas']);

        return $data;
    }
}
