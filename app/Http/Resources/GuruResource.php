<?php

namespace App\Http\Resources;

use App\Models\Guru;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A teacher as a person, keyed by NIP.
 *
 * Separate from {@see UserResource} because a timetable slot or a journal
 * points at the teacher, not at whatever login they happen to have — and a
 * teacher may have none.
 *
 * @mixin Guru
 */
class GuruResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nip' => $this->nip,
            'nama' => $this->nama,
            'jenis_kelamin' => $this->jenis_kelamin,
            'status' => $this->status,
            'mata_pelajaran' => $this->whenLoaded('mataPelajaran', fn () => $this->mataPelajaran
                ->map(fn ($m) => ['id' => $m->id, 'nama' => $m->nama, 'utama' => (bool) $m->pivot->utama])
                ->all()),
        ];
    }
}
