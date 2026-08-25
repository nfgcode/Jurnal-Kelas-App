<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One set of class rules shared by the web KelasController and the API.
 * Authorization is enforced by route middleware, so authorize() defers to it.
 */
class KelasRequest extends FormRequest
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
        return [
            'nama_kelas' => 'required|string|max:255',
            'tingkat' => 'required|in:X,XI,XII',
            'jurusan_kode' => 'nullable|exists:jurusan,kode',
            'paralel' => 'required|integer|min:1|max:20',
            'ruangan_kode' => 'nullable|exists:ruangan,kode',
            'kapasitas' => 'required|integer|min:1|max:60',
            'tahun_ajaran_kode' => 'required|exists:tahun_ajaran,kode',
            'wali_kelas_nip' => 'nullable|exists:guru,nip',
            // The chair must be a student of this very class; the controller
            // checks that, because the rule needs the class being edited.
            'ketua_nis' => 'nullable|exists:siswa,nis',
        ];
    }
}
