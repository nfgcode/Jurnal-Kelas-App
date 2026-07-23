<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rules mirror the web KelasController; admin gate is enforced by the
 * `role:admin` route middleware, so authorize() defers to it.
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
            'jurusan' => 'nullable|string|max:255',
            'ruang' => 'nullable|string|max:50',
            'kapasitas' => 'required|integer|min:1|max:60',
            'tahun_ajaran' => 'required|string|max:9',
            'wali_kelas_id' => 'nullable|exists:users,id',
        ];
    }
}
