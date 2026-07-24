<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Presensi;
use App\Models\User;
use App\Support\Ringkasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class JurnalController extends Controller
{
    /**
     * The journal list, scoped and laid out per role: a teacher sees the
     * journals they wrote, a student sees their class's.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['nullable', 'exists:mata_pelajaran,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Jurnal::query()
            ->with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($q) => $q->where('status', 'hadir'),
            ])
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $id)))
            ->when($filters['mata_pelajaran_id'] ?? null, fn ($q, $id) => $q->whereHas('jadwal', fn ($j) => $j->where('mata_pelajaran_id', $id)))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari))
            ->latest('tanggal')
            ->latest('id');

        if ($user->isSiswa()) {
            return $this->riwayatSiswa($user, $query, $filters);
        }

        if ($user->isGuru()) {
            $query->where('guru_id', $user->id);
        }

        $jurnals = $query->paginate(18)->withQueryString();
        $milikSaya = $user->isGuru() ? Jurnal::where('guru_id', $user->id) : Jurnal::query();

        // Total and this-month count in one scan (SUM(BETWEEN) is 0/1 per row
        // on both MySQL and SQLite) instead of two separate COUNTs.
        $agregat = (clone $milikSaya)
            ->selectRaw('COUNT(*) as total, SUM(tanggal BETWEEN ? AND ?) as bulan_ini', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ])
            ->first();

        return view('jurnal.histori', [
            'jurnals' => $jurnals,
            // A guru filters only among the classes/subjects they teach; admin all.
            'kelasList' => Kelas::query()
                ->when($user->isGuru(), fn ($q) => $q->whereIn('id', Jadwal::where('guru_id', $user->id)->select('kelas_id')))
                ->orderBy('nama_kelas')->get(),
            'mapelList' => MataPelajaran::query()
                ->when($user->isGuru(), fn ($q) => $q->whereHas('jadwals', fn ($j) => $j->where('guru_id', $user->id)))
                ->orderBy('nama')->get(),
            'filters' => $filters,
            'statistik' => [
                'total' => (int) $agregat->total,
                'bulanIni' => (int) $agregat->bulan_ini,
                'kelas' => $user->isGuru()
                    ? Jadwal::where('guru_id', $user->id)->distinct()->count('kelas_id')
                    : Kelas::count(),
                'kehadiran' => Ringkasan::kehadiranGuru(clone $milikSaya),
            ],
        ]);
    }

    /**
     * The same records seen from the student's side: whose lesson it was, and
     * whether the student themself was present.
     */
    private function riwayatSiswa(User $user, $query, array $filters)
    {
        $kelas = $user->kelas;

        // A student with no class sees nothing — never every class's journals.
        // (Deleting a rombel NULLs its students' kelas_id.)
        if (! $kelas) {
            $jurnals = $query->whereRaw('1 = 0')->paginate(18)->withQueryString();

            return view('jurnal.riwayat', [
                'jurnals' => $jurnals,
                'presensiSaya' => collect(),
                'kelas' => null,
                'mapelList' => MataPelajaran::orderBy('nama')->get(),
                'filters' => $filters,
                'statistik' => ['total' => 0, 'tugas' => 0, 'kelengkapan' => 0],
            ]);
        }

        $jurnals = $query
            ->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $kelas->id))
            ->paginate(18)
            ->withQueryString();

        // The student's own attendance for the rows on this page.
        $presensiSaya = Presensi::where('siswa_id', $user->id)
            ->whereIn('jurnal_id', $jurnals->pluck('id'))
            ->get()
            ->keyBy('jurnal_id');

        // Total and how many carried a task, in one grouped pass over the class.
        $agregat = Jurnal::whereHas('jadwal', fn ($j) => $j->where('kelas_id', $kelas->id))
            ->selectRaw('COUNT(*) as total, COUNT(tugas) as tugas')
            ->first();

        return view('jurnal.riwayat', [
            'jurnals' => $jurnals,
            'presensiSaya' => $presensiSaya,
            'kelas' => $kelas,
            'mapelList' => MataPelajaran::orderBy('nama')->get(),
            'filters' => $filters,
            'statistik' => [
                'total' => (int) $agregat->total,
                'tugas' => (int) $agregat->tugas,
                'kelengkapan' => Ringkasan::kelengkapanKelas($kelas->id),
            ],
        ]);
    }

    /**
     * The journal form. A teacher reports their own attendance; a ketua kelas
     * reports the teacher's, which is why the two get different forms.
     */
    public function create(Request $request)
    {
        Gate::authorize('create', Jurnal::class);

        $user = $request->user();
        $jadwal = $this->jadwalTerpilih($request, $user);

        return view($user->isSiswa() ? 'jurnal.mengisi' : 'jurnal.isi', $this->konteksForm($user, $jadwal));
    }

    /**
     * Store a newly created jurnal entry.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Jurnal::class);

        $user = $request->user();
        $validated = $request->validate($this->rules($user));

        $jadwal = Jadwal::findOrFail($validated['jadwal_id']);
        $this->pastikanJadwalMilik($user, $jadwal);

        $data = $this->normalize($validated, $user);
        $data['guru_id'] = $user->isGuru() ? $user->id : $jadwal->guru_id;
        $data['diisi_oleh_id'] = $user->id;

        $jurnal = Jurnal::create($data);

        return redirect()->route('presensi.create', $jurnal->id)
            ->with('success', 'Jurnal tersimpan. Lengkapi presensi siswa berikut ini.');
    }

    /**
     * Display the specified jurnal entry.
     */
    public function show(Jurnal $jurnal)
    {
        Gate::authorize('view', $jurnal);

        $jurnal->load(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru', 'diisiOleh', 'presensis.siswa']);

        return view('jurnal.show', compact('jurnal'));
    }

    /**
     * Show the form for editing the specified jurnal entry.
     */
    public function edit(Request $request, Jurnal $jurnal)
    {
        Gate::authorize('update', $jurnal);

        $user = $request->user();

        return view(
            $user->isSiswa() ? 'jurnal.mengisi' : 'jurnal.isi',
            $this->konteksForm($user, $jurnal->jadwal, $jurnal)
        );
    }

    /**
     * Update the specified jurnal entry in storage.
     */
    public function update(Request $request, Jurnal $jurnal)
    {
        Gate::authorize('update', $jurnal);

        $user = $request->user();
        $validated = $request->validate($this->rules($user));

        // jadwal_id is editable, so re-check the caller may write against it —
        // otherwise an update could move the journal onto another class/teacher.
        $this->pastikanJadwalMilik($user, Jadwal::findOrFail($validated['jadwal_id']));

        $jurnal->update($this->normalize($validated, $user));

        return redirect()->route('jurnal.index')->with('success', 'Jurnal berhasil diperbarui.');
    }

    /**
     * Remove the specified jurnal entry from storage.
     */
    public function destroy(Jurnal $jurnal)
    {
        Gate::authorize('delete', $jurnal);

        $jurnal->delete();

        return redirect()->route('jurnal.index')->with('success', 'Jurnal berhasil dihapus.');
    }

    /**
     * The schedule a journal is written against must belong to the author: a
     * guru writes on their own timetable slot, a ketua kelas on their own
     * class's. An admin may write anywhere. Without this, a crafted jadwal_id
     * forges a journal into another class attributed to its teacher.
     */
    private function pastikanJadwalMilik(User $user, Jadwal $jadwal): void
    {
        abort_if($user->isGuru() && $jadwal->guru_id !== $user->id, 403,
            'Jadwal tersebut bukan jadwal mengajar Anda.');

        abort_if($user->isSiswa() && $jadwal->kelas_id !== $user->kelas_id, 403,
            'Jadwal tersebut bukan milik kelas Anda.');
    }

    /**
     * Which meeting the form is about: an explicit choice, otherwise the next
     * slot on today's timetable that has no journal yet.
     */
    private function jadwalTerpilih(Request $request, User $user): ?Jadwal
    {
        $kandidat = Jadwal::with(['kelas', 'mataPelajaran', 'guru'])
            ->when($user->isGuru(), fn ($query) => $query->where('guru_id', $user->id))
            ->when($user->isSiswa() && $user->kelas_id, fn ($query) => $query->where('kelas_id', $user->kelas_id));

        if ($id = $request->integer('jadwal_id')) {
            return (clone $kandidat)->find($id) ?? $kandidat->first();
        }

        $hariIni = (clone $kandidat)->where('hari', Ringkasan::hariIni())->orderBy('jam_ke_mulai')->get();
        $sudahAda = Jurnal::whereIn('jadwal_id', $hariIni->pluck('id'))
            ->whereDate('tanggal', today())
            ->pluck('jadwal_id');

        return $hariIni->firstWhere(fn ($j) => ! $sudahAda->contains($j->id))
            ?? $hariIni->first()
            ?? $kandidat->first();
    }

    /**
     * Everything both journal forms render around the chosen meeting.
     */
    private function konteksForm(User $user, ?Jadwal $jadwal, ?Jurnal $jurnal = null): array
    {
        $kelas = $jadwal?->kelas;
        $jumlahSiswa = $kelas ? $kelas->siswa()->count() : 0;

        $presensi = $jurnal
            ? Ringkasan::presensi(Presensi::where('jurnal_id', $jurnal->id))
            : ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0];

        // The teacher's own attendance record, or the class's view of their
        // teachers' — whichever the form's author is reporting on. Scoped to the
        // current month, matching the card's "Bulan Ini" label.
        $bulanIni = fn ($query) => $query
            ->whereMonth('tanggal', now()->month)
            ->whereYear('tanggal', now()->year);

        $rekapKehadiran = $user->isGuru()
            ? Ringkasan::kehadiranGuru($bulanIni(Jurnal::where('guru_id', $user->id)))
            : Ringkasan::kehadiranGuru(
                $bulanIni(Jurnal::whereHas('jadwal', fn ($q) => $q->where('kelas_id', $user->kelas_id)))
            );

        return [
            'jurnal' => $jurnal,
            'jadwal' => $jadwal,
            'kelas' => $kelas,
            'jumlahSiswa' => $jumlahSiswa,
            'presensi' => $presensi,
            'rekapKehadiran' => $rekapKehadiran,
            'pertemuanKe' => $jadwal ? Jurnal::where('jadwal_id', $jadwal->id)->count() + 1 : 0,
            'jadwalList' => Jadwal::with(['kelas', 'mataPelajaran'])
                ->when($user->isGuru(), fn ($query) => $query->where('guru_id', $user->id))
                ->when($user->isSiswa() && $user->kelas_id, fn ($query) => $query->where('kelas_id', $user->kelas_id))
                ->get(),
            'pertemuanTerakhir' => $kelas
                ? Jurnal::with(['jadwal.mataPelajaran', 'guru'])
                    ->whereHas('jadwal', fn ($query) => $query->where('kelas_id', $kelas->id))
                    ->when($jurnal, fn ($query) => $query->whereKeyNot($jurnal->id))
                    ->latest('tanggal')
                    ->latest('id')
                    ->take(3)
                    ->get()
                : collect(),
        ];
    }

    /**
     * Validation rules. Both roles report the same three outcomes — present, or
     * absent with or without work left behind. Only the student adds a free-text
     * note about the absence, since they are describing someone else's.
     */
    private function rules(User $user): array
    {
        $aturan = [
            'jadwal_id' => ['required', 'exists:jadwal,id'],
            'tanggal' => ['required', 'date'],
            'materi' => ['required', 'string'],
            'tugas' => ['nullable', 'string'],
            'kehadiran_guru' => ['required', Rule::in(['hadir', 'ada_tugas', 'tanpa_tugas'])],
        ];

        return $user->isSiswa()
            ? $aturan + ['kehadiran_guru_keterangan' => ['nullable', 'string']]
            : $aturan;
    }

    /**
     * Fold the chosen outcome onto the stored columns.
     *
     * The reason vocabulary (sakit/izin/alpa) the ketua kelas used to report is
     * retired — both roles now record whether work was left behind. Rows written
     * before this keep their `kehadiran_guru_alasan`, which
     * {@see Jurnal::kehadiranGuruChip()} still renders.
     */
    private function normalize(array $validated, User $user): array
    {
        $pilihan = $validated['kehadiran_guru'];
        unset($validated['kehadiran_guru']);

        $data = $validated + [
            'kehadiran_guru_status' => $pilihan === 'hadir' ? 'hadir' : 'tidak_hadir',
            'kehadiran_guru_alasan' => null,
            'kehadiran_guru_ada_tugas' => $pilihan === 'hadir' ? null : $pilihan === 'ada_tugas',
        ];

        // The guru's own form carries no note field, so it never keeps a stale one.
        return $user->isSiswa() ? $data : $data + ['kehadiran_guru_keterangan' => null];
    }
}
