<?php
use Illuminate\Support\Facades\DB;
function phase2bFixtures(): array {
    DB::table('tahun_ajaran')->insert([['kode'=>'2026/2027','mulai'=>'2026-07-01','selesai'=>'2027-06-30','aktif'=>1],['kode'=>'2027/2028','mulai'=>'2027-07-01','selesai'=>'2028-06-30','aktif'=>0]]);
    DB::table('jurusan')->insert(['kode'=>'QA','nama'=>'Disposable QA','aktif'=>1]);
    $x=DB::table('kelas')->insertGetId(['nama_kelas'=>'X QA 1','tingkat'=>'X','jurusan_kode'=>'QA','tahun_ajaran_kode'=>'2026/2027']);
    $g=DB::table('kelas')->insertGetId(['nama_kelas'=>'XII QA 1','tingkat'=>'XII','jurusan_kode'=>'QA','tahun_ajaran_kode'=>'2026/2027']);
    DB::table('siswa')->insert(['nis'=>'G001','nama'=>'Synthetic QA','kelas_id'=>$g,'status'=>'aktif','jurusan_kode'=>'QA']);
    DB::table('riwayat_kelas_siswa')->insert(['siswa_nis'=>'G001','kelas_id'=>$g,'tahun_ajaran_kode'=>'2026/2027','tingkat'=>'XII','aktif'=>1,'status'=>'aktif','tanggal_mulai'=>'2026-07-01']);
    DB::table('guru')->insert(['nip'=>'QAGURU','nama'=>'Synthetic Teacher']);
    $subject=DB::table('mata_pelajaran')->insertGetId(['kode'=>'QA','nama'=>'Synthetic Subject']);
    $schedule=DB::table('jadwal')->insertGetId(['kelas_id'=>$g,'mata_pelajaran_id'=>$subject,'guru_nip'=>'QAGURU','hari'=>'Senin']);
    DB::table('jurnal')->insert(['jadwal_id'=>$schedule,'tanggal'=>'2026-10-01','materi'=>'Synthetic','public_id'=>(string)Illuminate\Support\Str::ulid()]);
    DB::table('presensi_harian')->insert(['kelas_id'=>$g,'tanggal'=>'2026-10-01','siswa_nis'=>'G001','status'=>'hadir']);
    DB::table('presensi_harian_log')->insert(['kelas_id'=>$g,'tanggal'=>'2026-10-01','jumlah_siswa'=>1]);
    $actor=App\Models\User::factory()->create(['role'=>'admin','status'=>'aktif']);
    return ['class'=>$x,'graduation_class'=>$g,'actor'=>$actor->id];
}
function phase2bLegacy(): array {
    $out=[];
    foreach(DB::select('SHOW TABLES') as $row) {
        $table=array_values((array)$row)[0];
        if(str_starts_with($table,'academic_workflow_') || $table==='migrations' || $table==='phase2b_markers') continue;
        $rows=array_map(fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),DB::table($table)->get()->all());
        sort($rows);$out[$table]=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
    }
    ksort($out);return $out;
}
