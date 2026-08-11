<?php

namespace App\Http\Requests\Api;

use App\Models\Jurnal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The API accepts the journal's stored columns directly (rather than the web
 * form's role-specific vocabulary). Ownership is authorized in the controller
 * via JurnalPolicy, so authorize() defers to it.
 */
class JurnalRequest extends FormRequest
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
        $aturan = [
            'jadwal_id' => 'required|exists:jadwal,id',
            'tanggal' => 'required|date',
            'materi' => 'required|string',
            'tugas' => 'nullable|string',
            'kegiatan' => 'nullable|string',
            'catatan' => 'nullable|string',
            'kehadiran_guru_status' => 'nullable|in:hadir,tidak_hadir',
            'kehadiran_guru_alasan' => 'nullable|in:sakit,izin,alpa',
            'kehadiran_guru_ada_tugas' => 'nullable|boolean',
            'kehadiran_guru_keterangan' => 'nullable|string',
        ];

        // Correcting an auto-filled journal can turn a system-recorded absence
        // into a presence, so the API demands the same honesty attestation the
        // web form does. Only on update: a store has no journal to correct.
        $jurnal = $this->route('jurnal');

        if ($jurnal instanceof Jurnal && $jurnal->dibuatSistem()) {
            $aturan['pernyataan'] = ['accepted'];
        }

        return $aturan;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pernyataan.accepted' => 'Jurnal ini dibuat otomatis oleh sistem. Kirim "pernyataan": true untuk menyatakan perubahan dilakukan dengan jujur sesuai keadaan sebenarnya.',
        ];
    }
}
