<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuruRequest;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Support\Halaman;
use App\Support\PendaftaranPengguna;
use App\Support\Urutan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The school's teacher register — people, not logins.
 *
 * The account is created alongside a new teacher (through the stored procedure
 * in {@see PendaftaranPengguna}, so the two rows land together or not at all),
 * but from then on the two are edited apart: this page is where a teacher's
 * name, subjects and homeroom live, and /admin/akun is where their credentials
 * do.
 */
class GuruController extends Controller
{
    public function __construct(private PendaftaranPengguna $pendaftaran) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:aktif,nonaktif'],
            'mata_pelajaran_id' => ['nullable', 'exists:mata_pelajaran,id'],
            'wali' => ['nullable', 'in:ya,tidak'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $guru = Guru::query()
            ->with(['akun', 'mataPelajaran', 'kelasWali'])
            ->withCount(['jadwals', 'kelasWali'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['mata_pelajaran_id'] ?? null, fn ($q, $id) => $q
                ->whereHas('mataPelajaran', fn ($m) => $m->where('mata_pelajaran.id', $id)))
            ->when($filters['wali'] ?? null, fn ($q, $wali) => $wali === 'ya'
                ? $q->whereHas('kelasWali')
                : $q->whereDoesntHave('kelasWali'))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari));

        Urutan::terapkan($guru, $request, [
            'nip' => fn ($q, $dir) => $q->orderBy('nip', $dir),
            'nama' => fn ($q, $dir) => $q->orderBy('nama', $dir),
            'status' => fn ($q, $dir) => $q->orderBy('status', $dir),
            'jadwal' => fn ($q, $dir) => $q->orderBy('jadwals_count', $dir),
        ], fn ($q) => $q->orderBy('nama'));

        return view('admin.guru.index', [
            'guru' => $guru->paginate(Halaman::perHalaman())->withQueryString(),
            'filters' => $filters,
            'mapelList' => MataPelajaran::orderBy('nama')->get(),
            'statistik' => [
                'total' => Guru::count(),
                'aktif' => Guru::where('status', 'aktif')->count(),
                'wali' => Kelas::whereNotNull('wali_kelas_nip')->distinct()->count('wali_kelas_nip'),
                // A teacher with no subject recorded cannot be put on a
                // timetable at all, so this is the queue an admin works through.
                'tanpaMapel' => Guru::where('status', 'aktif')->whereDoesntHave('mataPelajaran')->count(),
                'tanpaAkun' => Guru::whereDoesntHave('akun')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.guru.create', $this->opsiForm());
    }

    public function store(GuruRequest $request)
    {
        $data = $request->validated();

        $guru = $this->pendaftaran->guru($data);

        $this->syncMapel($guru, $data);
        $this->syncPerwalian($guru, $data['kelas_wali'] ?? []);

        if (($data['status'] ?? 'aktif') !== 'aktif') {
            $guru->update(['status' => $data['status']]);
        }

        return redirect()->route('admin.guru.index')
            ->with('success', "Guru {$guru->nama} berhasil ditambahkan beserta akunnya.");
    }

    public function show(Guru $guru)
    {
        $guru->load(['akun', 'mataPelajaran', 'kelasWali']);
        $guru->loadCount(['jadwals', 'jurnals']);

        return view('admin.guru.show', [
            'guru' => $guru,
            'jadwals' => $guru->jadwals()->with(['kelas', 'mataPelajaran', 'ruangan'])->get(),
        ]);
    }

    public function edit(Guru $guru)
    {
        $guru->load(['akun', 'mataPelajaran', 'kelasWali']);

        return view('admin.guru.edit', ['guru' => $guru] + $this->opsiForm());
    }

    public function update(GuruRequest $request, Guru $guru)
    {
        $data = $request->validated();

        DB::transaction(function () use ($guru, $data) {
            $guru->update([
                'nama' => $data['nama'],
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                'no_hp' => $data['no_hp'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'status' => $data['status'],
            ]);

            $this->simpanAkun($guru, $data);
            $this->syncMapel($guru, $data);
            $this->syncPerwalian($guru, $data['kelas_wali'] ?? []);
        });

        return redirect()->route('admin.guru.show', $guru)
            ->with('success', 'Data guru berhasil diperbarui.');
    }

    /**
     * A teacher who has taught is never deleted.
     *
     * Their NIP is stamped on every journal and timetable row they own, and the
     * foreign keys would cascade those away — erasing the school's record of
     * lessons that actually happened. Setting them nonaktif is the real answer;
     * deletion is only for a row entered by mistake.
     */
    public function destroy(Guru $guru)
    {
        $jejak = $guru->jadwals()->count() + $guru->jurnals()->count();

        if ($jejak > 0) {
            return back()->with('error', sprintf(
                '%s masih terhubung ke %d jadwal/jurnal. Ubah statusnya jadi "Nonaktif" — menghapusnya akan ikut menghapus riwayat mengajarnya.',
                $guru->nama,
                $jejak,
            ));
        }

        $nama = $guru->nama;
        $guru->delete(); // The account cascades with them.

        return redirect()->route('admin.guru.index')
            ->with('success', "Guru {$nama} berhasil dihapus beserta akunnya.");
    }

    /**
     * @return array<string, mixed>
     */
    private function opsiForm(): array
    {
        return [
            'mapelList' => MataPelajaran::orderBy('nama')->get(),
            'kelasList' => Kelas::with('waliKelas')->orderBy('nama_kelas')->get(),
        ];
    }

    /**
     * Credentials are optional on update: an empty password box means the
     * teacher keeps the one they have.
     *
     * @param  array<string, mixed>  $data
     */
    private function simpanAkun(Guru $guru, array $data): void
    {
        $akun = $guru->akun;

        if (! $akun) {
            return;
        }

        $akun->username = $data['username'];
        $akun->email = $data['email'];
        // A nonaktif teacher should not be able to sign in; the two states are
        // edited here as one so they cannot disagree.
        $akun->status = $data['status'] === 'aktif' ? 'aktif' : 'nonaktif';

        if (filled($data['password'] ?? null)) {
            $akun->password = Hash::make($data['password']);
        }

        $akun->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncMapel(Guru $guru, array $data): void
    {
        $ids = array_values(array_unique($data['mata_pelajaran_id'] ?? []));
        $utama = in_array($data['mapel_utama'] ?? null, $ids) ? (int) $data['mapel_utama'] : null;

        $guru->mataPelajaran()->sync(
            collect($ids)->mapWithKeys(fn ($id) => [(int) $id => ['utama' => (int) $id === $utama]])->all()
        );
    }

    /**
     * Make this teacher the wali of exactly $kelasIds. Both this form and the
     * Kelas form write the same kelas.wali_kelas_nip, so they stay two views of
     * one fact; an empty selection releases every class they held.
     *
     * @param  array<int|string>  $kelasIds
     */
    private function syncPerwalian(Guru $guru, array $kelasIds): void
    {
        Kelas::whereIn('id', $kelasIds)->update(['wali_kelas_nip' => $guru->nip]);

        Kelas::where('wali_kelas_nip', $guru->nip)
            ->when($kelasIds, fn ($query) => $query->whereNotIn('id', $kelasIds))
            ->update(['wali_kelas_nip' => null]);
    }
}
