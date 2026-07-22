<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\User;
use App\Support\Ringkasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    /**
     * School-wide journal report, one row per meeting.
     */
    public function jurnal(Request $request)
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'guru_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'in:terisi,telat'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $jurnals = $this->kueriJurnal($filters)
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($query) => $query->where('status', 'hadir'),
            ])
            ->latest('tanggal')
            ->latest('jurnal.id')
            ->paginate(18)
            ->withQueryString();

        // Completeness measures what was written against what was scheduled, so
        // "belum diisi" is a gap in the timetable rather than a row in the table.
        $kelengkapan = Ringkasan::kelengkapan('kelas_id');
        $rataKelengkapan = $kelengkapan ? round(array_sum($kelengkapan) / count($kelengkapan)) : 0;
        $terisi = Jurnal::count();
        $terjadwal = $rataKelengkapan > 0 ? (int) round($terisi / ($rataKelengkapan / 100)) : 0;

        return view('admin.laporan.jurnal', [
            'jurnals' => $jurnals,
            'kelasList' => Kelas::orderBy('nama_kelas')->get(),
            'guruList' => User::where('role', 'guru')->orderBy('name')->get(),
            'filters' => $filters,
            'statistik' => [
                'terisi' => $terisi,
                'belum' => max(0, $terjadwal - $terisi),
                'telat' => Jurnal::whereRaw($this->ekspresiTerlambat())->count(),
                'kelengkapan' => $rataKelengkapan,
            ],
        ]);
    }

    /**
     * Attendance recap, one row per meeting rather than per student — the view
     * that lets a supervisor spot a bad session at a glance.
     */
    public function presensi(Request $request)
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'guru_id' => ['nullable', 'exists:users,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $pertemuan = $this->kueriJurnal($filters)
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($query) => $query->where('status', 'hadir'),
                'presensis as sakit_count' => fn ($query) => $query->where('status', 'sakit'),
                'presensis as izin_count' => fn ($query) => $query->where('status', 'izin'),
                'presensis as alpa_count' => fn ($query) => $query->where('status', 'alpa'),
            ])
            ->latest('tanggal')
            ->latest('jurnal.id')
            ->paginate(18)
            ->withQueryString();

        $rekap = Ringkasan::presensi();

        return view('admin.laporan.presensi', [
            'pertemuan' => $pertemuan,
            'kelasList' => Kelas::orderBy('nama_kelas')->get(),
            'guruList' => User::where('role', 'guru')->orderBy('name')->get(),
            'filters' => $filters,
            'rekap' => $rekap,
            'total' => array_sum($rekap),
            'totalPertemuan' => Jurnal::count(),
        ]);
    }

    /**
     * Journal rows narrowed by the filters both reports share.
     */
    private function kueriJurnal(array $filters)
    {
        return Jurnal::query()
            ->with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            ->when($filters['kelas_id'] ?? null, fn ($query, $id) => $query->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $id)))
            ->when($filters['guru_id'] ?? null, fn ($query, $id) => $query->where('guru_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->whereRaw(
                $status === 'telat' ? $this->ekspresiTerlambat() : 'NOT (' . $this->ekspresiTerlambat() . ')'
            ))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(
                fn ($inner) => $inner->where('materi', 'like', "%{$q}%")
                    ->orWhereHas('guru', fn ($g) => $g->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('jadwal.kelas', fn ($k) => $k->where('nama_kelas', 'like', "%{$q}%"))
            ));
    }

    /**
     * "Telat" is derived rather than stored: a journal filed more than a day
     * after the lesson it describes. Date arithmetic has no portable syntax,
     * so the expression is chosen per driver (MySQL in production, SQLite in
     * the test suite).
     */
    private function ekspresiTerlambat(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "jurnal.created_at > datetime(jurnal.tanggal, '+2 day')"
            : 'jurnal.created_at > DATE_ADD(jurnal.tanggal, INTERVAL 2 DAY)';
    }
}
