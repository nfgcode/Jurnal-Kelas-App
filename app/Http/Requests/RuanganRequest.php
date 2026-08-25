<?php

namespace App\Http\Requests;

use App\Models\Ruangan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One set of room rules shared by create and update. Authorization is enforced
 * by route middleware, so authorize() defers to it.
 */
class RuanganRequest extends FormRequest
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
        // On update the row keeps its own code, so the uniqueness check has to
        // ignore it — otherwise saving a room without touching its code fails.
        $ruangan = $this->route('ruangan');

        return [
            'kode' => [
                'required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\.]+$/',
                Rule::unique('ruangan', 'kode')->ignore($ruangan?->kode, 'kode'),
            ],
            'nama' => 'required|string|max:255',
            'jenis' => ['required', Rule::in(array_keys(Ruangan::JENIS))],
            'kapasitas' => 'required|integer|min:1|max:200',
            'gedung' => 'nullable|string|max:100',
            'lantai' => 'nullable|integer|min:1|max:10',
            'keterangan' => 'nullable|string|max:1000',
            'status' => 'required|in:aktif,perbaikan,nonaktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.regex' => 'Kode ruangan hanya boleh huruf, angka, titik, dan tanda hubung — mis. R-101 atau LAB-RPL-1.',
            'kode.unique' => 'Kode ruangan ini sudah dipakai.',
        ];
    }
}
