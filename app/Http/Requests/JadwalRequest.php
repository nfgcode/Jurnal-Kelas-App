<?php

namespace App\Http\Requests;

use App\Rules\GuruMengampuMapel;
use App\Support\JamPelajaran;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One set of timetable rules shared by the web JadwalController and the API.
 */
class JadwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maks = JamPelajaran::maksimal();

        return [
            'kelas_id' => 'required|exists:kelas,id',
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'guru_nip' => ['required', 'exists:guru,nip', new GuruMengampuMapel($this->input('mata_pelajaran_id'))],
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'jam_ke_mulai' => "required|integer|min:1|max:{$maks}",
            'jam_ke_selesai' => "required|integer|min:1|max:{$maks}|gte:jam_ke_mulai",
            // jam_mulai/jam_selesai are not accepted from the client at all:
            // the model derives them from the period numbers on save.
            'ruangan_kode' => 'nullable|exists:ruangan,kode',
        ];
    }
}
