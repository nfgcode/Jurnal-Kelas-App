<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Ruangan;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\SimpanPresensiJurnal;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Builds a school large enough for the analytics screens to say something:
 * a full timetable, a term of journals, and per-student attendance.
 *
 * People and accounts are seeded as the schema now holds them — a `guru`/`siswa`
 * row for the person and a `users` row for their login — but in bulk rather
 * than through the stored procedure, which exists for one-at-a-time entry by an
 * admin, not for writing 372 accounts.
 */
class DemoSeeder extends Seeder
{
    /** Lesson periods that carry a class; JP 5 is the break. */
    private const JP_SLOT = [[1, 2], [3, 4], [6, 7], [8, 9]];

    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /**
     * Every demo login shares one password. Hashed once and reused: bcrypt is
     * deliberately slow, and hashing it per row made reseeding — which the test
     * suite does for every single test — cost seconds of pure key stretching.
     */
    private string $sandi;

    /**
     * School days of journal history to generate. A full term keeps the "Bulan
     * Lalu" filter populated in real use, but the suite reseeds for every test,
     * so under `testing` it is trimmed to keep that reseed cheap.
     */
    private function hariRiwayat(): int
    {
        return app()->environment('testing') ? 20 : 90;
    }

    public function run(): void
    {
        // Deterministic output so screenshots and demos stay comparable.
        mt_srand(20260722);

        $this->sandi = Hash::make('password');

        $ruangan = $this->seedRuangan();
        $tahun = $this->seedTahunAjaran();
        $jurusan = $this->seedJurusan();
        $this->seedAdmin();
        $guru = $this->seedGuru();
        $mapel = $this->seedMataPelajaran();
        $pengampu = $this->seedGuruMapel($guru, $mapel);
        $kelas = $this->seedKelas($guru, $ruangan, $jurusan, $tahun);
        $siswa = $this->seedSiswa($kelas);
        $jadwal = $this->seedJadwal($kelas, $mapel, $pengampu);

        $this->seedJurnalDanPresensi($jadwal, $siswa);
    }

    /**
     * @return array<int, Ruangan>
     */
    private function seedRuangan(): array
    {
        $ruangan = [];

        // One home room per rombel, numbered the way schools number them.
        for ($i = 0; $i < 12; $i++) {
            $kode = 'R-'.(101 + $i);
            $ruangan[] = Ruangan::create([
                'kode' => $kode,
                'nama' => 'Ruang Kelas '.(101 + $i),
                'jenis' => 'kelas',
                'kapasitas' => 36,
                'gedung' => 'Gedung '.chr(65 + intdiv($i, 6)),
                'lantai' => 1 + intdiv($i % 6, 3),
            ]);
        }

        // Special rooms, so the register is not just a list of identical boxes.
        foreach ([
            ['LAB-KOM', 'Laboratorium Komputer', 'laboratorium', 32],
            ['LAB-IPA', 'Laboratorium IPA', 'laboratorium', 30],
            ['AULA', 'Aula Serbaguna', 'aula', 150],
            ['PERPUS', 'Perpustakaan', 'perpustakaan', 40],
        ] as [$kode, $nama, $jenis, $kapasitas]) {
            $ruangan[] = Ruangan::create([
                'kode' => $kode,
                'nama' => $nama,
                'jenis' => $jenis,
                'kapasitas' => $kapasitas,
                'gedung' => 'Gedung A',
                'lantai' => 1,
            ]);
        }

        return $ruangan;
    }

    private function seedTahunAjaran(): TahunAjaran
    {
        return TahunAjaran::create([
            'kode' => '2026/2027',
            'mulai' => '2026-07-13',
            'selesai' => '2027-06-19',
            'aktif' => true,
        ]);
    }

    /**
     * @return array<string, Jurusan>
     */
    private function seedJurusan(): array
    {
        $daftar = [
            ['IPA', 'Ilmu Pengetahuan Alam', 'Akademik'],
            ['IPS', 'Ilmu Pengetahuan Sosial', 'Akademik'],
            ['TKJ', 'Teknik Komputer dan Jaringan', 'Teknologi Informasi'],
        ];

        $jurusan = [];

        foreach ($daftar as [$kode, $nama, $bidang]) {
            $jurusan[$kode] = Jurusan::create(['kode' => $kode, 'nama' => $nama, 'bidang' => $bidang]);
        }

        return $jurusan;
    }

    private function seedAdmin(): User
    {
        return User::create([
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
     * @return array<int, Guru>
     */
    private function seedGuru(): array
    {
        // Eighteen for twelve classes. Twelve would exactly saturate the week
        // (12 classes x 24 blocks = 12 teachers x 24 blocks), leaving the
        // scheduler no room to route around a clash — which is how the old data
        // ended up with a teacher in two rooms at once.
        $nama = [
            'Budi Santoso', 'Siti Nurhaliza', 'Ahmad Hidayat', 'Dewi Lestari',
            'Rina Marlina', 'Eko Prasetyo', 'Fitri Handayani', 'Gunawan Wibowo',
            'Hesti Purnama', 'Irfan Maulana', 'Joko Susilo', 'Kartika Sari',
            'Lukman Hakim', 'Maya Anggraini', 'Nanda Pratama', 'Oki Rahmawati',
            'Putri Ramadhani', 'Rahmat Kurniawan',
        ];

        $guru = [];
        $akun = [];

        foreach ($nama as $i => $name) {
            $nip = (string) (198504120000 + $i);

            $guru[] = [
                'nip' => $nip,
                'nama' => $name,
                'jenis_kelamin' => $i % 2 === 0 ? 'L' : 'P',
                'no_hp' => '0812'.str_pad((string) (3000 + $i), 8, '0', STR_PAD_LEFT),
                'alamat' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $akun[] = [
                'username' => $this->slug($name),
                'nama' => null,
                'email' => $this->slug($name).'@jurnalkelas.app',
                'password' => $this->sandi,
                'role' => 'guru',
                // One pending account keeps the status column on the account
                // list honest.
                'status' => $i === 11 ? 'pending' : 'aktif',
                'nip' => $nip,
                'last_active_at' => now()->subDays(mt_rand(0, 4)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('guru')->insert($guru);
        DB::table('users')->insert($akun);

        return Guru::orderBy('nip')->get()->all();
    }

    /**
     * @return array<int, MataPelajaran>
     */
    private function seedMataPelajaran(): array
    {
        $daftar = [
            ['Matematika', 'MTK', 'wajib', 6],
            ['Bahasa Indonesia', 'BIN', 'wajib', 5],
            ['Bahasa Inggris', 'BIG', 'wajib', 4],
            ['Fisika', 'FIS', 'peminatan', 4],
            ['Kimia', 'KIM', 'peminatan', 4],
            ['Biologi', 'BIO', 'peminatan', 4],
            ['Ekonomi', 'EKO', 'peminatan', 4],
            ['Sejarah', 'SEJ', 'wajib', 3],
            ['Geografi', 'GEO', 'peminatan', 3],
            ['Sosiologi', 'SOS', 'peminatan', 3],
            ['PPKn', 'PKN', 'wajib', 2],
            ['Pendidikan Agama', 'PAI', 'wajib', 3],
            ['PJOK', 'PJK', 'wajib', 3],
            ['Informatika', 'INF', 'wajib', 2],
            ['Produktif TKJ', 'TKJ', 'kejuruan', 8],
            ['Seni Budaya', 'SBD', 'wajib', 2],
            ['Prakarya', 'PKY', 'wajib', 2],
            ['Bahasa Jawa', 'BJW', 'muatan_lokal', 2],
        ];

        return array_map(fn ($row) => MataPelajaran::create([
            'nama' => $row[0],
            'kode' => $row[1],
            'kelompok' => $row[2],
            'jp_per_minggu' => $row[3],
            'deskripsi' => "Mata pelajaran {$row[0]}.",
        ]), $daftar);
    }

    /**
     * Record which teacher is certified for which subject, and hand back the
     * mapping the timetable is then built from.
     *
     * Built once and used for both, because the schedule form now refuses a
     * pairing that is not in this pivot — seed data that contradicted it would
     * be data an admin could not have entered by hand.
     *
     * Three teachers per subject, so the scheduler has somewhere to turn when
     * its first choice is already teaching. One certified teacher per subject
     * would make a clash unavoidable the moment two classes want that subject
     * in the same period.
     *
     * @param  array<int, Guru>  $guruList
     * @param  array<int, MataPelajaran>  $mapelList
     * @return array<int, array<int, string>> NIPs keyed by subject id
     */
    private function seedGuruMapel(array $guruList, array $mapelList): array
    {
        $pengampu = [];
        $pivot = [];
        $n = count($guruList);
        $utamaTerpakai = [];

        foreach ($mapelList as $i => $mapel) {
            $nips = [];

            for ($k = 0; $k < 3; $k++) {
                $guru = $guruList[($i * 3 + $k) % $n];
                $nips[] = $guru->nip;

                // Each teacher's first subject is their principal one; the rest
                // are the second and third they also cover.
                $utama = ! isset($utamaTerpakai[$guru->nip]);
                $utamaTerpakai[$guru->nip] = true;

                $pivot[] = [
                    'guru_nip' => $guru->nip,
                    'mata_pelajaran_id' => $mapel->id,
                    'utama' => $utama,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $pengampu[$mapel->id] = array_values(array_unique($nips));
        }

        DB::table('guru_mata_pelajaran')->insert($pivot);

        return $pengampu;
    }

    /**
     * @param  array<int, Guru>  $guru
     * @param  array<int, Ruangan>  $ruangan
     * @param  array<string, Jurusan>  $jurusan
     * @return array<int, Kelas>
     */
    private function seedKelas(array $guru, array $ruangan, array $jurusan, TahunAjaran $tahun): array
    {
        $rombel = [
            ['X', 'IPA', 1], ['X', 'IPA', 2], ['X', 'IPS', 1], ['X', 'TKJ', 1],
            ['XI', 'IPA', 1], ['XI', 'IPA', 2], ['XI', 'IPS', 1], ['XI', 'TKJ', 1],
            ['XII', 'IPA', 1], ['XII', 'IPS', 1], ['XII', 'IPS', 2], ['XII', 'TKJ', 1],
        ];

        $kelas = [];

        foreach ($rombel as $i => [$tingkat, $kodeJurusan, $urut]) {
            $kelas[] = Kelas::create([
                'nama_kelas' => "{$tingkat} {$kodeJurusan} {$urut}",
                'tingkat' => $tingkat,
                'jurusan_kode' => $jurusan[$kodeJurusan]->kode,
                'paralel' => $urut,
                'ruangan_kode' => $ruangan[$i]->kode,
                'kapasitas' => 36,
                'tahun_ajaran_kode' => $tahun->kode,
                // One wali per class; there are more teachers than classes, so
                // nobody holds two homerooms.
                'wali_kelas_nip' => $guru[$i]->nip,
            ]);
        }

        return $kelas;
    }

    /**
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

            for ($i = 0; $i < 30; $i++) {
                $orang[] = [
                    'nis' => (string) $nis,
                    'nisn' => '007'.$nis,
                    'nama' => $depan[$i].' '.$belakang[$i % count($belakang)],
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
                    // A handful of dormant and pending accounts keep the
                    // account-list status column honest.
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

        // The first student of each class chairs it. The fact lives on the
        // class, so it is set here rather than on each student row.
        foreach ($nisPerKelas as $kelasId => $daftarNis) {
            DB::table('kelas')->where('id', $kelasId)->update(['ketua_nis' => $daftarNis[0]]);
        }

        $semua = Siswa::whereIn('nis', array_merge(...array_values($nisPerKelas)))->get()->keyBy('nis');

        $siswa = [];
        foreach ($nisPerKelas as $kelasId => $daftarNis) {
            $siswa[$kelasId] = array_map(fn ($n) => $semua[$n], $daftarNis);
        }

        return $siswa;
    }

    /**
     * Fill every class's week without ever booking a teacher twice.
     *
     * The subject bag is walked slot by slot; when nobody certified for the next
     * subject is free, that subject is pushed to the back and the next one is
     * tried. A class keeps the same teacher for a subject all week whenever that
     * teacher is free, so the timetable still reads like a real one.
     *
     * @param  array<int, Kelas>  $kelasList
     * @param  array<int, MataPelajaran>  $mapelList
     * @param  array<int, array<int, string>>  $pengampu  NIPs keyed by subject id
     * @return array<int, Jadwal>
     */
    private function seedJadwal(array $kelasList, array $mapelList, array $pengampu): array
    {
        $rows = [];
        $penjadwal = new PenjadwalTanpaBentrok;

        foreach ($kelasList as $kelas) {
            // Shuffle per class. Walking the subject list in a fixed order
            // leaves the week on a modular cycle, which can starve a teacher of
            // any lesson on a given weekday.
            $bag = $mapelList;
            shuffle($bag);
            $guruKelas = [];

            foreach (self::HARI as $hari) {
                foreach (self::JP_SLOT as [$mulai, $selesai]) {
                    [$mapel, $nip] = $this->pilihUntukSlot($bag, $pengampu, $guruKelas, $penjadwal, $hari, $mulai);

                    if ($mapel === null) {
                        continue; // Nobody free at all: leave the slot empty.
                    }

                    $guruKelas[$mapel->id] = $nip;
                    $penjadwal->tandai($nip, $hari, $mulai);

                    $rows[] = [
                        'kelas_id' => $kelas->id,
                        'mata_pelajaran_id' => $mapel->id,
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

        return Jadwal::orderBy('id')->get()->all();
    }

    /**
     * Take the first subject from the bag whose teachers are not all busy.
     *
     * The bag rotates rather than shrinks, so a subject that could not be placed
     * now is tried again in a later slot instead of being lost.
     *
     * @param  array<int, MataPelajaran>  $bag
     * @param  array<int, array<int, string>>  $pengampu
     * @param  array<int, string>  $guruKelas
     * @return array{0: MataPelajaran|null, 1: string|null}
     */
    private function pilihUntukSlot(
        array &$bag,
        array $pengampu,
        array $guruKelas,
        PenjadwalTanpaBentrok $penjadwal,
        string $hari,
        int $jamKe,
    ): array {
        for ($coba = 0; $coba < count($bag); $coba++) {
            $mapel = array_shift($bag);
            $bag[] = $mapel; // straight to the back, whether it fits or not

            $nip = $penjadwal->pilih(
                $pengampu[$mapel->id] ?? [],
                $hari,
                $jamKe,
                $guruKelas[$mapel->id] ?? null,
            );

            if ($nip !== null) {
                return [$mapel, $nip];
            }
        }

        return [null, null];
    }

    /**
     * Walk backwards through school days, writing a journal for most scheduled
     * meetings and an attendance row per enrolled student.
     *
     * @param  array<int, Jadwal>  $jadwalList
     * @param  array<int, array<int, Siswa>>  $siswaPerKelas
     */
    private function seedJurnalDanPresensi(array $jadwalList, array $siswaPerKelas): void
    {
        $materi = [
            'Persamaan kuadrat', 'Teks eksposisi', 'Narrative text', 'Gerak lurus beraturan',
            'Ikatan kimia', 'Sistem peredaran darah', 'Permintaan dan penawaran',
            'Kerajaan Majapahit', 'Peta dan penginderaan jauh', 'Interaksi sosial',
            'Norma dalam masyarakat', 'Akhlak terpuji', 'Kebugaran jasmani',
            'Instalasi jaringan LAN', 'Algoritma dasar', 'Seni rupa dua dimensi',
        ];

        $tugas = [
            'Latihan soal buku paket halaman 42–45.', 'Membuat rangkuman bab 3.',
            'Laporan praktikum.', 'Tugas kelompok, dikumpulkan pertemuan berikutnya.',
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
        $hariRiwayat = $this->hariRiwayat();

        while ($hariTerkumpul < $hariRiwayat) {
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
                    // would have generated has to be written here as well —
                    // without it every link to the journal is unbuildable.
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
                    'diisi_oleh_id' => null,
                    'diisi_oleh_peran' => 'guru',
                    // A late journal is one written more than a day afterwards;
                    // this is what drives the "Telat" status chip.
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
     * One roster per meeting, marked by the teacher who taught it — plus the
     * class-day rollup those rosters derive into, so the recaps and exports have
     * something to read.
     *
     * A student's day usually looks the same in every lesson: they were in
     * school or they were not. So a base status is drawn per class-day and each
     * lesson mostly repeats it, deviating occasionally the way a real timetable
     * does — which is the whole reason a roster hangs off the meeting. The daily
     * row then takes the most consequential mark of the day, exactly as
     * {@see SimpanPresensiJurnal} computes it at runtime.
     *
     * @param  array<int, array<int, Siswa>>  $siswaPerKelas
     */
    private function seedPresensi(array $siswaPerKelas): void
    {
        $keterangan = ['sakit' => 'Surat dokter', 'izin' => 'Izin keluarga', 'alpa' => 'Tanpa keterangan'];
        $bobot = ['hadir' => 0, 'sakit' => 1, 'izin' => 2, 'alpa' => 3];
        $now = now();

        // `diisi_oleh_id` records the *account* that acted, not the person row,
        // so the meeting's NIP is mapped to its teacher's login.
        $akunPerNip = User::whereNotNull('nip')->pluck('id', 'nip');

        $simpan = function (array $pertemuan) use ($siswaPerKelas, $keterangan, $bobot, $akunPerNip, $now) {
            if ($pertemuan === []) {
                return;
            }

            $kelasId = (int) $pertemuan[0]->kelas_id;
            $tanggal = substr((string) $pertemuan[0]->tanggal, 0, 10);
            $terakhir = $pertemuan[count($pertemuan) - 1];

            $perPertemuan = [];
            $harian = [];

            foreach ($siswaPerKelas[$kelasId] ?? [] as $siswa) {
                $roll = mt_rand(1, 100);

                $dasar = match (true) {
                    $roll <= 88 => 'hadir',
                    $roll <= 94 => 'sakit',
                    $roll <= 97 => 'izin',
                    default => 'alpa',
                };

                $terberat = $dasar;

                foreach ($pertemuan as $baris) {
                    $status = mt_rand(1, 100) <= 94
                        ? $dasar
                        : ['hadir', 'sakit', 'izin', 'alpa'][mt_rand(0, 3)];

                    $perPertemuan[] = [
                        'jurnal_id' => $baris->jurnal_id,
                        'siswa_nis' => $siswa->nis,
                        'status' => $status,
                        'keterangan' => $keterangan[$status] ?? null,
                        'diisi_oleh_id' => $akunPerNip[$baris->guru_nip] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if ($bobot[$status] > $bobot[$terberat]) {
                        $terberat = $status;
                    }
                }

                $harian[] = [
                    'kelas_id' => $kelasId,
                    'tanggal' => $tanggal,
                    'siswa_nis' => $siswa->nis,
                    'status' => $terberat,
                    'keterangan' => $keterangan[$terberat] ?? null,
                    'diisi_oleh_id' => $akunPerNip[$terakhir->guru_nip] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($perPertemuan, 1000) as $chunk) {
                DB::table('presensi')->insertOrIgnore($chunk);
            }

            foreach (array_chunk($harian, 1000) as $chunk) {
                DB::table('presensi_harian')->insertOrIgnore($chunk);
            }
        };

        // Ordered by class then date, so one pass can gather each class-day's
        // meetings together without holding the whole timetable in memory.
        $baris = DB::table('jurnal')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->select(['jurnal.id as jurnal_id', 'jurnal.tanggal', 'jadwal.kelas_id', 'jadwal.guru_nip'])
            ->orderBy('jadwal.kelas_id')
            ->orderBy('jurnal.tanggal')
            ->orderBy('jurnal.id')
            ->lazy(500);

        $kunci = null;
        $kumpulan = [];

        foreach ($baris as $row) {
            $ini = $row->kelas_id.'|'.substr((string) $row->tanggal, 0, 10);

            if ($kunci !== null && $ini !== $kunci) {
                $simpan($kumpulan);
                $kumpulan = [];
            }

            $kunci = $ini;
            $kumpulan[] = $row;
        }

        $simpan($kumpulan);
    }

    private function slug(string $name): string
    {
        return str_replace(' ', '.', mb_strtolower($name));
    }
}
