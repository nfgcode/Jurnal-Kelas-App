<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JurusanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $data = ['aktif' => $this->input('aktif', false)];

        if (is_string($this->input('kode'))) {
            $data['kode'] = mb_strtoupper($this->input('kode'));
        }

        foreach (['nama', 'bidang', 'deskripsi'] as $field) {
            if (is_string($this->input($field))) {
                $data[$field] = trim($this->input($field));
            }
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:20', 'regex:/^\S+\z/u',
                Rule::unique('jurusan', 'kode')->ignore($this->route('jurusan')?->kode, 'kode')],
            'nama' => ['required', 'string', 'max:255'],
            'bidang' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode.regex' => 'Kode jurusan tidak boleh mengandung spasi atau whitespace.',
            'kode.unique' => 'Kode jurusan ini sudah dipakai.',
        ];
    }
}
