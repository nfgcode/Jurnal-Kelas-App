<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Student records, plus the login issued alongside a new one. Same create /
 * update asymmetry as {@see GuruRequest}.
 */
class SiswaRequest extends FormRequest
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
        $siswa = $this->route('siswa');
        $baru = $siswa === null;
        $akunId = $siswa?->akun?->id;

        return [
            'nis' => $baru
                ? ['required', 'string', 'min:4', 'max:20', 'regex:/^[0-9]+$/', 'unique:siswa,nis']
                : ['nullable'],
            'nisn' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/', Rule::unique('siswa', 'nisn')->ignore($siswa?->nis, 'nis')],
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'is_ketua_kelas' => ['nullable', 'boolean'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif', 'lulus'])],

            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($akunId)],
            'username' => ['required', 'string', 'min:3', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($akunId)],
            'password' => $baru ? ['required', 'string', 'min:8'] : ['nullable', 'string', 'min:8'],

            'izinkan_nama_sama' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nis.regex' => 'NIS hanya boleh berisi angka.',
            'nis.unique' => 'NIS ini sudah terdaftar atas nama siswa lain.',
            'nisn.regex' => 'NISN hanya boleh berisi angka.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, dan tanda hubung.',
        ];
    }
}
