<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Teacher records, plus the login issued alongside a new one.
 *
 * On create the account fields are required: a teacher is entered in order to
 * be given access. On update they are optional — the person's details are
 * edited far more often than their credentials, and blanking the password box
 * must mean "leave it alone", not "erase it".
 */
class GuruRequest extends FormRequest
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
        $guru = $this->route('guru');
        $baru = $guru === null;
        $akunId = $guru?->akun?->id;

        return [
            'nip' => $baru
                ? ['required', 'string', 'min:4', 'max:20', 'regex:/^[0-9]+$/', 'unique:guru,nip']
                : ['nullable'],
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],

            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($akunId)],
            'username' => ['required', 'string', 'min:3', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($akunId)],
            'password' => $baru ? ['required', 'string', 'min:8'] : ['nullable', 'string', 'min:8'],

            'mata_pelajaran_id' => ['array'],
            'mata_pelajaran_id.*' => ['integer', 'exists:mata_pelajaran,id'],
            'mapel_utama' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],

            'kelas_wali' => ['array'],
            'kelas_wali.*' => ['integer', 'exists:kelas,id'],

            // Two teachers really can share a name; the admin confirms it once.
            'izinkan_nama_sama' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nip.regex' => 'NIP hanya boleh berisi angka.',
            'nip.unique' => 'NIP ini sudah terdaftar atas nama guru lain.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, dan tanda hubung.',
        ];
    }
}
