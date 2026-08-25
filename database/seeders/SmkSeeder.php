<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A full vocational school (SMK) the size of a real one: ten competency areas
 * (jurusan), several with parallel classes (Akuntansi 1 / Akuntansi 2), across
 * all three grades — so the app can be exercised the way a large SMK would use
 * it: many rombel, many jurusan, its own productive subjects per jurusan.
 *
 * Not wired into DatabaseSeeder (which keeps DemoSeeder for the test suite). Run
 * it deliberately against the dev database:
 *
 *     php artisan migrate:fresh
 *     php artisan db:seed --class=SmkSeeder
 *
 * Sizing is "realistis penuh": ~45 rombel, 30 students each (~1.350 siswa), 45
 * teachers, and 60 school days of journals + attendance.
 */
class SmkSeeder extends Seeder
{
    /** Lesson-period blocks that carry a class; the gap between JP 4 and 6 is the break. */
    private const JP_SLOT = [[1, 2], [3, 4], [6, 7], [8, 9]];

    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    private const TINGKAT = ['X', 'XI', 'XII'];

    private const TAHUN_AJARAN = '2026/2027';

    private const SISWA_PER_KELAS = 30;

    private const HARI_RIWAYAT = 60;

    /**
     * Every demo login shares one password, hashed once. bcrypt is deliberately
     * slow; hashing it per row cost minutes across 1.400 accounts.
     */
    private string $sandi;

    /**
     * The ten jurusan. `paralel` is how many classes of that jurusan run per
     * grade (Akuntansi has two: "1" and "2"); `produktif` are the jurusan's own
     * vocational subjects [nama, kode, jp/minggu]. Codes are unique across the
     * whole subject list.
     */
    private function daftarJurusan(): array
    {
        return [
            'AKL' => ['nama' => 'Akuntansi dan Keuangan Lembaga', 'bidang' => 'Bisnis dan Manajemen', 'paralel' => 2, 'produktif' => [
                ['Akuntansi Keuangan', 'PAK', 6],
                ['Komputer Akuntansi', 'PKA', 5],
                ['Praktikum Akuntansi Lembaga', 'PAL', 5],
            ]],
            'MPLB' => ['nama' => 'Manajemen Perkantoran dan Layanan Bisnis', 'bidang' => 'Bisnis dan Manajemen', 'paralel' => 2, 'produktif' => [
                ['Otomatisasi Perkantoran', 'POP', 6],
                ['Kearsipan', 'PKR', 5],
                ['Korespondensi', 'PKO', 5],
            ]],
            'BDP' => ['nama' => 'Bisnis Digital dan Pemasaran', 'bidang' => 'Bisnis dan Manajemen', 'paralel' => 1, 'produktif' => [
                ['Bisnis Online', 'PBO', 6],
                ['Pengelolaan Bisnis Ritel', 'PBR', 5],
                ['Pemasaran', 'PPM', 5],
            ]],
            'TKJ' => ['nama' => 'Teknik Komputer dan Jaringan', 'bidang' => 'Teknologi Informasi', 'paralel' => 2, 'produktif' => [
                ['Administrasi Sistem Jaringan', 'PAS', 6],
                ['Teknologi Jaringan Berbasis Luas', 'PTJ', 6],
                ['Komputer dan Jaringan Dasar', 'PKJ', 5],
            ]],
            'RPL' => ['nama' => 'Rekayasa Perangkat Lunak', 'bidang' => 'Teknologi Informasi', 'paralel' => 2, 'produktif' => [
                ['Pemrograman Web dan Perangkat Bergerak', 'PPW', 6],
                ['Pemrograman Berorientasi Objek', 'PBP', 6],
                ['Basis Data', 'PBD', 5],
            ]],
            'DKV' => ['nama' => 'Desain Komunikasi Visual', 'bidang' => 'Seni dan Ekonomi Kreatif', 'paralel' => 1, 'produktif' => [
                ['Desain Grafis Percetakan', 'PDG', 6],
                ['Animasi 2D dan 3D', 'PAN', 5],
                ['Fotografi', 'PFO', 5],
            ]],
            'TKR' => ['nama' => 'Teknik Kendaraan Ringan Otomotif', 'bidang' => 'Teknologi Manufaktur dan Rekayasa', 'paralel' => 2, 'produktif' => [
                ['Pemeliharaan Mesin Kendaraan Ringan', 'PMK', 8],
                ['Pemeliharaan Sasis dan Pemindah Tenaga', 'PSP', 7],
                ['Pemeliharaan Kelistrikan Kendaraan', 'PKK', 5],
            ]],
            'TBSM' => ['nama' => 'Teknik dan Bisnis Sepeda Motor', 'bidang' => 'Teknologi Manufaktur dan Rekayasa', 'paralel' => 1, 'produktif' => [
                ['Pemeliharaan Mesin Sepeda Motor', 'PMS', 8],
                ['Pemeliharaan Kelistrikan Sepeda Motor', 'PKM', 6],
                ['Pemeliharaan Sasis Sepeda Motor', 'PSS', 5],
            ]],
            'TITL' => ['nama' => 'Teknik Instalasi Tenaga Listrik', 'bidang' => 'Teknologi Manufaktur dan Rekayasa', 'paralel' => 1, 'produktif' => [
                ['Instalasi Penerangan Listrik', 'PIP', 7],
                ['Instalasi Tenaga Listrik', 'PIT', 7],
                ['Instalasi Motor Listrik', 'PIM', 6],
            ]],
            'KUL' => ['nama' => 'Kuliner', 'bidang' => 'Pariwisata', 'paralel' => 1, 'produktif' => [
                ['Pengolahan dan Penyajian Makanan', 'PPP', 8],
                ['Produk Pastry dan Bakery', 'PPB', 6],
                ['Tata Hidang', 'PTH', 5],
            ]],
        ];
    }

    /** Normative + adaptive subjects every jurusan takes [nama, kode, kelompok, jp]. */
    private function daftarUmum(): array
    {
        return [
            ['Pendidikan Agama dan Budi Pekerti', 'PAI', 'wajib', 3],
            ['Pendidikan Pancasila', 'PPC', 'wajib', 2],
            ['Bahasa Indonesia', 'BIN', 'wajib', 4],
            ['Matematika', 'MTK', 'wajib', 4],
            ['Bahasa Inggris', 'BIG', 'wajib', 4],
            ['Sejarah', 'SEJ', 'wajib', 2],
            ['Pendidikan Jasmani Olahraga dan Kesehatan', 'PJK', 'wajib', 3],
            ['Seni Budaya', 'SBD', 'wajib', 2],
            ['Informatika', 'INF', 'wajib', 4],
            ['Projek Ilmu Pengetahuan Alam dan Sosial', 'IPAS', 'wajib', 4],
            ['Projek Kreatif dan Kewirausahaan', 'PKW', 'kejuruan', 5],
            ['Bahasa Jawa', 'BJW', 'muatan_lokal', 2],
        ];
    }

    public function run(): void
    {
        // Deterministic output, so a reseed lands the same demo every time.
        mt_srand(20260806);

        $this->sandi = Hash::make('password');

        $this->seedAdmin();

        [$mapelUmum, $mapelProduktif] = $this->seedMataPelajaran();

        $rombelSpec = $this->rencanaRombel();
        $this->seedRuangan($rombelSpec);
        $this->seedTahunAjaran();
        $this->seedJurusan();
        // Two teachers per rombel. One-per-rombel exactly saturates the week
        // (45 classes x 24 blocks = 45 teachers x 24 blocks), which is why the
        // old data could not avoid booking someone into two rooms at once.
        $guru = $this->seedGuru(count($rombelSpec) * 2);
        [$guruUmum, $guruProduktif] = $this->bagiGuru($guru);

        $kelas = $this->seedKelas($rombelSpec, $guru);
        $siswa = $this->seedSiswa($kelas);
        $jadwal = $this->seedJadwal($kelas, $rombelSpec, $mapelUmum, $mapelProduktif, $guruUmum, $guruProduktif);

        $this->seedJurnalDanPresensi($jadwal, $siswa);

        $this->command?->info(sprintf(
            'SMK ter-seed: %d guru, %d rombel (10 jurusan), %d siswa, %d jadwal.',
            count($guru),
            count($kelas),
            count($siswa, COUNT_RECURSIVE) - count($siswa),
            count($jadwal),
        ));
    }

    /**
     * A room per rombel plus the shared facilities a vocational school has.
     * The codes come from the rombel plan, so a class and its room agree.
     *
     * @param  array<int, array<string, string>>  $rombelSpec
     */
    private function seedTahunAjaran(): void
    {
        TahunAjaran::create([
            'kode' => self::TAHUN_AJARAN,
            'mulai' => '2026-07-13',
            'selesai' => '2027-06-19',
            'aktif' => true,
        ]);
    }

    /**
     * The competency areas, as rows rather than as a long name repeated on
     * every one of the 45 classes.
     */
    private function seedJurusan(): void
    {
        $rows = [];

        foreach ($this->daftarJurusan() as $kode => $j) {
            $rows[] = [
                'kode' => $kode,
                'nama' => $j['nama'],
                'bidang' => $j['bidang'] ?? null,
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('jurusan')->insert($rows);
    }

    private function seedRuangan(array $rombelSpec): void
    {
        $rows = [];

        foreach ($rombelSpec as $spec) {
            $rows[] = [
                'kode' => $spec['ruang'],
                'nama' => 'Ruang '.substr($spec['ruang'], 2),
                'jenis' => 'kelas',
                'kapasitas' => 36,
                'gedung' => 'Gedung '.$spec['tingkat'],
                'lantai' => 1,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ([
            ['LAB-KOM-1', 'Laboratorium Komputer 1', 'laboratorium', 36],
            ['LAB-KOM-2', 'Laboratorium Komputer 2', 'laboratorium', 36],
            ['BENGKEL-TKR', 'Bengkel Teknik Kendaraan Ringan', 'bengkel', 24],
            ['BENGKEL-TP', 'Bengkel Teknik Pemesinan', 'bengkel', 24],
            ['LAB-BOGA', 'Dapur Tata Boga', 'laboratorium', 24],
            ['AULA', 'Aula Serbaguna', 'aula', 400],
            ['PERPUS', 'Perpustakaan', 'perpustakaan', 60],
            ['LAPANGAN', 'Lapangan Olahraga', 'olahraga', 100],
        ] as [$kode, $nama, $jenis, $kapasitas]) {
            $rows[] = [
                'kode' => $kode,
                'nama' => $nama,
                'jenis' => $jenis,
                'kapasitas' => $kapasitas,
                'gedung' => 'Gedung Praktik',
                'lantai' => 1,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('ruangan')->insert($chunk);
        }
    }

    private function seedAdmin(): void
    {
        User::create([
            'username' => 'admin',
            'nama' => 'Administrator',
            'email' => 'admin@jurnalkelas.app',
            'password' => $this->sandi,
            'role' => 'admin',
            'status' => 'aktif',
            'last_active_at' => now(),
        ]);
    }

    /**
     * @return array{0: array<string, MataPelajaran>, 1: array<string, array<int, MataPelajaran>>}
     *                                                                                             shared subjects keyed by code; productive subjects keyed by jurusan code
     */
    private function seedMataPelajaran(): array
    {
        $umum = [];
        foreach ($this->daftarUmum() as [$nama, $kode, $kelompok, $jp]) {
            $umum[$kode] = MataPelajaran::create([
                'nama' => $nama,
                'kode' => $kode,
                'kelompok' => $kelompok,
                'jp_per_minggu' => $jp,
                'deskripsi' => "Mata pelajaran {$nama}.",
            ]);
        }

        $produktif = [];
        foreach ($this->daftarJurusan() as $kode => $j) {
            foreach ($j['produktif'] as [$nama, $kodeMapel, $jp]) {
                $produktif[$kode][] = MataPelajaran::create([
                    'nama' => $nama,
                    'kode' => $kodeMapel,
                    'kelompok' => 'kejuruan',
                    'jp_per_minggu' => $jp,
                    'deskripsi' => "Mata pelajaran produktif {$j['nama']}.",
                ]);
            }
        }

        return [$umum, $produktif];
    }

    /**
     * Flatten the jurusan config into one row per class to build: grade × jurusan
     * × parallel. A jurusan with paralel=1 is named plainly ("X TITL"); with more
     * than one it is numbered ("X AKL 1", "X AKL 2").
     *
     * @return array<int, array{tingkat: string, kode: string, nama: string, nama_kelas: string, ruang: string}>
     */
    private function rencanaRombel(): array
    {
        $rombel = [];
        $ruang = 101;

        foreach (self::TINGKAT as $tingkat) {
            foreach ($this->daftarJurusan() as $kode => $j) {
                for ($p = 1; $p <= $j['paralel']; $p++) {
                    $namaKelas = $j['paralel'] > 1 ? "{$tingkat} {$kode} {$p}" : "{$tingkat} {$kode}";

                    $rombel[] = [
                        'tingkat' => $tingkat,
                        'kode' => $kode,
                        'nama' => $j['nama'],
                        'nama_kelas' => $namaKelas,
                        'paralel' => $p,
                        'ruang' => 'R-'.$ruang++,
                    ];
                }
            }
        }

        return $rombel;
    }

    /**
     * Teachers, one per class so every rombel gets a distinct wali kelas. Names
     * are drawn deterministically from first/last name pools and de-duplicated.
     *
     * @return array<int, Guru>
     */
    private function seedGuru(int $jumlah): array
    {
        $depan = ['Budi', 'Siti', 'Ahmad', 'Dewi', 'Rina', 'Eko', 'Fitri', 'Gunawan',
            'Hesti', 'Irfan', 'Joko', 'Kartika', 'Lukman', 'Maya', 'Nanda', 'Oki',
            'Putri', 'Rahmat', 'Sri', 'Tono', 'Umi', 'Vino', 'Wati', 'Yudi', 'Zaki'];

        $belakang = ['Santoso', 'Nurhaliza', 'Hidayat', 'Lestari', 'Marlina', 'Prasetyo',
            'Handayani', 'Wibowo', 'Purnama', 'Maulana', 'Susilo', 'Sari', 'Wijaya',
            'Rahmawati', 'Kurniawan', 'Anggraini', 'Pratama', 'Fadhilah'];

        $nama = [];
        $i = 0;
        while (count($nama) < $jumlah) {
            $kandidat = $depan[$i % count($depan)].' '.$belakang[intdiv($i, count($depan)) % count($belakang)];
            if (! in_array($kandidat, $nama, true)) {
                $nama[] = $kandidat;
            }
            $i++;
        }

        $orang = [];
        $akun = [];

        foreach ($nama as $k => $name) {
            $nip = (string) (198500000001 + $k);
            // One dormant teacher keeps the status columns honest.
            $aktif = $k !== $jumlah - 1;

            $orang[] = [
                'nip' => $nip,
                'nama' => $name,
                'jenis_kelamin' => $k % 2 === 0 ? 'L' : 'P',
                'no_hp' => '0812'.str_pad((string) (10000 + $k), 8, '0', STR_PAD_LEFT),
                'alamat' => null,
                'status' => $aktif ? 'aktif' : 'nonaktif',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $akun[] = [
                'username' => $this->slug($name).($k + 1),
                'nama' => null,
                'email' => $this->slug($name).($k + 1).'@jurnalkelas.app',
                'password' => $this->sandi,
                'role' => 'guru',
                'status' => $aktif ? 'aktif' : 'nonaktif',
                'nip' => $nip,
                'last_active_at' => now()->subDays(mt_rand(0, 5)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('guru')->insert($orang);

        foreach (array_chunk($akun, 500) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        // Re-read in insertion order so the wali-kelas pairing below stays
        // one-teacher-per-class.
        $terdaftar = Guru::whereIn('nip', array_column($orang, 'nip'))->get()->keyBy('nip');

        return array_map(fn ($row) => $terdaftar[$row['nip']], $orang);
    }

    /**
     * Split the teacher list into a shared pool (teaches normative/adaptive
     * subjects across the school) and three productive teachers per jurusan.
     *
     * @param  array<int, Guru>  $guru
     * @return array{0: array<int, Guru>, 1: array<string, array<int, Guru>>}
     */
    private function bagiGuru(array $guru): array
    {
        $jurusan = array_keys($this->daftarJurusan());
        $produktif = [];
        $idx = 0;

        // The first 3×jurusan teachers are the productive specialists.
        foreach ($jurusan as $kode) {
            $produktif[$kode] = array_slice($guru, $idx, 3);
            $idx += 3;
        }

        // Whatever is left teaches the shared subjects.
        $umum = array_slice($guru, $idx);

        return [$umum, $produktif];
    }

    /**
     * @param  array<int, array<string, string>>  $rombelSpec
     * @param  array<int, Guru>  $guru
     * @return array<int, Kelas>
     */
    private function seedKelas(array $rombelSpec, array $guru): array
    {
        $kelas = [];

        foreach ($rombelSpec as $i => $spec) {
            $kelas[] = Kelas::create([
                'nama_kelas' => $spec['nama_kelas'],
                'tingkat' => $spec['tingkat'],
                'jurusan_kode' => $spec['kode'],
                'paralel' => $spec['paralel'],
                'ruangan_kode' => $spec['ruang'],
                'kapasitas' => 36,
                'tahun_ajaran_kode' => self::TAHUN_AJARAN,
                // One distinct homeroom teacher per class (guru list is sized to match).
                'wali_kelas_nip' => $guru[$i]->nip,
            ]);
        }

        return $kelas;
    }

    /**
     * 30 students per class. Names repeat across classes (they are different
     * people); nis / email are unique.
     *
     * @param  array<int, Kelas>  $kelasList
     * @return array<int, array<int, Siswa>> students keyed by kelas id
     */
    private function seedSiswa(array $kelasList): array
    {
        $depan = ['Adinda', 'Bagas', 'Cindy', 'Dimas', 'Elsa', 'Farhan', 'Gita', 'Hafiz',
            'Indah', 'Jefri', 'Kirana', 'Lutfi', 'Mila', 'Naufal', 'Olivia', 'Pandu',
            'Qori', 'Rizky', 'Salsa', 'Taufik', 'Umar', 'Vina', 'Wahyu', 'Yasmin',
            'Zahra', 'Andre', 'Bella', 'Candra', 'Dinda', 'Erlangga'];

        $belakang = ['Putri Maharani', 'Dwi Nugroho', 'Aulia Rahma', 'Arya Pratama',
            'Nur Fadhilah', 'Aditya Wijaya', 'Ayu Lestari', 'Rizky Ramadhan',
            'Permata Sari', 'Kurniawan', 'Dewi Anjani', 'Hidayatullah'];

        $orang = [];
        $akun = [];
        $nisPerKelas = [];
        $nis = 20261001;

        foreach ($kelasList as $kelas) {
            $nisPerKelas[$kelas->id] = [];

            for ($i = 0; $i < self::SISWA_PER_KELAS; $i++) {
                $orang[] = [
                    'nis' => (string) $nis,
                    'nisn' => '007'.$nis,
                    'nama' => $depan[$i % count($depan)].' '.$belakang[$i % count($belakang)],
                    'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                    'kelas_id' => $kelas->id,
                    'no_hp' => null,
                    'alamat' => null,
                    'status' => 'aktif',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $akun[] = [
                    'username' => 'siswa'.$nis,
                    'nama' => null,
                    'email' => 'siswa'.$nis.'@jurnalkelas.app',
                    'password' => $this->sandi,
                    'role' => 'siswa',
                    // A handful of dormant/pending accounts keep the status column honest.
                    'status' => match (true) {
                        $nis % 97 === 0 => 'nonaktif',
                        $nis % 89 === 0 => 'pending',
                        default => 'aktif',
                    },
                    'nis' => (string) $nis,
                    'last_active_at' => now()->subDays(mt_rand(0, 6)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $nisPerKelas[$kelas->id][] = (string) $nis;
                $nis++;
            }
        }

        foreach (array_chunk($orang, 500) as $chunk) {
            DB::table('siswa')->insert($chunk);
        }

        foreach (array_chunk($akun, 500) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        // The first student of each class chairs it. The fact belongs to the
        // class, so it is written there.
        foreach ($nisPerKelas as $kelasId => $daftarNis) {
            DB::table('kelas')->where('id', $kelasId)->update(['ketua_nis' => $daftarNis[0]]);
        }

        $semua = Siswa::whereIn('nis', array_column($orang, 'nis'))->get()->keyBy('nis');

        $siswa = [];
        foreach ($nisPerKelas as $kelasId => $daftarNis) {
            $siswa[$kelasId] = array_map(fn ($n) => $semua[$n], $daftarNis);
        }

        return $siswa;
    }

    /**
     * Weekly timetable per class: 6 days × 4 JP blocks = 24 meetings, drawn from
     * the jurusan's own subject set (12 shared + 3 productive). Each subject keeps
     * one teacher within a class — productive subjects go to that jurusan's
     * specialists, shared subjects spread round-robin across the shared pool.
     *
     * @param  array<int, Kelas>  $kelasList
     * @param  array<int, array<string, string>>  $rombelSpec
     * @param  array<string, MataPelajaran>  $mapelUmum
     * @param  array<string, array<int, MataPelajaran>>  $mapelProduktif
     * @param  array<int, Guru>  $guruUmum
     * @param  array<string, array<int, Guru>>  $guruProduktif
     * @return array<int, Jadwal>
     */
    private function seedJadwal(
        array $kelasList,
        array $rombelSpec,
        array $mapelUmum,
        array $mapelProduktif,
        array $guruUmum,
        array $guruProduktif,
    ): array {
        $rows = [];
        $penjadwal = new PenjadwalTanpaBentrok;
        // Every (teacher, subject) pairing the timetable ends up using has to
        // exist in guru_mata_pelajaran, or the schedule form would reject data
        // the seeder itself produced.
        $pengampu = [];

        foreach ($kelasList as $i => $kelas) {
            $kode = $rombelSpec[$i]['kode'];

            // Who may teach what for this class: the shared pool covers the
            // normative subjects, the jurusan's own specialists cover its
            // productive ones.
            $kandidat = [];
            foreach (array_values($mapelUmum) as $m) {
                $kandidat[$m->id] = array_map(fn ($g) => $g->nip, $guruUmum);
            }
            foreach ($mapelProduktif[$kode] as $m) {
                $kandidat[$m->id] = array_map(fn ($g) => $g->nip, $guruProduktif[$kode]);
            }

            $bag = array_merge(array_values($mapelUmum), $mapelProduktif[$kode]);
            shuffle($bag);
            $guruKelas = [];

            foreach (self::HARI as $hari) {
                foreach (self::JP_SLOT as [$mulai, $selesai]) {
                    [$m, $nip] = $this->pilihUntukSlot($bag, $kandidat, $guruKelas, $penjadwal, $hari, $mulai);

                    if ($m === null) {
                        continue; // Nobody qualified is free: leave the slot empty.
                    }

                    $guruKelas[$m->id] = $nip;
                    $penjadwal->tandai($nip, $hari, $mulai);
                    $pengampu[$nip][$m->id] = isset($mapelProduktif[$kode])
                        && in_array($m->id, array_map(fn ($x) => $x->id, $mapelProduktif[$kode]), true);

                    $rows[] = [
                        'kelas_id' => $kelas->id,
                        'mata_pelajaran_id' => $m->id,
                        'guru_nip' => $nip,
                        'hari' => $hari,
                        'jam_ke_mulai' => $mulai,
                        'jam_ke_selesai' => $selesai,
                        'ruangan_kode' => $kelas->ruangan_kode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('jadwal')->insert($chunk);
        }

        $this->seedGuruMapel($pengampu);

        return Jadwal::orderBy('id')->get()->all();
    }

    /**
     * Take the first subject from the bag whose teachers are not all busy.
     *
     * The bag rotates rather than shrinks, so a subject that could not be placed
     * in this slot is tried again in a later one instead of being lost.
     *
     * @param  array<int, MataPelajaran>  $bag
     * @param  array<int, array<int, string>>  $kandidat  NIPs keyed by subject id
     * @param  array<int, string>  $guruKelas
     * @return array{0: MataPelajaran|null, 1: string|null}
     */
    private function pilihUntukSlot(
        array &$bag,
        array $kandidat,
        array $guruKelas,
        PenjadwalTanpaBentrok $penjadwal,
        string $hari,
        int $jamKe,
    ): array {
        for ($coba = 0; $coba < count($bag); $coba++) {
            $m = array_shift($bag);
            $bag[] = $m;

            $nip = $penjadwal->pilih($kandidat[$m->id] ?? [], $hari, $jamKe, $guruKelas[$m->id] ?? null);

            if ($nip !== null) {
                return [$m, $nip];
            }
        }

        return [null, null];
    }

    /**
     * Record the (teacher, subject) pairings the timetable relies on.
     *
     * `utama` marks a productive subject: those belong to the jurusan's own
     * specialists, which is the closest thing a vocational school has to a
     * teacher's principal subject.
     *
     * @param  array<string, array<int, bool>>  $pengampu  produktif-flag keyed by nip, then subject id
     */
    private function seedGuruMapel(array $pengampu): void
    {
        $rows = [];

        foreach ($pengampu as $nip => $mapel) {
            $sudahUtama = false;

            foreach ($mapel as $mapelId => $produktif) {
                $rows[] = [
                    'guru_nip' => $nip,
                    'mata_pelajaran_id' => $mapelId,
                    'utama' => $produktif && ! $sudahUtama,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $sudahUtama = $sudahUtama || $produktif;
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('guru_mata_pelajaran')->insert($chunk);
        }
    }

    /**
     * Walk backwards through school days, writing a journal for most scheduled
     * meetings and an attendance row per enrolled student. Mirrors DemoSeeder's
     * proven generation, scaled to this school and 60 days of history.
     *
     * @param  array<int, Jadwal>  $jadwalList
     * @param  array<int, array<int, User>>  $siswaPerKelas
     */
    private function seedJurnalDanPresensi(array $jadwalList, array $siswaPerKelas): void
    {
        $materi = [
            'Persamaan kuadrat', 'Teks eksposisi', 'Narrative text', 'Jurnal penyesuaian',
            'Konfigurasi VLAN', 'Perawatan sistem rem', 'Sketsa desain logo',
            'Kearsipan sistem subjek', 'Pemrograman fungsi', 'Basis data relasional',
            'Norma dalam masyarakat', 'Akhlak terpuji', 'Kebugaran jasmani',
            'Instalasi penerangan 1 fasa', 'Teknik plating hidangan', 'Fotografi produk',
        ];

        $tugas = [
            'Latihan soal buku paket halaman 42–45.', 'Membuat rangkuman bab 3.',
            'Laporan praktikum kejuruan.', 'Tugas kelompok, dikumpulkan pertemuan berikutnya.',
            null, null,
        ];

        // Index the timetable by weekday so each date only touches its own rows.
        $jadwalPerHari = [];
        foreach ($jadwalList as $jadwal) {
            $jadwalPerHari[$jadwal->hari][] = $jadwal;
        }

        $tanggal = Carbon::today();
        $hariTerkumpul = 0;
        $jurnalRows = [];
        $now = now();

        while ($hariTerkumpul < self::HARI_RIWAYAT) {
            $namaHari = self::HARI[$tanggal->dayOfWeekIso - 1] ?? null;

            if ($namaHari === null) {
                $tanggal = $tanggal->copy()->subDay();

                continue;
            }

            foreach ($jadwalPerHari[$namaHari] ?? [] as $jadwal) {
                // Roughly one meeting in eight is still waiting on its journal.
                if (mt_rand(1, 100) <= 12) {
                    continue;
                }

                $hadir = mt_rand(1, 100);
                $adaTugas = mt_rand(1, 100) <= 60;

                $jurnalRows[] = [
                    // Bulk insert skips model events, so the route key the model
                    // would have generated has to be written here as well.
                    'public_id' => (string) Str::ulid(),
                    'jadwal_id' => $jadwal->id,
                    'tanggal' => $tanggal->toDateString(),
                    'materi' => $materi[array_rand($materi)],
                    'tugas' => $tugas[array_rand($tugas)],
                    'kegiatan' => null,
                    'catatan' => null,
                    'kehadiran_guru_status' => $hadir <= 92 ? 'hadir' : 'tidak_hadir',
                    'kehadiran_guru_alasan' => null,
                    'kehadiran_guru_ada_tugas' => $hadir <= 92 ? null : $adaTugas,
                    'kehadiran_guru_keterangan' => null,
                    'guru_nip' => $jadwal->guru_nip,
                    'diisi_oleh_id' => null,
                    // Guru-authored side — the column the unique-per-meeting index keys on.
                    'diisi_oleh_peran' => 'guru',
                    // A late journal is written more than a day afterwards; this
                    // drives the "Telat" status chip.
                    'created_at' => mt_rand(1, 100) <= 8
                        ? $tanggal->copy()->addDays(2)->setTime(9, 0)
                        : $tanggal->copy()->setTime(15, 0),
                    'updated_at' => $now,
                ];
            }

            $hariTerkumpul++;
            $tanggal = $tanggal->copy()->subDay();
        }

        foreach (array_chunk($jurnalRows, 500) as $chunk) {
            DB::table('jurnal')->insert($chunk);
        }

        $this->seedPresensi($siswaPerKelas);
    }

    /**
     * One roll call per class per school day — the shape attendance actually has
     * (see the presensi_harian table). The days come from the journals already
     * seeded, so a class only has attendance on days it had lessons.
     *
     * @param  array<int, array<int, Siswa>>  $siswaPerKelas
     */
    private function seedPresensi(array $siswaPerKelas): void
    {
        $keterangan = ['sakit' => 'Surat dokter', 'izin' => 'Izin keluarga', 'alpa' => 'Tanpa keterangan'];
        $now = now();

        // The ketua kelas files it. `diisi_oleh_id` records the *account* that
        // acted, not the student record, so the NIS is mapped to their login.
        $akunPerNis = User::whereNotNull('nis')->pluck('id', 'nis');

        $pengisi = [];
        foreach ($siswaPerKelas as $kelasId => $daftar) {
            $ketua = collect($daftar)->firstWhere('is_ketua_kelas', true) ?? ($daftar[0] ?? null);
            $pengisi[$kelasId] = $ketua ? ($akunPerNis[$ketua->nis] ?? null) : null;
        }

        $buffer = [];

        DB::table('jurnal')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->selectRaw('DISTINCT jadwal.kelas_id, jurnal.tanggal')
            ->orderBy('jadwal.kelas_id')
            ->orderBy('jurnal.tanggal')
            ->chunk(500, function ($hari) use ($siswaPerKelas, $keterangan, $pengisi, $now, &$buffer) {
                foreach ($hari as $baris) {
                    foreach ($siswaPerKelas[$baris->kelas_id] ?? [] as $siswa) {
                        $roll = mt_rand(1, 100);

                        $status = match (true) {
                            $roll <= 88 => 'hadir',
                            $roll <= 94 => 'sakit',
                            $roll <= 97 => 'izin',
                            default => 'alpa',
                        };

                        $buffer[] = [
                            'kelas_id' => $baris->kelas_id,
                            'tanggal' => substr((string) $baris->tanggal, 0, 10),
                            'siswa_nis' => $siswa->nis,
                            'status' => $status,
                            'keterangan' => $keterangan[$status] ?? null,
                            'diisi_oleh_id' => $pengisi[$baris->kelas_id] ?? null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach (array_chunk($buffer, 1000) as $chunk) {
                    DB::table('presensi_harian')->insertOrIgnore($chunk);
                }

                $buffer = [];
            });
    }

    private function slug(string $name): string
    {
        return str_replace(' ', '.', mb_strtolower($name));
    }
}
