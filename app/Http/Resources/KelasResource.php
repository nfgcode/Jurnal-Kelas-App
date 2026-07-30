<?php

namespace App\Http\Resources;

use App\Models\Kelas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Kelas
 */
class KelasResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_kelas' => $this->nama_kelas,
            'tingkat' => $this->tingkat,
            'jurusan' => $this->jurusan,
            'ruang' => $this->ruang,
            'kapasitas' => $this->kapasitas,
            'tahun_ajaran' => $this->tahun_ajaran,
            'wali_kelas_id' => $this->wali_kelas_id,
            'jumlah_siswa' => $this->whenCounted('siswa'),
            'jumlah_jadwal' => $this->whenCounted('jadwals'),
            'wali_kelas' => new UserResource($this->whenLoaded('waliKelas')),
            'siswa' => UserResource::collection($this->whenLoaded('siswa')),
        ];
    }
}
