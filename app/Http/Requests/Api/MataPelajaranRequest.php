<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules mirror the web MataPelajaranController. The kode uniqueness check
 * ignores the record being edited on update (null on store).
 */
class MataPelajaranRequest extends FormRequest
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
        $id = $this->route('mataPelajaran')?->id;

        return [
            'nama' => 'required|string|max:255',
            'kode' => ['required', 'string', 'max:20', Rule::unique('mata_pelajaran', 'kode')->ignore($id)],
            'kelompok' => 'required|in:wajib,peminatan,muatan_lokal,kejuruan',
            'jp_per_minggu' => 'required|integer|min:1|max:12',
            'deskripsi' => 'nullable|string',
        ];
    }
}
