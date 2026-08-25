<?php

use Illuminate\Support\Facades\DB;

$hash = bcrypt('rahasia');
$out = [];
DB::statement('CALL sp_tambah_guru(?,?,?,?,?,?,?,?,?)', ['198001011', 'Budi Santoso', 'L', '0811', 'Jl. Mawar', 'budi.s', 'budi@sekolah.id', $hash, 0]);
$out[] = 'insert1 guru='.DB::table('guru')->count().' users='.DB::table('users')->count();
try {
    DB::statement('CALL sp_tambah_guru(?,?,?,?,?,?,?,?,?)', ['198001011', 'Nama Lain', 'L', null, null, 'lain', 'lain@sekolah.id', $hash, 1]);
    $out[] = 'DUPLIKAT NIP LOLOS (BUG)';
} catch (Throwable $e) {
    $out[] = 'nip dup ditolak: '.(str_contains($e->getMessage(), 'NIP_SUDAH_ADA') ? 'NIP_SUDAH_ADA' : '?');
}
$out[] = 'after nip-dup guru='.DB::table('guru')->count().' users='.DB::table('users')->count();
try {
    DB::statement('CALL sp_tambah_guru(?,?,?,?,?,?,?,?,?)', ['198001012', 'Budi Santoso', 'L', null, null, 'budi2', 'budi2@sekolah.id', $hash, 0]);
    $out[] = 'DUPLIKAT NAMA LOLOS (BUG)';
} catch (Throwable $e) {
    $out[] = 'nama dup ditolak: '.(str_contains($e->getMessage(), 'NAMA_SUDAH_ADA') ? 'NAMA_SUDAH_ADA' : '?');
}
$out[] = 'ROLLBACK CHECK guru='.DB::table('guru')->count().' users='.DB::table('users')->count().' (harus 1/1)';
DB::statement('CALL sp_tambah_guru(?,?,?,?,?,?,?,?,?)', ['198001012', 'Budi Santoso', 'L', null, null, 'budi2', 'budi2@sekolah.id', $hash, 1]);
$out[] = 'izinkan_nama_sama=1 -> guru='.DB::table('guru')->count();
try {
    DB::table('users')->insert(['username' => 'x', 'email' => 'x@x.id', 'password' => $hash, 'role' => 'guru', 'status' => 'aktif', 'nip' => null]);
    $out[] = 'TRIGGER AKUN GAGAL (BUG)';
} catch (Throwable $e) {
    $out[] = 'trigger akun: '.(str_contains($e->getMessage(), 'AKUN_GURU_BUTUH_NIP') ? 'AKUN_GURU_BUTUH_NIP' : '?');
}
echo implode("\n", $out), "\n";
