<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
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
        $tahunAktif = TahunAjaran::berjalan();

        return view('admin.tahun-ajaran.index', [
            'tahunList' => $tahunList,
            'tahunAktif' => $tahunAktif,
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

        // Bawaan nonaktif kecuali dicentang secara sadar
        $aktif = (bool) ($data['aktif'] ?? false);

        $tahun = TahunAjaran::create([
            'kode' => $data['kode'],
            'mulai' => $data['mulai'] ?? null,
            'selesai' => $data['selesai'] ?? null,
            'aktif' => $aktif,
        ]);

        AuditAdmin::catat(
            aksi: 'tahun_ajaran.tambah',
            tipeEntitas: 'tahun_ajaran',
            entitasId: $tahun->kode,
            deskripsi: "Menambahkan tahun ajaran baru: {$tahun->kode}",
            metadata: ['kode' => $tahun->kode, 'aktif' => $tahun->aktif],
        );

        return redirect()->route('admin.tahun-ajaran.index')
            ->with('success', "Tahun ajaran {$tahun->kode} berhasil ditambahkan.");
    }

    /**
     * Aktifkan tahun ajaran tertentu secara eksplisit.
     */
    public function aktifkan(TahunAjaran $tahunAjaran)
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

        return redirect()->route('admin.tahun-ajaran.index')
            ->with('success', "Tahun ajaran {$tahunAjaran->kode} kini aktif.");
    }
}
