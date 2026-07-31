<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Presensi;
use App\Models\User;
use App\Support\Halaman;
use App\Support\Periode;
use App\Support\Ringkasan;
use App\Support\Urutan;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $periode = Periode::dari($request);

        $query = Jurnal::query()
            ->with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            ->withCount([
                'presensis as total_siswa',
                'presensis as hadir_count' => fn ($q) => $q->where('status', 'hadir'),
            ])
            ->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()])
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $id)))
            ->when($filters['mata_pelajaran_id'] ?? null, fn ($q, $id) => $q->whereHas('jadwal', fn ($j) => $j->where('mata_pelajaran_id', $id)))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari));

        if ($user->isSiswa()) {
            return $this->riwayatSiswa($request, $user, $query, $filters, $periode);
        }

        if ($user->isGuru()) {
            $query->where('guru_id', $user->id);
        }

        // Newest first unless the reader asked for another column.
        $peta = array_intersect_key(Jurnal::petaUrutan(), array_flip(['tanggal', 'kelas', 'mapel', 'hadir', 'status']));
        Urutan::terapkan($query, $request, $peta, fn ($q) => $q->latest('tanggal')->latest('id'));

        $jurnals = $query->paginate(Halaman::perHalaman())->withQueryString();
        $milikSaya = $user->isGuru() ? Jurnal::where('guru_id', $user->id) : Jurnal::query();

        // A guru filters only among the classes/subjects they teach; admin all.
        $kelasList = Kelas::query()
            ->when($user->isGuru(), fn ($q) => $q->whereIn('id', Jadwal::where('guru_id', $user->id)->select('kelas_id')))
            ->orderBy('nama_kelas')
            ->get();

        // All-time total and the selected period's count in one scan (SUM(BETWEEN)
        // is 0/1 per row on both MySQL and SQLite) instead of two separate COUNTs.
        $agregat = (clone $milikSaya)
            ->selectRaw('COUNT(*) as total, SUM(tanggal BETWEEN ? AND ?) as periode', [
                $periode->mulaiString(),
                $periode->selesaiString(),
            ])
            ->first();

        return view('jurnal.histori', [
            'jurnals' => $jurnals,
            'periode' => $periode,
            'kelasList' => $kelasList,
            'mapelList' => MataPelajaran::query()
                ->when($user->isGuru(), fn ($q) => $q->whereHas('jadwals', fn ($j) => $j->where('guru_id', $user->id)))
                ->orderBy('nama')->get(),
            'filters' => $filters,
            'statistik' => [
                'total' => (int) $agregat->total,
                'periode' => (int) $agregat->periode,
                // The filter list already holds exactly the classes this figure
                // counts — a guru's own, or every class for admin — so counting
                // it costs nothing instead of a second query.
                'kelas' => $kelasList->count(),
                // Scoped to the period so the cards describe the same rows as
                // the table below them.
                'kehadiran' => Ringkasan::kehadiranGuru(
                    (clone $milikSaya)->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()])
                ),
            ],
        ]);
    }

    /**
     * The same records seen from the student's side: whose lesson it was, and
     * whether the student themself was present.
     */
    private function riwayatSiswa(Request $request, User $user, $query, array $filters, Periode $periode)
    {
        $kelas = $user->kelas;

        $peta = array_intersect_key(Jurnal::petaUrutan(), array_flip(['tanggal', 'mapel', 'guru', 'status']));
        Urutan::terapkan($query, $request, $peta, fn ($q) => $q->latest('tanggal')->latest('id'));

        // A student with no class sees nothing — never every class's journals.
        // (Deleting a rombel NULLs its students' kelas_id.)
        if (! $kelas) {
            $jurnals = $query->whereRaw('1 = 0')->paginate(Halaman::perHalaman())->withQueryString();

            return view('jurnal.riwayat', [
                'jurnals' => $jurnals,
                'periode' => $periode,
                'presensiSaya' => collect(),
                'kelas' => null,
                'mapelList' => MataPelajaran::orderBy('nama')->get(),
                'filters' => $filters,
                'statistik' => ['total' => 0, 'tugas' => 0, 'kelengkapan' => 0],
            ]);
        }

        $jurnals = $query
            ->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $kelas->id))
            ->paginate(Halaman::perHalaman())
            ->withQueryString();

        // The student's own attendance for the rows on this page.
        $presensiSaya = Presensi::where('siswa_id', $user->id)
            ->whereIn('jurnal_id', $jurnals->pluck('id'))
            ->get()
            ->keyBy('jurnal_id');

        // Total and how many carried a task, in one grouped pass over the class —
        // within the selected period, so the cards match the table.
        $agregat = Jurnal::whereHas('jadwal', fn ($j) => $j->where('kelas_id', $kelas->id))
            ->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()])
            ->selectRaw('COUNT(*) as total, COUNT(tugas) as tugas')
            ->first();

        return view('jurnal.riwayat', [
            'jurnals' => $jurnals,
            'periode' => $periode,
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
        $tanggal = $this->tanggalAcuan($request);
        $jadwal = $this->jadwalTerpilih($request, $user, $tanggal);

        return view($user->isSiswa() ? 'jurnal.mengisi' : 'jurnal.isi',
            $this->konteksForm($user, $jadwal, $tanggal));
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

        $peran = Jurnal::peranPengisi($user);

        // One journal per side per meeting. Checked here for a helpful message,
        // and caught below because two rapid submits can both pass this check.
        if ($lama = Jurnal::sudahAda($jadwal->id, $validated['tanggal'], $peran)) {
            return $this->tolakGanda($lama, $peran);
        }

        $data = $this->normalize($validated, $user);
        $data['guru_id'] = $user->isGuru() ? $user->id : $jadwal->guru_id;
        $data['diisi_oleh_id'] = $user->id;
        $data['diisi_oleh_peran'] = $peran;

        try {
            $jurnal = Jurnal::create($data);
        } catch (QueryException $e) {
            // Lost the race against a concurrent submit — the unique index held.
            if (! Jurnal::pelanggaranGanda($e)) {
                throw $e;
            }

            return $this->tolakGanda(
                Jurnal::sudahAda($jadwal->id, $validated['tanggal'], $peran),
                $peran,
            );
        }

        return redirect()->route('presensi.create', $jurnal)
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

        // Editing stays on the journal's own date, so its slot is in the list.
        return view(
            $user->isSiswa() ? 'jurnal.mengisi' : 'jurnal.isi',
            $this->konteksForm($user, $jurnal->jadwal, $this->tanggalAcuan($request, $jurnal), $jurnal)
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

        // Moving a journal onto a meeting/date that already has one from this side
        // would collide with the unique index; reject it the same way as a
        // duplicate create. The row being edited is excluded from the check.
        $lama = Jurnal::sudahAda(
            (int) $validated['jadwal_id'],
            $validated['tanggal'],
            $jurnal->diisi_oleh_peran ?? Jurnal::peranPengisi($user),
            $jurnal->id,
        );

        if ($lama) {
            return $this->tolakGanda($lama, $lama->diisi_oleh_peran);
        }

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
     * Send a double submit back to the form with a message that points at the
     * journal already on file, rather than silently creating a second copy of the
     * same lesson (which would also inflate journal completeness).
     */
    private function tolakGanda(?Jurnal $lama, string $peran): RedirectResponse
    {
        $siapa = $lama?->diisiOleh?->name;
        $sisi = $peran === 'siswa' ? 'perwakilan kelas' : 'guru pengajar';

        // Name the meeting: with a day's worth of slots in the dropdown, "this
        // meeting" alone leaves the writer guessing which one was refused.
        $jadwal = $lama?->jadwal;
        $slot = $jadwal
            ? collect([
                $jadwal->kelas?->nama_kelas,
                $jadwal->mataPelajaran?->nama,
                'JP '.$jadwal->jpLabel(),
            ])->filter()->join(' · ')
            : null;

        $pesan = 'Jurnal '.($slot ? "{$slot} " : '')."pada {$lama?->tanggal?->translatedFormat('j F Y')}"
            ." sudah diisi dari sisi {$sisi}"
            .($siapa ? " oleh {$siapa}" : '')
            .'. Silakan buka jurnal tersebut bila ingin memperbaruinya.';

        return back()->withInput()->withErrors(['jadwal_id' => $pesan]);
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
     * The date the form is about. A late journal is normal here — the app has a
     * "Telat" status for exactly that — so any valid date is accepted and the
     * timetable follows it rather than being pinned to today.
     */
    private function tanggalAcuan(Request $request, ?Jurnal $jurnal = null): Carbon
    {
        $request->validate(['tanggal' => ['nullable', 'date']]);

        return match (true) {
            (bool) $request->query('tanggal') => Carbon::parse($request->query('tanggal'))->startOfDay(),
            $jurnal !== null => $jurnal->tanggal->copy()->startOfDay(),
            default => today(),
        };
    }

    /**
     * Which meeting the form is about: an explicit choice, otherwise the first
     * slot on that date's timetable that has no journal from this user's side.
     */
    private function jadwalTerpilih(Request $request, User $user, Carbon $tanggal): ?Jadwal
    {
        $kandidat = Jadwal::with(['kelas', 'mataPelajaran', 'guru'])->untukPengguna($user);

        if ($id = $request->integer('jadwal_id')) {
            // Falling back to the first slot of the day keeps a stale id — from a
            // bookmarked link or a changed timetable — from emptying the form.
            return (clone $kandidat)->find($id)
                ?? (clone $kandidat)->padaHariDari($tanggal)->orderBy('jam_ke_mulai')->first();
        }

        $hariItu = (clone $kandidat)->padaHariDari($tanggal)->orderBy('jam_ke_mulai')->get();
        $terisi = $this->jadwalTerisi($user, $hariItu->pluck('id')->all(), $tanggal);

        return $hariItu->firstWhere(fn ($j) => ! in_array($j->id, $terisi, true))
            ?? $hariItu->first();
    }

    /**
     * Which of those slots already carry a journal from this user's side on that
     * date. One query for the whole dropdown rather than one per option.
     *
     * @param  array<int>  $jadwalIds
     * @return array<int>
     */
    private function jadwalTerisi(User $user, array $jadwalIds, Carbon $tanggal): array
    {
        if ($jadwalIds === []) {
            return [];
        }

        return Jurnal::whereIn('jadwal_id', $jadwalIds)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->where('diisi_oleh_peran', Jurnal::peranPengisi($user))
            ->pluck('jadwal_id')
            ->all();
    }

    /**
     * Everything both journal forms render around the chosen meeting.
     */
    private function konteksForm(User $user, ?Jadwal $jadwal, Carbon $tanggal, ?Jurnal $jurnal = null): array
    {
        $kelas = $jadwal?->kelas;
        $jumlahSiswa = $kelas ? $kelas->siswa()->count() : 0;

        // Only the slots taught on that date's weekday, and only this user's —
        // the whole timetable would be an unusable list, and would offer meetings
        // they are not allowed to file against anyway.
        $jadwalList = Jadwal::with(['kelas', 'mataPelajaran'])
            ->untukPengguna($user)
            ->padaHariDari($tanggal)
            ->orderBy('jam_ke_mulai')
            ->get();

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
            'jadwalList' => $jadwalList,
            'tanggalAktif' => $tanggal,
            // Marked in the dropdown so a slot already filed from this side is
            // obvious before saving, instead of being refused afterwards.
            'jadwalTerisi' => $this->jadwalTerisi($user, $jadwalList->pluck('id')->all(), $tanggal),
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
