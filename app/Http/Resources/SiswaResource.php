<?php

namespace App\Http\Resources;

use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student as a person, keyed by NIS — what a roster row actually references.
 *
 * @mixin Siswa
 */
class SiswaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nis' => $this->nis,
            'nisn' => $this->nisn,
            'nama' => $this->nama,
            'jenis_kelamin' => $this->jenis_kelamin,
            'kelas_id' => $this->kelas_id,
            'is_ketua_kelas' => (bool) $this->is_ketua_kelas,
            'status' => $this->status,
            'kelas' => new KelasResource($this->whenLoaded('kelas')),
        ];
    }
}
