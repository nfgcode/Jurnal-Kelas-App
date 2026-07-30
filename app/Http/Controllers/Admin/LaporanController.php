<?php

namespace App\Http\Controllers\Admin;

use App\Support\Halaman;

use App\Http\Controllers\Controller;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\User;
use App\Support\Periode;
use App\Support\Ringkasan;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LaporanController extends Controller
{
    /**
     * School-wide journal report, one row per meeting.
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

        $terisiCount = fn ($query) => $query->withCount([
            'presensis as total_siswa',
            'presensis as hadir_count' => fn ($q) => $q->where('status', 'hadir'),
        ]);

        // Same filtered rows the table shows, as an Excel workbook.
        if ($request->query('ekspor') === 'xlsx') {
            return $this->eksporJurnal($terisiCount($this->kueriJurnal($filters, $periode)), $periode);
        }

        $jurnals = $terisiCount($this->kueriJurnal($filters, $periode))
            ->latest('tanggal')
            ->latest('jurnal.id')
            ->paginate(Halaman::perHalaman())
            ->withQueryString();

        // Completeness measures what was written against what was scheduled over
        // the same period, so "belum diisi" is a gap in the timetable rather than
        // a row in the table. The schedule is counted directly instead of being
        // reverse-engineered from a rounded percentage (which double-rounded
        // "belum" off by a handful of meetings).
        $kelengkapan = Ringkasan::kelengkapan('kelas_id', $periode);
        $rataKelengkapan = $kelengkapan ? round(array_sum($kelengkapan) / count($kelengkapan)) : 0;
        $terjadwal = Ringkasan::terjadwal($periode);
        // Distinct meetings: a lesson may carry a guru and a ketua journal.
        $terisi = Jurnal::hitungPertemuan(Jurnal::whereBetween('tanggal', $rentang));

        return view('admin.laporan.jurnal', [
            'periode' => $periode,
            'jurnals' => $jurnals,
            'kelasList' => Kelas::orderBy('tingkat')->orderBy('nama_kelas')->get(),
            'guruList' => User::where('role', 'guru')->orderBy('name')->get(),
            'kelengkapan' => $kelengkapan,
            'filters' => $filters,
            'statistik' => [
                'terisi' => $terisi,
                'belum' => max(0, $terjadwal - $terisi),
                'telat' => Jurnal::hitungPertemuan(
                    Jurnal::whereBetween('tanggal', $rentang)->whereRaw($this->ekspresiTerlambat())
                ),
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
        $periode = Periode::dari($request);
        $rentang = [$periode->mulaiString(), $periode->selesaiString()];

        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'guru_id' => ['nullable', 'exists:users,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $rekapCount = fn ($query) => $query->withCount([
            'presensis as total_siswa',
            'presensis as hadir_count' => fn ($q) => $q->where('status', 'hadir'),
            'presensis as sakit_count' => fn ($q) => $q->where('status', 'sakit'),
            'presensis as izin_count' => fn ($q) => $q->where('status', 'izin'),
            'presensis as alpa_count' => fn ($q) => $q->where('status', 'alpa'),
        ]);

        if ($request->query('ekspor') === 'xlsx') {
            return $this->eksporPresensi($rekapCount($this->kueriJurnal($filters, $periode)), $periode);
        }

        $pertemuan = $rekapCount($this->kueriJurnal($filters, $periode))
            ->latest('tanggal')
            ->latest('jurnal.id')
            ->paginate(Halaman::perHalaman())
            ->withQueryString();

        $rekap = Ringkasan::presensi(null, $periode);
        $kelasList = Kelas::orderBy('tingkat')->orderBy('nama_kelas')->get();

        return view('admin.laporan.presensi', [
            'periode' => $periode,
            'pertemuan' => $pertemuan,
            'kelasList' => $kelasList,
            'guruList' => User::where('role', 'guru')->orderBy('name')->get(),
            'kehadiranPerKelas' => Ringkasan::presensiPerKelas($periode),
            'filters' => $filters,
            'rekap' => $rekap,
            'total' => array_sum($rekap),
            'totalPertemuan' => Jurnal::hitungPertemuan(Jurnal::whereBetween('tanggal', $rentang)),
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
                $status === 'telat' ? $this->ekspresiTerlambat() : 'NOT ('.$this->ekspresiTerlambat().')'
            ))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->cari($q));
    }

    /**
     * "Telat" is derived rather than stored; the SQL predicate lives on the
     * model so the dashboard drill-down shares one definition.
     */
    private function ekspresiTerlambat(): string
    {
        return Jurnal::ekspresiTerlambat();
    }

    /**
     * The filtered journal report as an .xlsx workbook, one row per meeting.
     * Counts are ints so Excel treats them as numbers, not text.
     */
    private function eksporJurnal($query, Periode $periode)
    {
        $header = ['Tanggal', 'Kelas', 'Mata Pelajaran', 'Guru', 'Materi', 'Tugas', 'Jml Siswa', 'Hadir', 'Status'];

        $rows = function () use ($query) {
            // lazy() chunks and eager-loads the jadwal/kelas relations per chunk
            // (cursor() would lazy-load them one row at a time — N+1).
            foreach ($query->latest('tanggal')->latest('jurnal.id')->lazy() as $j) {
                yield [
                    $j->tanggal->format('Y-m-d'),
                    $j->jadwal?->kelas?->nama_kelas,
                    $j->jadwal?->mataPelajaran?->nama,
                    $j->guru?->name,
                    $j->materi,
                    $j->tugas,
                    (int) $j->total_siswa,
                    (int) $j->hadir_count,
                    $j->statusPengisian()['label'],
                ];
            }
        };

        return XlsxExport::download('rekap-jurnal-'.$this->slugPeriode($periode).'.xlsx', $header, $rows());
    }

    /**
     * The filtered attendance recap as an .xlsx workbook, one row per meeting.
     */
    private function eksporPresensi($query, Periode $periode)
    {
        $header = ['Tanggal', 'Kelas', 'Mata Pelajaran', 'Guru', 'Total', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Persen Hadir'];

        $rows = function () use ($query) {
            foreach ($query->latest('tanggal')->latest('jurnal.id')->lazy() as $j) {
                yield [
                    $j->tanggal->format('Y-m-d'),
                    $j->jadwal?->kelas?->nama_kelas,
                    $j->jadwal?->mataPelajaran?->nama,
                    $j->guru?->name,
                    (int) $j->total_siswa,
                    (int) $j->hadir_count,
                    (int) $j->sakit_count,
                    (int) $j->izin_count,
                    (int) $j->alpa_count,
                    $j->total_siswa ? (int) round($j->hadir_count / $j->total_siswa * 100) : 0,
                ];
            }
        };

        return XlsxExport::download('rekap-presensi-'.$this->slugPeriode($periode).'.xlsx', $header, $rows());
    }

    /** A filename-safe stamp for the export's period. */
    private function slugPeriode(Periode $periode): string
    {
        return Str::slug($periode->mulaiString().'-'.$periode->selesaiString());
    }
}
