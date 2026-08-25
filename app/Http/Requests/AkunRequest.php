<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Credentials only.
 *
 * What used to be validated here — name, NIP/NIS, class, ketua kelas — belongs
 * to the person, and is validated by {@see GuruRequest} / {@see SiswaRequest}
 * on the pages that own those records. An account page that could rename
 * someone was exactly the confusion the split set out to remove.
 *
 * The admin gate is enforced by the `role:admin` route middleware, so
 * authorize() defers to it.
 */
class AkunRequest extends FormRequest
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
        $akun = $this->route('akun');

        return [
            'username' => [
                'required', 'string', 'min:3', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($akun),
            ],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($akun)],
            'password' => [$akun ? 'nullable' : 'required', 'string', 'min:8'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif', 'pending'])],

            // Only an admin account carries its own name; a guru or siswa
            // account reads it from the person row, and the database trigger
            // nulls it out if one is sent anyway.
            'nama' => ['required_if:role,admin', 'nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'guru', 'siswa'])],
        ];
    }
}
