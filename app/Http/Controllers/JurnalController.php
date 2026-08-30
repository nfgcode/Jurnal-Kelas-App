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
use Illuminate\Support\Collection;
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
            'tingkat' => ['nullable', 'in:X,XI,XII'],
            'jurusan' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $periode = Periode::dari($request);

        $query = Jurnal::query()
            ->with(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru'])
            // Attendance beside a journal row is the roster its own teacher
            // marked for that lesson — per subject, see the scope's note.
            ->denganPresensi()
            ->whereBetween('tanggal', [$periode->mulaiString(), $periode->selesaiString()])
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $id)))
            ->when($filters['mata_pelajaran_id'] ?? null, fn ($q, $id) => $q->whereHas('jadwal', fn ($j) => $j->where('mata_pelajaran_id', $id)))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari));

        if ($user->isSiswa()) {
            return $this->riwayatSiswa($request, $user, $query, $filters, $periode);
        }

        if ($user->isGuru()) {
            $query->diampu($user->nip);
        }

        // Grade and jurusan narrow by the meeting's class — applied only on the
        // guru/admin listing (a siswa sees a single class, so they never reach here).
        $query
            ->when($filters['tingkat'] ?? null, fn ($q, $t) => $q->whereHas('jadwal.kelas', fn ($k) => $k->where('tingkat', $t)))
            ->when($filters['jurusan'] ?? null, fn ($q, $j) => $q->whereHas('jadwal.kelas', fn ($k) => $k->where('jurusan_kode', $j)));

        // Newest first unless the reader asked for another column.
        $peta = array_intersect_key(Jurnal::petaUrutan(), array_flip(['tanggal', 'jam', 'kelas', 'mapel', 'materi', 'tugas', 'persen', 'kehadiran_guru', 'status']));
        Urutan::terapkan($query, $request, $peta, fn ($q) => $q->latest('tanggal')->latest('id'));

        $jurnals = $query->paginate(Halaman::perHalaman())->withQueryString();
        // The stat cards count journals a person wrote, so the nightly backfill's
        // placeholders are left out — they mean the opposite of "filled in".
        $milikSaya = ($user->isGuru() ? Jurnal::diampu($user->nip) : Jurnal::query())->manusia();

        // A guru filters only among the classes/subjects they teach; admin all.
        $kelasList = Kelas::query()
            ->when($user->isGuru(), fn ($q) => $q->whereIn('id', Jadwal::where('guru_nip', $user->nip)->select('kelas_id')))
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
                ->when($user->isGuru(), fn ($q) => $q->whereHas('jadwals', fn ($j) => $j->where('guru_nip', $user->nip)))
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

        $peta = array_intersect_key(Jurnal::petaUrutan($user), array_flip(['tanggal', 'jam', 'mapel', 'guru', 'kehadiran_guru', 'materi', 'tugas', 'presensi_saya', 'status']));
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

        // The student's own mark on each meeting listed on this page — keyed by
        // journal, because a roster now belongs to one lesson of one subject and
        // the same day may hold several different answers.
        $presensiSaya = Presensi::where('siswa_nis', $user->nis)
            ->whereIn('jurnal_id', $jurnals->pluck('id')->all())
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
     * The journal form. The class writes it — through its ketua kelas, who gets
     * the wording written for a student; an admin correcting a record gets the
     * neutral form instead.
     */
    public function create(Request $request)
    {
        Gate::authorize('create', Jurnal::class);

        $user = $request->user();
        $tanggal = $this->tanggalAcuan($request);
        $jadwal = $this->jadwalTerpilih($request, $user, $tanggal);

        // A meeting the nightly backfill already placeholdered is corrected by
        // editing that row (which "adopts" it), never by filing a second journal
        // beside it — so send the writer straight there.
        if ($jadwal && $sistem = Jurnal::sudahAda($jadwal->id, $tanggal->toDateString(), Jurnal::PERAN_SISTEM)) {
            return redirect()->route('jurnal.edit', $sistem)
                ->with('success', 'Jurnal pertemuan ini sudah dibuat otomatis oleh sistem. Silakan periksa dan perbaiki di sini.');
        }

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

        // The nightly backfill may already hold this meeting. Its peran is
        // 'sistem', so the unique index would happily accept a second journal
        // beside it — send the writer to correct that one instead, exactly as
        // create() does when the form is opened on the slot.
        if ($sistem = Jurnal::sudahAda($jadwal->id, $validated['tanggal'], Jurnal::PERAN_SISTEM)) {
            // withInput(): the backfill can land on this slot while the form is
            // open (it runs at 00:30), and the writer must not lose what they
            // typed. The edit form reads old() first, so their materi/tugas
            // carry straight over into the journal they are redirected to.
            return redirect()->route('jurnal.edit', $sistem)
                ->withInput()
                ->with('success', 'Jurnal pertemuan ini sudah dibuat otomatis oleh sistem. Isian Anda dibawa ke sini — periksa, centang pernyataan, lalu simpan.');
        }

        $data = $this->normalize($validated, $user);
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

        // Saving a journal does not hand the writer an attendance roster next:
        // the roster belongs to the lesson's teacher, and the class only reads it.
        return redirect()->route('jurnal.show', $jurnal)
            ->with('success', 'Jurnal tersimpan.');
    }

    /**
     * Display the specified jurnal entry.
     */
    public function show(Jurnal $jurnal)
    {
        Gate::authorize('view', $jurnal);

        $jurnal->load(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru', 'diisiOleh']);

        // The attendance shown beside a journal is the roster this meeting's own
        // teacher marked — the record that can differ from the lesson before it.
        $presensi = Presensi::with('siswa')
            ->where('jurnal_id', $jurnal->id)
            ->get()
            ->sortBy(fn ($p) => $p->siswa?->nama ?? '')
            ->values();

        return view('jurnal.show', [
            'jurnal' => $jurnal,
            'presensi' => $presensi,
            'rekap' => $this->rekapPresensi($presensi),
            // How many students the class holds, so "12 dari 30 ditandai" can be
            // said rather than only "12".
            'jumlahSiswa' => $jurnal->jadwal?->kelas?->siswa()->count() ?? 0,
        ]);
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
        $dariSistem = $jurnal->dibuatSistem();

        // Correcting an auto-filled journal is honesty-sensitive (it can flip an
        // automatic "absent" into "present"), so it requires an explicit
        // truthfulness attestation before the change is accepted.
        $aturan = $this->rules($user);

        if ($dariSistem) {
            $aturan['pernyataan'] = ['accepted'];
        }

        $validated = $request->validate($aturan, [
            'pernyataan.accepted' => 'Centang pernyataan kejujuran dulu sebelum mengubah jurnal otomatis ini.',
        ]);
        unset($validated['pernyataan']);

        // jadwal_id is editable, so re-check the caller may write against it —
        // otherwise an update could move the journal onto another class/teacher.
        $this->pastikanJadwalMilik($user, Jadwal::findOrFail($validated['jadwal_id']));

        // A human editing a system placeholder "adopts" it as their own entry, so
        // both the collision check and the saved row take the editor's side.
        $peran = $dariSistem
            ? Jurnal::peranPengisi($user)
            : ($jurnal->diisi_oleh_peran ?? Jurnal::peranPengisi($user));

        // Moving a journal onto a meeting/date that already has one from this side
        // would collide with the unique index; reject it the same way as a
        // duplicate create. The row being edited is excluded from the check.
        $lama = Jurnal::sudahAda((int) $validated['jadwal_id'], $validated['tanggal'], $peran, $jurnal->id);

        if ($lama) {
            return $this->tolakGanda($lama, $lama->diisi_oleh_peran);
        }

        $data = $this->normalize($validated, $user);

        if ($dariSistem) {
            $data['diisi_oleh_peran'] = $peran;
            $data['diisi_oleh_id'] = $user->id;
        }

        // The "diedit setelah hari-H" flag is set by the Jurnal `updating` event,
        // so it applies uniformly to every edit path (web + API).
        $jurnal->update($data);

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
        $siapa = $lama?->diisiOleh?->nama;
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
        abort_if($user->isGuru() && $jadwal->guru_nip !== $user->nip, 403,
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

        // The roster the meeting's teacher marked — context for whoever is
        // writing the journal, never something they edit from here.
        $presensi = $this->rekapPresensi(
            $jurnal ? Presensi::where('jurnal_id', $jurnal->id)->get() : collect()
        );

        // How the class's teachers have been turning up this month — the record
        // the journal's author is actually reporting on. Read from the meeting's
        // own class rather than the reader's, so an admin filling a journal for
        // XI RPL 1 sees XI RPL 1. Scoped to the current month, matching the
        // card's "Bulan Ini" label.
        $rekapKehadiran = Ringkasan::kehadiranGuru(
            Jurnal::whereHas('jadwal', fn ($q) => $q->where('kelas_id', $kelas?->id ?? 0))
                ->whereMonth('tanggal', now()->month)
                ->whereYear('tanggal', now()->year)
        );

        return [
            'jurnal' => $jurnal,
            'jadwal' => $jadwal,
            'kelas' => $kelas,
            // Editing an auto-filled journal triggers the truthfulness attestation.
            'dariSistem' => $jurnal?->dibuatSistem() ?? false,
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
     * Fold a collection of roster rows into the hadir/sakit/izin/alpa totals
     * every journal screen renders, always with all four keys present.
     *
     * @param  Collection<int, Presensi>  $presensi
     * @return array<string, int>
     */
    private function rekapPresensi($presensi): array
    {
        $per = $presensi->countBy('status');

        return [
            'hadir' => (int) ($per['hadir'] ?? 0),
            'sakit' => (int) ($per['sakit'] ?? 0),
            'izin' => (int) ($per['izin'] ?? 0),
            'alpa' => (int) ($per['alpa'] ?? 0),
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
