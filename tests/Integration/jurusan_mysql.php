<?php

use App\Support\CadanganData;
use App\Support\ImporPengguna;
use App\Support\PencatatanAkademik;
use App\Support\PendaftaranPengguna;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Run only against a disposable MySQL database, never the application's database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDriverName() !== 'mysql' || ! str_contains(DB::connection()->getDatabaseName(), '_jurusan_test_')) {
    throw new RuntimeException('Use a disposable database with _jurusan_test_ in its name.');
}

$checks = 0;
$check = function (bool $condition, string $name) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException($name);
    }
    $checks++;
    echo "PASS: {$name}\n";
};
$service = app(PencatatanAkademik::class);
$service->jurusan(['kode' => 'tkj', 'nama' => 'Jaringan', 'aktif' => true]);
$check(DB::table('jurusan')->where('kode', 'TKJ')->exists(), 'Procedure normalizes code');

foreach ([['kode' => 'TKJ', 'nama' => 'Duplicate'], ['kode' => 'TK J', 'nama' => 'Whitespace'],
    ['kode' => 'LONG'.str_repeat('X', 20), 'nama' => 'Long'], ['kode' => 'INVALID', 'nama' => '']] as $data) {
    try {
        $service->jurusan($data + ['aktif' => true]);
        throw new RuntimeException('Invalid procedure call accepted');
    } catch (ValidationException $e) {
        $check(DB::table('jurusan')->count() === 1, 'Failed procedure leaves no added data: '.$data['kode']);
        $check(! DB::connection()->getPdo()->inTransaction(), 'Failed procedure closes transaction');
    }
}
$service->jurusan(['kode' => 'RPL', 'nama' => 'Rekayasa', 'aktif' => true]);
$check(DB::table('jurusan')->count() === 2, 'Procedure works after failures');

DB::table('tahun_ajaran')->insert(['kode' => '2026/2027', 'aktif' => true]);
$kelasA = DB::table('kelas')->insertGetId(['nama_kelas' => 'X TKJ', 'tingkat' => 'X', 'jurusan_kode' => 'TKJ', 'tahun_ajaran_kode' => '2026/2027']);
$kelasB = DB::table('kelas')->insertGetId(['nama_kelas' => 'X RPL', 'tingkat' => 'X', 'jurusan_kode' => 'RPL', 'tahun_ajaran_kode' => '2026/2027']);
DB::table('siswa')->insert(['nis' => '0026001', 'nama' => 'Siswa Uji', 'kelas_id' => $kelasA, 'jurusan_kode' => 'RPL']);
$student = fn () => DB::table('siswa')->where('nis', '0026001')->first();
$check($student()->jurusan_kode === 'TKJ', 'Raw insert follows class');
DB::table('siswa')->where('nis', '0026001')->update(['jurusan_kode' => 'RPL']);
$check($student()->jurusan_kode === 'TKJ', 'Raw manual jurusan edit is overwritten');
DB::table('siswa')->where('nis', '0026001')->update(['kelas_id' => $kelasB]);
$check($student()->jurusan_kode === 'RPL', 'Move follows destination class');
DB::table('kelas')->where('id', $kelasB)->update(['jurusan_kode' => null]);
$check($student()->jurusan_kode === null, 'Class jurusan null propagates');
DB::table('kelas')->where('id', $kelasB)->update(['jurusan_kode' => 'RPL']);
$check($student()->jurusan_kode === 'RPL', 'Class jurusan change propagates');
DB::table('jurusan')->where('kode', 'RPL')->update(['kode' => 'PPLG']);
$check($student()->jurusan_kode === 'PPLG' && DB::table('kelas')->where('id', $kelasB)->value('jurusan_kode') === 'PPLG', 'Code rename cascades to class and student');
try {
    DB::table('jurusan')->where('kode', 'PPLG')->delete();
    throw new RuntimeException('Referenced jurusan deleted');
} catch (QueryException $e) {
    $check(($e->errorInfo[1] ?? null) === 1451, 'FK restricts jurusan deletion');
}
DB::beginTransaction();
DB::table('kelas')->where('id', $kelasB)->update(['jurusan_kode' => null]);
DB::rollBack();
$check($student()->jurusan_kode === 'PPLG', 'Class and student changes roll back together');

$row = (array) $student();
$row['jurusan_kode'] = 'TKJ';
app(CadanganData::class)->pulihkan(['tabel' => ['siswa' => [$row]]], 'gabung');
$check($student()->jurusan_kode === 'PPLG', 'Restore recomputes jurusan');
DB::table('kelas')->where('id', $kelasB)->delete();
$check($student()->kelas_id === null && $student()->jurusan_kode === null, 'Class delete clears both references');

$registered = app(PendaftaranPengguna::class)->siswa([
    'nis' => '0026002', 'nama' => 'Siswa Procedure', 'kelas_id' => $kelasA,
    'username' => 'siswa.procedure', 'email' => 'procedure@example.test', 'password' => 'password123',
]);
$check($registered->jurusan_kode === 'TKJ', 'sp_tambah_siswa also follows class');
$kelasRow = (array) DB::table('kelas')->where('id', $kelasA)->first();
app(CadanganData::class)->pulihkan(['tabel' => ['kelas' => [$kelasRow]]], 'ganti');
$check(DB::table('siswa')->where('nis', $registered->nis)->value('kelas_id') === $kelasA,
    'Partial class replace restore retains enrolment');
$check(DB::table('siswa')->where('nis', $registered->nis)->value('jurusan_kode') === 'TKJ',
    'Partial class replace restore keeps jurusan synchronized');

DB::table('guru')->insert(['nip' => '900001', 'nama' => 'Guru Uji']);
$mapel = DB::table('mata_pelajaran')->insertGetId(['kode' => 'UJI', 'nama' => 'Pelajaran Uji']);
$jadwal = DB::table('jadwal')->insertGetId([
    'kelas_id' => $kelasA, 'guru_nip' => '900001', 'mata_pelajaran_id' => $mapel,
    'hari' => 'Senin', 'jam_ke_mulai' => 1, 'jam_ke_selesai' => 2,
]);
DB::table('jurnal')->insert([
    'public_id' => (string) Str::ulid(), 'jadwal_id' => $jadwal,
    'tanggal' => '2026-10-07', 'materi' => 'Uji', 'siswa_nis' => $registered->nis,
    'diisi_oleh_id' => $registered->akun->id, 'diisi_oleh_peran' => 'siswa',
]);
try {
    DB::table('siswa')->where('nis', $registered->nis)->delete();
    throw new RuntimeException('Journal student deleted');
} catch (QueryException $e) {
    $check(($e->errorInfo[1] ?? null) === 1451, 'FK restricts deletion of journal student');
}

foreach (['siswa' => 'jurusan_kode', 'jurnal' => 'siswa_nis'] as $table => $column) {
    $indexes = array_filter(Schema::getIndexes($table), fn ($i) => $i['columns'] === [$column]);
    $check(count($indexes) === 1, "Exactly one supporting index on {$table}.{$column}");
}
foreach (["SELECT nis FROM siswa WHERE jurusan_kode = 'TKJ'", "SELECT id FROM jurnal WHERE siswa_nis = '0026002'",
    "SELECT id FROM kelas WHERE ketua_nis = '0026002'"] as $sql) {
    $plan = DB::select('EXPLAIN '.$sql);
    $check($plan[0]->key !== null, 'EXPLAIN selects index: '.$sql);
}
$check(Artisan::call('migrate:rollback', ['--step' => 3, '--force' => true]) === 0, 'New migrations roll back with populated data');
$check(! Schema::hasColumn('siswa', 'jurusan_kode') && ! Schema::hasColumn('jurnal', 'siswa_nis'), 'Rollback removes new columns');
$check(Artisan::call('migrate', ['--force' => true]) === 0, 'New migrations reapply with populated data');
$check(DB::table('siswa')->where('nis', $registered->nis)->value('jurusan_kode') === 'TKJ', 'Migration backfills student jurusan');
$check(DB::table('jurnal')->value('siswa_nis') === $registered->nis, 'Migration backfills journal student from author account');

$import = [
    'nis' => '0026003', 'nama' => 'Siswa Impor', 'status' => 'aktif',
    '_kelas_id' => $kelasA, '_username' => 'siswa.impor', '_password' => 'password123',
    '_user_id' => null, 'email' => 'impor@example.test',
];
ImporPengguna::simpan([['data' => $import, 'error' => [], 'aksi' => 'baru']], 'siswa');
$check(DB::table('siswa')->where('nis', $import['nis'])->value('jurusan_kode') === 'TKJ', 'Student import follows class');
$kelasTanpaJurusan = DB::table('kelas')->insertGetId([
    'nama_kelas' => 'X Umum', 'tingkat' => 'X', 'jurusan_kode' => null, 'tahun_ajaran_kode' => '2026/2027',
]);
$import['_kelas_id'] = $kelasTanpaJurusan;
ImporPengguna::simpan([['data' => $import, 'error' => [], 'aksi' => 'perbarui']], 'siswa');
$check(DB::table('siswa')->where('nis', $import['nis'])->value('jurusan_kode') === null, 'Import transfer clears jurusan in a class without jurusan');

$backup = [];
foreach (CadanganData::semuaTabel() as $table) {
    if (Schema::hasTable($table)) {
        $backup[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
    }
}
app(CadanganData::class)->pulihkan(['tabel' => $backup], 'ganti');
$check(collect($backup)->every(fn ($rows, $table) => DB::table($table)->count() === count($rows)), 'Full replace restore retains all backed-up row counts');
$check(DB::table('jurnal')->value('siswa_nis') === $registered->nis, 'Full replace restore retains journal student reference');
$check(DB::table('siswa')->leftJoin('kelas', 'kelas.id', '=', 'siswa.kelas_id')
    ->whereRaw('NOT (siswa.jurusan_kode <=> kelas.jurusan_kode)')->count() === 0, 'Full replace restore keeps every student jurusan synchronized');

$invalidBackup = $backup;
$invalidBackup['jurnal'][0]['kolom_tidak_ada'] = 'invalid';
try {
    app(CadanganData::class)->pulihkan(['tabel' => $invalidBackup], 'ganti');
    throw new RuntimeException('Invalid backup accepted');
} catch (QueryException $e) {
    $check(collect($backup)->every(fn ($rows, $table) => DB::table($table)->count() === count($rows))
        && DB::table('jurnal')->value('siswa_nis') === $registered->nis, 'Failed replace restore rolls back data and references');
}
echo "{$checks} MySQL checks passed.\n";
