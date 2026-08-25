<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiswaRequest;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Support\Halaman;
use App\Support\PendaftaranPengguna;
use App\Support\Urutan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The school's student register — people, not logins. Counterpart of
 * {@see GuruController}; see that class for why the two are separate pages.
 */
class SiswaController extends Controller
{
    public function __construct(private PendaftaranPengguna $pendaftaran) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'tingkat' => ['nullable', 'in:X,XI,XII'],
            'jurusan' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:aktif,nonaktif,lulus'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $siswa = Siswa::query()
            ->with(['akun', 'kelas'])
            ->when($filters['kelas_id'] ?? null, fn ($q, $id) => $q->where('kelas_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['tingkat'] ?? null, fn ($q, $t) => $q
                ->whereIn('kelas_id', Kelas::where('tingkat', $t)->select('id')))
            ->when($filters['jurusan'] ?? null, fn ($q, $j) => $q
                ->whereIn('kelas_id', Kelas::where('jurusan', $j)->select('id')))
            ->when($filters['q'] ?? null, fn ($q, $cari) => $q->cari($cari));

        Urutan::terapkan($siswa, $request, [
            'nis' => fn ($q, $dir) => $q->orderBy('nis', $dir),
            'nama' => fn ($q, $dir) => $q->orderBy('nama', $dir),
            'status' => fn ($q, $dir) => $q->orderBy('status', $dir),
            'kelas' => fn ($q, $dir) => $q->orderBy(
                Kelas::select('nama_kelas')->whereColumn('kelas.id', 'siswa.kelas_id'), $dir
            ),
        ], fn ($q) => $q->orderBy('nama'));

        return view('admin.siswa.index', [
            'siswa' => $siswa->paginate(Halaman::perHalaman())->withQueryString(),
            'filters' => $filters,
            'kelasList' => Kelas::orderBy('nama_kelas')->get(),
            'statistik' => [
                'total' => Siswa::count(),
                'aktif' => Siswa::where('status', 'aktif')->count(),
                // A student with no class is invisible to attendance: nobody's
                // roster includes them. Worth surfacing, not burying.
                'tanpaKelas' => Siswa::where('status', 'aktif')->whereNull('kelas_id')->count(),
                'tanpaAkun' => Siswa::whereDoesntHave('akun')->count(),
                'ketua' => Siswa::where('is_ketua_kelas', true)->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.siswa.create', $this->opsiForm());
    }

    public function store(SiswaRequest $request)
    {
        $data = $request->validated();

        $siswa = $this->pendaftaran->siswa($data);

        $siswa->update([
            'is_ketua_kelas' => (bool) ($data['is_ketua_kelas'] ?? false),
            'status' => $data['status'] ?? 'aktif',
        ]);

        return redirect()->route('admin.siswa.index')
            ->with('success', "Siswa {$siswa->nama} berhasil ditambahkan beserta akunnya.");
    }

    public function show(Siswa $siswa)
    {
        $siswa->load(['akun', 'kelas.waliKelas']);

        return view('admin.siswa.show', [
            'siswa' => $siswa,
            'rekapPresensi' => $siswa->presensiHarian()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function edit(Siswa $siswa)
    {
        $siswa->load('akun');

        return view('admin.siswa.edit', ['siswa' => $siswa] + $this->opsiForm());
    }

    public function update(SiswaRequest $request, Siswa $siswa)
    {
        $data = $request->validated();

        DB::transaction(function () use ($siswa, $data) {
            $siswa->update([
                'nisn' => $data['nisn'] ?? null,
                'nama' => $data['nama'],
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                'kelas_id' => $data['kelas_id'] ?? null,
                'is_ketua_kelas' => (bool) ($data['is_ketua_kelas'] ?? false),
                'no_hp' => $data['no_hp'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'status' => $data['status'],
            ]);

            $this->simpanAkun($siswa, $data);
        });

        return redirect()->route('admin.siswa.show', $siswa)
            ->with('success', 'Data siswa berhasil diperbarui.');
    }

    /**
     * A student with attendance on record is never deleted — the roster rows
     * would cascade away with them, rewriting the school's history of who was
     * in class. "Lulus" and "nonaktif" are what actually happens to students.
     */
    public function destroy(Siswa $siswa)
    {
        $jejak = $siswa->presensiHarian()->count();

        if ($jejak > 0) {
            return back()->with('error', sprintf(
                '%s punya %s catatan presensi. Ubah statusnya jadi "Lulus" atau "Nonaktif" — menghapusnya akan ikut menghapus riwayat kehadirannya.',
                $siswa->nama,
                number_format($jejak, 0, ',', '.'),
            ));
        }

        $nama = $siswa->nama;
        $siswa->delete(); // The account cascades with them.

        return redirect()->route('admin.siswa.index')
            ->with('success', "Siswa {$nama} berhasil dihapus beserta akunnya.");
    }

    /**
     * @return array<string, mixed>
     */
    private function opsiForm(): array
    {
        return ['kelasList' => Kelas::orderBy('nama_kelas')->get()];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function simpanAkun(Siswa $siswa, array $data): void
    {
        $akun = $siswa->akun;

        if (! $akun) {
            return;
        }

        $akun->username = $data['username'];
        $akun->email = $data['email'];
        $akun->status = $data['status'] === 'aktif' ? 'aktif' : 'nonaktif';

        if (filled($data['password'] ?? null)) {
            $akun->password = Hash::make($data['password']);
        }

        $akun->save();
    }
}
