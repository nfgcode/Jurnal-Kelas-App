<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paralel.unique' => 'Rombel dengan tingkat, jurusan, dan nomor paralel ini sudah ada di tahun ajaran tersebut.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $kelas = $this->route('kela');

        return [
            'nama_kelas' => 'required|string|max:255',
            'tingkat' => 'required|in:X,XI,XII',
            'jurusan_kode' => 'nullable|exists:jurusan,kode',
            // A school year has one "XII TKJ 2". The database says so too
            // (kelas_rombel_unique); without this rule that would reach the
            // admin as a crash page instead of as a message on the field.
            'paralel' => [
                'required', 'integer', 'min:1', 'max:20',
                Rule::unique('kelas', 'paralel')
                    ->where('tahun_ajaran_kode', $this->input('tahun_ajaran_kode'))
                    ->where('tingkat', $this->input('tingkat'))
                    ->where('jurusan_kode', $this->input('jurusan_kode'))
                    ->ignore($kelas?->id),
            ],
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
