<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TransisiTahunAjaran;
use App\Support\AuditAdmin;
use App\Support\KesiapanTransisi;
use App\Support\TransisiAkademik;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransisiController extends Controller
{
    private function validasiPeriode(?string $asal, string $tujuan): void
    {
        if (! $asal || strcmp($tujuan, $asal) <= 0 || TahunAjaran::where('kode', $tujuan)->where('aktif', true)->exists()) {
            throw ValidationException::withMessages(['tahun_tujuan' => 'Pilih tahun tujuan nonaktif setelah tahun asal.']);
        }
    }

    /**
     * Tampilan utama manajemen kenaikan kelas & kelulusan siswa (transisi akademik).
     */
    public function index(Request $request)
    {
        $tahunList = TahunAjaran::orderByDesc('kode')->get();
        $tahunAktif = TahunAjaran::where('aktif', true)->first();
        $request->validate([
            'tahun_asal' => ['nullable', 'string', 'exists:tahun_ajaran,kode'],
            'tahun_tujuan' => ['nullable', 'string', 'exists:tahun_ajaran,kode'],
        ]);
        $tahunAsalPilihan = $request->query('tahun_asal') ?: $tahunAktif?->kode;
        $tahunTujuanList = $tahunList->filter(fn ($t) => ! $t->aktif && $tahunAsalPilihan && strcmp($t->kode, $tahunAsalPilihan) > 0)->sortBy('kode')->values();
        $tahunTujuanPilihan = $request->query('tahun_tujuan') ?: $tahunTujuanList->first()?->kode;
        $transisiSelesai = $tahunAsalPilihan && $tahunTujuanPilihan && TransisiTahunAjaran::where('tahun_asal', $tahunAsalPilihan)->where('tahun_tujuan', $tahunTujuanPilihan)->where('status', 'selesai')->exists();
        if ($transisiSelesai && strcmp($tahunTujuanPilihan, $tahunAsalPilihan) > 0) {
            $tahunTujuanList = $tahunTujuanList->merge($tahunList->where('kode', $tahunTujuanPilihan))->unique('kode')->sortBy('kode')->values();
        }
        if ($tahunTujuanPilihan && ! $tahunTujuanList->contains('kode', $tahunTujuanPilihan)) {
            throw ValidationException::withMessages(['tahun_tujuan' => 'Pilih tahun tujuan nonaktif setelah tahun asal.']);
        }

        $transisiList = TransisiTahunAjaran::with(['diprosesOleh', 'tahunAsal', 'tahunTujuan'])
            ->latest('id')
            ->paginate(10, ['*'], 'riwayat_page')->withQueryString();

        // Evaluasi kesiapan transisi akademik (read-only checklist)
        $checklist = KesiapanTransisi::evaluasi($tahunAsalPilihan, $tahunTujuanPilihan);

        $adaTahunTujuan = $tahunTujuanList->isNotEmpty();
        $siswaAsal = Siswa::where('status', 'aktif')->whereIn('kelas_id', Kelas::where('tahun_ajaran_kode', $tahunAsalPilihan)->select('id'));
        $statistik = [
            'tahun_kode' => $tahunAsalPilihan ?? 'Belum Ditentukan',
            'total_siswa' => (clone $siswaAsal)->count(),
            'tingkat_x' => Siswa::where('status', 'aktif')
                ->whereIn('kelas_id', Kelas::where('tahun_ajaran_kode', $tahunAsalPilihan)->where('tingkat', 'X')->select('id'))
                ->count(),
            'tingkat_xi' => Siswa::where('status', 'aktif')
                ->whereIn('kelas_id', Kelas::where('tahun_ajaran_kode', $tahunAsalPilihan)->where('tingkat', 'XI')->select('id'))
                ->count(),
            'tingkat_xii' => Siswa::where('status', 'aktif')
                ->whereIn('kelas_id', Kelas::where('tahun_ajaran_kode', $tahunAsalPilihan)->where('tingkat', 'XII')->select('id'))
                ->count(),
        ];

        return view('admin.transisi.index', [
            'tahunList' => $tahunList,
            'tahunTujuanList' => $tahunTujuanList,
            'tahunAktif' => $tahunAktif,
            'transisiList' => $transisiList,
            'statistik' => $statistik,
            'checklist' => $checklist,
            'tahunAsalPilihan' => $tahunAsalPilihan,
            'tahunTujuanPilihan' => $tahunTujuanPilihan,
            'adaTahunTujuan' => $adaTahunTujuan,
            'transisiSelesai' => $transisiSelesai,
        ]);
    }

    /**
     * Siapkan rombel kelas tujuan pada tahun ajaran baru.
     */
    public function siapkanKelas(Request $request)
    {
        $data = $request->validate([
            'tahun_tujuan' => ['required', 'string', 'exists:tahun_ajaran,kode'],
            'tahun_asal' => ['nullable', 'string', 'exists:tahun_ajaran,kode'],
        ]);

        $this->validasiPeriode($data['tahun_asal'] ?? TahunAjaran::where('aktif', true)->value('kode'), $data['tahun_tujuan']);

        try {
            $hasil = TransisiAkademik::siapkanKelasTujuan(
                $data['tahun_tujuan'],
                $data['tahun_asal'] ?? null
            );

            AuditAdmin::catat(
                aksi: 'kelas.penyiapan_tahunan',
                tipeEntitas: 'kelas',
                entitasId: $data['tahun_tujuan'],
                deskripsi: "Penyiapan rombel kelas tujuan {$data['tahun_tujuan']}: {$hasil['dibuat']} dibuat baru, {$hasil['sudah_ada']} sudah ada.",
                metadata: $hasil,
                jumlah: $hasil['total'],
            );

            return redirect()->route('admin.transisi.index', $request->only('tahun_asal', 'tahun_tujuan'))
                ->with('success', "Penyiapan kelas selesai: {$hasil['dibuat']} rombel dibuat baru, {$hasil['sudah_ada']} sudah ada.");
        } catch (Throwable $e) {
            return redirect()->route('admin.transisi.index', $request->only('tahun_asal', 'tahun_tujuan'))
                ->with('error', 'Gagal menyiapkan kelas: '.$e->getMessage());
        }
    }

    /**
     * Pratinjau kenaikan kelas dan kelulusan (Dry-Run).
     */
    public function pratinjau(Request $request)
    {
        $data = $request->validate([
            'tahun_asal' => ['required', 'string', 'exists:tahun_ajaran,kode'],
            'tahun_tujuan' => ['required', 'string', 'exists:tahun_ajaran,kode'],
        ]);

        $this->validasiPeriode($data['tahun_asal'] ?? TahunAjaran::where('aktif', true)->value('kode'), $data['tahun_tujuan']);

        try {
            $pratinjau = TransisiAkademik::pratinjau($data['tahun_asal'], $data['tahun_tujuan']);

            return view('admin.transisi.pratinjau', [
                'pratinjau' => $pratinjau,
                'kelasListTujuan' => Kelas::where('tahun_ajaran_kode', $data['tahun_tujuan'])->orderBy('nama_kelas')->get(),
            ]);
        } catch (Throwable $e) {
            return redirect()->route('admin.transisi.index', $request->only('tahun_asal', 'tahun_tujuan'))
                ->with('error', 'Gagal memuat pratinjau: '.$e->getMessage());
        }
    }

    /**
     * Eksekusi transaksi transisi tahun ajaran secara penuh.
     */
    public function eksekusi(Request $request)
    {
        $data = $request->validate([
            'tahun_asal' => ['required', 'string', 'exists:tahun_ajaran,kode'],
            'tahun_tujuan' => ['required', 'string', 'exists:tahun_ajaran,kode'],
            'konfirmasi' => ['accepted'],
            'tanggal_lulus' => ['nullable', 'date'],
            'nonaktifkan_akun' => ['nullable', 'boolean'],
            'aktifkan_tahun_tujuan' => ['nullable', 'boolean'],
            'snapshot' => ['required', 'string'],
            'pengecualian' => ['required', 'array'],
            'pengecualian.*' => ['required', 'array:aksi,kelas_id,catatan'],
            'pengecualian.*.aksi' => ['required', 'in:promosi,lulus,tinggal_kelas,tunda_lulus,keluar,pindah'],
            'pengecualian.*.kelas_id' => ['sometimes', 'integer', 'min:1'],
            'pengecualian.*.catatan' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ], [
            'konfirmasi.accepted' => 'Centang kotak konfirmasi sebelum mengeksekusi transisi.',
        ]);

        $this->validasiPeriode($data['tahun_asal'] ?? TahunAjaran::where('aktif', true)->value('kode'), $data['tahun_tujuan']);

        try {
            $hasil = TransisiAkademik::eksekusi(
                $data['tahun_asal'],
                $data['tahun_tujuan'],
                [
                    'snapshot' => $data['snapshot'],
                    'wajib_keputusan_lengkap' => true,
                    'pengecualian' => $data['pengecualian'] ?? [],
                    'nonaktifkan_akun' => (bool) ($data['nonaktifkan_akun'] ?? false),
                    'tanggal_lulus' => $data['tanggal_lulus'] ?? now()->toDateString(),
                    'aktifkan_tahun_tujuan' => (bool) ($data['aktifkan_tahun_tujuan'] ?? false),
                ],
                $request->user()
            );

            return redirect()->route('admin.transisi.index', $request->only('tahun_asal', 'tahun_tujuan'))
                ->with('success', sprintf(
                    'Transisi tahun ajaran berhasil! %d siswa dipromosikan, %d siswa lulus, %d tinggal kelas, %d keluar/pindah.',
                    $hasil['siswa_dipromosikan'],
                    $hasil['siswa_lulus'],
                    $hasil['siswa_tinggal'],
                    $hasil['siswa_pindah']
                ));
        } catch (Throwable $e) {
            return redirect()->route('admin.transisi.index', $request->only('tahun_asal', 'tahun_tujuan'))
                ->with('error', $e instanceof \DomainException || $e instanceof ValidationException
                    ? 'Eksekusi transisi gagal: '.$e->getMessage()
                    : 'Eksekusi transisi gagal. Tidak ada perubahan disimpan; hubungi administrator.');
        }
    }
}
