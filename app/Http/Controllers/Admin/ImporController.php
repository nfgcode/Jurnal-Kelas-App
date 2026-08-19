<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Support\ImporPengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

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
        $data = $request->validate([
            'jenis' => ['required', Rule::in(ImporPengguna::JENIS)],
            // 10 MB is far above any realistic roster and well inside the
            // default PHP upload limits, so a legitimate file never bounces.
            'berkas' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
            'password_bawaan' => ['nullable', 'string', 'min:8', 'max:255'],
            'perbarui' => ['nullable', 'boolean'],
        ]);

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
        } catch (RuntimeException $e) {
            Storage::disk(self::DISK)->delete(self::FOLDER.'/'.$nama);

            return back()->with('error', $e->getMessage())->withInput();
        }

        if ($hasil['fatal']) {
            Storage::disk(self::DISK)->delete(self::FOLDER.'/'.$nama);

            return back()->with('error', $hasil['fatal'])->withInput();
        }

        return view('admin.impor.pratinjau', [
            'jenis' => $jenis,
            'kolom' => ImporPengguna::kolom($jenis),
            'hasil' => $hasil,
            'berkas' => $nama,
            'namaAsli' => $data['berkas']->getClientOriginalName(),
            'passwordBawaan' => $data['password_bawaan'] ?? null,
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
        $data = $request->validate([
            'jenis' => ['required', Rule::in(ImporPengguna::JENIS)],
            // A ULID plus extension — anything else is not a name this app wrote,
            // and letting it through would be a path traversal into storage.
            'berkas' => ['required', 'string', 'regex:/^[0-9A-Za-z]{26}\.(xlsx|csv)$/'],
            'password_bawaan' => ['nullable', 'string', 'min:8', 'max:255'],
            'perbarui' => ['nullable', 'boolean'],
        ]);

        $path = self::FOLDER.'/'.$data['berkas'];

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
        } catch (RuntimeException $e) {
            Storage::disk(self::DISK)->delete($path);

            return redirect()->route('admin.impor.index')->with('error', $e->getMessage());
        }

        $ringkas = ImporPengguna::simpan($hasil['baris'], $data['jenis']);

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

        return redirect()->route('admin.users.index', ['role' => $data['jenis']])
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
