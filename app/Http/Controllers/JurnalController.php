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
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->where(fn ($inner) => $inner
                ->where('materi', 'like', "%{$cari}%")
                ->orWhere('tugas', 'like', "%{$cari}%")
                ->orWhere('kegiatan', 'like', "%{$cari}%")
                ->orWhereHas('guru', fn ($g) => $g->where('name', 'like', "%{$cari}%"))
                ->orWhereHas('jadwal.kelas', fn ($k) => $k->where('nama_kelas', 'like', "%{$cari}%"))
                ->orWhereHas('jadwal.mataPelajaran', fn ($m) => $m->where('nama', 'like', "%{$cari}%"))))
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

        return view('jurnal.histori', [
            'jurnals' => $jurnals,
            'kelasList' => Kelas::orderBy('nama_kelas')->get(),
            'mapelList' => MataPelajaran::orderBy('nama')->get(),
            'filters' => $filters,
            'statistik' => [
                'total' => (clone $milikSaya)->count(),
                'bulanIni' => (clone $milikSaya)->whereMonth('tanggal', now()->month)->count(),
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

        $jurnals = $query
            ->when($kelas, fn ($q) => $q->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $kelas->id)))
            ->paginate(18)
            ->withQueryString();

        // The student's own attendance for the rows on this page.
        $presensiSaya = Presensi::where('siswa_id', $user->id)
            ->whereIn('jurnal_id', $jurnals->pluck('id'))
            ->get()
            ->keyBy('jurnal_id');

        $jurnalKelas = Jurnal::when($kelas, fn ($q) => $q->whereHas('jadwal', fn ($j) => $j->where('kelas_id', $kelas->id)));

        return view('jurnal.riwayat', [
            'jurnals' => $jurnals,
            'presensiSaya' => $presensiSaya,
            'kelas' => $kelas,
            'mapelList' => MataPelajaran::orderBy('nama')->get(),
            'guruList' => User::where('role', 'guru')->orderBy('name')->get(),
            'filters' => $filters,
            'statistik' => [
                'total' => (clone $jurnalKelas)->count(),
                'tugas' => (clone $jurnalKelas)->whereNotNull('tugas')->count(),
                'kelengkapan' => $kelas ? (Ringkasan::kelengkapan('kelas_id')[$kelas->id] ?? 0) : 0,
            ],
        ]);
    }

    /**
     * The journal form. A teacher reports their own attendance; a ketua kelas
     * reports the teacher's, which is why the two get different forms.
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $jadwal = $this->jadwalTerpilih($request, $user);

        return view($user->isSiswa() ? 'jurnal.mengisi' : 'jurnal.isi', $this->konteksForm($user, $jadwal));
    }

    /**
     * Store a newly created jurnal entry.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate($this->rules($user));

        $data = $this->normalize($validated, $user);
        $data['guru_id'] = $user->isGuru()
            ? $user->id
            : Jadwal::find($validated['jadwal_id'])?->guru_id;
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
        $jurnal->load(['jadwal.kelas', 'jadwal.mataPelajaran', 'guru', 'diisiOleh', 'presensis.siswa']);

        return view('jurnal.show', compact('jurnal'));
    }

    /**
     * Show the form for editing the specified jurnal entry.
     */
    public function edit(Request $request, Jurnal $jurnal)
    {
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
        $user = $request->user();
        $validated = $request->validate($this->rules($user));

        $jurnal->update($this->normalize($validated, $user));

        return redirect()->route('jurnal.index')->with('success', 'Jurnal berhasil diperbarui.');
    }

    /**
     * Remove the specified jurnal entry from storage.
     */
    public function destroy(Jurnal $jurnal)
    {
        $jurnal->delete();

        return redirect()->route('jurnal.index')->with('success', 'Jurnal berhasil dihapus.');
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
        // teachers' — whichever the form's author is reporting on.
        $rekapKehadiran = $user->isGuru()
            ? Ringkasan::kehadiranGuru(Jurnal::where('guru_id', $user->id))
            : Ringkasan::kehadiranGuru(
                Jurnal::whereHas('jadwal', fn ($q) => $q->where('kelas_id', $user->kelas_id))
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
            'guruList' => User::where('role', 'guru')->orderBy('name')->get(),
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
     * Validation rules. The teacher reports whether work was left behind; the
     * student reports the reason they observed.
     */
    private function rules(User $user): array
    {
        $aturan = [
            'jadwal_id' => ['required', 'exists:jadwal,id'],
            'tanggal' => ['required', 'date'],
            'materi' => ['required', 'string'],
            'tugas' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ];

        if ($user->isSiswa()) {
            return $aturan + [
                'kehadiran_guru' => ['required', Rule::in(['hadir', 'sakit', 'izin', 'alpa'])],
                'kehadiran_guru_keterangan' => ['nullable', 'string'],
            ];
        }

        return $aturan + [
            'kehadiran_guru' => ['required', Rule::in(['hadir', 'ada_tugas', 'tanpa_tugas'])],
        ];
    }

    /**
     * Fold either role's vocabulary onto the stored columns.
     */
    private function normalize(array $validated, User $user): array
    {
        $pilihan = $validated['kehadiran_guru'];
        unset($validated['kehadiran_guru']);

        if ($user->isSiswa()) {
            return $validated + [
                'kehadiran_guru_status' => $pilihan === 'hadir' ? 'hadir' : 'tidak_hadir',
                'kehadiran_guru_alasan' => $pilihan === 'hadir' ? null : $pilihan,
                'kehadiran_guru_ada_tugas' => null,
            ];
        }

        return $validated + [
            'kehadiran_guru_status' => $pilihan === 'hadir' ? 'hadir' : 'tidak_hadir',
            'kehadiran_guru_alasan' => null,
            'kehadiran_guru_ada_tugas' => $pilihan === 'hadir' ? null : $pilihan === 'ada_tugas',
            'kehadiran_guru_keterangan' => null,
        ];
    }
}
