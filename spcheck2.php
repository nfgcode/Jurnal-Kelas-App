<?php

use Illuminate\Support\Facades\DB;

function coba(string $label, callable $fn): void
{
    try {
        $fn();
        printf("   %-42s %s\n", $label, 'DITERIMA');
    } catch (Throwable $e) {
        if (preg_match('/45000[^:]*: *\d+ ([A-Z_]+)/', $e->getMessage(), $m)
            || preg_match('/([A-Z][A-Z_]{6,})/', $e->getMessage(), $m)) {
            printf("   %-42s ditolak: %s\n", $label, $m[1]);
        } else {
            printf("   %-42s ditolak: %s\n", $label, substr($e->getMessage(), 0, 70));
        }
    }
}

$mapelSebelum = DB::table('mata_pelajaran')->count();
$pivotSebelum = DB::table('guru_mata_pelajaran')->count();
$kelasSebelum = DB::table('kelas')->count();
$jadwalSebelum = DB::table('jadwal')->count();

echo "== sp_tambah_kelas ==\n";
coba('rombel baru', fn () => DB::statement('CALL sp_tambah_kelas(?,?,?,?,?,?,?,?, @id)',
    ['XII UJI SP', 'XII', 'TKJ', 9, null, 30, '2026/2027', null]));
coba('rombel sama persis', fn () => DB::statement('CALL sp_tambah_kelas(?,?,?,?,?,?,?,?, @id)',
    ['XII UJI SP lagi', 'XII', 'TKJ', 9, null, 30, '2026/2027', null]));
coba('tahun ajaran tidak ada', fn () => DB::statement('CALL sp_tambah_kelas(?,?,?,?,?,?,?,?, @id)',
    ['XII UJI SP 8', 'XII', 'TKJ', 8, null, 30, '1999/2000', null]));
coba('wali bukan guru aktif', fn () => DB::statement('CALL sp_tambah_kelas(?,?,?,?,?,?,?,?, @id)',
    ['XII UJI SP 7', 'XII', 'TKJ', 7, null, 30, '2026/2027', '000000000000']));

echo "\n== sp_tambah_mata_pelajaran (2 tabel) ==\n";
$nip = DB::table('guru')->value('nip');
coba('mapel + 2 guru pengampu', fn () => DB::statement('CALL sp_tambah_mata_pelajaran(?,?,?,?,?,?, @id)',
    ['Mapel Uji', 'UJI1', 'wajib', 2, null, json_encode([$nip, DB::table('guru')->skip(1)->value('nip')])]));
coba('kode kembar', fn () => DB::statement('CALL sp_tambah_mata_pelajaran(?,?,?,?,?,?, @id)',
    ['Mapel Uji 2', 'UJI1', 'wajib', 2, null, json_encode([$nip])]));
coba('NIP guru tidak dikenal (ROLLBACK?)', fn () => DB::statement('CALL sp_tambah_mata_pelajaran(?,?,?,?,?,?, @id)',
    ['Mapel Uji 3', 'UJI3', 'wajib', 2, null, json_encode(['000000000000'])]));
printf("   -> mapel bertambah %d (harus 1), pivot bertambah %d (harus 2)\n",
    DB::table('mata_pelajaran')->count() - $mapelSebelum,
    DB::table('guru_mata_pelajaran')->count() - $pivotSebelum);
printf("   -> UJI3 tersimpan? %s (harus tidak — rollback)\n",
    DB::table('mata_pelajaran')->where('kode', 'UJI3')->exists() ? 'YA (BUG)' : 'tidak');

echo "\n== sp_tambah_jadwal ==\n";
$mapelUji = DB::table('mata_pelajaran')->where('kode', 'UJI1')->value('id');
$kelasA = DB::table('kelas')->value('id');
$kelasB = DB::table('kelas')->skip(1)->value('id');
coba('slot kosong JP 11-12', fn () => DB::statement('CALL sp_tambah_jadwal(?,?,?,?,?,?,?, @id)',
    [$kelasA, $mapelUji, $nip, 'Jumat', 11, 12, null]));
coba('kelas bentrok (sama persis)', fn () => DB::statement('CALL sp_tambah_jadwal(?,?,?,?,?,?,?, @id)',
    [$kelasA, $mapelUji, $nip, 'Jumat', 11, 12, null]));
coba('guru bentrok (kelas lain)', fn () => DB::statement('CALL sp_tambah_jadwal(?,?,?,?,?,?,?, @id)',
    [$kelasB, $mapelUji, $nip, 'Jumat', 11, 12, null]));
coba('overlap JP 10-11 (awal beda)', fn () => DB::statement('CALL sp_tambah_jadwal(?,?,?,?,?,?,?, @id)',
    [$kelasA, $mapelUji, $nip, 'Jumat', 10, 11, null]));
$mapelLain = DB::table('mata_pelajaran')->where('id', '!=', $mapelUji)
    ->whereNotIn('id', DB::table('guru_mata_pelajaran')->where('guru_nip', $nip)->pluck('mata_pelajaran_id'))
    ->value('id');
coba('guru tidak mengampu mapel', fn () => DB::statement('CALL sp_tambah_jadwal(?,?,?,?,?,?,?, @id)',
    [$kelasA, $mapelLain, $nip, 'Sabtu', 11, 12, null]));

printf("\n   kelas bertambah %d (harus 1), jadwal bertambah %d (harus 1)\n",
    DB::table('kelas')->count() - $kelasSebelum,
    DB::table('jadwal')->count() - $jadwalSebelum);
