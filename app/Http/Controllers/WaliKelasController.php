<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Presensi;
use App\Support\Halaman;
use App\Support\Ringkasan;
use App\Support\Urutan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * "Mode Wali Kelas" — the same screens a guru works with, narrowed to the one
 * class they are homeroom teacher of: its roster, its timetable, its journals
 * and its attendance. Everything outside that class is deliberately absent.
 *
 * Every action resolves the same context through {@see konteks()}, which also
 * enforces that the signed-in user really is the wali of the class shown.
 */
class WaliKelasController extends Controller
{
    /**
     * Overview of the homeroom class: KPIs, roster with attendance, recent journals.
     */
    public function index(Request $request)
    {
        [$kelasWali, $kelas] = $this->konteks($request);

        $siswa = $kelas->siswa()->orderBy('name')->get();
        $rekapSiswa = $this->rekapPerSiswa($siswa->pluck('id')->all());

        $presensiKelas = $this->presensiKelas($kelas);
        $kelengkapan = Ringkasan::kelengkapanKelas($kelas->id);
        $totalPresensi = array_sum($presensiKelas) ?: 1;

        return view('wali-kelas.index', [
            'kelasWali' => $kelasWali,
            'kelas' => $kelas,
            'siswa' => $siswa,
            'rekapSiswa' => $rekapSiswa,
            'kpi' => [
                'jumlah_siswa' => $siswa->count(),
                'rata_kehadiran' => round($presensiKelas['hadir'] / $totalPresensi * 100),
                'kelengkapan' => $kelengkapan,
                'alpa' => $presensiKelas['alpa'],
            ],
            'jurnalTerakhir' => $this->jurnalKelas($kelas)
                ->with(['jadwal.mataPelajaran', 'guru'])
                ->latest('tanggal')
                ->latest('id')
                ->take(5)
                ->get(),
        ]);
    }

    /**
     * The class itself: its particulars and the full roll with attendance.
     */
    public function siswa(Request $request)
    {
        [$kelasWali, $kelas] = $this->konteks($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $siswa = $kelas->siswa()
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->cari($q))
            ->orderBy('name')
            ->get();

        return view('wali-kelas.siswa', [
            'kelasWali' => $kelasWali,
            'kelas' => $kelas->loadCount(['siswa', 'jadwals'])->load('waliKelas'),
            'siswa' => $siswa,
            'rekapSiswa' => $this->rekapPerSiswa($siswa->pluck('id')->all()),
            'filters' => $filters,
            'jumlahKetua' => $siswa->where('is_ketua_kelas', true)->count(),
        ]);
    }

    /**
     * The class timetable as the weekly matrix, same layout as the guru view.
     */
    public function jadwal(Request $request)
    {
        [$kelasWali, $kelas] = $this->konteks($request);

        $jadwals = $kelas->jadwals()->with(['mataPelajaran', 'guru'])->get();

        // Index by day and starting period so the matrix can place each block.
        $matriks = [];
        foreach ($jadwals as $jadwal) {
            $matriks[$jadwal->hari][$jadwal->jam_ke_mulai] = $jadwal;
        }

        return view('wali-kelas.jadwal', [
            'kelasWali' => $kelasWali,
            'kelas' => $kelas,
            'matriks' => $matriks,
            'statistik' => [
                'totalJadwal' => $jadwals->count(),
                'jp' => $jadwals->sum(fn ($j) => $j->jam_ke_selesai - $j->jam_ke_mulai + 1),
                'guru' => $jadwals->pluck('guru_id')->unique()->count(),
                'mapel' => $jadwals->pluck('mata_pelajaran_id')->unique()->count(),
            ],
        ]);
    }

    /**
     * Every journal written for this class, whoever taught the lesson.
     */
    public function jurnal(Request $request)
    {
        [$kelasWali, $kelas] = $this->konteks($request);

        $filters = $request->validate([
            'mata_pelajaran_id' => ['nullable', 'exists:mata_pelajaran,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $jurnals = $this->jurnalKelas($kelas)
            ->with(['jadwal.mataPelajaran', 'guru'])
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($q) => $q->where('status', 'hadir'),
            ])
            ->when($filters['mata_pelajaran_id'] ?? null, fn ($q, $id) => $q->whereHas('jadwal', fn ($j) => $j->where('mata_pelajaran_id', $id)))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari));

        $peta = array_intersect_key(Jurnal::petaUrutan(), array_flip(['tanggal', 'mapel', 'guru', 'hadir', 'status']));
        Urutan::terapkan($jurnals, $request, $peta, fn ($q) => $q->latest('tanggal')->latest('id'));

        $jurnals = $jurnals->paginate(Halaman::perHalaman())->withQueryString();

        // Total and this-month count folded into one scan; SUM(BETWEEN) yields
        // 0/1 per row on both MySQL and SQLite.
        $agregat = $this->jurnalKelas($kelas)
            ->selectRaw('COUNT(*) as total, SUM(tanggal BETWEEN ? AND ?) as bulan_ini', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ])
            ->first();

        return view('wali-kelas.jurnal', [
            'kelasWali' => $kelasWali,
            'kelas' => $kelas,
            'jurnals' => $jurnals,
            'mapelList' => $this->mapelKelas($kelas),
            'filters' => $filters,
            'statistik' => [
                'total' => (int) $agregat->total,
                'bulanIni' => (int) $agregat->bulan_ini,
                'kelengkapan' => Ringkasan::kelengkapanKelas($kelas->id),
                'kehadiran' => Ringkasan::kehadiranGuru($this->jurnalKelas($kelas)),
            ],
        ]);
    }

    /**
     * Attendance for the class two ways: the standing per-student recap a wali
     * actually acts on, and the meeting-by-meeting history behind it.
     */
    public function presensi(Request $request)
    {
        [$kelasWali, $kelas] = $this->konteks($request);

        $filters = $request->validate([
            'mata_pelajaran_id' => ['nullable', 'exists:mata_pelajaran,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $pertemuan = $this->jurnalKelas($kelas)
            ->with(['jadwal.mataPelajaran', 'guru'])
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($q) => $q->where('status', 'hadir'),
                'presensis as sakit_count' => fn ($q) => $q->where('status', 'sakit'),
                'presensis as izin_count' => fn ($q) => $q->where('status', 'izin'),
                'presensis as alpa_count' => fn ($q) => $q->where('status', 'alpa'),
            ])
            ->when($filters['mata_pelajaran_id'] ?? null, fn ($q, $id) => $q->whereHas('jadwal', fn ($j) => $j->where('mata_pelajaran_id', $id)))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari));

        $peta = array_intersect_key(Jurnal::petaUrutan(), array_flip(['tanggal', 'mapel', 'guru', 'siswa', 'hadir']));
        Urutan::terapkan($pertemuan, $request, $peta, fn ($q) => $q->latest('tanggal')->latest('id'));

        $pertemuan = $pertemuan->paginate(Halaman::perHalaman())->withQueryString();

        $siswa = $kelas->siswa()->orderBy('name')->get();

        return view('wali-kelas.presensi', [
            'kelasWali' => $kelasWali,
            'kelas' => $kelas,
            'pertemuan' => $pertemuan,
            'siswa' => $siswa,
            'rekapSiswa' => $this->rekapPerSiswa($siswa->pluck('id')->all()),
            'rekap' => $this->presensiKelas($kelas),
            'mapelList' => $this->mapelKelas($kelas),
            'filters' => $filters,
            // With no filter active the paginator's own total is the same
            // number — no need for a second COUNT over the class.
            'totalPertemuan' => ($filters['mata_pelajaran_id'] ?? null) || ($filters['q'] ?? null)
                ? $this->jurnalKelas($kelas)->count()
                : $pertemuan->total(),
        ]);
    }

    /**
     * The classes this teacher is wali of, and which one the screen is about.
     * A teacher who chairs no class has no business here at all; asking for a
     * class they don't chair silently falls back to their first.
     *
     * @return array{0: Collection<int, Kelas>, 1: Kelas}
     */
    private function konteks(Request $request): array
    {
        $kelasWali = Kelas::where('wali_kelas_id', $request->user()->id)
            ->orderBy('nama_kelas')
            ->get();

        abort_if($kelasWali->isEmpty(), 403, 'Anda bukan wali kelas mana pun.');

        $kelas = $kelasWali->firstWhere('id', $request->integer('kelas_id')) ?? $kelasWali->first();

        return [$kelasWali, $kelas];
    }

    /**
     * Journals belonging to this class, whoever taught the lesson — the base
     * every journal and attendance figure on these screens is drawn from.
     */
    private function jurnalKelas(Kelas $kelas)
    {
        return Jurnal::untukKelas($kelas->id);
    }

    /**
     * Attendance totals across every meeting of this class. Explicit joins so
     * the optimizer drives from the class's few dozen jadwal rows instead of
     * scanning all ~110k presensi rows through a nested EXISTS.
     *
     * @return array<string, int>
     */
    private function presensiKelas(Kelas $kelas): array
    {
        return Ringkasan::presensi(
            Presensi::query()
                ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
                ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
                ->where('jadwal.kelas_id', $kelas->id)
        );
    }

    /**
     * Only the subjects actually taught to this class, so the filter never
     * offers one that cannot match.
     */
    private function mapelKelas(Kelas $kelas)
    {
        return MataPelajaran::whereHas('jadwals', fn ($q) => $q->where('kelas_id', $kelas->id))
            ->orderBy('nama')
            ->get();
    }

    /**
     * Per-student attendance counts plus the derived percentage, keyed by
     * student id. Same formula as fn_persentase_kehadiran_siswa, so the figures
     * agree with the API and the stored function.
     *
     * @param  array<int>  $siswaIds
     * @return array<int, array{hadir: int, sakit: int, izin: int, alpa: int, total: int, persen: float}>
     */
    private function rekapPerSiswa(array $siswaIds): array
    {
        if ($siswaIds === []) {
            return [];
        }

        return Presensi::whereIn('siswa_id', $siswaIds)
            ->selectRaw('siswa_id, status, COUNT(*) as total')
            ->groupBy('siswa_id', 'status')
            ->get()
            ->groupBy('siswa_id')
            ->map(function ($rows) {
                $hitung = $rows->pluck('total', 'status');

                $rekap = [
                    'hadir' => (int) ($hitung['hadir'] ?? 0),
                    'sakit' => (int) ($hitung['sakit'] ?? 0),
                    'izin' => (int) ($hitung['izin'] ?? 0),
                    'alpa' => (int) ($hitung['alpa'] ?? 0),
                ];

                $rekap['total'] = array_sum($rekap);
                $rekap['persen'] = $rekap['total'] ? round($rekap['hadir'] / $rekap['total'] * 100, 2) : 0.0;

                return $rekap;
            })
            ->all();
    }
}
