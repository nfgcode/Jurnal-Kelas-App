<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Presensi;
use App\Models\User;
use App\Support\Periode;
use App\Support\Ringkasan;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * School-wide overview: headline counts, the period's fill trend, the
     * attendance and teacher-attendance rollups, and the per-class completeness
     * grid — every flow figure confined to the selected {@see Periode} so its
     * label and its number tell the same story.
     */
    public function index(Request $request)
    {
        $periode = Periode::dari($request);
        $rentang = [$periode->mulaiString(), $periode->selesaiString()];

        $kelasList = Kelas::orderBy('tingkat')->orderBy('nama_kelas')->get();

        // Stock figures (people, classes, subjects, the timetable) are all-time
        // by nature; only the flow figure — journals written — is scoped to the
        // period, its delta measured against the preceding equal-length window.
        // People-written journals only: a nightly backfill placeholder is the
        // absence of the teacher's entry, so counting it here would report the
        // automation's output as teaching activity and hide the shortfall.
        $jurnalPeriode = Jurnal::manusia()->whereBetween('tanggal', $rentang)->count();
        $sebelumnya = $periode->sebelumnya();
        $jurnalSebelumnya = Jurnal::manusia()->whereBetween('tanggal', [$sebelumnya->mulaiString(), $sebelumnya->selesaiString()])->count();
        $trenJurnal = $jurnalSebelumnya > 0
            ? round(($jurnalPeriode - $jurnalSebelumnya) / $jurnalSebelumnya * 100, 1)
            : 0;

        $kpi = [
            'siswa' => User::where('role', 'siswa')->count(),
            'guru' => User::where('role', 'guru')->count(),
            'kelas' => $kelasList->count(),
            'mapel' => MataPelajaran::count(),
            'jadwal' => Jadwal::count(),
            'jurnal' => $jurnalPeriode,
        ];

        // Teachers whose absence in this period was not covered by an assignment
        // are the ones the "perlu perhatian" callout points at. A backfill row
        // also reads "tidak hadir · tanpa tugas", but nobody verified that — it
        // only means the journal is missing, so it must not brand a teacher here.
        $guruPerluPerhatian = Jurnal::manusia()
            ->whereBetween('tanggal', $rentang)
            ->where('kehadiran_guru_status', 'tidak_hadir')
            ->where(fn ($query) => $query->whereNull('kehadiran_guru_ada_tugas')->orWhere('kehadiran_guru_ada_tugas', false))
            ->distinct()
            ->count('guru_id');

        // "Latest journals" stays a recency feed, not a period slice — it is the
        // newest activity whatever the filter says.
        $jurnalTerbaru = Jurnal::with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            ->withCount([
                'presensis as total_presensi',
                'presensis as hadir_count' => fn ($query) => $query->where('status', 'hadir'),
            ])
            ->latest('tanggal')
            ->latest('id')
            ->take(7)
            ->get();

        // Maps that let a heatmap cell drill down: its row label back to a
        // kelas id, and its column label back to the real date. The label rule
        // is shared with Ringkasan::heatmapJurnal so the keys line up exactly.
        $hariSekolah = $periode->hariSekolah();
        $lintasBulan = $hariSekolah !== [] && $hariSekolah[0]->format('Y-m') !== end($hariSekolah)->format('Y-m');
        $petaTanggal = [];
        foreach ($hariSekolah as $tanggal) {
            $petaTanggal[Ringkasan::labelTanggal($tanggal, $lintasBulan)] = $tanggal->toDateString();
        }

        return view('admin.dashboard', [
            'periode' => $periode,
            'petaKelas' => $kelasList->pluck('id', 'nama_kelas')->all(),
            'petaTanggal' => $petaTanggal,
            'kpi' => $kpi,
            'trenJurnal' => $trenJurnal,
            'pengisian' => Ringkasan::harian(Jurnal::manusia(), $periode),
            'presensi' => Ringkasan::presensi(null, $periode),
            'kehadiranGuru' => Ringkasan::kehadiranGuru(null, $periode),
            'guruPerluPerhatian' => $guruPerluPerhatian,
            // "Most active" must credit teachers for what they wrote themselves,
            // never for journals the nightly job filed under their name.
            'guruTeraktif' => User::where('role', 'guru')
                ->withCount(['jurnals' => fn ($query) => $query->manusia()->whereBetween('tanggal', $rentang)])
                ->orderByDesc('jurnals_count')
                ->take(5)
                ->get(),
            'jurnalTerbaru' => $jurnalTerbaru,
            'heatmap' => Ringkasan::heatmapJurnal($kelasList, $periode),
        ]);
    }

    /**
     * The meetings behind a clickable figure, as JSON for the drill-down modal.
     *
     * A bar drills into one day's journals, a "guru teraktif" row into that
     * teacher's journals across the period, and a heatmap cell into one class
     * on one day. The active period rides along on the query string so the
     * detail matches the figure that was clicked.
     */
    public function detail(Request $request)
    {
        $data = $request->validate([
            'tipe' => ['required', 'in:jurnal,guru,kelas,terisi,otomatis,telat,presensi,belum,kelengkapan,guru_perhatian'],
            'tanggal' => ['nullable', 'date'],
            'guru_id' => ['nullable', 'exists:users,id'],
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'tingkat' => ['nullable', 'in:X,XI,XII'],
            'jurusan' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:hadir,sakit,izin,alpa'],
        ]);

        $periode = Periode::dari($request);
        $rentang = [$periode->mulaiString(), $periode->selesaiString()];

        // Some figures drill into something other than the meeting list.
        switch ($data['tipe']) {
            case 'presensi':
                return $this->detailPresensi($data, $periode, $rentang);
            case 'belum':
                return $this->detailBelum($data, $periode);
            case 'kelengkapan':
                return $this->detailKelengkapan($periode, $data);
        }

        $query = Jurnal::query()
            ->with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($q) => $q->where('status', 'hadir'),
            ])
            ->latest('tanggal')
            ->latest('id');

        $judul = 'Detail';
        $meta = $periode->label();

        switch ($data['tipe']) {
            case 'jurnal':
                $tanggal = Carbon::parse($data['tanggal'] ?? $periode->selesaiString());
                $query->whereDate('tanggal', $tanggal->toDateString());
                $judul = 'Jurnal '.$tanggal->translatedFormat('l, j F Y');
                $meta = null;
                break;

            case 'guru':
                $guru = User::findOrFail($data['guru_id']);
                $query->where('guru_id', $guru->id)->whereBetween('tanggal', $rentang);
                $judul = 'Jurnal '.$guru->name;
                break;

            case 'kelas':
                $kelas = Kelas::findOrFail($data['kelas_id']);
                $query->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $kelas->id));

                if (! empty($data['tanggal'])) {
                    $tanggal = Carbon::parse($data['tanggal']);
                    $query->whereDate('tanggal', $tanggal->toDateString());
                    $judul = $kelas->nama_kelas.' · '.$tanggal->translatedFormat('j F Y');
                    $meta = null;
                } else {
                    $query->whereBetween('tanggal', $rentang);
                    $judul = 'Jurnal '.$kelas->nama_kelas;
                }
                break;

            case 'terisi':
                $this->terapkanFilter($query, $data);
                $query->manusia()->whereBetween('tanggal', $rentang);
                $judul = 'Jurnal Diisi Guru';
                break;

            case 'otomatis':
                // The meetings the nightly backfill covered — i.e. the ones whose
                // teacher still has not written their own journal.
                $this->terapkanFilter($query, $data);
                $query->where('jurnal.diisi_oleh_peran', Jurnal::PERAN_SISTEM)
                    ->whereBetween('tanggal', $rentang);
                $judul = 'Jurnal Diisi Otomatis';
                break;

            case 'telat':
                $this->terapkanFilter($query, $data);
                $query->manusia()->whereBetween('tanggal', $rentang)->whereRaw(Jurnal::ekspresiTerlambat());
                $judul = 'Jurnal Terlambat Isi';
                break;

            case 'guru_perhatian':
                // The meetings behind the "perlu perhatian" callout: teachers
                // absent this period without leaving an assignment. Same
                // predicate the dashboard counts, listed meeting-by-meeting.
                $query->where('kehadiran_guru_status', 'tidak_hadir')
                    ->where(fn ($q) => $q->whereNull('kehadiran_guru_ada_tugas')->orWhere('kehadiran_guru_ada_tugas', false))
                    ->whereBetween('tanggal', $rentang);
                $judul = 'Guru Perlu Perhatian';
                break;
        }

        $baris = $query->take(50)->get()->map(fn ($jurnal) => [
            'tanggal' => $jurnal->tanggal->format('d/m/Y'),
            'kelas' => $jurnal->jadwal?->kelas?->nama_kelas,
            'mapel' => $jurnal->jadwal?->mataPelajaran?->nama,
            'guru' => $jurnal->guru?->name,
            // Let the modal link a teacher through to their profile, mirroring
            // x-guru-link elsewhere. Null keeps the cell plain text.
            'guruUrl' => $jurnal->guru ? route('admin.users.show', $jurnal->guru) : null,
            'materi' => $jurnal->materi,
            'guruChip' => $jurnal->kehadiranGuruChip(),
            'statusChip' => $jurnal->statusPengisian(),
            'hadir' => (int) $jurnal->hadir_count,
            'total' => (int) $jurnal->total_siswa,
        ]);

        return response()->json([
            'judul' => $judul,
            'meta' => $meta,
            'tampilan' => 'pertemuan',
            'kosong' => $baris->isEmpty(),
            'baris' => $baris->values(),
        ]);
    }

    /**
     * The attendance rows behind a "Presensi Siswa" segment: one row per
     * student marked with $status over the period, newest meeting first.
     */
    private function detailPresensi(array $data, Periode $periode, array $rentang)
    {
        $status = $data['status'] ?? null;

        $baris = Presensi::query()
            ->join('jurnal', 'presensi.jurnal_id', '=', 'jurnal.id')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->whereBetween('jurnal.tanggal', $rentang)
            ->when($status, fn ($query) => $query->where('presensi.status', $status))
            ->when($data['kelas_id'] ?? null, fn ($query, $id) => $query->where('jadwal.kelas_id', $id))
            // tingkat/jurusan live on kelas, which this builder query doesn't join —
            // narrow by the matching class ids instead.
            ->when($data['tingkat'] ?? null, fn ($query, $t) => $query->whereIn('jadwal.kelas_id', Kelas::where('tingkat', $t)->select('id')))
            ->when($data['jurusan'] ?? null, fn ($query, $j) => $query->whereIn('jadwal.kelas_id', Kelas::where('jurusan', $j)->select('id')))
            ->when($data['guru_id'] ?? null, fn ($query, $id) => $query->where('jurnal.guru_id', $id))
            ->with(['siswa', 'jurnal.jadwal.kelas', 'jurnal.jadwal.mataPelajaran'])
            ->orderByDesc('jurnal.tanggal')
            ->orderByDesc('presensi.id')
            ->select('presensi.*')
            ->take(50)
            ->get()
            ->map(fn ($presensi) => [
                'tanggal' => $presensi->jurnal?->tanggal?->format('d/m/Y'),
                'siswa' => $presensi->siswa?->name,
                'kelas' => $presensi->jurnal?->jadwal?->kelas?->nama_kelas,
                'mapel' => $presensi->jurnal?->jadwal?->mataPelajaran?->nama,
                'keterangan' => $presensi->keterangan,
                'statusChip' => $this->presensiChip($presensi->status),
            ]);

        return response()->json([
            'judul' => 'Presensi Siswa'.($status ? ' · '.ucfirst($status) : ''),
            'meta' => $periode->label(),
            'tampilan' => 'presensi',
            'kosong' => $baris->isEmpty(),
            'baris' => $baris->values(),
        ]);
    }

    /**
     * The chip tone each attendance status wears, matched to the report legend.
     *
     * @return array{label: string, tone: string}
     */
    private function presensiChip(string $status): array
    {
        $tone = ['hadir' => 'green', 'sakit' => 'khaki', 'izin' => 'yellow', 'alpa' => 'red'];

        return ['label' => ucfirst($status), 'tone' => $tone[$status] ?? 'neutral'];
    }

    /**
     * The scheduled meetings the period never got a journal for — the names
     * behind "Belum Diisi". Each school day is checked against the timetable,
     * and any slot without a matching journal is listed, newest day first.
     */
    private function detailBelum(array $data, Periode $periode)
    {
        $jadwalPerHari = Jadwal::query()
            ->with(['kelas', 'mataPelajaran', 'guru'])
            ->when($data['kelas_id'] ?? null, fn ($query, $id) => $query->where('kelas_id', $id))
            ->when($data['tingkat'] ?? null, fn ($query, $t) => $query->whereHas('kelas', fn ($k) => $k->where('tingkat', $t)))
            ->when($data['jurusan'] ?? null, fn ($query, $j) => $query->whereHas('kelas', fn ($k) => $k->where('jurusan', $j)))
            ->when($data['guru_id'] ?? null, fn ($query, $id) => $query->where('guru_id', $id))
            ->get()
            ->groupBy('hari');

        // Journals already written in the period, as a fast (jadwal, date) set —
        // bounded to just the timetable slots in view, so a filtered drill-down
        // doesn't materialize the whole school's journals.
        $jadwalIds = $jadwalPerHari->flatten()->pluck('id');
        $terisi = Jurnal::query()
            ->whereIn('jadwal_id', $jadwalIds)
            ->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()])
            ->get(['jadwal_id', 'tanggal'])
            ->mapWithKeys(fn ($jurnal) => [$jurnal->jadwal_id.'|'.$jurnal->tanggal->toDateString() => true]);

        $baris = [];

        foreach (array_reverse($periode->hariSekolah()) as $tanggal) {
            $hari = Ringkasan::HARI[$tanggal->dayOfWeekIso - 1] ?? null;

            foreach ($jadwalPerHari[$hari] ?? [] as $jadwal) {
                if (isset($terisi[$jadwal->id.'|'.$tanggal->toDateString()])) {
                    continue;
                }

                $baris[] = [
                    'tanggal' => $tanggal->format('d/m/Y'),
                    'kelas' => $jadwal->kelas?->nama_kelas,
                    'mapel' => $jadwal->mataPelajaran?->nama,
                    'guru' => $jadwal->guru?->name,
                ];

                if (count($baris) >= 50) {
                    break 2;
                }
            }
        }

        return response()->json([
            'judul' => 'Belum Diisi',
            'meta' => $periode->label(),
            'tampilan' => 'belum',
            'kosong' => $baris === [],
            'baris' => $baris,
        ]);
    }

    /**
     * Journal completeness per class over the period — the classes behind the
     * "Kelengkapan" figure, worst first so the ones needing attention lead.
     */
    private function detailKelengkapan(Periode $periode, array $data)
    {
        $kelengkapan = Ringkasan::kelengkapan('kelas_id', $periode);

        $baris = Kelas::orderBy('tingkat')->orderBy('nama_kelas')
            ->when($data['tingkat'] ?? null, fn ($q, $t) => $q->where('tingkat', $t))
            ->when($data['jurusan'] ?? null, fn ($q, $j) => $q->where('jurusan', $j))
            ->get()
            ->map(fn ($kelas) => [
                'kelas' => $kelas->nama_kelas,
                'persen' => (int) round($kelengkapan[$kelas->id] ?? 0),
            ])
            ->sortBy('persen')
            ->values();

        return response()->json([
            'judul' => 'Kelengkapan per Kelas',
            'meta' => $periode->label(),
            'tampilan' => 'kelengkapan',
            'kosong' => $baris->isEmpty(),
            'baris' => $baris,
        ]);
    }

    /**
     * Apply the report's own kelas/guru/tingkat/jurusan filters to a journal
     * query, so a card's drill-down shows the same slice the table is showing.
     */
    private function terapkanFilter($query, array $data): void
    {
        $query
            ->when($data['kelas_id'] ?? null, fn ($q, $id) => $q->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $id)))
            ->when($data['tingkat'] ?? null, fn ($q, $t) => $q->whereHas('jadwal.kelas', fn ($k) => $k->where('tingkat', $t)))
            ->when($data['jurusan'] ?? null, fn ($q, $j) => $q->whereHas('jadwal.kelas', fn ($k) => $k->where('jurusan', $j)))
            ->when($data['guru_id'] ?? null, fn ($q, $id) => $q->where('guru_id', $id));
    }
}
