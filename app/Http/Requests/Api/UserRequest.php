<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules mirror the web Admin\UserController. On update the password is optional
 * and the unique checks ignore the record being edited. Admin gate is enforced
 * by the `role:admin` route middleware.
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
            'nip' => ['nullable', 'required_if:role,guru', 'string', 'max:255', Rule::unique('users')->ignore($user)],
            'nis' => ['nullable', 'required_if:role,siswa', 'string', 'max:255', Rule::unique('users')->ignore($user)],
            'kelas_id' => ['nullable', 'required_if:role,siswa', 'exists:kelas,id'],
        ];
    }
}
