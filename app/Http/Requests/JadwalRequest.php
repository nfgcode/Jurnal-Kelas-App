<?php

namespace App\Http\Requests;

use App\Models\Jadwal;
use App\Rules\GuruMengampuMapel;
use App\Support\JamPelajaran;
use Illuminate\Contracts\Validation\Validator;
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

    /**
     * Refuse a slot that puts a class, a teacher or a room in two places at once.
     *
     * The database enforces this too — `jadwal_kelas_slot_unique` and friends —
     * but a unique key can only compare the opening period, and it reports a
     * clash as a 1062 error, which reaches the admin as a crash page rather than
     * as "Bu Rina sudah mengajar XI TKJ 1 pada jam itu". So the overlap is
     * checked here, where it can name the lesson in the way, and the keys stay
     * as the last line of defence against a race between two admins.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return; // The values below are only meaningful once they validate.
            }

            $bentrok = [
                'kelas_id' => ['kelas_id', 'Kelas ini sudah ada pelajaran lain pada jam tersebut'],
                'guru_nip' => ['guru_nip', 'Guru ini sudah mengajar di jam tersebut'],
                'ruangan_kode' => ['ruangan_kode', 'Ruangan ini sudah dipakai pada jam tersebut'],
            ];

            foreach ($bentrok as $kolom => [$field, $pesan]) {
                $nilai = $this->input($kolom);

                if ($nilai === null || $nilai === '') {
                    continue; // A slot with no room booked cannot clash over one.
                }

                $lain = $this->slotBerbenturan($kolom, $nilai);

                if ($lain) {
                    $validator->errors()->add($field, sprintf(
                        '%s: %s %s JP %s.',
                        $pesan,
                        $lain->kelas?->nama_kelas ?? '—',
                        $lain->hari,
                        $lain->jpLabel(),
                    ));
                }
            }
        }];
    }

    /**
     * The first timetable row on the same day whose period range overlaps the
     * one being saved, for the given column.
     *
     * Overlap rather than equality: a lesson at JP 1–2 and one at JP 2–3 share
     * a period without sharing a start, which the unique key cannot see.
     */
    private function slotBerbenturan(string $kolom, mixed $nilai): ?Jadwal
    {
        $ini = $this->route('jadwal');

        return Jadwal::with('kelas')
            ->where($kolom, $nilai)
            ->where('hari', $this->input('hari'))
            ->when($ini, fn ($q) => $q->whereKeyNot($ini->id))
            ->where('jam_ke_mulai', '<=', (int) $this->input('jam_ke_selesai'))
            ->where('jam_ke_selesai', '>=', (int) $this->input('jam_ke_mulai'))
            ->first();
    }
}
