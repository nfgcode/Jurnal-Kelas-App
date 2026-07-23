<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\JurnalResource;
use App\Models\Jurnal;
use App\Support\Periode;
use App\Support\Ringkasan;
use Illuminate\Http\Request;

/**
 * Read-only school-wide reports, mirroring the web Admin\LaporanController but
 * returning JSON (paginated rows + a `meta_laporan` block of aggregates).
 */
class LaporanController extends Controller
{
    /**
     * Journal report, one row per meeting, with completeness statistics.
     */
    public function jurnal(Request $request)
    {
        $periode = Periode::dari($request);
        $rentang = [$periode->mulaiString(), $periode->selesaiString()];

        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'guru_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'in:terisi,telat'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $jurnals = $this->kueriJurnal($filters, $periode)
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($query) => $query->where('status', 'hadir'),
            ])
            ->latest('tanggal')
            ->latest('jurnal.id')
            ->paginate(18);

        $kelengkapan = Ringkasan::kelengkapan('kelas_id', $periode);
        $rataKelengkapan = $kelengkapan ? round(array_sum($kelengkapan) / count($kelengkapan)) : 0;
        $terjadwal = Ringkasan::terjadwal($periode);
        $terisi = Jurnal::whereBetween('tanggal', $rentang)->count();

        return JurnalResource::collection($jurnals)->additional([
            'meta_laporan' => [
                'periode' => ['mulai' => $periode->mulaiString(), 'selesai' => $periode->selesaiString()],
                'statistik' => [
                    'terisi' => $terisi,
                    'belum' => max(0, $terjadwal - $terisi),
                    'telat' => Jurnal::whereBetween('tanggal', $rentang)->whereRaw(Jurnal::ekspresiTerlambat())->count(),
                    'kelengkapan' => $rataKelengkapan,
                ],
            ],
        ]);
    }

    /**
     * Attendance recap, one row per meeting, with the period rollup.
     */
    public function presensi(Request $request)
    {
        $periode = Periode::dari($request);
        $rentang = [$periode->mulaiString(), $periode->selesaiString()];

        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'guru_id' => ['nullable', 'exists:users,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $pertemuan = $this->kueriJurnal($filters, $periode)
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($query) => $query->where('status', 'hadir'),
                'presensis as sakit_count' => fn ($query) => $query->where('status', 'sakit'),
                'presensis as izin_count' => fn ($query) => $query->where('status', 'izin'),
                'presensis as alpa_count' => fn ($query) => $query->where('status', 'alpa'),
            ])
            ->latest('tanggal')
            ->latest('jurnal.id')
            ->paginate(18);

        $rekap = Ringkasan::presensi(null, $periode);

        return JurnalResource::collection($pertemuan)->additional([
            'meta_laporan' => [
                'periode' => ['mulai' => $periode->mulaiString(), 'selesai' => $periode->selesaiString()],
                'rekap' => $rekap,
                'total' => array_sum($rekap),
                'total_pertemuan' => Jurnal::whereBetween('tanggal', $rentang)->count(),
                'kehadiran_per_kelas' => Ringkasan::presensiPerKelas($periode),
            ],
        ]);
    }

    /**
     * Journal rows narrowed by the filters both reports share.
     */
    private function kueriJurnal(array $filters, ?Periode $periode = null)
    {
        return Jurnal::query()
            ->with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            ->when($periode, fn ($query) => $query->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()]))
            ->when($filters['kelas_id'] ?? null, fn ($query, $id) => $query->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $id)))
            ->when($filters['guru_id'] ?? null, fn ($query, $id) => $query->where('guru_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->whereRaw(
                $status === 'telat' ? Jurnal::ekspresiTerlambat() : 'NOT (' . Jurnal::ekspresiTerlambat() . ')'
            ))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(
                fn ($inner) => $inner->where('materi', 'like', "%{$q}%")
                    ->orWhereHas('guru', fn ($g) => $g->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('jadwal.kelas', fn ($k) => $k->where('nama_kelas', 'like', "%{$q}%"))
            ));
    }
}
