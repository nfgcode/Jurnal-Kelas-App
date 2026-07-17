<?php

namespace Database\Seeders;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Seed the application with demo data.
     */
    public function run(): void
    {
        // ---- Admin ----
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@jurnalkelas.app',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // ---- Guru (Teachers) ----
        $guru1 = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@jurnalkelas.app',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'nip' => '198501012010011001',
        ]);

        $guru2 = User::create([
            'name' => 'Siti Nurhaliza',
            'email' => 'siti@jurnalkelas.app',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'nip' => '198702152011012002',
        ]);

        // ---- Kelas (Classes) ----
        $kelas1 = Kelas::create([
            'nama_kelas' => 'X IPA 1',
            'tingkat' => 'X',
            'jurusan' => 'IPA',
            'tahun_ajaran' => '2024/2025',
            'wali_kelas_id' => $guru1->id,
        ]);

        $kelas2 = Kelas::create([
            'nama_kelas' => 'XI IPS 1',
            'tingkat' => 'XI',
            'jurusan' => 'IPS',
            'tahun_ajaran' => '2024/2025',
            'wali_kelas_id' => $guru2->id,
        ]);

        $kelas3 = Kelas::create([
            'nama_kelas' => 'XII IPA 1',
            'tingkat' => 'XII',
            'jurusan' => 'IPA',
            'tahun_ajaran' => '2024/2025',
        ]);

        // ---- Mata Pelajaran (Subjects) ----
        $mapel1 = MataPelajaran::create([
            'nama' => 'Matematika',
            'kode' => 'MTK',
            'deskripsi' => 'Mata pelajaran Matematika untuk semua tingkat.',
        ]);

        $mapel2 = MataPelajaran::create([
            'nama' => 'Bahasa Indonesia',
            'kode' => 'BIN',
            'deskripsi' => 'Mata pelajaran Bahasa Indonesia.',
        ]);

        $mapel3 = MataPelajaran::create([
            'nama' => 'Bahasa Inggris',
            'kode' => 'BIG',
            'deskripsi' => 'Mata pelajaran Bahasa Inggris.',
        ]);

        $mapel4 = MataPelajaran::create([
            'nama' => 'Fisika',
            'kode' => 'FIS',
            'deskripsi' => 'Mata pelajaran Fisika untuk jurusan IPA.',
        ]);

        $mapel5 = MataPelajaran::create([
            'nama' => 'Ekonomi',
            'kode' => 'EKO',
            'deskripsi' => 'Mata pelajaran Ekonomi untuk jurusan IPS.',
        ]);

        // ---- Jadwal (Schedules) ----
        Jadwal::create([
            'kelas_id' => $kelas1->id,
            'mata_pelajaran_id' => $mapel1->id,
            'guru_id' => $guru1->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:30',
            'jam_selesai' => '09:00',
        ]);

        Jadwal::create([
            'kelas_id' => $kelas1->id,
            'mata_pelajaran_id' => $mapel4->id,
            'guru_id' => $guru1->id,
            'hari' => 'Selasa',
            'jam_mulai' => '07:30',
            'jam_selesai' => '09:00',
        ]);

        Jadwal::create([
            'kelas_id' => $kelas2->id,
            'mata_pelajaran_id' => $mapel2->id,
            'guru_id' => $guru2->id,
            'hari' => 'Senin',
            'jam_mulai' => '09:15',
            'jam_selesai' => '10:45',
        ]);

        Jadwal::create([
            'kelas_id' => $kelas2->id,
            'mata_pelajaran_id' => $mapel5->id,
            'guru_id' => $guru2->id,
            'hari' => 'Rabu',
            'jam_mulai' => '07:30',
            'jam_selesai' => '09:00',
        ]);

        Jadwal::create([
            'kelas_id' => $kelas3->id,
            'mata_pelajaran_id' => $mapel3->id,
            'guru_id' => $guru1->id,
            'hari' => 'Kamis',
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:30',
        ]);

        // ---- Siswa (Students) ----
        $siswaData = [
            ['name' => 'Ahmad Fauzi', 'email' => 'ahmad@siswa.app', 'nis' => '20240001', 'kelas_id' => $kelas1->id],
            ['name' => 'Dewi Lestari', 'email' => 'dewi@siswa.app', 'nis' => '20240002', 'kelas_id' => $kelas1->id],
            ['name' => 'Eko Prasetyo', 'email' => 'eko@siswa.app', 'nis' => '20240003', 'kelas_id' => $kelas1->id],
            ['name' => 'Fitri Handayani', 'email' => 'fitri@siswa.app', 'nis' => '20240004', 'kelas_id' => $kelas1->id],
            ['name' => 'Gunawan Wibowo', 'email' => 'gunawan@siswa.app', 'nis' => '20240005', 'kelas_id' => $kelas2->id],
            ['name' => 'Hana Pertiwi', 'email' => 'hana@siswa.app', 'nis' => '20240006', 'kelas_id' => $kelas2->id],
            ['name' => 'Irfan Hakim', 'email' => 'irfan@siswa.app', 'nis' => '20240007', 'kelas_id' => $kelas2->id],
            ['name' => 'Joko Susilo', 'email' => 'joko@siswa.app', 'nis' => '20240008', 'kelas_id' => $kelas3->id],
            ['name' => 'Kartika Sari', 'email' => 'kartika@siswa.app', 'nis' => '20240009', 'kelas_id' => $kelas3->id],
            ['name' => 'Lukman Hakim', 'email' => 'lukman@siswa.app', 'nis' => '20240010', 'kelas_id' => $kelas3->id],
        ];

        foreach ($siswaData as $siswa) {
            User::create([
                'name' => $siswa['name'],
                'email' => $siswa['email'],
                'password' => Hash::make('password'),
                'role' => 'siswa',
                'nis' => $siswa['nis'],
                'kelas_id' => $siswa['kelas_id'],
            ]);
        }
    }
}
