<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AuditAdmin;
use App\Support\BackupEngine;
use App\Support\CadanganData;
use App\Support\ImporEngine;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ManajemenDataController extends Controller
{
    /**
     * Landing Hub Manajemen Data: Dua Kartu (Impor Data & Ekspor/Cadangkan Data).
     */
    public function index()
    {
        $kesehatan = BackupEngine::statusKesehatan();

        return view('admin.manajemen_data.index', [
            'kesehatan' => $kesehatan,
            'totalTabel' => count(BackupEngine::TABEL_DATABASE),
            'kategoriImpor' => [
                ImporEngine::KATEGORI_SISWA => 'Data Siswa & Akun Siswa',
                ImporEngine::KATEGORI_GURU => 'Data Guru & Akun Guru',
            ],
        ]);
    }

    /**
     * Halaman Panduan Lengkap Manajemen Data.
     */
    public function panduan()
    {
        return view('admin.manajemen_data.panduan');
    }

    /**
     * Wizard Impor Data: Form Pemilihan Kategori, Mode, dan Upload File.
     */
    public function imporIndex()
    {
        return view('admin.manajemen_data.impor_index', [
            'kategoriMendukungGanti' => ImporEngine::KATEGORI_MENDUKUNG_GANTI,
        ]);
    }

    /**
     * Memproses upload file dan menampilkan Pratinjau Terklasifikasi.
     */
    public function imporPratinjau(Request $request)
    {
        $data = $request->validate([
            'kategori' => ['required', 'string', 'in:siswa,guru'],
            'mode' => ['required', 'string', 'in:tambah,perbarui,ganti_keseluruhan'],
            'berkas' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
        ]);

        $kategori = $data['kategori'];
        $mode = $data['mode'];

        // Simpan file sementara di storage lokal privat
        $filename = 'preview_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $data['berkas']->getClientOriginalExtension();
        $tempPath = $data['berkas']->storeAs('temp_impor', $filename, 'local');
        $fullPath = Storage::disk('local')->path($tempPath);

        try {
            $preview = ImporEngine::periksa($fullPath, $kategori, $mode);
            if ($preview['fatal'] !== null) {
                Storage::disk('local')->delete($tempPath);
                return back()->withInput()->withErrors(['berkas' => $preview['fatal']]);
            }

            return view('admin.manajemen_data.impor_pratinjau', [
                'kategori' => $kategori,
                'mode' => $mode,
                'tempPath' => $tempPath,
                'filename' => $data['berkas']->getClientOriginalName(),
                'preview' => $preview,
            ]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($tempPath);
            return back()->withInput()->withErrors(['berkas' => 'Gagal membaca berkas impor: ' . $e->getMessage()]);
        }
    }

    /**
     * Melakukan commit impor data yang telah dipratinjau.
     */
    public function imporCommit(Request $request)
    {
        $data = $request->validate([
            'temp_path' => ['required', 'string'],
            'kategori' => ['required', 'string', 'in:siswa,guru'],
            'mode' => ['required', 'string', 'in:tambah,perbarui,ganti_keseluruhan'],
            'fingerprint' => ['required', 'string'],
            'exclusions' => ['nullable', 'array'],
            'exclusions.*' => ['integer'],
            'konfirmasi_ganti' => ['nullable', 'string'],
        ]);

        if ($data['mode'] === ImporEngine::MODE_GANTI && ($data['konfirmasi_ganti'] ?? '') !== 'SAYA_YAKIN_GANTI_KESELURUHAN') {
            return back()->withErrors(['konfirmasi_ganti' => 'Anda wajib mengetik konfirmasi dengan benar sebelum menjalankan mode Ganti Keseluruhan.']);
        }

        $fullPath = Storage::disk('local')->path($data['temp_path']);
        if (! file_exists($fullPath)) {
            return redirect()->route('admin.manajemen-data.impor.index')->withErrors(['berkas' => 'Sesi pratinjau telah berakhir. Silakan unggah berkas kembali.']);
        }

        try {
            $hasil = ImporEngine::commit(
                $fullPath,
                $data['kategori'],
                $data['mode'],
                $data['fingerprint'],
                $data['exclusions'] ?? [],
                null,
                $request->user()
            );

            Storage::disk('local')->delete($data['temp_path']);

            return redirect()->route('admin.manajemen-data.index')->with('status', "Impor {$data['kategori']} mode {$data['mode']} selesai: {$hasil['baru']} baru, {$hasil['diperbarui']} diperbarui, {$hasil['dinonaktifkan']} dinonaktifkan, {$hasil['dilewati']} dilewati.");
        } catch (Throwable $e) {
            return back()->withErrors(['error' => 'Gagal mengeksekusi impor: ' . $e->getMessage()]);
        }
    }

    /**
     * Dashboard Ekspor Administratif & Cadangan Database.
     */
    public function cadanganIndex()
    {
        $arsipList = DB::table('cadangan_arsip')
            ->latest('id')
            ->paginate(15);

        $kesehatan = BackupEngine::statusKesehatan();

        return view('admin.manajemen_data.cadangan_index', [
            'arsipList' => $arsipList,
            'kesehatan' => $kesehatan,
            'tabelList' => BackupEngine::TABEL_DATABASE,
        ]);
    }

    /**
     * Membuat cadangan database manual (sql_gz, sql, zip).
     */
    public function cadanganStore(Request $request, BackupEngine $engine)
    {
        $data = $request->validate([
            'format' => ['required', 'string', 'in:sql_gz,sql,zip_berkas'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            if ($data['format'] === BackupEngine::FORMAT_ZIP) {
                $hasil = $engine->buatBackupBerkasPenting('manual', $request->user(), $data['catatan'] ?? null);
            } else {
                $hasil = $engine->buatBackupDatabase($data['format'], 'manual', $request->user(), $data['catatan'] ?? null);
            }

            AuditAdmin::catat(
                aksi: 'cadangan.manual_create',
                tipeEntitas: 'cadangan',
                entitasId: $hasil['nama_berkas'],
                deskripsi: "Membuat cadangan database manual {$hasil['nama_berkas']}",
                metadata: $hasil,
                status: 'berhasil',
                jumlah: 1
            );

            return back()->with('status', "Cadangan {$hasil['nama_berkas']} berhasil dibuat dan diverifikasi.");
        } catch (Throwable $e) {
            return back()->withErrors(['format' => 'Gagal membuat cadangan: ' . $e->getMessage()]);
        }
    }

    /**
     * Download privat file cadangan terenkripsi/terverifikasi.
     */
    public function cadanganDownload(int $id, Request $request)
    {
        $arsip = DB::table('cadangan_arsip')->where('id', $id)->first();
        if (! $arsip) {
            abort(404, 'Arsip cadangan tidak ditemukan.');
        }

        $lokasi = $arsip->lokasi_privat;
        if (! Storage::disk(BackupEngine::DISK)->exists($lokasi)) {
            abort(404, 'Berkas fisik cadangan tidak ditemukan pada penyimpanan privat.');
        }

        AuditAdmin::catat(
            aksi: 'cadangan.unduh',
            tipeEntitas: 'cadangan',
            entitasId: $arsip->nama_berkas,
            deskripsi: "Mengunduh berkas cadangan {$arsip->nama_berkas}",
            metadata: ['arsip_id' => $arsip->id, 'ukuran' => $arsip->ukuran_bytes],
            status: 'berhasil',
            jumlah: 1
        );

        $path = Storage::disk(BackupEngine::DISK)->path($lokasi);
        $response = response()->download($path, $arsip->nama_berkas, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    /**
     * Toggle status pin/penting cadangan.
     */
    public function cadanganTogglePin(int $id)
    {
        $arsip = DB::table('cadangan_arsip')->where('id', $id)->first();
        if (! $arsip) {
            abort(404);
        }

        $newVal = ! (bool) $arsip->penting;
        DB::table('cadangan_arsip')->where('id', $id)->update(['penting' => $newVal, 'updated_at' => now()]);

        return back()->with('status', "Status pin cadangan #{$id} berhasil diperbarui.");
    }

    /**
     * Menghapus arsip cadangan secara aman (proteksi pinned dan last-valid).
     */
    public function cadanganHapus(int $id)
    {
        $arsip = DB::table('cadangan_arsip')->where('id', $id)->first();
        if (! $arsip) {
            abort(404);
        }

        if ($arsip->penting) {
            return back()->withErrors(['delete' => 'Cadangan bertanda PENTING tidak boleh dihapus. Hapus tanda pin terlebih dahulu.']);
        }

        $lastValidId = DB::table('cadangan_arsip')
            ->where('status_validasi', 'terverifikasi')
            ->latest('id')
            ->value('id');

        if ((int) $arsip->id === (int) $lastValidId) {
            return back()->withErrors(['delete' => 'Cadangan valid terakhir tidak boleh dihapus demi keselamatan sistem pemulihan bencana.']);
        }

        if (Storage::disk(BackupEngine::DISK)->exists($arsip->lokasi_privat)) {
            Storage::disk(BackupEngine::DISK)->delete($arsip->lokasi_privat);
        }

        DB::table('cadangan_arsip')->where('id', $id)->delete();

        AuditAdmin::catat(
            aksi: 'cadangan.hapus',
            tipeEntitas: 'cadangan',
            entitasId: $arsip->nama_berkas,
            deskripsi: "Menghapus arsip cadangan {$arsip->nama_berkas}",
            metadata: ['id' => $id],
            status: 'berhasil',
            jumlah: 1
        );

        return back()->with('status', "Cadangan {$arsip->nama_berkas} berhasil dihapus.");
    }

    /**
     * Ekspor administratif CSV aman per tabel.
     */
    public function eksporCsv(Request $request, BackupEngine $engine): BinaryFileResponse
    {
        $data = $request->validate([
            'tabel' => ['required', 'string', 'in:' . implode(',', BackupEngine::TABEL_DATABASE)],
        ]);

        $tabel = $data['tabel'];
        $cursor = DB::table($tabel)->cursor();

        AuditAdmin::catat(
            aksi: 'ekspor.csv',
            tipeEntitas: 'ekspor',
            entitasId: $tabel,
            deskripsi: "Ekspor administratif CSV tabel {$tabel}",
            metadata: ['tabel' => $tabel],
            status: 'berhasil',
            jumlah: DB::table($tabel)->count()
        );

        return $engine->eksporCsvAman($tabel, $cursor);
    }

    /**
     * Dashboard Uji Pemulihan Staging Terisolasi (Subfase 8.5).
     * Terpisah dari Ekspor/Cadangan; Production Restore tetap DISABLED.
     */
    public function pemulihanIndex()
    {
        $arsipList = DB::table('cadangan_arsip')
            ->whereIn('format', [BackupEngine::FORMAT_SQL_GZ, BackupEngine::FORMAT_SQL])
            ->latest('id')
            ->paginate(15);

        return view('admin.manajemen_data.pemulihan_index', [
            'arsipList' => $arsipList,
            'isRunnerAvailable' => file_exists(base_path('tests/Integration/Phase2B_runner.py')),
        ]);
    }

    /**
     * Validasi Arsip & Simulasi Pengujian Restore Staging Terisolasi.
     */
    public function pemulihanUjiStaging(Request $request, \App\Support\RestoreStagingEngine $stagingEngine)
    {
        $data = $request->validate([
            'arsip_id' => ['required', 'integer', 'exists:cadangan_arsip,id'],
        ]);

        $arsip = DB::table('cadangan_arsip')->where('id', $data['arsip_id'])->first();
        if (! Storage::disk(BackupEngine::DISK)->exists($arsip->lokasi_privat)) {
            return back()->withErrors(['arsip' => 'Berkas arsip fisik tidak ditemukan di private storage.']);
        }

        $fullPath = Storage::disk(BackupEngine::DISK)->path($arsip->lokasi_privat);
        $manifest = json_decode($arsip->metadata_manifest ?? '[]', true) ?: [];

        // 1. Validasi Berkas & Checksum
        $val = $stagingEngine->validasiArsip($fullPath, $arsip->checksum_sha256, $manifest);
        if (! $val['valid']) {
            return back()->withErrors(['arsip' => 'Validasi integritas arsip gagal: ' . ($val['error'] ?? 'Checksum atau format rusak.')]);
        }

        // 2. Validasi Struktur SQL (Allowlist)
        $sqlVal = $stagingEngine->validasiStrukturSql($fullPath);
        if (! $sqlVal['valid']) {
            return back()->withErrors(['arsip' => 'Struktur SQL ditolak oleh allowlist keamanan: ' . ($sqlVal['error'] ?? 'Instruksi tidak diizinkan.')]);
        }

        // 3. Verifikasi ketersediaan runner staging disposable
        $runnerScript = base_path('tests/Integration/Phase2B_runner.py');
        if (! file_exists($runnerScript)) {
            return back()->withErrors(['staging' => 'Runner MySQL disposable NOT AVAILABLE. Uji staging ditolak secara fail-closed demi keamanan produksi.']);
        }

        // 4. Buat laporan dampak read-only yang aman (tanpa password, hash, atau token)
        $stagingReport = $stagingEngine->verifikasiIntegritasDanDampak(DB::connection(), $manifest);

        AuditAdmin::catat(
            aksi: 'pemulihan.uji_staging',
            tipeEntitas: 'cadangan_arsip',
            entitasId: (string) $arsip->id,
            deskripsi: "Menjalankan uji verifikasi staging terisolasi untuk arsip {$arsip->nama_berkas}",
            metadata: [
                'checksum' => $arsip->checksum_sha256,
                'status' => $stagingReport['status'],
                'tables_checked' => $stagingReport['tables_checked'],
            ],
            status: 'berhasil'
        );

        $approvalToken = Crypt::encryptString(json_encode([
            'arsip_id' => $arsip->id,
            'checksum' => $arsip->checksum_sha256,
            'status' => $stagingReport['status'],
            'created_at' => now()->timestamp,
            'admin_id' => auth()->id(),
        ], JSON_THROW_ON_ERROR));

        return view('admin.manajemen_data.pemulihan_laporan', [
            'arsip' => $arsip,
            'sqlVal' => $sqlVal,
            'report' => $stagingReport,
            'approvalToken' => $approvalToken,
        ]);
    }

    /**
     * Persetujuan Administratif Hasil Uji Staging dengan Reautentikasi Sandi.
     * Catatan: Persetujuan ini bersifat administratif dan TIDAK mengeksekusi restore live ke produksi.
     */
    public function pemulihanSetujui(Request $request)
    {
        $data = $request->validate([
            'approval_token' => ['required', 'string'],
            'password' => ['required', 'string'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        // Verifikasi Reautentikasi Sandi Admin
        if (! \Illuminate\Support\Facades\Hash::check($data['password'], auth()->user()->password)) {
            return back()->withErrors(['password' => 'Kata sandi reautentikasi salah. Tindakan persetujuan ditolak.']);
        }

        try {
            $payload = json_decode(Crypt::decryptString($data['approval_token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return back()->withErrors(['approval' => 'Token persetujuan tidak valid atau rusak.']);
        }

        // Cek masa berlaku token persetujuan (maks 15 menit)
        if (now()->timestamp - ($payload['created_at'] ?? 0) > 900) {
            return back()->withErrors(['approval' => 'Sesi persetujuan telah kedaluwarsa (>15 menit). Harap ulangi uji staging.']);
        }

        if ((int) ($payload['admin_id'] ?? 0) !== (int) auth()->id()) {
            return back()->withErrors(['approval' => 'Token persetujuan tidak cocok dengan identitas admin yang sedang aktif.']);
        }

        $arsip = DB::table('cadangan_arsip')->where('id', $payload['arsip_id'])->first();
        if (! $arsip || $arsip->checksum_sha256 !== $payload['checksum']) {
            return back()->withErrors(['approval' => 'Kondisi arsip telah berubah sejak uji staging dijalankan. Persetujuan dibatalkan.']);
        }

        AuditAdmin::catat(
            aksi: 'pemulihan.persetujuan_administratif',
            tipeEntitas: 'cadangan_arsip',
            entitasId: (string) $arsip->id,
            deskripsi: "Admin " . auth()->user()->nama . " memberikan persetujuan administratif hasil staging arsip {$arsip->nama_berkas}",
            metadata: [
                'checksum' => $payload['checksum'],
                'catatan' => $data['catatan'] ?? null,
                'staging_status' => $payload['status'],
                'live_cutover' => 'DISABLED_BY_POLICY',
            ],
            status: 'berhasil'
        );

        return redirect()->route('admin.manajemen-data.pemulihan.index')
            ->with('status', "Persetujuan administratif untuk arsip {$arsip->nama_berkas} berhasil dicatat dalam audit trail. (Catatan: Restore produksi tetap DISABLED).");
    }
}
