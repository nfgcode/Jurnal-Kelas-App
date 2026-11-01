<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Support\AuditAdmin;
use App\Support\BantuanImporAi;
use App\Support\ImporPengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Bulk import of siswa and guru accounts from a spreadsheet, admin-only.
 *
 * Three steps, deliberately: download a template, upload the filled-in file and
 * read the verdict on every row, then confirm. The uploaded file is kept on
 * disk between the preview and the confirmation so the second step reads the
 * exact bytes the first one judged — re-uploading it would let the file change
 * underneath the preview the admin actually approved.
 */
class ImporController extends Controller
{
    /** Where an uploaded file waits between preview and confirmation. */
    private const DISK = 'local';

    private const FOLDER = 'impor';

    /**
     * How long a previewed-but-unconfirmed upload is kept. Long enough for an
     * admin to read the preview, fetch a coffee and come back; short enough that
     * a spreadsheet full of names and emails is not left on disk indefinitely by
     * someone who simply closed the tab.
     */
    private const UMUR_MAKS_JAM = 6;

    /**
     * The import screen: what the template looks like and how to fill it.
     */
    public function index()
    {
        $this->bersihkanBerkasLama();

        return view('admin.impor.index', [
            'kolomSiswa' => ImporPengguna::kolom('siswa'),
            'kolomGuru' => ImporPengguna::kolom('guru'),
            'jumlahKelas' => Kelas::count(),
            'promptSiswa' => BantuanImporAi::promptSiswa(),
            'promptGuru' => BantuanImporAi::promptGuru(),
            'peringatanPrivasi' => BantuanImporAi::PERINGATAN_PRIVASI,
        ]);
    }

    /**
     * The blank .xlsx template for one kind of account.
     */
    public function template(Request $request)
    {
        $jenis = $this->jenis($request);

        return ImporPengguna::template($jenis);
    }

    /**
     * Read the uploaded file and show what importing it would do — without
     * writing anything.
     */
    public function pratinjau(Request $request)
    {
        // Remove before validation: framework error redirects must never flash this field.
        $password = $request->input('password_bawaan');
        $request->request->remove('password_bawaan');
        $data = validator($request->all() + ['password_bawaan' => $password], [
            'jenis' => ['required', Rule::in(ImporPengguna::JENIS)],
            // 10 MB is far above any realistic roster and well inside the
            // default PHP upload limits, so a legitimate file never bounces.
            'berkas' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
            'password_bawaan' => ['nullable', 'string', 'min:8', 'max:255'],
            'perbarui' => ['nullable', 'boolean'],
        ])->validate();

        $this->bersihkanBerkasLama();

        $jenis = $data['jenis'];
        $nama = ImporPengguna::namaSimpanan($data['berkas']->getClientOriginalName());
        $data['berkas']->storeAs(self::FOLDER, $nama, self::DISK);

        try {
            $hasil = ImporPengguna::periksa(
                Storage::disk(self::DISK)->path(self::FOLDER.'/'.$nama),
                $jenis,
                $data['password_bawaan'] ?? null,
                (bool) ($data['perbarui'] ?? false),
            );
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete(self::FOLDER.'/'.$nama);

            return back()->with('error', 'Berkas impor tidak dapat diproses.')->withInput($request->except('password_bawaan', 'berkas'));
        }

        if ($hasil['fatal']) {
            Storage::disk(self::DISK)->delete(self::FOLDER.'/'.$nama);

            return back()->with('error', 'Berkas impor tidak sesuai template.')->withInput($request->except('password_bawaan', 'berkas'));
        }

        if (! $request->session()->has('impor_owner')) {
            $request->session()->put('impor_owner', bin2hex(random_bytes(32)));
        }
        $request->session()->put('impor.'.$nama, Crypt::encryptString(json_encode([
            'owner' => (string) $request->user()->getAuthIdentifier(),
            'session' => hash('sha256', (string) $request->session()->get('impor_owner')),
            'expires' => now()->addHours(self::UMUR_MAKS_JAM)->timestamp,
            'hash' => hash_file('sha256', Storage::disk(self::DISK)->path(self::FOLDER.'/'.$nama)),
            'jenis' => $jenis, 'perbarui' => (bool) ($data['perbarui'] ?? false),
            'password' => $data['password_bawaan'] ?? null,
        ], JSON_THROW_ON_ERROR)));
        foreach ($hasil['baris'] as &$baris) {
            unset($baris['data']['password'], $baris['data']['_password']);
        }
        unset($baris);

        return view('admin.impor.pratinjau', [
            'jenis' => $jenis,
            'kolom' => ImporPengguna::kolom($jenis),
            'hasil' => $hasil,
            'berkas' => $nama,
            'namaAsli' => $data['berkas']->getClientOriginalName(),
            'perbarui' => (bool) ($data['perbarui'] ?? false),
        ]);
    }

    /**
     * Commit the previewed file. It is re-read and re-validated rather than
     * trusted from the form: the rows are the file's, not the browser's, and
     * the database may have moved on since the preview was rendered.
     */
    public function simpan(Request $request)
    {
        $request->request->remove('password_bawaan');
        $data = $request->validate([
            'jenis' => ['required', Rule::in(ImporPengguna::JENIS)],
            // A ULID plus extension — anything else is not a name this app wrote,
            // and letting it through would be a path traversal into storage.
            'berkas' => ['required', 'string', 'regex:/^[0-9A-Za-z]{26}\.(xlsx|csv)$/'],
        ]);

        $path = self::FOLDER.'/'.$data['berkas'];

        try {
            $encrypted = $request->session()->pull('impor.'.$data['berkas']);
            $preview = $encrypted ? json_decode(Crypt::decryptString($encrypted), true, 16, JSON_THROW_ON_ERROR) : null;
            if (! $preview || $preview['owner'] !== (string) $request->user()->getAuthIdentifier()
                || $preview['session'] !== hash('sha256', (string) $request->session()->get('impor_owner'))
                || $preview['expires'] <= now()->timestamp || $preview['jenis'] !== $data['jenis']
                || ! Storage::disk(self::DISK)->exists($path)
                || ! hash_equals($preview['hash'], hash_file('sha256', Storage::disk(self::DISK)->path($path)))) {
                throw new RuntimeException('Preview unavailable.');
            }
            // Atomic claim: concurrent stale-session confirmations cannot reuse the file.
            $claimed = self::FOLDER.'/'.bin2hex(random_bytes(12)).'.'.pathinfo($path, PATHINFO_EXTENSION);
            if (! @rename(Storage::disk(self::DISK)->path($path), Storage::disk(self::DISK)->path($claimed))) {
                throw new RuntimeException('Preview unavailable.');
            }
            $path = $claimed;
            $data['password_bawaan'] = $preview['password'];
            $data['perbarui'] = $preview['perbarui'];
        } catch (Throwable $e) {
            return redirect()->route('admin.impor.index')->with('error', 'Pratinjau tidak tersedia atau telah berubah. Silakan unggah ulang.');
        }

        if (! Storage::disk(self::DISK)->exists($path)) {
            return redirect()->route('admin.impor.index')
                ->with('error', 'Berkas unggahan sudah tidak tersedia. Silakan unggah ulang.');
        }

        try {
            $hasil = ImporPengguna::periksa(
                Storage::disk(self::DISK)->path($path),
                $data['jenis'],
                $data['password_bawaan'] ?? null,
                (bool) ($data['perbarui'] ?? false),
            );
            $ringkas = ImporPengguna::simpan($hasil['baris'], $data['jenis']);
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($path);

            AuditAdmin::catat(
                aksi: "impor.{$data['jenis']}",
                tipeEntitas: 'impor',
                entitasId: $data['berkas'],
                deskripsi: "Impor {$data['jenis']} gagal diproses.",
                metadata: ['jenis' => $data['jenis'], 'berkas' => $data['berkas']],
                status: 'gagal',
            );

            return redirect()->route('admin.impor.index')->with('error', 'Impor tidak dapat diproses. Silakan periksa berkas dan unggah ulang.');
        }

        AuditAdmin::imporSelesai(
            $data['jenis'],
            (int) ($ringkas['baru'] ?? 0),
            (int) ($ringkas['perbarui'] ?? 0),
            (int) ($ringkas['dilewati'] ?? 0),
            $data['berkas'],
        );

        // The file has served its purpose; keeping it would leave a pile of
        // spreadsheets full of personal data on disk.
        Storage::disk(self::DISK)->delete($path);

        $pesan = 'Impor '.$data['jenis'].' selesai: '.$ringkas['baru'].' akun baru';

        if ($ringkas['perbarui']) {
            $pesan .= ', '.$ringkas['perbarui'].' diperbarui';
        }

        if ($ringkas['dilewati']) {
            $pesan .= ', '.$ringkas['dilewati'].' dilewati karena masih bermasalah';
        }

        return redirect()->route($data['jenis'] === 'guru' ? 'admin.guru.index' : 'admin.siswa.index')
            ->with($ringkas['baru'] || $ringkas['perbarui'] ? 'success' : 'error', $pesan.'.');
    }

    private function jenis(Request $request): string
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(ImporPengguna::JENIS)],
        ]);

        return $data['jenis'];
    }

    /**
     * Drop uploads nobody confirmed.
     *
     * A file is deleted on commit, but a preview that is never confirmed — the
     * admin spots ten bad rows and goes back to Excel — would otherwise leave a
     * spreadsheet of names, emails and NIS numbers sitting in storage forever.
     * Swept here rather than on a schedule so it needs no cron to be true.
     */
    private function bersihkanBerkasLama(): void
    {
        $disk = Storage::disk(self::DISK);
        $batas = now()->subHours(self::UMUR_MAKS_JAM)->getTimestamp();

        foreach ($disk->files(self::FOLDER) as $berkas) {
            if ($disk->lastModified($berkas) < $batas) {
                $disk->delete($berkas);
            }
        }
    }
}
