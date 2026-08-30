<?php

namespace App\Http\Resources;

use App\Models\Presensi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One student's attendance for one meeting — the per-subject record its teacher
 * marked. The day-level view of the same facts is {@see PresensiHarianResource}.
 *
 * @mixin Presensi
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
            'siswa_nis' => $this->siswa_nis,
            'status' => $this->status,
            'keterangan' => $this->keterangan,
            'diisi_oleh_id' => $this->diisi_oleh_id,
            'siswa' => new SiswaResource($this->whenLoaded('siswa')),
        ];
    }
}
