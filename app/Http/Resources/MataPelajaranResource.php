<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MataPelajaran
 */
class MataPelajaranResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'kode' => $this->kode,
            'kelompok' => $this->kelompok,
            'kelompok_label' => $this->kelompokLabel(),
            'jp_per_minggu' => $this->jp_per_minggu,
            'deskripsi' => $this->deskripsi,
            'jumlah_jadwal' => $this->whenCounted('jadwals'),
        ];
    }
}
