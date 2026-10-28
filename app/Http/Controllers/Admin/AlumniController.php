<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\TahunAjaran;
use App\Models\TinjauanRetensi;
use App\Support\AuditAdmin;
use App\Support\Halaman;
use App\Support\KebijakanRetensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AlumniController extends Controller
{
    /**
     * Tampilan daftar alumni beserta status siklus hidup dan retensinya.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'tahun_lulus' => ['nullable', 'string', 'max:9'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Alumni::query()
            ->with(['siswa.akun', 'kelasTerakhir', 'tahunLulus'])
            ->when($filters['tahun_lulus'] ?? null, fn ($q, $th) => $q->where('tahun_lulus', $th))
            ->when($filters['q'] ?? null, function ($q, $cari) {
                $q->whereHas('siswa', fn ($s) => $s->where('nama', 'like', "%{$cari}%")
                    ->orWhere('nis', 'like', "%{$cari}%"));
            })
            ->latest('tahun_lulus')
            ->latest('tanggal_lulus');

        $alumni = $query->paginate(Halaman::perHalaman())->withQueryString();

        // Evaluasi ringkasan siklus hidup untuk setiap baris yang tampil
        $alumniEvaluasi = $alumni->through(function ($item) {
            $eval = KebijakanRetensi::evaluasi($item->siswa);
            $item->evaluasi = $eval;

            return $item;
        });

        $tahunLulusList = Alumni::distinct('tahun_lulus')->orderByDesc('tahun_lulus')->pluck('tahun_lulus');

        // Statistik agregat
        $totalAlumni = Alumni::count();

        return view('admin.alumni.index', [
            'alumni' => $alumniEvaluasi,
            'filters' => $filters,
            'tahunLulusList' => $tahunLulusList,
            'statistik' => [
                'total' => $totalAlumni,
            ],
        ]);
    }

    /**
     * Tampilan detail alumni, inspeksi dependensi relasional, dan kelayakan retensi.
     */
    public function show(Alumni $alumni)
    {
        $alumni->load(['siswa.akun', 'kelasTerakhir', 'tahunLulus']);

        $siswa = $alumni->siswa;
        $evaluasi = KebijakanRetensi::evaluasi($siswa);

        $riwayatKelas = $siswa->riwayatKelas()
            ->with(['kelas', 'tahunAjaran'])
            ->orderByDesc('tahun_ajaran_kode')
            ->get();

        $riwayatTinjauan = Schema::hasTable('tinjauan_retensi')
            ? TinjauanRetensi::with('ditinjauOleh')->where('siswa_nis', $siswa->nis)->latest()->get()
            : collect();

        return view('admin.alumni.show', [
            'alumni' => $alumni,
            'siswa' => $siswa,
            'evaluasi' => $evaluasi,
            'riwayatKelas' => $riwayatKelas,
            'riwayatTinjauan' => $riwayatTinjauan,
        ]);
    }

    /**
     * Simpan keputusan tinjauan administratif siklus hidup & retensi.
     */
    public function simpanTinjauan(Request $request, Alumni $alumni)
    {
        $data = $request->validate([
            'status_tinjauan' => [
                'required',
                Rule::in([
                    TinjauanRetensi::STATUS_PERLU_TINJAUAN,
                    TinjauanRetensi::STATUS_DITINJAU,
                    TinjauanRetensi::STATUS_DITUNDA,
                    TinjauanRetensi::STATUS_DISETUJUI_ARSIP,
                ]),
            ],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $siswa = $alumni->siswa;
        $evaluasi = KebijakanRetensi::evaluasi($siswa);

        if (Schema::hasTable('tinjauan_retensi')) {
            TinjauanRetensi::create([
                'siswa_nis' => $siswa->nis,
                'status_tinjauan' => $data['status_tinjauan'],
                'catatan' => $data['catatan'] ?? null,
                'ditinjau_oleh_id' => $request->user()->id,
                'ditinjau_pada' => now(),
            ]);
        }

        AuditAdmin::catat(
            aksi: 'retensi.tinjauan',
            tipeEntitas: 'alumni',
            entitasId: $siswa->nis,
            deskripsi: "Tinjauan retensi untuk alumni {$siswa->nama} (NIS: {$siswa->nis}): status diubah menjadi {$data['status_tinjauan']}.",
            metadata: [
                'siswa_nis' => $siswa->nis,
                'status_tinjauan' => $data['status_tinjauan'],
                'catatan' => $data['catatan'] ?? null,
                'status_kelayakan' => $evaluasi['status_kelayakan'],
                'total_dependensi' => $evaluasi['ringkasan_dependensi']['total_dependensi'],
            ],
            user: $request->user(),
        );

        return redirect()->route('admin.alumni.show', $alumni)
            ->with('success', 'Hasil tinjauan administratif berhasil dicatat.');
    }

    /**
     * Nonaktifkan atau aktifkan kembali akun login alumni (eksplisit & terpisah).
     */
    public function alihkanAkun(Request $request, Alumni $alumni)
    {
        $siswa = $alumni->siswa;
        $akun = $siswa->akun;

        if (! $akun) {
            return back()->with('error', 'Alumni ini belum memiliki akun login.');
        }

        $statusBaru = $akun->status === 'aktif' ? 'nonaktif' : 'aktif';
        $akun->update(['status' => $statusBaru]);

        AuditAdmin::catat(
            aksi: 'alumni.status_akun',
            tipeEntitas: 'users',
            entitasId: (string) $akun->id,
            deskripsi: "Mengubah status akun login alumni {$siswa->nama} menjadi {$statusBaru}.",
            metadata: ['nis' => $siswa->nis, 'status_akun' => $statusBaru],
            user: $request->user(),
        );

        return back()->with('success', "Status akun login alumni diubah menjadi {$statusBaru}.");
    }
}
