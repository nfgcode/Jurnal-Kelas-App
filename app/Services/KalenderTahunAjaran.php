<?php

namespace App\Services;

use App\Models\TahunAjaran;
use App\Support\AuditAdmin;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class KalenderTahunAjaran
{
    public function siapkanBerikutnya(): TahunAjaran
    {
        foreach (['mulai', 'selesai'] as $batas) {
            $pola = config('kalender_akademik.'.$batas);
            if (! is_string($pola) || ! preg_match('/^(\d{2})-(\d{2})$/', $pola, $bagian)
                || ! checkdate((int) $bagian[1], (int) $bagian[2], 2001)) {
                throw ValidationException::withMessages([$batas => 'Pola kalender harus tanggal tahunan valid MM-DD (tanpa 29 Februari).']);
            }
        }

        return \App\Support\AcademicOperation::run(function () {
            // Lock the source; repeated daily/manual calls always target the same successor.
            $asal = TahunAjaran::where('aktif', true)->lockForUpdate()->first();
            $awal = $asal ? (int) substr($asal->kode, 0, 4) + 1 : now()->year;
            if (! $asal) {
                if (now()->format('m-d') < config('kalender_akademik.mulai')) {
                    $awal--;
                }
                // No active source: bootstrap the calendar's current period, never
                // advance through newly prepared inactive rows on repeated calls.
                $asal = TahunAjaran::where('kode', ($awal - 1).'/'.$awal)->lockForUpdate()->first();
            }
            $kode = $awal.'/'.($awal + 1);
            if ($ada = TahunAjaran::find($kode)) {
                return $ada;
            }
            $mulai = $asal?->mulai?->copy()->addYearNoOverflow()->toDateString()
                ?? $awal.'-'.config('kalender_akademik.mulai');
            $selesai = $asal?->selesai?->copy()->addYearNoOverflow()->toDateString()
                ?? ($awal + (config('kalender_akademik.selesai') < config('kalender_akademik.mulai') ? 1 : 0)).'-'.config('kalender_akademik.selesai');
            $this->validasiTanggal($mulai, $selesai);
            // Unique primary key also protects simultaneous bootstrap calls without a source row.
            $tahunBaru = TahunAjaran::firstOrCreate(['kode' => $kode], ['mulai' => $mulai, 'selesai' => $selesai, 'aktif' => false]);
            if ($tahunBaru->wasRecentlyCreated) {
                AuditAdmin::catatKritis('tahun_ajaran.siapkan', 'tahun_ajaran', $kode,
                    "Menyiapkan tahun ajaran nonaktif {$kode}", ['mulai' => $mulai, 'selesai' => $selesai, 'aktif' => false]);
            }

            return $tahunBaru;
        });
    }

    public function validasiTanggal(?string $mulai, ?string $selesai, ?string $kecuali = null): void
    {
        if ((bool) $mulai !== (bool) $selesai) {
            throw ValidationException::withMessages(['selesai' => 'Isi kedua tanggal atau kosongkan keduanya.']);
        }
        if (! $mulai) {
            return;
        }
        if (CarbonImmutable::parse($selesai)->lt(CarbonImmutable::parse($mulai))) {
            throw ValidationException::withMessages(['selesai' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.']);
        }
        if (TahunAjaran::when($kecuali, fn ($q) => $q->where('kode', '!=', $kecuali))
            ->whereDate('mulai', '<=', $selesai)->whereDate('selesai', '>=', $mulai)->exists()) {
            throw ValidationException::withMessages(['mulai' => 'Periode bertumpang tindih dengan tahun ajaran lain.']);
        }
    }
}
