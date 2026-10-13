<?php

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDriverName() !== 'sqlite' || ! str_ends_with(DB::connection()->getDatabaseName(), 'jurusan-ui-20261007.sqlite')) {
    throw new RuntimeException('Use the disposable jurusan UI SQLite database.');
}

if (in_array('--reset', $argv, true)) {
    Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
}

DB::transaction(function () {
    User::create([
        'username' => 'audit.admin', 'nama' => 'Admin Audit', 'email' => 'audit.admin@example.test',
        'password' => 'password123', 'role' => 'admin', 'status' => 'aktif',
    ]);
    TahunAjaran::create(['kode' => '2026/2027', 'aktif' => true]);
    Jurusan::create(['kode' => 'TKJ', 'nama' => 'Teknik Komputer dan Jaringan', 'bidang' => 'Teknologi Informasi', 'aktif' => true]);
    for ($i = 1; $i <= 30; $i++) {
        Jurusan::create([
            'kode' => sprintf('UJI%02d', $i), 'nama' => 'Jurusan pengujian '.$i,
            'bidang' => 'Bidang Uji', 'aktif' => $i % 2 === 0,
        ]);
    }
    $kelas = Kelas::create(['nama_kelas' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'tahun_ajaran_kode' => '2026/2027']);
    $siswa = Siswa::create(['nis' => '0026001', 'nama' => 'Ketua Audit', 'kelas_id' => $kelas->id, 'status' => 'aktif']);
    $kelas->update(['ketua_nis' => $siswa->nis]);
    $ketua = User::create([
        'username' => 'audit.ketua', 'nis' => $siswa->nis, 'email' => 'audit.ketua@example.test',
        'password' => 'password123', 'role' => 'siswa', 'status' => 'aktif',
    ]);
    Guru::create(['nip' => '900001', 'nama' => 'Guru Audit', 'status' => 'aktif']);
    User::create([
        'username' => 'audit.guru', 'nip' => '900001', 'email' => 'audit.guru@example.test',
        'password' => 'password123', 'role' => 'guru', 'status' => 'aktif',
    ]);
    $mapel = MataPelajaran::create(['kode' => 'UJI', 'nama' => 'Pelajaran Audit']);
    $jadwal = Jadwal::create([
        'kelas_id' => $kelas->id, 'guru_nip' => '900001', 'mata_pelajaran_id' => $mapel->id,
        'hari' => today()->locale('id')->dayName, 'jam_ke_mulai' => 1, 'jam_ke_selesai' => 2,
    ]);
    Jurnal::create([
        'public_id' => '01JAUDIT000000000000000001', 'jadwal_id' => $jadwal->id,
        'tanggal' => today()->toDateString(), 'materi' => 'Materi pengujian browser',
        'diisi_oleh_id' => $ketua->id, 'diisi_oleh_peran' => 'siswa', 'siswa_nis' => $siswa->nis,
    ]);
});
echo "Disposable UI fixtures ready.\n";
