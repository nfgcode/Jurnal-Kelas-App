<?php

namespace App\Http\Resources;

use App\Models\PresensiHarian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One student's attendance for one school day.
 *
 * @mixin PresensiHarian
 */
class PresensiHarianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kelas_id' => $this->kelas_id,
            'tanggal' => $this->tanggal?->toDateString(),
            'siswa_id' => $this->siswa_id,
            'status' => $this->status,
            'keterangan' => $this->keterangan,
            'diisi_oleh_id' => $this->diisi_oleh_id,
            'siswa' => new UserResource($this->whenLoaded('siswa')),
            'kelas' => new KelasResource($this->whenLoaded('kelas')),
        ];
    }
}
