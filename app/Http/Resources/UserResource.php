<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An account, plus enough of the person behind it to be useful.
 *
 * `nama` is resolved through the guru/siswa relation, so a client reading this
 * sees the same name every screen does — but the field it would have to write
 * to change that name lives on the person endpoint, not here.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'nama' => $this->nama,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'nip' => $this->nip,
            'nis' => $this->nis,
            'kelas_id' => $this->kelas_id,
            'is_ketua_kelas' => $this->isKetuaKelas(),
            'last_active_at' => $this->last_active_at?->toISOString(),
            'guru' => $this->whenLoaded('guru', fn () => [
                'nip' => $this->guru->nip,
                'nama' => $this->guru->nama,
                'status' => $this->guru->status,
            ]),
            'siswa' => $this->whenLoaded('siswa', fn () => [
                'nis' => $this->siswa->nis,
                'nama' => $this->siswa->nama,
                'kelas_id' => $this->siswa->kelas_id,
                'status' => $this->siswa->status,
            ]),
        ];
    }
}
