<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Jadwal
 */
class JadwalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kelas_id' => $this->kelas_id,
            'mata_pelajaran_id' => $this->mata_pelajaran_id,
            'guru_id' => $this->guru_id,
            'hari' => $this->hari,
            'jam_ke_mulai' => $this->jam_ke_mulai,
            'jam_ke_selesai' => $this->jam_ke_selesai,
            'jp_label' => $this->jpLabel(),
            'jam_mulai' => $this->jam_mulai,
            'jam_selesai' => $this->jam_selesai,
            'ruang' => $this->ruang,
            'kelas' => new KelasResource($this->whenLoaded('kelas')),
            'mata_pelajaran' => new MataPelajaranResource($this->whenLoaded('mataPelajaran')),
            'guru' => new UserResource($this->whenLoaded('guru')),
        ];
    }
}
