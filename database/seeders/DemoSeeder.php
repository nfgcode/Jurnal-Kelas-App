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

/**
 * Builds a school large enough for the analytics screens to say something:
 * a full timetable, a term of journals, and per-student attendance.
 */
class DemoSeeder extends Seeder
{
    /** School days of journal history to generate. */
    private const HARI_RIWAYAT = 20;

    /** Lesson periods that carry a class; JP 5 is the break. */
    private const JP_SLOT = [[1, 2], [3, 4], [6, 7], [8, 9]];

    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public function run(): void
    {
        // Deterministic output so screenshots and demos stay comparable.
        mt_srand(20260722);

        $admin = $this->seedAdmin();
        $guru = $this->seedGuru();
        $mapel = $this->seedMataPelajaran();
        $kelas = $this->seedKelas($guru);
        $siswa = $this->seedSiswa($kelas);
        $jadwal = $this->seedJadwal($kelas, $mapel, $guru);

        $this->seedJurnalDanPresensi($jadwal, $siswa);

        unset($admin);
    }

    private function seedAdmin(): User
    {
        return User::create([
            'name' => 'Administrator',
            'email' => 'admin@jurnalkelas.app',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'aktif',
            'last_active_at' => now(),
        ]);
    }

    /**
     * @return array<int, User>
     */
    private function seedGuru(): array
    {
        $nama = [
            'Budi Santoso', 'Siti Nurhaliza', 'Ahmad Hidayat', 'Dewi Lestari',
            'Rina Marlina', 'Eko Prasetyo', 'Fitri Handayani', 'Gunawan Wibowo',
            'Hesti Purnama', 'Irfan Maulana', 'Joko Susilo', 'Kartika Sari',
        ];

        $guru = [];

        foreach ($nama as $i => $name) {
            $guru[] = User::create([
                'name' => $name,
                'email' => $this->slug($name) . '@jurnalkelas.app',
                'password' => Hash::make('password'),
                'role' => 'guru',
                'status' => $i === 11 ? 'pending' : 'aktif',
                'nip' => (string) (198504120000 + $i),
                'last_active_at' => now()->subDays(mt_rand(0, 4)),
            ]);
        }

        return $guru;
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
     * @param  array<int, User>  $guru
     * @return array<int, Kelas>
     */
    private function seedKelas(array $guru): array
    {
        $rombel = [
            ['X', 'IPA', 1], ['X', 'IPA', 2], ['X', 'IPS', 1], ['X', 'TKJ', 1],
            ['XI', 'IPA', 1], ['XI', 'IPA', 2], ['XI', 'IPS', 1], ['XI', 'TKJ', 1],
            ['XII', 'IPA', 1], ['XII', 'IPS', 1], ['XII', 'IPS', 2], ['XII', 'TKJ', 1],
        ];

        $kelas = [];

        foreach ($rombel as $i => [$tingkat, $jurusan, $urut]) {
            $kelas[] = Kelas::create([
                'nama_kelas' => "{$tingkat} {$jurusan} {$urut}",
                'tingkat' => $tingkat,
                'jurusan' => $jurusan,
                'ruang' => 'R-' . (101 + $i),
                'kapasitas' => 36,
                'tahun_ajaran' => '2026/2027',
                'wali_kelas_id' => $guru[$i % count($guru)]->id,
            ]);
        }

        return $kelas;
    }

    /**
     * @param  array<int, Kelas>  $kelasList
     * @return array<int, array<int, User>>  students keyed by kelas id
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

            for ($i = 0; $i < 30; $i++) {
                $anggota[] = User::create([
                    'name' => $depan[$i] . ' ' . $belakang[$i % count($belakang)],
                    'email' => 'siswa' . $nis . '@jurnalkelas.app',
                    'password' => Hash::make('password'),
                    'role' => 'siswa',
                    // A handful of dormant and pending accounts keep the
                    // Kelola Pengguna status column honest.
                    'status' => match (true) {
                        $nis % 97 === 0 => 'nonaktif',
                        $nis % 89 === 0 => 'pending',
                        default => 'aktif',
                    },
                    'nis' => (string) $nis,
                    'kelas_id' => $kelas->id,
                    // The first student of each class chairs it and may fill
                    // the class journal.
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
     * @param  array<int, Kelas>  $kelasList
     * @param  array<int, MataPelajaran>  $mapelList
     * @param  array<int, User>  $guruList
     * @return array<int, Jadwal>
     */
    private function seedJadwal(array $kelasList, array $mapelList, array $guruList): array
    {
        // Each subject keeps the same teacher across the school, which is what
        // the "Guru Pengampu" column on Mata Pelajaran reports.
        $pengampu = [];
        foreach ($mapelList as $i => $mapel) {
            $pengampu[$mapel->id] = $guruList[$i % count($guruList)];
        }

        $jadwal = [];

        foreach ($kelasList as $kelas) {
            // Shuffle per class. Walking the subject list in a fixed order
            // leaves the week on a modular cycle, which can starve a teacher of
            // any lesson on a given weekday.
            $urutan = $mapelList;
            shuffle($urutan);
            $rotasi = 0;

            foreach (self::HARI as $hari) {
                foreach (self::JP_SLOT as [$mulai, $selesai]) {
                    $mapel = $urutan[$rotasi % count($urutan)];
                    $rotasi++;

                    $jadwal[] = Jadwal::create([
                        'kelas_id' => $kelas->id,
                        'mata_pelajaran_id' => $mapel->id,
                        'guru_id' => $pengampu[$mapel->id]->id,
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
     * meetings and an attendance row per enrolled student.
     *
     * @param  array<int, Jadwal>  $jadwalList
     * @param  array<int, array<int, User>>  $siswaPerKelas
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
     * @param  array<int, array<int, User>>  $siswaPerKelas
     */
    private function seedPresensi(array $siswaPerKelas): void
    {
        $keterangan = ['sakit' => 'Surat dokter', 'izin' => 'Izin keluarga', 'alpa' => 'Tanpa keterangan'];
        $now = now();
        $buffer = [];

        DB::table('jurnal')
            ->join('jadwal', 'jurnal.jadwal_id', '=', 'jadwal.id')
            ->select('jurnal.id', 'jadwal.kelas_id')
            ->orderBy('jurnal.id')
            ->chunk(500, function ($jurnals) use ($siswaPerKelas, $keterangan, $now, &$buffer) {
                foreach ($jurnals as $jurnal) {
                    foreach ($siswaPerKelas[$jurnal->kelas_id] ?? [] as $siswa) {
                        $roll = mt_rand(1, 100);

                        $status = match (true) {
                            $roll <= 88 => 'hadir',
                            $roll <= 94 => 'sakit',
                            $roll <= 97 => 'izin',
                            default => 'alpa',
                        };

                        $buffer[] = [
                            'jurnal_id' => $jurnal->id,
                            'siswa_id' => $siswa->id,
                            'status' => $status,
                            'keterangan' => $keterangan[$status] ?? null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach (array_chunk($buffer, 1000) as $chunk) {
                    DB::table('presensi')->insert($chunk);
                }

                $buffer = [];
            });
    }

    private function slug(string $name): string
    {
        return str_replace(' ', '.', mb_strtolower($name));
    }
}
