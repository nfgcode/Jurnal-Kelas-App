<?php
require __DIR__.'/Phase2A8_bootstrap.php';
use Illuminate\Support\Facades\DB;
$r=['assertions'=>0];
$assert=function($ok,$message)use(&$r){if(!$ok)throw new RuntimeException($message);$r['assertions']++;};
$reject=function($sql,$code)use($assert){try{DB::statement($sql);throw new RuntimeException('SQL unexpectedly succeeded');}catch(Illuminate\Database\QueryException $e){$assert((int)$e->errorInfo[1]===$code,'Wrong rejection: '.$e->getMessage());}};
try {
DB::table('tahun_ajaran')->insert([['kode'=>'2026/2027','mulai'=>'2026-07-01','selesai'=>'2027-06-30','aktif'=>1],['kode'=>'2027/2028','mulai'=>'2027-07-01','selesai'=>'2028-06-30','aktif'=>0],['kode'=>'2028/2029','mulai'=>'2028-07-01','selesai'=>'2029-06-30','aktif'=>0]]);
DB::table('jurusan')->insert(['kode'=>'QA','nama'=>'Disposable QA','aktif'=>1]);
$c=DB::table('kelas')->insertGetId(['nama_kelas'=>'X QA 1','tingkat'=>'X','jurusan_kode'=>'QA','tahun_ajaran_kode'=>'2026/2027']);
DB::table('siswa')->insert(['nis'=>'QA001','nama'=>'Synthetic QA','kelas_id'=>$c,'status'=>'aktif','jurusan_kode'=>'QA']);
DB::table('riwayat_kelas_siswa')->insert(['siswa_nis'=>'QA001','kelas_id'=>$c,'tahun_ajaran_kode'=>'2026/2027','tingkat'=>'X','aktif'=>1,'status'=>'aktif','tanggal_mulai'=>'2026-07-01']);
$reject("UPDATE tahun_ajaran SET aktif=1 WHERE kode='2027/2028'",1062);
$reject("INSERT INTO riwayat_kelas_siswa(siswa_nis,kelas_id,tahun_ajaran_kode,tingkat,aktif,status) SELECT siswa_nis,kelas_id,tahun_ajaran_kode,tingkat,aktif,status FROM riwayat_kelas_siswa WHERE siswa_nis='QA001'",1062);
DB::table('riwayat_kelas_siswa')->insert(['siswa_nis'=>'QA001','kelas_id'=>$c,'tahun_ajaran_kode'=>'2026/2027','tingkat'=>'X','aktif'=>0,'status'=>'aktif']);
DB::table('riwayat_kelas_siswa')->insert(['siswa_nis'=>'QA001','kelas_id'=>$c,'tahun_ajaran_kode'=>'2026/2027','tingkat'=>'X','aktif'=>0,'status'=>'aktif']);
$assert(DB::table('riwayat_kelas_siswa')->count()===3,'Inactive rows coexist');
$keys=DB::select("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('jadwal_kelas_id_foreign','jadwal_mata_pelajaran_id_foreign','jadwal_guru_nip_foreign','jurnal_jadwal_id_foreign','presensi_harian_kelas_id_foreign','presensi_harian_log_kelas_id_foreign')");
$assert(count($keys)===6,'Six expected historical keys');
$fk=DB::select("SELECT CONSTRAINT_NAME,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()");
$rules=array_column(array_map(fn($x)=>(array)$x,$fk),'DELETE_RULE','CONSTRAINT_NAME');
foreach($keys as $key){$assert($rules[$key->CONSTRAINT_NAME]==='RESTRICT','FK must restrict');}
DB::table('guru')->insert(['nip'=>'QAGURU','nama'=>'Synthetic Teacher']);
$subject=DB::table('mata_pelajaran')->insertGetId(['kode'=>'QA','nama'=>'Synthetic Subject']);
$schedule=DB::table('jadwal')->insertGetId(['kelas_id'=>$c,'mata_pelajaran_id'=>$subject,'guru_nip'=>'QAGURU','hari'=>'Senin']);
DB::table('jurnal')->insert(['jadwal_id'=>$schedule,'tanggal'=>'2026-10-01','materi'=>'Synthetic','public_id'=>(string)Illuminate\Support\Str::ulid()]);
DB::table('presensi_harian')->insert(['kelas_id'=>$c,'tanggal'=>'2026-10-01','siswa_nis'=>'QA001','status'=>'hadir']);
DB::table('presensi_harian_log')->insert(['kelas_id'=>$c,'tanggal'=>'2026-10-01','jumlah_siswa'=>1]);
$reject("DELETE FROM kelas WHERE id=$c",1451);
$reject("DELETE FROM mata_pelajaran WHERE id=$subject",1451);
$reject("DELETE FROM guru WHERE nip='QAGURU'",1451);
$reject("DELETE FROM jadwal WHERE id=$schedule",1451);
// Isolate the remaining two class FKs so each gets its own actual raw-delete proof.
DB::beginTransaction();DB::table('jurnal')->delete();DB::table('jadwal')->delete();DB::table('presensi_harian_log')->delete();$reject("DELETE FROM kelas WHERE id=$c",1451);DB::rollBack();
DB::beginTransaction();DB::table('jurnal')->delete();DB::table('jadwal')->delete();DB::table('presensi_harian')->delete();$reject("DELETE FROM kelas WHERE id=$c",1451);DB::rollBack();
$before=[];foreach(['tahun_ajaran','kelas','siswa','riwayat_kelas_siswa','guru','mata_pelajaran','jadwal','jurnal','presensi_harian','presensi_harian_log']as$t){$before[$t]=DB::table($t)->orderBy(match($t){'siswa'=>'nis','guru'=>'nip','tahun_ajaran'=>'kode',default=>'id'})->get()->toJson();}
foreach(['2026_10_31_000001_guard_academic_activation','2026_10_30_000001_restrict_historical_master_deletion']as$name){$m=require '/repo/database/migrations/'.$name.'.php';$m->down();$m->up();$assert(true,'down/re-up');}
foreach($before as$t=>$rows){$now=DB::table($t)->orderBy(match($t){'siswa'=>'nis','guru'=>'nip','tahun_ajaran'=>'kode',default=>'id'})->get()->toJson();$assert($rows===$now,'Fixture altered by migration cycle');}
$reject("UPDATE tahun_ajaran SET aktif=1 WHERE kode='2027/2028'",1062);
$reject("INSERT INTO riwayat_kelas_siswa(siswa_nis,kelas_id,tahun_ajaran_kode,tingkat,aktif,status) SELECT siswa_nis,kelas_id,tahun_ajaran_kode,tingkat,aktif,status FROM riwayat_kelas_siswa WHERE siswa_nis='QA001' AND aktif=1",1062);
$generated=DB::select("SELECT TABLE_NAME,COLUMN_NAME,EXTRA,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='tahun_ajaran' AND COLUMN_NAME='active_slot') OR (TABLE_NAME='riwayat_kelas_siswa' AND COLUMN_NAME='siswa_nis_aktif')) ORDER BY TABLE_NAME");
$assert(count($generated)===2,'Both generated columns survive cycles');
foreach($generated as$column){$assert(str_contains($column->EXTRA,'VIRTUAL GENERATED') && $column->GENERATION_EXPRESSION!=='','Generated expression survives cycles');}
$assert((int)DB::table('tahun_ajaran')->where('aktif',1)->value('active_slot')===1,'Active year generated value');
$assert(DB::table('tahun_ajaran')->where('aktif',0)->whereNotNull('active_slot')->count()===0,'Inactive years generated NULL');
$assert(DB::table('riwayat_kelas_siswa')->where('aktif',1)->value('siswa_nis_aktif')==='QA001','Active membership generated value');
$assert(DB::table('riwayat_kelas_siswa')->where('aktif',0)->whereNotNull('siswa_nis_aktif')->count()===0,'Inactive memberships generated NULL');
$r['generated_columns_after_cycles']=$generated;
$r['cycles']=['2026_10_30_000001_restrict_historical_master_deletion','2026_10_31_000001_guard_academic_activation'];
$r['fixture_preserved']=true;$r['fk_restrict_keys']=count($keys);$r['generated_unique_year']=true;$r['generated_unique_membership']=true;
} catch(Throwable $e) {$r['error']=$e->getMessage();$r['class']=$e::class;}
echo json_encode($r);exit(isset($r['error'])?2:0);
