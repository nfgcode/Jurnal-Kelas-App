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
            'jurusan_kode' => $this->jurusan_kode,
            'jurusan' => $this->whenLoaded('jurusan', fn () => [
                'kode' => $this->jurusan->kode,
                'nama' => $this->jurusan->nama,
            ]),
            'paralel' => $this->paralel,
            'ketua_nis' => $this->ketua_nis,
            'ruangan_kode' => $this->ruangan_kode,
            'ruangan' => $this->whenLoaded('ruangan', fn () => [
                'kode' => $this->ruangan->kode,
                'nama' => $this->ruangan->nama,
            ]),
            'kapasitas' => $this->kapasitas,
            'tahun_ajaran' => $this->tahun_ajaran_kode,
            'wali_kelas_nip' => $this->wali_kelas_nip,
            'jumlah_siswa' => $this->whenCounted('siswa'),
            'jumlah_jadwal' => $this->whenCounted('jadwals'),
            'wali_kelas' => new GuruResource($this->whenLoaded('waliKelas')),
            'siswa' => SiswaResource::collection($this->whenLoaded('siswa')),
        ];
    }
}
