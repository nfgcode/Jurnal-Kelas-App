<?php

namespace App\Support;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bulk creation of siswa and guru accounts from a filled-in spreadsheet.
 *
 * The template this class hands out ({@see template()}) and the reader that
 * takes it back ({@see periksa()}) are defined by the same column list, so the
 * file an admin downloads can never drift from the file the importer expects.
 *
 * Importing is deliberately two steps: `periksa()` validates every row and
 * reports what it would do, and only `simpan()` writes. A spreadsheet of a
 * hundred students is exactly the kind of input where the first attempt has
 * three typos in it, and finding them after the accounts exist is much worse.
 */
class ImporPengguna
{
    /** The two kinds of account this importer knows how to create. */
    public const JENIS = ['siswa', 'guru'];

    /** Rows accepted in one file — a school year's intake, with room to spare. */
    public const MAX_BARIS = 1000;

    /**
     * The template's columns, in order: key => [title, required?, hint].
     * The key is what the rest of this class works with; the title is what the
     * spreadsheet shows and what an uploaded file is matched against.
     *
     * @return array<string, array{judul: string, wajib: bool, petunjuk: string}>
     */
    public static function kolom(string $jenis): array
    {
        $umum = [
            'nama' => ['judul' => 'Nama Lengkap', 'wajib' => true, 'petunjuk' => 'Nama sesuai data sekolah.'],
            'email' => ['judul' => 'Email', 'wajib' => true, 'petunjuk' => 'Harus unik. Dipakai untuk login.'],
        ];

        $penutup = [
            'password' => ['judul' => 'Password', 'wajib' => false, 'petunjuk' => 'Minimal 8 karakter. Kosongkan untuk memakai Password Bawaan pada formulir impor.'],
            'status' => ['judul' => 'Status', 'wajib' => false, 'petunjuk' => 'aktif / nonaktif / pending. Kosong dianggap aktif.'],
        ];

        if ($jenis === 'guru') {
            return $umum + [
                'nip' => ['judul' => 'NIP', 'wajib' => true, 'petunjuk' => 'Harus unik. Dipakai untuk login.'],
                'wali_kelas' => ['judul' => 'Wali Kelas', 'wajib' => false, 'petunjuk' => 'Nama kelas perwalian, pisahkan dengan koma bila lebih dari satu. Kosongkan bila bukan wali kelas.'],
            ] + $penutup;
        }

        return $umum + [
            'nis' => ['judul' => 'NIS', 'wajib' => true, 'petunjuk' => 'Harus unik. Dipakai untuk login.'],
            'kelas' => ['judul' => 'Kelas', 'wajib' => true, 'petunjuk' => 'Nama kelas persis seperti terdaftar, contoh: XII RPL 1.'],
            'ketua_kelas' => ['judul' => 'Ketua Kelas', 'wajib' => false, 'petunjuk' => 'Isi "ya" bila siswa ini ketua kelas. Ketua kelas yang mengisi presensi harian kelasnya.'],
        ] + $penutup;
    }

    /**
     * The blank workbook an admin fills in: the data sheet (header + two example
     * rows) and a Petunjuk sheet explaining every column, plus the class names
     * that actually exist — the single most common thing to get wrong.
     */
    public static function template(string $jenis): BinaryFileResponse
    {
        $kolom = self::kolom($jenis);
        $header = array_column($kolom, 'judul');

        $sheets = [
            'Data '.ucfirst($jenis) => [
                'header' => $header,
                'rows' => self::contoh($jenis),
            ],
            'Petunjuk' => [
                'header' => ['Kolom', 'Wajib', 'Penjelasan'],
                'rows' => self::petunjuk($jenis, $kolom),
            ],
        ];

        return XlsxExport::downloadWorkbook('template-impor-'.$jenis.'.xlsx', $sheets);
    }

    /**
     * Two filled-in rows, so the person filling the template can see the shape
     * expected of each column instead of inferring it from the heading.
     *
     * @return array<int, array<int, string>>
     */
    private static function contoh(string $jenis): array
    {
        // A real class name if the school has one, so the example is copyable.
        $kelas = Kelas::orderBy('nama_kelas')->value('nama_kelas') ?? 'X RPL 1';

        if ($jenis === 'guru') {
            return [
                ['Budi Santoso', 'budi.santoso@contoh.sch.id', '198501012010011001', $kelas, '', 'aktif'],
                ['Siti Aminah', 'siti.aminah@contoh.sch.id', '199003152015032002', '', '', 'aktif'],
            ];
        }

        return [
            ['Ahmad Fauzi', 'ahmad.fauzi@contoh.sch.id', '2024001', $kelas, 'ya', '', 'aktif'],
            ['Dewi Lestari', 'dewi.lestari@contoh.sch.id', '2024002', $kelas, '', '', 'aktif'],
        ];
    }

    /**
     * The Petunjuk sheet: one row per column, then the reference lists a filler
     * would otherwise have to guess at.
     *
     * @param  array<string, array{judul: string, wajib: bool, petunjuk: string}>  $kolom
     * @return array<int, array<int, string>>
     */
    private static function petunjuk(string $jenis, array $kolom): array
    {
        $rows = [];

        foreach ($kolom as $def) {
            $rows[] = [$def['judul'], $def['wajib'] ? 'Wajib' : 'Opsional', $def['petunjuk']];
        }

        $rows[] = ['', '', ''];
        $rows[] = ['Catatan', '', 'Jangan mengubah atau menghapus baris judul (baris pertama). Hapus dua baris contoh sebelum mengunggah.'];
        $rows[] = ['', '', 'Satu baris = satu akun. Baris kosong diabaikan.'];
        $rows[] = ['', '', 'Maksimal '.self::MAX_BARIS.' baris per berkas.'];

        // Both templates reference class names — siswa in Kelas, guru in Wali
        // Kelas — and a mistyped one is the single most common import failure.
        $kolomKelas = $jenis === 'siswa' ? 'Kelas' : 'Wali Kelas';

        $rows[] = ['', '', ''];
        $rows[] = ['Kelas terdaftar', '', 'Salin salah satu nama berikut ke kolom '.$kolomKelas.':'];

        foreach (Kelas::orderBy('nama_kelas')->pluck('nama_kelas') as $nama) {
            $rows[] = ['', '', $nama];
        }

        return $rows;
    }

    /**
     * Read and validate a filled-in file without touching the database.
     *
     * Returns the per-row verdict the preview screen renders: what would be
     * created, what is already there, and every reason a row cannot be used.
     *
     * @return array{baris: array<int, array{nomor: int, data: array<string, string>, error: array<int, string>, aksi: string}>, fatal: ?string, ringkas: array<string, int>}
     */
    public static function periksa(string $path, string $jenis, ?string $passwordBawaan = null, bool $perbarui = false): array
    {
        $kolom = self::kolom($jenis);
        $isi = XlsxReader::baca($path);

        if ($isi === []) {
            return self::kosong('Berkas tidak berisi data apa pun.');
        }

        $petaKolom = self::petakanHeader(array_shift($isi), $kolom);

        if ($petaKolom === null) {
            return self::kosong(
                'Judul kolom tidak dikenali. Gunakan template impor '.$jenis
                .' — baris pertama harus berisi: '.implode(', ', array_column($kolom, 'judul')).'.'
            );
        }

        if (count($isi) > self::MAX_BARIS) {
            return self::kosong('Berkas berisi '.count($isi).' baris; maksimal '.self::MAX_BARIS.' baris per unggahan.');
        }

        // Class names are matched case-insensitively, because "XII RPL 1" and
        // "xii rpl 1" are the same class to everyone except a string compare.
        $kelasPeta = Kelas::pluck('id', 'nama_kelas')
            ->mapWithKeys(fn ($id, $nama) => [mb_strtolower(trim($nama)) => $id])
            ->all();

        $baris = [];
        // Duplicates inside the file itself: the database's unique index would
        // only catch the second one, after the first had already been written.
        $terlihat = ['email' => [], 'nis' => [], 'nip' => []];

        foreach ($isi as $i => $row) {
            // Every column key is present even when the file omits an optional
            // one, so the checks below can read them without guarding each.
            $data = array_fill_keys(array_keys($kolom), '');

            foreach ($petaKolom as $kunci => $indeks) {
                $data[$kunci] = trim((string) ($row[$indeks] ?? ''));
            }

            $baris[] = self::periksaBaris(
                // +2: row 1 is the header, and spreadsheets count from 1.
                $i + 2,
                $data,
                $jenis,
                $kelasPeta,
                $terlihat,
                $passwordBawaan,
                $perbarui
            );
        }

        return [
            'baris' => $baris,
            'fatal' => null,
            'ringkas' => [
                'total' => count($baris),
                'baru' => count(array_filter($baris, fn ($b) => $b['aksi'] === 'baru')),
                'perbarui' => count(array_filter($baris, fn ($b) => $b['aksi'] === 'perbarui')),
                'gagal' => count(array_filter($baris, fn ($b) => $b['error'] !== [])),
            ],
        ];
    }

    /**
     * One row's verdict. `$terlihat` is threaded by reference so a value that
     * already appeared earlier in the same file is reported on the later row.
     *
     * @param  array<string, string>  $data
     * @param  array<string, int>  $kelasPeta
     * @param  array<string, array<string, int>>  $terlihat
     * @return array{nomor: int, data: array<string, string>, error: array<int, string>, aksi: string}
     */
    private static function periksaBaris(
        int $nomor,
        array $data,
        string $jenis,
        array $kelasPeta,
        array &$terlihat,
        ?string $passwordBawaan,
        bool $perbarui
    ): array {
        $error = [];

        // The messages are spelled out in Indonesian here rather than left to
        // Laravel's English defaults: they are read straight off the preview
        // table by the admin fixing the spreadsheet, next to text that is
        // Indonesian throughout.
        $validator = Validator::make($data, [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'status' => ['nullable', 'in:aktif,nonaktif,pending'],
        ], [
            'required' => ':attribute wajib diisi.',
            'email' => ':attribute bukan alamat email yang sah.',
            'max' => ':attribute maksimal :max karakter.',
            'in' => 'Status harus aktif, nonaktif, atau pending.',
        ], [
            'nama' => 'Nama Lengkap',
            'email' => 'Email',
            'status' => 'Status',
        ]);

        foreach ($validator->errors()->all() as $pesan) {
            $error[] = $pesan;
        }

        $identitas = $jenis === 'guru' ? 'nip' : 'nis';
        $labelIdentitas = strtoupper($identitas);

        if (($data[$identitas] ?? '') === '') {
            $error[] = $labelIdentitas.' wajib diisi.';
        }

        // A password must come from somewhere: the row, or the form's fallback.
        $password = $data['password'] !== '' ? $data['password'] : (string) $passwordBawaan;

        if ($password === '') {
            $error[] = 'Password wajib diisi, atau isi Password Bawaan pada formulir impor.';
        } elseif (mb_strlen($password) < 8) {
            $error[] = 'Password minimal 8 karakter.';
        }

        $kelasId = null;

        if ($jenis === 'siswa') {
            $namaKelas = mb_strtolower($data['kelas'] ?? '');

            if ($namaKelas === '') {
                $error[] = 'Kelas wajib diisi.';
            } elseif (! isset($kelasPeta[$namaKelas])) {
                $error[] = 'Kelas "'.$data['kelas'].'" tidak terdaftar.';
            } else {
                $kelasId = $kelasPeta[$namaKelas];
            }
        }

        // Duplicates within the file.
        foreach (['email', $identitas] as $unik) {
            $nilai = mb_strtolower($data[$unik] ?? '');

            if ($nilai === '') {
                continue;
            }

            if (isset($terlihat[$unik][$nilai])) {
                $error[] = strtoupper($unik).' "'.$data[$unik].'" sudah dipakai pada baris '.$terlihat[$unik][$nilai].' berkas ini.';
            } else {
                $terlihat[$unik][$nilai] = $nomor;
            }
        }

        // Duplicates against accounts that already exist. The email is the
        // identity an import updates on; a clashing NIP/NIS on a *different*
        // account is always an error, since both double as login identifiers.
        $adaEmail = $data['email'] !== ''
            ? User::where('email', $data['email'])->first()
            : null;

        if ($adaEmail && ! $perbarui) {
            $error[] = 'Email sudah terdaftar atas nama '.$adaEmail->name
                .'. Centang "Perbarui data yang sudah ada" bila memang ingin menimpanya.';
        }

        if (($data[$identitas] ?? '') !== '') {
            $bentrok = User::where($identitas, $data[$identitas])
                ->when($adaEmail, fn ($q) => $q->whereKeyNot($adaEmail->id))
                ->first();

            if ($bentrok) {
                $error[] = $labelIdentitas.' sudah dipakai akun lain ('.$bentrok->name.').';
            }
        }

        return [
            'nomor' => $nomor,
            'data' => $data + ['_kelas_id' => $kelasId, '_password' => $password, '_user_id' => $adaEmail?->id],
            'error' => $error,
            'aksi' => $error !== [] ? 'gagal' : ($adaEmail ? 'perbarui' : 'baru'),
        ];
    }

    /**
     * Write the rows that passed. Rows with errors are skipped, never guessed
     * at, and the whole batch runs in one transaction so a failure halfway
     * through does not leave a half-imported class.
     *
     * @param  array<int, array{data: array<string, mixed>, error: array<int, string>, aksi: string}>  $baris
     * @return array{baru: int, perbarui: int, dilewati: int}
     */
    public static function simpan(array $baris, string $jenis): array
    {
        $hasil = ['baru' => 0, 'perbarui' => 0, 'dilewati' => 0];

        DB::transaction(function () use ($baris, $jenis, &$hasil) {
            foreach ($baris as $row) {
                if ($row['error'] !== []) {
                    $hasil['dilewati']++;

                    continue;
                }

                $data = $row['data'];

                $atribut = [
                    'name' => $data['nama'],
                    'email' => $data['email'],
                    'role' => $jenis,
                    'status' => $data['status'] !== '' ? $data['status'] : 'aktif',
                    'password' => Hash::make($data['_password']),
                ];

                if ($jenis === 'guru') {
                    $atribut['nip'] = $data['nip'];
                } else {
                    $atribut['nis'] = $data['nis'];
                    $atribut['kelas_id'] = $data['_kelas_id'];
                    $atribut['is_ketua_kelas'] = self::boolean($data['ketua_kelas'] ?? '');
                }

                $user = $data['_user_id'] ? User::find($data['_user_id']) : null;

                if ($user) {
                    $user->update($atribut);
                    $hasil['perbarui']++;
                } else {
                    $user = User::create($atribut);
                    $hasil['baru']++;
                }

                if ($jenis === 'guru' && $user) {
                    self::terapkanPerwalian($user, $data['wali_kelas'] ?? '');
                }
            }
        });

        return $hasil;
    }

    /**
     * Make this guru the wali of the classes named in the cell. Only additive:
     * an empty cell leaves whatever perwalian the account already has, because
     * a blank column in a bulk file is much more likely to mean "not stated"
     * than "release every class this teacher looks after".
     */
    private static function terapkanPerwalian(User $guru, string $daftar): void
    {
        $nama = array_filter(array_map('trim', explode(',', $daftar)));

        if ($nama === []) {
            return;
        }

        foreach ($nama as $satu) {
            Kelas::whereRaw('LOWER(nama_kelas) = ?', [mb_strtolower($satu)])
                ->update(['wali_kelas_id' => $guru->id]);
        }
    }

    /**
     * Match the uploaded file's header row to the template's columns, by title,
     * case- and spacing-insensitively. Returns null when a required column is
     * missing — that is a wrong file, not a fixable row.
     *
     * @param  array<int, string>  $header
     * @param  array<string, array{judul: string, wajib: bool, petunjuk: string}>  $kolom
     * @return array<string, int>|null column key => index in the row
     */
    private static function petakanHeader(array $header, array $kolom): ?array
    {
        $normal = [];

        foreach ($header as $i => $judul) {
            $normal[self::normal($judul)] = $i;
        }

        $peta = [];

        foreach ($kolom as $kunci => $def) {
            $cari = self::normal($def['judul']);

            if (isset($normal[$cari])) {
                $peta[$kunci] = $normal[$cari];

                continue;
            }

            // An optional column may simply be absent from the file; a required
            // one being absent means this is not the template.
            if ($def['wajib']) {
                return null;
            }
        }

        return $peta;
    }

    /** Header titles compared without case, spacing or punctuation noise. */
    private static function normal(string $teks): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($teks)));
    }

    /** The many ways a spreadsheet says yes. */
    private static function boolean(string $nilai): bool
    {
        return in_array(mb_strtolower(trim($nilai)), ['ya', 'y', 'yes', 'true', '1', 'ketua'], true);
    }

    /**
     * @return array{baris: array<int, mixed>, fatal: string, ringkas: array<string, int>}
     */
    private static function kosong(string $pesan): array
    {
        return [
            'baris' => [],
            'fatal' => $pesan,
            'ringkas' => ['total' => 0, 'baru' => 0, 'perbarui' => 0, 'gagal' => 0],
        ];
    }

    /** A collision-free name for the uploaded file while it waits for review. */
    public static function namaSimpanan(string $asli): string
    {
        $ext = strtolower(pathinfo($asli, PATHINFO_EXTENSION)) === 'csv' ? 'csv' : 'xlsx';

        return (string) Str::ulid().'.'.$ext;
    }
}
