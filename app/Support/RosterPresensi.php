<?php

namespace App\Support;

use App\Models\Jurnal;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Collection;

/** Resolve evidence for this date; a current class is not historical evidence. */
class RosterPresensi
{
    public static function untukJurnal(Jurnal $jurnal): Collection
    {
        $kelas = $jurnal->jadwal->kelas;
        $tanggal = $jurnal->tanggal->toDateString();
        $dated = RiwayatKelasSiswa::where('kelas_id', $kelas->id)
            ->whereNotNull('tanggal_mulai')->whereDate('tanggal_mulai', '<=', $tanggal)
            ->where(fn ($q) => $q->whereDate('tanggal_selesai', '>=', $tanggal)
                ->orWhere(fn ($open) => $open->whereNull('tanggal_selesai')->where('aktif', true)))
            ->select('siswa_nis');

        return Siswa::where(function ($q) use ($jurnal, $kelas, $tanggal, $dated) {
            $q->whereIn('nis', $dated)
                ->orWhereIn('nis', $jurnal->presensis()->select('siswa_nis'));
            if ($tanggal === today()->toDateString() && $kelas->tahunAjaran?->aktif) {
                $q->orWhere(fn ($current) => $current->where('kelas_id', $kelas->id)->where('status', 'aktif')
                    ->whereDoesntHave('riwayatKelas'));
            }
        })->orderBy('nama')->get();
    }
}
