<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\PresensiHarian;
use App\Models\User;
use App\Support\Halaman;
use App\Support\Periode;
use App\Support\RekapPresensi;
use App\Support\Ringkasan;
use App\Support\Urutan;
use App\Support\XlsxExport;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Reading student attendance.
 *
 * Attendance is filed once a day per class by the ketua kelas (see
 * {@see PresensiHarianController}); everything here only reads it back — the
 * class-by-day recap a guru or admin works from, a student's own record, and
 * the Excel export a guru downloads per day or per month.
 */
class PresensiController extends Controller
{
    /**
     * A student sees their own attendance; everyone else the class-day recap
     * for the classes they are entitled to see.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // A regular student's own record. A ketua kelas gets the class recap
        // instead — with the button that files today's roll call on it.
        if ($user->isSiswa() && ! $user->isKetuaKelas()) {
            return $this->rekapSiswa($request, $user);
        }

        return $this->rekapKelas($request, $user);
    }

    /**
     * The recap as an .xlsx workbook, per day or per month.
     *
     * Two genuinely different reports rather than one with a different date
     * range: a day is a roll call (one row per student, the status they got),
     * a month is a recap (one row per student, how many days of each) plus the
     * H/S/I/A grid an attendance book keeps.
     */
    public function ekspor(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'mode' => ['nullable', 'in:harian,bulanan'],
            'tanggal' => ['nullable', 'date'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'kelas_id' => ['nullable', 'exists:kelas,id'],
        ]);

        $mode = $data['mode'] ?? 'bulanan';
        $kelasIds = $this->kelasTerbaca($user, $data['kelas_id'] ?? null);

        return $mode === 'harian'
            ? $this->eksporHarian($kelasIds, $data['tanggal'] ?? null)
            : $this->eksporBulanan($kelasIds, $data['bulan'] ?? null);
    }

    /**
     * One sheet, one row per student: the roll call for a single day.
     *
     * @param  array<int>|null  $kelasIds
     */
    private function eksporHarian(?array $kelasIds, ?string $tanggal)
    {
        $hari = Carbon::parse($tanggal ?? today())->toDateString();

        $header = ['Tanggal', 'Kelas', 'NIS', 'Nama Siswa', 'Status', 'Keterangan'];

        $baris = PresensiHarian::query()
            ->join('users', 'presensi_harian.siswa_id', '=', 'users.id')
            ->join('kelas', 'presensi_harian.kelas_id', '=', 'kelas.id')
            ->select([
                'kelas.nama_kelas',
                'users.nis',
                'users.name',
                'presensi_harian.status',
                'presensi_harian.keterangan',
            ])
            ->whereDate('presensi_harian.tanggal', $hari)
            ->when($kelasIds !== null, fn ($q) => $q->whereIn('presensi_harian.kelas_id', $kelasIds))
            ->orderBy('kelas.nama_kelas')
            ->orderBy('users.name');

        $rows = function () use ($baris, $hari) {
            foreach ($baris->lazy() as $row) {
                yield [
                    $hari,
                    $row->nama_kelas,
                    $row->nis,
                    $row->name,
                    ucfirst($row->status),
                    $row->keterangan,
                ];
            }
        };

        return XlsxExport::download('rekap-presensi-harian-'.$hari.'.xlsx', $header, $rows());
    }

    /**
     * Two sheets: the per-student recap, and the day-by-day H/S/I/A grid behind
     * it, so a disputed total can be traced to the days that produced it.
     *
     * @param  array<int>|null  $kelasIds
     */
    private function eksporBulanan(?array $kelasIds, ?string $bulan)
    {
        $awal = Carbon::parse(($bulan ?? now()->format('Y-m')).'-01')->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();
        $mulai = $awal->toDateString();
        $selesai = $akhir->toDateString();

        $siswa = RekapPresensi::perSiswa($kelasIds, $mulai, $selesai)->get();

        $rekapHeader = ['Kelas', 'NIS', 'Nama Siswa', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Total Hari', 'Persen Hadir'];

        $rekapRows = $siswa->map(fn ($r) => [
            $r->nama_kelas,
            $r->nis,
            $r->nama,
            (int) $r->hadir,
            (int) $r->sakit,
            (int) $r->izin,
            (int) $r->alpa,
            (int) $r->total_hari,
            (int) Ringkasan::persen((int) $r->hadir, (int) $r->total_hari),
        ])->all();

        // The grid: a column per calendar day of the month, H/S/I/A per cell.
        $tanggalList = [];

        for ($t = $awal->copy(); $t->lte($akhir); $t->addDay()) {
            $tanggalList[] = $t->copy();
        }

        $matriks = RekapPresensi::matriksHarian($kelasIds, $mulai, $selesai);

        $gridHeader = array_merge(
            ['Kelas', 'NIS', 'Nama Siswa'],
            array_map(fn (Carbon $t) => $t->format('j'), $tanggalList),
            ['H', 'S', 'I', 'A']
        );

        $gridRows = $siswa->map(function ($r) use ($tanggalList, $matriks) {
            $harian = $matriks[$r->siswa_id] ?? [];

            return array_merge(
                [$r->nama_kelas, $r->nis, $r->nama],
                array_map(fn (Carbon $t) => RekapPresensi::huruf($harian[$t->toDateString()] ?? null), $tanggalList),
                [(int) $r->hadir, (int) $r->sakit, (int) $r->izin, (int) $r->alpa]
            );
        })->all();

        return XlsxExport::downloadWorkbook(
            'rekap-presensi-bulanan-'.$awal->format('Y-m').'.xlsx',
            [
                'Rekap '.$awal->translatedFormat('F Y') => ['header' => $rekapHeader, 'rows' => $rekapRows],
                'Detail Harian' => ['header' => $gridHeader, 'rows' => $gridRows],
            ]
        );
    }

    /**
     * The class ids $user may read attendance for, or null for "all" (admin),
     * optionally narrowed to the one class they asked for.
     *
     * @return array<int>|null
     */
    private function kelasTerbaca(User $user, int|string|null $pilih = null): ?array
    {
        $ids = $this->kelasTerjangkau($user);

        if ($pilih === null) {
            return $ids;
        }

        $pilih = (int) $pilih;

        // A class they cannot reach narrows to nothing rather than widening to
        // everything — an unreachable id must never fall back to "all classes".
        return $ids === null || in_array($pilih, $ids, true) ? [$pilih] : [];
    }

    /**
     * The classes whose attendance $user may read, or null for "all" (admin).
     * A guru reaches the classes they teach plus any they are wali of; a
     * student only their own.
     *
     * @return array<int>|null
     */
    private function kelasTerjangkau(User $user): ?array
    {
        if ($user->isAdmin()) {
            return null;
        }

        if ($user->isSiswa()) {
            return array_values(array_filter([$user->kelas_id]));
        }

        return Jadwal::where('guru_id', $user->id)->pluck('kelas_id')
            ->merge(Kelas::where('wali_kelas_id', $user->id)->pluck('id'))
            ->unique()->values()->all();
    }

    /**
     * The student's own attendance: the totals, and the day-by-day history
     * behind them.
     */
    private function rekapSiswa(Request $request, User $user)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:hadir,sakit,izin,alpa'],
        ]);

        $periode = Periode::dari($request);

        $riwayat = PresensiHarian::with('kelas')
            ->where('siswa_id', $user->id)
            ->dalamPeriode($periode->mulaiString(), $periode->selesaiString())
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('tanggal')
            ->paginate(Halaman::perHalaman())
            ->withQueryString();

        return view('presensi.rekap-saya', [
            'periode' => $periode,
            'riwayat' => $riwayat,
            'rekap' => Ringkasan::presensi(
                PresensiHarian::where('siswa_id', $user->id), $periode
            ),
            'filters' => $filters,
        ]);
    }

    /**
     * One row per class-day: how the class was marked that day.
     */
    private function rekapKelas(Request $request, User $user)
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'tingkat' => ['nullable', 'in:X,XI,XII'],
            'jurusan' => ['nullable', 'string', 'max:100'],
        ]);

        $periode = Periode::dari($request);
        $kelasIds = $this->kelasTerjangkau($user);

        $terfilter = fn () => RekapPresensi::perKelasHari($kelasIds, $periode->mulaiString(), $periode->selesaiString())
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->where('presensi_harian.kelas_id', $id))
            ->when($filters['tingkat'] ?? null, fn ($q, $t) => $q->where('kelas.tingkat', $t))
            ->when($filters['jurusan'] ?? null, fn ($q, $j) => $q->where('kelas.jurusan', $j));

        $baris = $terfilter();

        Urutan::terapkan($baris, $request, PresensiHarian::petaUrutan(), fn ($q) => $q
            ->orderByDesc('presensi_harian.tanggal')->orderBy('kelas.nama_kelas'));

        $baris = $baris->paginate(Halaman::perHalaman())->withQueryString();

        $kelasList = Kelas::query()
            ->when($kelasIds !== null, fn ($q) => $q->whereIn('id', $kelasIds))
            ->orderBy('nama_kelas')->get();

        // A ketua kelas opens this screen to answer one question — "have I filed
        // today?" — so the answer is computed here rather than left to a click.
        $kelasKetua = $user->isKetuaKelas() ? $kelasList->firstWhere('id', $user->kelas_id) : null;

        return view('presensi.index', [
            'baris' => $baris,
            'periode' => $periode,
            'kelasList' => $kelasList,
            'filters' => $filters,
            'rekap' => Ringkasan::presensi(
                PresensiHarian::query()->when($kelasIds !== null, fn ($q) => $q->whereIn('kelas_id', $kelasIds)),
                $periode
            ),
            'kelasKetua' => $kelasKetua,
            'sudahIsiHariIni' => $kelasKetua
                ? PresensiHarian::sudahDiisi($kelasKetua->id, today()->toDateString())
                : false,
            // Class-days on record in this period — the honest denominator for
            // "how much of the period has been filed". The paginator's own total
            // would move with the class filter; this one is a stable reference.
            'totalHari' => $baris->total(),
            'bolehEkspor' => $user->isGuru() || $user->isAdmin(),
        ]);
    }
}
