<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TransisiTahunAjaran;
use App\Support\AuditAdmin;
use App\Support\TransisiAkademik;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransisiController extends Controller
{
    /**
     * Tampilan utama manajemen tahun ajaran dan transisi akademik.
     */
    public function index()
    {
        $tahunList = TahunAjaran::withCount('kelas')->orderByDesc('kode')->get();
        $tahunAktif = TahunAjaran::berjalan();

        $transisiList = TransisiTahunAjaran::with(['diprosesOleh', 'tahunAsal', 'tahunTujuan'])
            ->latest('id')
            ->get();

        // Hitung statistik siswa per tingkat di tahun ajaran aktif
        $statistik = [
            'total_siswa' => Siswa::where('status', 'aktif')->count(),
            'tingkat_x' => Siswa::where('status', 'aktif')
                ->whereIn('kelas_id', Kelas::where('tingkat', 'X')->select('id'))
                ->count(),
            'tingkat_xi' => Siswa::where('status', 'aktif')
                ->whereIn('kelas_id', Kelas::where('tingkat', 'XI')->select('id'))
                ->count(),
            'tingkat_xii' => Siswa::where('status', 'aktif')
                ->whereIn('kelas_id', Kelas::where('tingkat', 'XII')->select('id'))
                ->count(),
        ];

        return view('admin.transisi.index', [
            'tahunList' => $tahunList,
            'tahunAktif' => $tahunAktif,
            'transisiList' => $transisiList,
            'statistik' => $statistik,
        ]);
    }

    /**
     * Tambah tahun ajaran baru.
     */
    public function storeTahunAjaran(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:9', 'unique:tahun_ajaran,kode'],
            'mulai' => ['nullable', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'aktif' => ['nullable', 'boolean'],
        ], [
            'kode.unique' => 'Tahun ajaran dengan kode ini sudah terdaftar.',
            'selesai.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai.',
        ]);

        if (! TahunAjaran::formatValid($data['kode'])) {
            throw ValidationException::withMessages([
                'kode' => 'Format kode tahun ajaran harus berupa "YYYY/YYYY" berurutan (contoh: 2027/2028).',
            ]);
        }

        $tahun = TahunAjaran::create([
            'kode' => $data['kode'],
            'mulai' => $data['mulai'] ?? null,
            'selesai' => $data['selesai'] ?? null,
            'aktif' => (bool) ($data['aktif'] ?? false),
        ]);

        AuditAdmin::catat(
            aksi: 'tahun_ajaran.tambah',
            tipeEntitas: 'tahun_ajaran',
            entitasId: $tahun->kode,
            deskripsi: "Menambahkan tahun ajaran baru: {$tahun->kode}",
            metadata: ['kode' => $tahun->kode, 'aktif' => $tahun->aktif],
        );

        return redirect()->route('admin.transisi.index')
            ->with('success', "Tahun ajaran {$tahun->kode} berhasil ditambahkan.");
    }

    /**
     * Aktifkan tahun ajaran tertentu.
     */
    public function aktifkanTahunAjaran(TahunAjaran $tahunAjaran)
    {
        $tahunLama = TahunAjaran::berjalan()?->kode;
        $tahunAjaran->aktifkan();

        AuditAdmin::catat(
            aksi: 'tahun_ajaran.aktifkan',
            tipeEntitas: 'tahun_ajaran',
            entitasId: $tahunAjaran->kode,
            deskripsi: "Mengaktifkan tahun ajaran {$tahunAjaran->kode} (menggantikan {$tahunLama})",
            metadata: ['tahun_lama' => $tahunLama, 'tahun_baru' => $tahunAjaran->kode],
        );

        return redirect()->route('admin.transisi.index')
            ->with('success', "Tahun ajaran {$tahunAjaran->kode} kini aktif.");
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

            return redirect()->route('admin.transisi.index')
                ->with('success', "Penyiapan kelas selesai: {$hasil['dibuat']} rombel dibuat baru, {$hasil['sudah_ada']} sudah ada.");
        } catch (Throwable $e) {
            return redirect()->route('admin.transisi.index')
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

        try {
            $pratinjau = TransisiAkademik::pratinjau($data['tahun_asal'], $data['tahun_tujuan']);

            return view('admin.transisi.pratinjau', [
                'pratinjau' => $pratinjau,
                'kelasListTujuan' => Kelas::where('tahun_ajaran_kode', $data['tahun_tujuan'])->orderBy('nama_kelas')->get(),
            ]);
        } catch (Throwable $e) {
            return redirect()->route('admin.transisi.index')
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
            'pengecualian' => ['nullable', 'array'],
        ], [
            'konfirmasi.accepted' => 'Centang kotak konfirmasi sebelum mengeksekusi transisi.',
        ]);

        try {
            $hasil = TransisiAkademik::eksekusi(
                $data['tahun_asal'],
                $data['tahun_tujuan'],
                [
                    'pengecualian' => $data['pengecualian'] ?? [],
                    'nonaktifkan_akun' => (bool) ($data['nonaktifkan_akun'] ?? false),
                    'tanggal_lulus' => $data['tanggal_lulus'] ?? now()->toDateString(),
                    'aktifkan_tahun_tujuan' => (bool) ($data['aktifkan_tahun_tujuan'] ?? false),
                ],
                $request->user()
            );

            return redirect()->route('admin.transisi.index')
                ->with('success', sprintf(
                    'Transisi tahun ajaran berhasil! %d siswa dipromosikan, %d siswa lulus, %d tinggal kelas, %d keluar/pindah.',
                    $hasil['siswa_dipromosikan'],
                    $hasil['siswa_lulus'],
                    $hasil['siswa_tinggal'],
                    $hasil['siswa_pindah']
                ));
        } catch (Throwable $e) {
            return redirect()->route('admin.transisi.index')
                ->with('error', 'Eksekusi transisi gagal: '.$e->getMessage());
        }
    }
}
