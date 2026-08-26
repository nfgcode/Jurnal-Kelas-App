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
            // Read from the class when it is loaded; the accessor would fetch
            // it per student otherwise, which a class roster does 36 times.
            'is_ketua_kelas' => $this->relationLoaded('kelas')
                ? $this->kelas?->ketua_nis === $this->nis
                : (bool) $this->is_ketua_kelas,
            'status' => $this->status,
            'kelas' => new KelasResource($this->whenLoaded('kelas')),
        ];
    }
}
