<?php
require __DIR__.'/Phase2A8_bootstrap.php';
use Illuminate\Support\Facades\DB;
Illuminate\Support\Carbon::setTestNow('2027-07-01 10:00:00');
$r=['assertions'=>0,'cases'=>[]];
$assert=function($ok,$message)use(&$r){if(!$ok)throw new RuntimeException($message);$r['assertions']++;};
$source=(int)DB::table('kelas')->where('tahun_ajaran_kode','2026/2027')->value('id');
DB::table('riwayat_kelas_siswa')->where('aktif',0)->delete();
$target=DB::table('kelas')->insertGetId(['nama_kelas'=>'XI QA 1','tingkat'=>'XI','jurusan_kode'=>'QA','tahun_ajaran_kode'=>'2027/2028']);
$preview=App\Support\TransisiAkademik::pratinjau('2026/2027','2027/2028',['QA001'=>['aksi'=>'promosi','kelas_id'=>$target]]);
$run=function($name,$jobs)use(&$r,$assert){
    $processes=[];$dir='/scratch/'.$name;mkdir($dir,0700);
    DB::beginTransaction();App\Services\AktivasiTahunAjaran::kunci();
    try {
        foreach($jobs as$i=>$job){$job+=['ready'=>$dir.'/'.$i.'.ready','go'=>$dir.'/go','result'=>$dir.'/'.$i.'.result'];file_put_contents($dir.'/'.$i.'.json',json_encode($job));$processes[]=proc_open([PHP_BINARY,'/repo/tests/Integration/Phase2A8_worker.php',$dir.'/'.$i.'.json'],[['file','/dev/null','r'],['file',$dir.'/'.$i.'.out','w'],['file',$dir.'/'.$i.'.err','w']],$pipes);}
        $deadline=microtime(true)+25;
        while(count(glob($dir.'/*.ready'))!==count($jobs)){if(microtime(true)>$deadline)throw new RuntimeException('Worker ready timeout');usleep(10000);}
        touch($dir.'/go');$ids=array_map(fn($p)=>(int)file_get_contents($p),glob($dir.'/*.ready'));
        $proof=[];
        do {
            $proof=DB::select('SELECT t.PROCESSLIST_ID connection_id,l.OBJECT_NAME object_name,l.LOCK_STATUS status,l.LOCK_MODE mode FROM performance_schema.data_locks l JOIN performance_schema.threads t ON l.THREAD_ID=t.THREAD_ID WHERE l.LOCK_STATUS=? AND l.OBJECT_SCHEMA=DATABASE() AND l.OBJECT_NAME=?',['WAITING','academic_operation_lock']);
            $waiting=array_unique(array_map(fn($x)=>(int)$x->connection_id,$proof));
            if(count(array_intersect($ids,$waiting))===count($ids))break;
            if(microtime(true)>$deadline)throw new RuntimeException('No proven simultaneous database lock wait');usleep(10000);
        }while(true);
        $assert(count(array_intersect($ids,$waiting))===count($ids),'Both workers must really wait');
        $waits=DB::select('SELECT REQUESTING_ENGINE_TRANSACTION_ID requesting_transaction,BLOCKING_ENGINE_TRANSACTION_ID blocking_transaction FROM performance_schema.data_lock_waits');
        $assert(count($waits)>=count($jobs),'data_lock_waits proof missing');
        DB::commit();
        foreach($processes as$p){$assert(proc_close($p)===0,'Worker failed');}
        $results=array_map(fn($p)=>json_decode(file_get_contents($p),true,flags:JSON_THROW_ON_ERROR),glob($dir.'/*.result'));
        $assert(count($results)===count($jobs),'Missing worker results');
        $r['cases'][$name]=['wait_proof'=>$proof,'data_lock_waits'=>$waits,'results'=>$results];
        return $results;
    }catch(Throwable$e){if(DB::transactionLevel())DB::rollBack();foreach($processes as$p){if(is_resource($p)){proc_terminate($p);proc_close($p);}}throw$e;}
};
try {
    $results=$run('same-snapshot-double-execution',[['action'=>'transition','snapshot'=>$preview['snapshot'],'class'=>$target],['action'=>'transition','snapshot'=>$preview['snapshot'],'class'=>$target]]);
    $assert(count(array_filter($results,fn($x)=>$x['ok']))===1,'Exactly one transition succeeds');
    $assert(DB::table('transisi_tahun_ajaran')->where('status','selesai')->count()===1,'One completed transition');
    $assert(DB::table('siswa')->where('nis','QA001')->value('kelas_id')===$target,'Promoted exactly once');
    $assert(DB::table('riwayat_kelas_siswa')->where('aktif',true)->count()===1,'One active membership');
    $results=$run('future-planned-placement-not-yet-active',[['action'=>'activate','target'=>'2027/2028','clock'=>'2027-06-30 10:00:00'],['action'=>'activate','target'=>'2028/2029','clock'=>'2027-06-30 10:00:00']]);
    $assert(count(array_filter($results,fn($x)=>$x['ok']))===0,'Future placements must not permit early activation');
    $assert(DB::table('tahun_ajaran')->where('aktif',true)->pluck('kode')->all()===['2026/2027'],'Source remains active before destination start');
    // The valid activation is rollback-probed before each invalidating edit.
    // Controller first proves master WAITING, then lets the genuine model save
    // own the singleton until the genuine activation is proven WAITING on it.
    foreach ([
        ['period','mulai','2027-07-02','tahun_ajaran','kode','2027/2028','2027-07-01'],
        ['class','tahun_ajaran_kode','2028/2029','kelas','id',$target,'2027/2028'],
        ['department','aktif',0,'jurusan','kode','QA',1],
    ] as [$type,$field,$value,$table,$key,$keyValue,$original]) {
        DB::beginTransaction();
        try { app(App\Services\AktivasiTahunAjaran::class)->aktifkan('2027/2028'); }
        finally { DB::rollBack(); }
        $assert(DB::table('tahun_ajaran')->where('aktif',true)->pluck('kode')->all()===['2026/2027'],'Baseline activation rollback');
        $name='structural-'.$type.'-wins-before-activation';$dir='/scratch/'.$name;mkdir($dir,0700);$processes=[];
        $spawn=function($i,$job)use($dir,&$processes){
            $job+=['ready'=>$dir.'/'.$i.'.ready','go'=>$dir.'/'.$i.'.go','result'=>$dir.'/'.$i.'.result'];
            file_put_contents($dir.'/'.$i.'.json',json_encode($job));
            $processes[]=proc_open([PHP_BINARY,'/repo/tests/Integration/Phase2A8_worker.php',$dir.'/'.$i.'.json'],[['file','/dev/null','r'],['file',$dir.'/'.$i.'.out','w'],['file',$dir.'/'.$i.'.err','w']],$pipes);
        };
        $await=function($predicate,$message){$deadline=microtime(true)+20;while(!$predicate()){if(microtime(true)>$deadline)throw new RuntimeException($message);usleep(10000);}};
        $proofFor=function($id){return DB::select('SELECT t.PROCESSLIST_ID connection_id,l.OBJECT_NAME object_name,l.LOCK_STATUS status,l.LOCK_MODE mode FROM performance_schema.data_locks l JOIN performance_schema.threads t ON l.THREAD_ID=t.THREAD_ID WHERE t.PROCESSLIST_ID=? AND l.LOCK_STATUS=? AND l.OBJECT_NAME=?',[$id,'WAITING','academic_operation_lock']);};
        try {
            DB::beginTransaction();App\Services\AktivasiTahunAjaran::kunci();
            $spawn(0,['action'=>'structural-master','master_type'=>$type,'field'=>$field,'value'=>$value,'class'=>$target,'saved'=>$dir.'/saved','commit'=>$dir.'/commit']);
            $await(fn()=>is_file($dir.'/0.ready'),'Master ready timeout');touch($dir.'/0.go');
            $masterId=(int)file_get_contents($dir.'/0.ready');
            $await(fn()=>count($proofFor($masterId))>0,'Master WAITING proof missing');$masterProof=$proofFor($masterId);
            DB::commit();
            $await(fn()=>is_file($dir.'/saved'),'Master did not save');
            $assert((string)DB::table($table)->where($key,$keyValue)->value($field)===(string)$original,'Uncommitted master edit must remain invisible');
            $spawn(1,['action'=>'activate','target'=>'2027/2028']);
            $await(fn()=>is_file($dir.'/1.ready'),'Activation ready timeout');touch($dir.'/1.go');
            $academicId=(int)file_get_contents($dir.'/1.ready');
            $await(fn()=>count($proofFor($academicId))>0,'Activation WAITING proof missing');$academicProof=$proofFor($academicId);
            $edges=DB::select('SELECT rt.PROCESSLIST_ID requesting_connection,bt.PROCESSLIST_ID blocking_connection,r.OBJECT_NAME object_name FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks r ON r.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND r.ENGINE=w.ENGINE JOIN performance_schema.threads rt ON rt.THREAD_ID=w.REQUESTING_THREAD_ID JOIN performance_schema.threads bt ON bt.THREAD_ID=w.BLOCKING_THREAD_ID WHERE rt.PROCESSLIST_ID=? AND bt.PROCESSLIST_ID=?',[$academicId,$masterId]);
            $assert(count($edges)>0,'Activation must wait specifically for invalidating master');
            $assert(!is_file($dir.'/1.result'),'Academic request cannot finish before master commit');
            touch($dir.'/commit');
            foreach($processes as$p){$assert(proc_close($p)===0,'Structural worker failed');}$processes=[];
            $master=json_decode(file_get_contents($dir.'/0.result'),true,flags:JSON_THROW_ON_ERROR);$academic=json_decode(file_get_contents($dir.'/1.result'),true,flags:JSON_THROW_ON_ERROR);
            $r['cases'][$name]=['baseline_activation_valid'=>true,'master_wait_proof'=>$masterProof,'academic_wait_proof'=>$academicProof,'data_lock_waits'=>$edges,'master'=>$master,'academic'=>$academic,'committed_edit'=>DB::table($table)->where($key,$keyValue)->value($field)];
            $assert($master['ok']===true,'Structural edit must succeed');
            $assert($academic['ok']===false && $academic['class']===DomainException::class,'Activation must reject committed structural mutation');
            $assert((string)DB::table($table)->where($key,$keyValue)->value($field)===(string)$value,'Invalidating edit persisted');
            $assert(DB::table('tahun_ajaran')->where('aktif',true)->pluck('kode')->all()===['2026/2027'],'Rejected activation preserves active period');
            $assert(DB::table('siswa')->where('nis','QA001')->value('kelas_id')===$target,'Rejected activation preserves student');
            $assert(DB::table('riwayat_kelas_siswa')->where('aktif',true)->count()===1,'Rejected activation preserves membership');
            $assert(DB::table('transisi_tahun_ajaran')->where('status','selesai')->count()===1,'Rejected activation preserves transition');
        } finally {
            if(DB::transactionLevel())DB::rollBack();
            foreach($processes as$p){if(is_resource($p)){proc_terminate($p);proc_close($p);}}
        }
        DB::table($table)->where($key,$keyValue)->update([$field=>$original]);
    }
    $results=$run('activation-different-targets',[['action'=>'activate','target'=>'2027/2028'],['action'=>'activate','target'=>'2028/2029']]);
    $assert(count(array_filter($results,fn($x)=>$x['ok']))===1,'Exactly one valid activation');
    $assert(DB::table('tahun_ajaran')->where('aktif',true)->pluck('kode')->all()===['2027/2028'],'Only verified destination active');
    // Overlap model edit with an activation that is rejected; both must use the same lock.
    $results=$run('master-edit-activation-overlap',[['action'=>'master','class'=>$target],['action'=>'activate','target'=>'2028/2029']]);
    $assert(count(array_filter($results,fn($x)=>$x['ok']))===1,'Master succeeds, invalid activation rejected');
    $assert((int)DB::table('kelas')->where('id',$target)->value('kapasitas')===37,'Master edit persisted');
    $r['final']=['active_years'=>DB::table('tahun_ajaran')->where('aktif',true)->pluck('kode')->all(),'completed_transitions'=>DB::table('transisi_tahun_ajaran')->where('status','selesai')->count(),'active_memberships'=>DB::table('riwayat_kelas_siswa')->where('aktif',true)->count(),'student_class'=>DB::table('siswa')->where('nis','QA001')->value('kelas_id'),'destination_class'=>$target];
}catch(Throwable$e){$r['error']=$e->getMessage();$r['class']=$e::class;}
echo json_encode($r,JSON_THROW_ON_ERROR);exit(isset($r['error'])?2:0);
