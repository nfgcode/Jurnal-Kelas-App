<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Presensi
 */
class PresensiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'jurnal_id' => $this->jurnal_id,
            'siswa_id' => $this->siswa_id,
            'status' => $this->status,
            'keterangan' => $this->keterangan,
            'siswa' => new UserResource($this->whenLoaded('siswa')),
            'jurnal' => new JurnalResource($this->whenLoaded('jurnal')),
        ];
    }
}
