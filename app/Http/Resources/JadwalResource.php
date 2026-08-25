<?php

namespace App\Http\Resources;

use App\Models\Jadwal;
use App\Support\JamPelajaran;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Jadwal
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
            'guru_nip' => $this->guru_nip,
            'hari' => $this->hari,
            'jam_ke_mulai' => $this->jam_ke_mulai,
            'jam_ke_selesai' => $this->jam_ke_selesai,
            'jp_label' => $this->jpLabel(),
            // Derived from the period numbers via the bell schedule; see JamPelajaran.
            'jam_mulai' => JamPelajaran::mulai((int) $this->jam_ke_mulai),
            'jam_selesai' => JamPelajaran::selesai((int) $this->jam_ke_selesai),
            'ruangan_kode' => $this->ruangan_kode,
            'ruangan' => $this->whenLoaded('ruangan', fn () => [
                'kode' => $this->ruangan->kode,
                'nama' => $this->ruangan->nama,
            ]),
            'kelas' => new KelasResource($this->whenLoaded('kelas')),
            'mata_pelajaran' => new MataPelajaranResource($this->whenLoaded('mataPelajaran')),
            'guru' => new GuruResource($this->whenLoaded('guru')),
        ];
    }
}
