<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
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
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'nip' => $this->nip,
            'nis' => $this->nis,
            'kelas_id' => $this->kelas_id,
            'is_ketua_kelas' => (bool) $this->is_ketua_kelas,
            'last_active_at' => $this->last_active_at?->toISOString(),
            'kelas' => new KelasResource($this->whenLoaded('kelas')),
        ];
    }
}
