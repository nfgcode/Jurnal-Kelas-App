<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Jurnal
 */
class JurnalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'jadwal_id' => $this->jadwal_id,
            'tanggal' => $this->tanggal?->toDateString(),
            'materi' => $this->materi,
            'tugas' => $this->tugas,
            'kegiatan' => $this->kegiatan,
            'catatan' => $this->catatan,
            'kehadiran_guru' => [
                'status' => $this->kehadiran_guru_status,
                'alasan' => $this->kehadiran_guru_alasan,
                'ada_tugas' => $this->kehadiran_guru_ada_tugas,
                'keterangan' => $this->kehadiran_guru_keterangan,
                'chip' => $this->kehadiranGuruChip(),
            ],
            'status_pengisian' => $this->statusPengisian(),
            'guru_id' => $this->guru_id,
            'diisi_oleh_id' => $this->diisi_oleh_id,
            // Attendance rollups, present only when the query counted them.
            'total_siswa' => $this->whenHas('total_siswa', fn () => (int) $this->total_siswa),
            'hadir_count' => $this->whenHas('hadir_count', fn () => (int) $this->hadir_count),
            'sakit_count' => $this->whenHas('sakit_count', fn () => (int) $this->sakit_count),
            'izin_count' => $this->whenHas('izin_count', fn () => (int) $this->izin_count),
            'alpa_count' => $this->whenHas('alpa_count', fn () => (int) $this->alpa_count),
            'jadwal' => new JadwalResource($this->whenLoaded('jadwal')),
            'guru' => new UserResource($this->whenLoaded('guru')),
            'diisi_oleh' => new UserResource($this->whenLoaded('diisiOleh')),
            'presensi' => PresensiResource::collection($this->whenLoaded('presensis')),
        ];
    }
}
