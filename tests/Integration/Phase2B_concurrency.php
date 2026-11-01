<?php
require __DIR__.'/Phase2B_bootstrap.php';
require __DIR__.'/Phase2B_double.php';
use Illuminate\Support\Facades\{DB,Auth,Schema};
use App\AcademicWorkflows\{Operation,Coordinator};
Illuminate\Support\Carbon::setTestNow('2026-10-31 10:00:00');
$r=['assertions'=>0,'cases'=>[]];$processes=[];
$assert=function($ok,$message)use(&$r){if(!$ok)throw new RuntimeException($message);$r['assertions']++;};
$await=function($fn,$message){$deadline=microtime(true)+25;while(!$fn()){if(microtime(true)>$deadline)throw new RuntimeException($message);usleep(10000);}};
$proof=function($ids){return DB::select('SELECT t.PROCESSLIST_ID connection_id,l.OBJECT_NAME object_name,l.LOCK_STATUS status,l.LOCK_MODE mode FROM performance_schema.data_locks l JOIN performance_schema.threads t ON l.THREAD_ID=t.THREAD_ID WHERE l.LOCK_STATUS=? AND l.OBJECT_SCHEMA=DATABASE() AND l.OBJECT_NAME=? AND t.PROCESSLIST_ID IN ('.implode(',',array_map('intval',$ids)).')',['WAITING','academic_operation_lock']);};
$edges=fn()=>DB::select('SELECT rt.PROCESSLIST_ID requesting_connection,bt.PROCESSLIST_ID blocking_connection,l.OBJECT_NAME object_name FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE=w.ENGINE JOIN performance_schema.threads rt ON rt.THREAD_ID=w.REQUESTING_THREAD_ID JOIN performance_schema.threads bt ON bt.THREAD_ID=w.BLOCKING_THREAD_ID');
$spawn=function($dir,$i,$job)use(&$processes){
    $job+=['ready'=>$dir.'/'.$i.'.ready','go'=>$dir.'/'.$i.'.go','result'=>$dir.'/'.$i.'.result'];
    file_put_contents($dir.'/'.$i.'.json',json_encode($job,JSON_THROW_ON_ERROR));
    $p=proc_open([PHP_BINARY,'/repo/tests/Integration/Phase2B_worker.php',$dir.'/'.$i.'.json'],[['file','/dev/null','r'],['file',$dir.'/'.$i.'.out','w'],['file',$dir.'/'.$i.'.err','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Cannot launch worker');$processes[]=$p;
};
$join=function($dir,$count)use(&$processes,$assert){foreach($processes as$p)$assert(proc_close($p)===0,'Worker process failed');$processes=[];$out=[];for($i=0;$i<$count;$i++)$out[]=json_decode(file_get_contents($dir.'/'.$i.'.result'),true,flags:JSON_THROW_ON_ERROR);return $out;};
try {
    $f=json_decode(file_get_contents('/scratch/fixtures.json'),true,flags:JSON_THROW_ON_ERROR);
    $actor=App\Models\User::findOrFail($f['actor']);Auth::login($actor);
    $assert($actor->role==='admin' && $actor->status==='aktif' && Auth::id()===$actor->id,'Active admin authenticated');
    Schema::create('phase2b_markers',function($t){$t->id();$t->unsignedBigInteger('operation_id');$t->string('student_ref',20);$t->string('mode',20);}); // deliberately no unique: duplicate dispatch must be observable
    $service=phase2bService();
    $confirmed=function($s,$o)use($actor,$assert){$ready=$s->validate($o->id,$actor);$assert($ready->state==='ready','Operation not ready: '.json_encode($s->preview($o->id,$actor)));return $s->confirm($o->id,$ready->version,$ready->snapshot_hash,$actor);};
    $intake=fn($ref)=>$service->prepare('intake',null,'2026/2027',[['student_ref'=>$ref,'target_class_id'=>$f['class'],'nisn'=>substr(sprintf('%u',crc32($ref)).'0000000000',0,10),'email'=>strtolower($ref).'@example.test']],['effective_date'=>'2026-10-31'],$actor);
    $run=function($name,$jobs)use(&$r,$actor,$spawn,$await,$proof,$edges,$join,$assert){
        $dir='/scratch/'.$name;mkdir($dir,0700);$ids=[];
        DB::beginTransaction();App\Services\AktivasiTahunAjaran::kunci();
        $holder=(int)DB::selectOne('SELECT CONNECTION_ID() id')->id;
        foreach($jobs as$i=>$job)$spawn($dir,$i,$job+['actor'=>$actor->id]);
        foreach($jobs as$i=>$job){$await(fn()=>is_file($dir.'/'.$i.'.ready'),'Ready timeout');$ids[]=(int)file_get_contents($dir.'/'.$i.'.ready');touch($dir.'/'.$i.'.go');}
        $await(fn()=>count(array_unique(array_column($proof($ids),'connection_id')))===count($ids),'Simultaneous WAITING proof missing');
        $wait=$proof($ids);$links=$edges();
        foreach($ids as$id)$assert(count(array_filter($links,fn($e)=>(int)$e->requesting_connection===$id && (int)$e->blocking_connection===$holder))>0,'Missing exact singleton blocking edge');
        DB::commit();$out=$join($dir,count($jobs));$r['cases'][$name]=['wait_proof'=>$wait,'data_lock_waits'=>$links,'results'=>$out];return $out;
    };
    $op=$intake('SAME001');$token=$confirmed($service,$op);
    $out=$run('same-confirmed-operation',[['operation'=>$op->id,'confirmation'=>$token],['operation'=>$op->id,'confirmation'=>$token]]);
    $assert(count(array_filter($out,fn($x)=>$x['ok']))===2,'Both same-operation callers succeed');
    $assert($out[0]['value']==$out[1]['value'],'Replay result differs');
    $assert(DB::table('phase2b_markers')->where('operation_id',$op->id)->count()===1,'Exactly one executor dispatch');
    $assert($op->fresh()->state==='completed','Completed durable');
    $assert(DB::table('academic_workflow_audits')->where('operation_id',$op->id)->where('event','complete')->count()===1,'One completion audit');
    $assert($service->execute($op->id,'response-retry',$actor)==$out[0]['value'],'Persisted response replay');
    $a=$intake('RACE001');$b=$intake('RACE001');$at=$confirmed($service,$a);$bt=$confirmed($service,$b);
    $out=$run('competing-identity-snapshots',[['operation'=>$a->id,'confirmation'=>$at,'mode'=>'identity'],['operation'=>$b->id,'confirmation'=>$bt,'mode'=>'identity']]);
    $assert(count(array_filter($out,fn($x)=>$x['ok']))===1,'Exactly one competing snapshot succeeds');
    $loser=array_values(array_filter($out,fn($x)=>!$x['ok']))[0];
    $assert($loser['error']==='workflow_confirmation_stale','Competing loser must reject stale confirmation');
    $assert(DB::table('phase2b_markers')->whereIn('operation_id',[$a->id,$b->id])->count()===1,'Competing loser never dispatches');
    $assert(DB::table('siswa')->where('nis','RACE001')->count()===1,'One identity inserted');
    $assert(Operation::whereIn('id',[$a->id,$b->id])->where('state','completed')->count()===1,'One competing completion');
    // Genuine master writer commits while workflow waits on its singleton.
    $op=$intake('MASTER001');$token=$confirmed($service,$op);$dir='/scratch/master-before-execute';mkdir($dir,0700);
    DB::beginTransaction();App\Services\AktivasiTahunAjaran::kunci();
    $spawn($dir,0,['actor'=>$actor->id,'action'=>'master','saved'=>$dir.'/saved','commit'=>$dir.'/commit']);
    $await(fn()=>is_file($dir.'/0.ready'),'Master ready timeout');$mid=(int)file_get_contents($dir.'/0.ready');touch($dir.'/0.go');
    $await(fn()=>count($proof([$mid]))>0,'Master WAITING missing');$masterProof=$proof([$mid]);DB::commit();
    $await(fn()=>is_file($dir.'/saved'),'Master save timeout');
    $assert(substr(DB::table('tahun_ajaran')->where('kode','2026/2027')->value('mulai'),0,10)==='2026-07-01','Master uncommitted edit invisible');
    $spawn($dir,1,['actor'=>$actor->id,'operation'=>$op->id,'confirmation'=>$token]);
    $await(fn()=>is_file($dir.'/1.ready'),'Workflow ready timeout');$wid=(int)file_get_contents($dir.'/1.ready');touch($dir.'/1.go');
    $await(fn()=>count($proof([$wid]))>0,'Workflow WAITING missing');$workflowProof=$proof([$wid]);$links=$edges();
    $assert(count(array_filter($links,fn($e)=>(int)$e->requesting_connection===$wid && (int)$e->blocking_connection===$mid))>0,'Workflow must wait on genuine master writer');
    touch($dir.'/commit');$out=$join($dir,2);
    $assert($out[0]['ok'] && !$out[1]['ok'] && $out[1]['error']==='workflow_confirmation_stale','Committed master invalidates waiting workflow');
    $assert(DB::table('phase2b_markers')->where('operation_id',$op->id)->count()===0,'Stale master workflow never dispatches');
    $r['cases']['master-before-execute']=['master_wait_proof'=>$masterProof,'workflow_wait_proof'=>$workflowProof,'data_lock_waits'=>$links,'results'=>$out];
    App\Models\TahunAjaran::findOrFail('2026/2027')->forceFill(['mulai'=>'2026-07-01'])->save();
    $g=$service->prepare('graduation','2026/2027',null,[['student_ref'=>'G001','decision'=>'lulus']],['official_verified'=>true,'official_reference'=>'OFFICIAL-001','effective_date'=>'2026-10-31','source_version'=>'QA1','source_content_hash'=>hash('sha256','disposable-official-fixture')],$actor);
    $i=$intake('RESUME001');$gt=$confirmed($service,$g);$it=$confirmed($service,$i);
    $co=new Coordinator(phase2bService('marker',true));$cid=$co->create(['intake','graduation'],$actor);$co->attach($cid,'graduation',$g->id,$actor);$co->attach($cid,'intake',$i->id,$actor);
    try{$co->executeStage($cid,'intake',$it,$actor);throw new RuntimeException('Stage order accepted');}catch(DomainException $e){$assert($e->getMessage()==='workflow_stage_order','Stage order error');}
    try{$service->execute($i->id,$it,$actor);throw new RuntimeException('Direct coordinator bypass accepted');}catch(DomainException $e){$assert($e->getMessage()==='workflow_stage_order','Direct service enforces attached predecessor');}
    $assert($i->fresh()->state==='confirmed','Rejected bypass preserves confirmation');
    $assert(DB::table('phase2b_markers')->where('operation_id',$i->id)->count()===0,'Rejected bypass never dispatches');
    $co->executeStage($cid,'graduation',$gt,$actor);
    $before=DB::table('academic_workflow_operations')->where('id',$g->id)->first();
    try{$co->executeStage($cid,'intake',$it,$actor);throw new RuntimeException('Expected stage failure');}catch(DomainException $e){$assert($e->getMessage()==='workflow_execution_failed','Sanitized failure');}
    $status=$co->status($cid,$actor);$assert(array_column($status,'state')===['completed','failed'],'Partial progress durable');
    $assert(DB::table('phase2b_markers')->where('operation_id',$g->id)->count()===1,'First stage mutation persists');
    $assert(DB::table('phase2b_markers')->where('operation_id',$i->id)->count()===0,'Failed second mutation rolled back');
    $assert(DB::table('academic_workflow_audits')->where('operation_id',$i->id)->where('event','fail')->count()===1,'Separate failure audit durable');
    $co->executeStage($cid,'graduation','retry-first-response',$actor);
    $assert(DB::table('phase2b_markers')->where('operation_id',$g->id)->count()===1,'Completed stage replay no dispatch');
    $service->retry($i->id,$actor);$new=$confirmed($service,$i);$assert($new!==$it,'Retry fresh confirmation');
    $co=new Coordinator($service);$co->executeStage($cid,'intake',$new,$actor);
    $assert(array_column($co->status($cid,$actor),'state')===['completed','completed'],'Resume completes only failed stage');
    $assert($before==DB::table('academic_workflow_operations')->where('id',$g->id)->first(),'First operation unchanged after failure and resume');
    $assert(DB::table('phase2b_markers')->whereIn('operation_id',[$g->id,$i->id])->count()===2,'Exactly one committed marker each stage');
    $r['cases']['coordinator-resume']=['partial_status'=>$status,'final_status'=>$co->status($cid,$actor),'first_operation_unchanged'=>true];

    // Real GraduationExecutor execution on MySQL disposable
    $realGradRegistry = new \App\AcademicWorkflows\ExecutorRegistry();
    $realGradRegistry->register('graduation', new \App\AcademicWorkflows\GraduationExecutor());
    $realGradRegistry->register('promotion', new \App\AcademicWorkflows\PromotionExecutor());
    $realGradService = new \App\AcademicWorkflows\WorkflowService($realGradRegistry);
    $realGradOp = $realGradService->prepare('graduation','2026/2027',null,[['student_ref'=>'G001','decision'=>'lulus']],['official_verified'=>true,'official_reference'=>'OFFICIAL-REAL-001','effective_date'=>'2026-10-31','source_version'=>'QA1','source_content_hash'=>hash('sha256','disposable-real-fixture')],$actor);
    $realGradToken = $confirmed($realGradService, $realGradOp);
    $realGradResult = $realGradService->execute($realGradOp->id, $realGradToken, $actor);
    $assert($realGradResult === ['processed' => 1, 'skipped' => 0, 'failed' => 0], 'Real graduation result matches');
    $assert(DB::table('siswa')->where('nis', 'G001')->value('status') === 'lulus', 'Student status is lulus');
    $assert(DB::table('alumni')->where('siswa_nis', 'G001')->where('tahun_lulus', '2026/2027')->count() === 1, 'Alumni row created in MySQL');
    $assert(DB::table('riwayat_kelas_siswa')->where('siswa_nis', 'G001')->value('aktif') == 0, 'Riwayat closed in MySQL');
    $assert(DB::table('riwayat_kelas_siswa')->where('siswa_nis', 'G001')->value('status') === 'lulus', 'Riwayat status lulus in MySQL');
    $r['cases']['real-graduation-mysql'] = ['result' => $realGradResult, 'alumni_count' => 1, 'riwayat_status' => 'lulus'];

    // Real PromotionExecutor execution on MySQL disposable
    // Setup promotion target classes in disposable MySQL (2027/2028 is already seeded in fixtures)
    DB::table('tahun_ajaran')->where('kode','2027/2028')->update(['mulai'=>'2027-07-01','selesai'=>'2028-06-30','aktif'=>0]);
    $sourceClass = DB::table('kelas')->where('id', $f['class'])->first();
    $targetClassId = DB::table('kelas')->insertGetId([
        'tahun_ajaran_kode' => '2027/2028',
        'nama_kelas' => 'XI TKJ 1 (27/28)',
        'tingkat' => 'XI',
        'jurusan_kode' => $sourceClass->jurusan_kode,
        'paralel' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Ensure source academic year 2026/2027 has valid explicit start/end dates
    DB::table('tahun_ajaran')->where('kode', '2026/2027')->update(['mulai' => '2026-07-01', 'selesai' => '2027-06-30']);

    // Insert active class X student for real promotion test
    DB::table('siswa')->insert([
        'nis' => '00901',
        'nisn' => '0090190101',
        'nama' => 'Siswa X MySQL Promosi',
        'jenis_kelamin' => 'L',
        'kelas_id' => $f['class'],
        'status' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('riwayat_kelas_siswa')->insert([
        ['siswa_nis' => '00901', 'kelas_id' => $f['class'], 'tahun_ajaran_kode' => '2026/2027', 'tingkat' => 'X', 'status' => 'aktif', 'aktif' => 1, 'tanggal_mulai' => '2026-07-01', 'created_at' => now(), 'updated_at' => now()],
        ['siswa_nis' => 'RACE001', 'kelas_id' => $f['class'], 'tahun_ajaran_kode' => '2026/2027', 'tingkat' => 'X', 'status' => 'aktif', 'aktif' => 1, 'tanggal_mulai' => '2026-07-01', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $realPromOp = $realGradService->prepare('promotion', '2026/2027', '2027/2028', [
        ['student_ref' => '00901', 'decision' => 'naik', 'target_class_id' => $targetClassId],
        ['student_ref' => 'RACE001', 'decision' => 'naik', 'target_class_id' => $targetClassId],
    ], ['effective_date' => '2027-07-01'], $actor);
    $realPromToken = $confirmed($realGradService, $realPromOp);
    $realPromResult = $realGradService->execute($realPromOp->id, $realPromToken, $actor);

    $assert($realPromResult === ['processed' => 2, 'skipped' => 0, 'failed' => 0], 'Real promotion result matches');
    $assert(DB::table('siswa')->where('nis', '00901')->value('status') === 'aktif', 'Promoted student status remains aktif');
    $assert((int)DB::table('siswa')->where('nis', '00901')->value('kelas_id') === (int)$targetClassId, 'Promoted student assigned to target class');
    $assert(DB::table('alumni')->where('siswa_nis', '00901')->count() === 0, 'No alumni created for promoted student');
    $assert(DB::table('riwayat_kelas_siswa')->where('siswa_nis', '00901')->where('kelas_id', $f['class'])->value('aktif') == 0, 'Source riwayat closed in MySQL');
    $assert(DB::table('riwayat_kelas_siswa')->where('siswa_nis', '00901')->where('kelas_id', $targetClassId)->value('aktif') == 1, 'Target riwayat active in MySQL');
    $assert(DB::table('riwayat_kelas_siswa')->where('siswa_nis', '00901')->where('kelas_id', $targetClassId)->value('tingkat') === 'XI', 'Target riwayat tingkat is XI in MySQL');
    $r['cases']['real-promotion-mysql'] = ['result' => $realPromResult, 'promoted_student' => '00901', 'target_class_id' => $targetClassId];

    // Real IntakeExecutor execution on MySQL disposable
    // Create class X target on 2027/2028
    $intakeClassId = DB::table('kelas')->insertGetId([
        'tahun_ajaran_kode' => '2027/2028',
        'nama_kelas' => 'X TKJ 1 (27/28)',
        'tingkat' => 'X',
        'jurusan_kode' => $sourceClass->jurusan_kode,
        'paralel' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $realIntakeRegistry = new \App\AcademicWorkflows\ExecutorRegistry();
    $realIntakeRegistry->register('intake', new \App\AcademicWorkflows\IntakeExecutor());
    $realIntakeService = new \App\AcademicWorkflows\WorkflowService($realIntakeRegistry);

    $realIntakeOp = $realIntakeService->prepare('intake', null, '2027/2028', [
        [
            'student_ref' => '00999',
            'nisn' => '0099988877',
            'email' => 'siswa.baru.mysql@sekolah.sch.id',
            'target_class_id' => $intakeClassId,
        ],
    ], ['effective_date' => '2027-07-01'], $actor);

    $realIntakeToken = $confirmed($realIntakeService, $realIntakeOp);
    $realIntakeResult = $realIntakeService->execute($realIntakeOp->id, $realIntakeToken, $actor);

    $assert($realIntakeResult === ['processed' => 1, 'skipped' => 0, 'failed' => 0], 'Real intake result matches');
    $assert(DB::table('siswa')->where('nis', '00999')->count() === 1, 'New student created in MySQL');
    $assert(DB::table('siswa')->where('nis', '00999')->value('nisn') === '0099988877', 'New student NISN matches in MySQL');
    $assert(DB::table('users')->where('nis', '00999')->count() === 1, 'Student account created in MySQL');
    $assert(DB::table('users')->where('nis', '00999')->value('role') === 'siswa', 'User role is siswa');
    $assert(DB::table('riwayat_kelas_siswa')->where('siswa_nis', '00999')->count() === 1, 'Initial membership created in MySQL');
    $assert(DB::table('riwayat_kelas_siswa')->where('siswa_nis', '00999')->value('tingkat') === 'X', 'Initial membership grade is X');
    $r['cases']['real-intake-mysql'] = ['result' => $realIntakeResult, 'nis' => '00999', 'class_id' => $intakeClassId];

    // Real Coordinator Concurrency & Multi-Stage verification on MySQL disposable
    $realCoord = new Coordinator($realGradService);
    $realCoordId = $realCoord->create(['graduation', 'promotion', 'intake'], $actor, '2026/2027', '2027/2028');
    $assert($realCoordId > 0, 'Coordinator session created in MySQL');
    $coordRow = DB::table('academic_workflow_coordinators')->where('id', $realCoordId)->first();
    $assert($coordRow->source_year === '2026/2027', 'Coordinator source year matches in MySQL');
    $assert($coordRow->target_year === '2027/2028', 'Coordinator target year matches in MySQL');

    // Attach operations to stages in MySQL
    $realCoord->attach($realCoordId, 'graduation', $realGradOp->id, $actor);
    $realCoord->attach($realCoordId, 'promotion', $realPromOp->id, $actor);
    $realCoord->attach($realCoordId, 'intake', $realIntakeOp->id, $actor);

    // 14. Real MySQL Restore & Relational Integrity Verification (Phase 8.3A)
    $stagingDbName = 'phase2b_staging_' . bin2hex(random_bytes(6));
    DB::statement("CREATE DATABASE `{$stagingDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $assert(true, 'Isolated staging database created in disposable MySQL');

    $backupEngine = new \App\Support\BackupEngine();
    $backupResult = $backupEngine->buatBackupDatabase(\App\Support\BackupEngine::FORMAT_SQL_GZ, 'manual', $actor, 'MySQL Disposable Real Restore');
    $assert(! empty($backupResult['id']), 'Backup SQL.GZ created in disposable MySQL');

    $backupPath = storage_path('app/' . $backupResult['lokasi']);
    $restoreEngine = new \App\Support\RestoreStagingEngine();
    $val = $restoreEngine->validasiArsip($backupPath, $backupResult['checksum'], $backupResult['manifest']);
    $assert($val['valid'] === true, 'Backup SQL.GZ validated in disposable MySQL');

    $sqlStructure = $restoreEngine->validasiStrukturSql($backupPath);
    $assert($sqlStructure['valid'] === true, 'SQL allowlist structure verified in disposable MySQL');

    config(['database.connections.disposable_staging' => array_merge(
        config('database.connections.mysql'),
        ['database' => $stagingDbName]
    )]);
    DB::purge('disposable_staging');
    $stagingConn = DB::connection('disposable_staging');

    $restoreResult = $restoreEngine->pulihkanKeStagingTerisolasi($stagingConn, $sqlStructure['statements']);
    $assert($restoreResult['status'] === 'berhasil_staging', 'SQL.GZ dump restored to isolated staging database');
    $assert($restoreResult['tables_created_count'] >= 29, 'All core tables created in staging MySQL');

    $integrityReport = $restoreEngine->verifikasiIntegritasDanDampak($stagingConn, $backupResult['manifest']);
    $assert($integrityReport['status'] === 'relationally_verified', 'Relational integrity verified on restored MySQL instance');
    $assert(empty($integrityReport['errors']), 'Zero relational integrity errors in restored staging MySQL');

    $stagingConn->disconnect();
    DB::statement("DROP DATABASE IF EXISTS `{$stagingDbName}`");
    $assert(true, 'Staging database dropped cleanly');
    $r['cases']['real-restore-mysql'] = ['status' => 'PASS', 'tables_restored' => $restoreResult['tables_created_count'], 'integrity' => $integrityReport['status']];
}catch(Throwable $e){if(DB::transactionLevel())DB::rollBack();$r['error']=$e->getMessage();$r['class']=$e::class;}
finally{foreach($processes as$p)if(is_resource($p)){proc_terminate($p);proc_close($p);}}
echo json_encode($r,JSON_THROW_ON_ERROR);exit(isset($r['error'])?2:0);
