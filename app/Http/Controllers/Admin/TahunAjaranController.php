<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use App\Services\AktivasiTahunAjaran;
use App\Services\KalenderTahunAjaran;
use App\Support\AuditAdmin;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TahunAjaranController extends Controller
{
    /**
     * Tampilan utama manajemen periode tahun ajaran.
     */
    public function index()
    {
        $tahunList = TahunAjaran::withCount('kelas')->orderByDesc('kode')->get();
        $tahunAktif = TahunAjaran::where('aktif', true)->first();

        // Hitung statistik siswa aktif per tingkat pada periode aktif saat ini
        // Hindari double counting dari riwayat kelas; abaikan siswa alumni dan nonaktif
        $distribusiSiswa = [
            'X' => 0,
            'XI' => 0,
            'XII' => 0,
            'total' => 0,
        ];

        if ($tahunAktif) {
            $counts = \App\Models\Siswa::query()
                ->where('siswa.status', 'aktif')
                ->whereNotExists(function ($q) {
                    $q->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('alumni')
                        ->whereColumn('alumni.siswa_nis', 'siswa.nis');
                })
                ->join('kelas', 'kelas.id', '=', 'siswa.kelas_id')
                ->where('kelas.tahun_ajaran_kode', $tahunAktif->kode)
                ->select('kelas.tingkat', \Illuminate\Support\Facades\DB::raw('count(distinct siswa.nis) as total'))
                ->groupBy('kelas.tingkat')
                ->pluck('total', 'tingkat')
                ->toArray();

            $distribusiSiswa['X'] = (int) ($counts['X'] ?? 0);
            $distribusiSiswa['XI'] = (int) ($counts['XI'] ?? 0);
            $distribusiSiswa['XII'] = (int) ($counts['XII'] ?? 0);
            $distribusiSiswa['total'] = $distribusiSiswa['X'] + $distribusiSiswa['XI'] + $distribusiSiswa['XII'];
        }

        return view('admin.tahun-ajaran.index', [
            'tahunList' => $tahunList,
            'tahunAktif' => $tahunAktif,
            'distribusiSiswa' => $distribusiSiswa,
        ]);
    }

    /**
     * Simpan tahun ajaran baru (bawaan nonaktif).
     */
    public function store(Request $request)
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

        $tahun = \App\Support\AcademicOperation::run(function () use ($data) {
            app(KalenderTahunAjaran::class)->validasiTanggal($data['mulai'] ?? null, $data['selesai'] ?? null);
            // Creation never activates, even with a crafted request.
            $aktif = false;

            $tahun = TahunAjaran::create([
                'kode' => $data['kode'],
                'mulai' => $data['mulai'] ?? null,
                'selesai' => $data['selesai'] ?? null,
                'aktif' => $aktif,
            ]);

            AuditAdmin::catatKritis(
                aksi: 'tahun_ajaran.tambah',
                tipeEntitas: 'tahun_ajaran',
                entitasId: $tahun->kode,
                deskripsi: "Menambahkan tahun ajaran baru: {$tahun->kode}",
                metadata: ['kode' => $tahun->kode, 'aktif' => $tahun->aktif],
            );

            return $tahun;
        });

        return redirect()->route('admin.tahun-ajaran.index')
            ->with('success', "Tahun ajaran {$tahun->kode} berhasil ditambahkan.");
    }

    /**
     * Aktifkan tahun ajaran tertentu secara eksplisit.
     */
    public function siapkanBerikutnya(KalenderTahunAjaran $kalender)
    {
        $tahun = $kalender->siapkanBerikutnya();

        return redirect()->route('admin.tahun-ajaran.index')
            ->with('success', "Tahun ajaran {$tahun->kode} tersedia. Tinjau tanggal; aktivasi tetap manual.");
    }

    public function update(Request $request, TahunAjaran $tahunAjaran, KalenderTahunAjaran $kalender)
    {
        $data = $request->validate([
            'mulai' => ['nullable', 'date', 'required_with:selesai'],
            'selesai' => ['nullable', 'date', 'required_with:mulai', 'after_or_equal:mulai'],
        ]);
        \App\Support\AcademicOperation::run(function () use ($data, $tahunAjaran, $kalender) {
            $tahunAjaran = TahunAjaran::whereKey($tahunAjaran->kode)->lockForUpdate()->firstOrFail();
            $kalender->validasiTanggal($data['mulai'] ?? null, $data['selesai'] ?? null, $tahunAjaran->kode);
            $sebelum = ['mulai' => $tahunAjaran->mulai?->toDateString(), 'selesai' => $tahunAjaran->selesai?->toDateString()];
            $tahunAjaran->update(['mulai' => $data['mulai'] ?? null, 'selesai' => $data['selesai'] ?? null]);
            AuditAdmin::catatKritis('tahun_ajaran.ubah_tanggal', 'tahun_ajaran', $tahunAjaran->kode,
                "Mengubah tanggal tahun ajaran {$tahunAjaran->kode}", ['sebelum' => $sebelum, 'sesudah' => $data]);

        });

        return redirect()->route('admin.tahun-ajaran.index')->with('success', 'Tanggal tahun ajaran disimpan. Status aktif tidak berubah.');
    }

    public function aktifkan(Request $request, TahunAjaran $tahunAjaran)
    {
        $request->validate(['konfirmasi' => ['required', 'accepted']]);
        try {
            app(AktivasiTahunAjaran::class)->aktifkan($tahunAjaran->kode, $request->user());
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['tahun_ajaran' => $e->getMessage()]);
        }

        return redirect()->route('admin.tahun-ajaran.index')
            ->with('success', "Tahun ajaran {$tahunAjaran->kode} kini aktif.");
    }
}
