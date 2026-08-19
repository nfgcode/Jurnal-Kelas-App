<?php

namespace Database\Seeders;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
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
     * The ten jurusan. `paralel` is how many classes of that jurusan run per
     * grade (Akuntansi has two: "1" and "2"); `produktif` are the jurusan's own
     * vocational subjects [nama, kode, jp/minggu]. Codes are unique across the
     * whole subject list.
     */
    private function daftarJurusan(): array
    {
        return [
            'AKL' => ['nama' => 'Akuntansi dan Keuangan Lembaga', 'paralel' => 2, 'produktif' => [
                ['Akuntansi Keuangan', 'PAK', 6],
                ['Komputer Akuntansi', 'PKA', 5],
                ['Praktikum Akuntansi Lembaga', 'PAL', 5],
            ]],
            'MPLB' => ['nama' => 'Manajemen Perkantoran dan Layanan Bisnis', 'paralel' => 2, 'produktif' => [
                ['Otomatisasi Perkantoran', 'POP', 6],
                ['Kearsipan', 'PKR', 5],
                ['Korespondensi', 'PKO', 5],
            ]],
            'BDP' => ['nama' => 'Bisnis Digital dan Pemasaran', 'paralel' => 1, 'produktif' => [
                ['Bisnis Online', 'PBO', 6],
                ['Pengelolaan Bisnis Ritel', 'PBR', 5],
                ['Pemasaran', 'PPM', 5],
            ]],
            'TKJ' => ['nama' => 'Teknik Komputer dan Jaringan', 'paralel' => 2, 'produktif' => [
                ['Administrasi Sistem Jaringan', 'PAS', 6],
                ['Teknologi Jaringan Berbasis Luas', 'PTJ', 6],
                ['Komputer dan Jaringan Dasar', 'PKJ', 5],
            ]],
            'RPL' => ['nama' => 'Rekayasa Perangkat Lunak', 'paralel' => 2, 'produktif' => [
                ['Pemrograman Web dan Perangkat Bergerak', 'PPW', 6],
                ['Pemrograman Berorientasi Objek', 'PBP', 6],
                ['Basis Data', 'PBD', 5],
            ]],
            'DKV' => ['nama' => 'Desain Komunikasi Visual', 'paralel' => 1, 'produktif' => [
                ['Desain Grafis Percetakan', 'PDG', 6],
                ['Animasi 2D dan 3D', 'PAN', 5],
                ['Fotografi', 'PFO', 5],
            ]],
            'TKR' => ['nama' => 'Teknik Kendaraan Ringan Otomotif', 'paralel' => 2, 'produktif' => [
                ['Pemeliharaan Mesin Kendaraan Ringan', 'PMK', 8],
                ['Pemeliharaan Sasis dan Pemindah Tenaga', 'PSP', 7],
                ['Pemeliharaan Kelistrikan Kendaraan', 'PKK', 5],
            ]],
            'TBSM' => ['nama' => 'Teknik dan Bisnis Sepeda Motor', 'paralel' => 1, 'produktif' => [
                ['Pemeliharaan Mesin Sepeda Motor', 'PMS', 8],
                ['Pemeliharaan Kelistrikan Sepeda Motor', 'PKM', 6],
                ['Pemeliharaan Sasis Sepeda Motor', 'PSS', 5],
            ]],
            'TITL' => ['nama' => 'Teknik Instalasi Tenaga Listrik', 'paralel' => 1, 'produktif' => [
                ['Instalasi Penerangan Listrik', 'PIP', 7],
                ['Instalasi Tenaga Listrik', 'PIT', 7],
                ['Instalasi Motor Listrik', 'PIM', 6],
            ]],
            'KUL' => ['nama' => 'Kuliner', 'paralel' => 1, 'produktif' => [
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

        $this->seedAdmin();

        [$mapelUmum, $mapelProduktif] = $this->seedMataPelajaran();

        $rombelSpec = $this->rencanaRombel();
        $guru = $this->seedGuru(count($rombelSpec));
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

    private function seedAdmin(): void
    {
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@jurnalkelas.app',
            'password' => Hash::make('password'),
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
     * @return array<int, User>
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

        $guru = [];
        foreach ($nama as $k => $name) {
            $guru[] = User::create([
                'name' => $name,
                'email' => $this->slug($name).($k + 1).'@jurnalkelas.app',
                'password' => Hash::make('password'),
                'role' => 'guru',
                // One dormant teacher keeps the Kelola Pengguna status column honest.
                'status' => $k === $jumlah - 1 ? 'nonaktif' : 'aktif',
                'nip' => (string) (198500000001 + $k),
                'last_active_at' => now()->subDays(mt_rand(0, 5)),
            ]);
        }

        return $guru;
    }

    /**
     * Split the teacher list into a shared pool (teaches normative/adaptive
     * subjects across the school) and three productive teachers per jurusan.
     *
     * @param  array<int, User>  $guru
     * @return array{0: array<int, User>, 1: array<string, array<int, User>>}
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
     * @param  array<int, User>  $guru
     * @return array<int, Kelas>
     */
    private function seedKelas(array $rombelSpec, array $guru): array
    {
        $kelas = [];

        foreach ($rombelSpec as $i => $spec) {
            $kelas[] = Kelas::create([
                'nama_kelas' => $spec['nama_kelas'],
                'tingkat' => $spec['tingkat'],
                // The full jurusan name — it is shown as a caption and drives the
                // jurusan filter dropdown, where "Teknik Komputer dan Jaringan"
                // reads better than "TKJ".
                'jurusan' => $spec['nama'],
                'ruang' => $spec['ruang'],
                'kapasitas' => 36,
                'tahun_ajaran' => self::TAHUN_AJARAN,
                // One distinct homeroom teacher per class (guru list is sized to match).
                'wali_kelas_id' => $guru[$i]->id,
            ]);
        }

        return $kelas;
    }

    /**
     * 30 students per class. Names repeat across classes (they are different
     * people); nis / email are unique.
     *
     * @param  array<int, Kelas>  $kelasList
     * @return array<int, array<int, User>> students keyed by kelas id
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

        $siswa = [];
        $nis = 20261001;

        foreach ($kelasList as $kelas) {
            $anggota = [];

            for ($i = 0; $i < self::SISWA_PER_KELAS; $i++) {
                $anggota[] = User::create([
                    'name' => $depan[$i % count($depan)].' '.$belakang[$i % count($belakang)],
                    'email' => 'siswa'.$nis.'@jurnalkelas.app',
                    'password' => Hash::make('password'),
                    'role' => 'siswa',
                    // A handful of dormant/pending accounts keep the status column honest.
                    'status' => match (true) {
                        $nis % 97 === 0 => 'nonaktif',
                        $nis % 89 === 0 => 'pending',
                        default => 'aktif',
                    },
                    'nis' => (string) $nis,
                    'kelas_id' => $kelas->id,
                    // The first student of each class chairs it and may fill the journal.
                    'is_ketua_kelas' => $i === 0,
                    'last_active_at' => now()->subDays(mt_rand(0, 6)),
                ]);

                $nis++;
            }

            $siswa[$kelas->id] = $anggota;
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
     * @param  array<int, User>  $guruUmum
     * @param  array<string, array<int, User>>  $guruProduktif
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
        $jadwal = [];
        // Rotates each shared subject across the shared pool as classes are filled,
        // so no single teacher carries the same subject for the whole school.
        $rotasiUmum = [];

        foreach ($kelasList as $i => $kelas) {
            $kode = $rombelSpec[$i]['kode'];

            // The subject → teacher map for this class, fixed for the whole week.
            $mapel = array_merge(array_values($mapelUmum), $mapelProduktif[$kode]);
            $guruUntuk = [];

            foreach (array_values($mapelUmum) as $m) {
                $rotasiUmum[$m->id] ??= 0;
                $guruUntuk[$m->id] = $guruUmum[$rotasiUmum[$m->id]++ % count($guruUmum)]->id;
            }
            foreach ($mapelProduktif[$kode] as $k => $m) {
                $guruUntuk[$m->id] = $guruProduktif[$kode][$k % count($guruProduktif[$kode])]->id;
            }

            // Shuffle the subject bag per class, then walk it to fill 24 slots.
            $urutan = $mapel;
            shuffle($urutan);
            $rotasi = 0;

            foreach (self::HARI as $hari) {
                foreach (self::JP_SLOT as [$mulai, $selesai]) {
                    $m = $urutan[$rotasi % count($urutan)];
                    $rotasi++;

                    $jadwal[] = Jadwal::create([
                        'kelas_id' => $kelas->id,
                        'mata_pelajaran_id' => $m->id,
                        'guru_id' => $guruUntuk[$m->id],
                        'hari' => $hari,
                        'jam_ke_mulai' => $mulai,
                        'jam_ke_selesai' => $selesai,
                        'jam_mulai' => sprintf('%02d:%02d', 6 + $mulai, ($mulai % 2) * 30),
                        'jam_selesai' => sprintf('%02d:%02d', 7 + $selesai, ($selesai % 2) * 30),
                        'ruang' => $kelas->ruang,
                    ]);
                }
            }
        }

        return $jadwal;
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
                    'guru_id' => $jadwal->guru_id,
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
     * @param  array<int, array<int, User>>  $siswaPerKelas
     */
    private function seedPresensi(array $siswaPerKelas): void
    {
        $keterangan = ['sakit' => 'Surat dokter', 'izin' => 'Izin keluarga', 'alpa' => 'Tanpa keterangan'];
        $now = now();

        // The ketua kelas files it; fall back to whoever is first in the class so
        // demo data still names a plausible author.
        $pengisi = [];
        foreach ($siswaPerKelas as $kelasId => $daftar) {
            $ketua = collect($daftar)->firstWhere('is_ketua_kelas', true) ?? ($daftar[0] ?? null);
            $pengisi[$kelasId] = $ketua?->id;
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
                            'siswa_id' => $siswa->id,
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
