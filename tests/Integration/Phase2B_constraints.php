<?php
require __DIR__.'/Phase2B_bootstrap.php';
use Illuminate\Support\Facades\DB;
$r=['assertions'=>0];
$assert=function($ok,$message)use(&$r){if(!$ok)throw new RuntimeException($message);$r['assertions']++;};
$reject=function($fn,$code)use($assert){try{$fn();throw new RuntimeException('Constraint unexpectedly accepted');}catch(Illuminate\Database\QueryException $e){$assert((int)$e->errorInfo[1]===$code,'Wrong SQL rejection '.$e->getMessage());}};
try {
    $f=json_decode(file_get_contents('/scratch/fixtures.json'),true,flags:JSON_THROW_ON_ERROR);
    DB::beginTransaction();
    $o=DB::table('academic_workflow_operations')->insertGetId(['kind'=>'intake','source_year'=>'2026/2027','target_year'=>'2027/2028','actor_id'=>$f['actor'],'confirmed_by'=>$f['actor'],'source_metadata'=>'{}']);
    $i=DB::table('academic_workflow_items')->insertGetId(['operation_id'=>$o,'student_ref'=>'RAW001','target_class_id'=>$f['class']]);
    $a=DB::table('academic_workflow_audits')->insertGetId(['operation_id'=>$o,'actor_id'=>$f['actor'],'kind'=>'intake','event'=>'prepare','next_state'=>'draft','outcome'=>'success','student_refs'=>'[]','metadata'=>'{}','created_at'=>now()]);
    $c=DB::table('academic_workflow_coordinators')->insertGetId(['actor_id'=>$f['actor']]);
    $s=DB::table('academic_workflow_stages')->insertGetId(['coordinator_id'=>$c,'kind'=>'intake','position'=>2,'operation_id'=>$o]);
    $ids=['academic_workflow_operations'=>$o,'academic_workflow_items'=>$i,'academic_workflow_audits'=>$a,'academic_workflow_coordinators'=>$c,'academic_workflow_stages'=>$s];
    $keys=DB::select("SELECT k.TABLE_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME LIKE 'academic_workflow_%' AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME,k.COLUMN_NAME");
    $assert(count($keys)===11,'Expected eleven workflow FKs');
    foreach($keys as $key){
        $assert($key->DELETE_RULE==='RESTRICT','FK not RESTRICT');
        $bad=in_array($key->COLUMN_NAME,['source_year','target_year'])?'9998/9999':999999999;
        $reject(fn()=>DB::table($key->TABLE_NAME)->where('id',$ids[$key->TABLE_NAME])->update([$key->COLUMN_NAME=>$bad]),1452);
        $value=DB::table($key->TABLE_NAME)->where('id',$ids[$key->TABLE_NAME])->value($key->COLUMN_NAME);
        $reject(fn()=>DB::table($key->REFERENCED_TABLE_NAME)->where($key->REFERENCED_COLUMN_NAME,$value)->delete(),1451);
    }
    $reject(fn()=>DB::table('academic_workflow_items')->insert(['operation_id'=>$o,'student_ref'=>'RAW001']),1062);
    $reject(fn()=>DB::table('academic_workflow_stages')->insert(['coordinator_id'=>$c,'kind'=>'intake','position'=>0]),1062);
    $reject(fn()=>DB::table('academic_workflow_stages')->insert(['coordinator_id'=>$c,'kind'=>'graduation','position'=>2]),1062);
    $c2=DB::table('academic_workflow_coordinators')->insertGetId(['actor_id'=>$f['actor']]);
    $reject(fn()=>DB::table('academic_workflow_stages')->insert(['coordinator_id'=>$c2,'kind'=>'intake','position'=>2,'operation_id'=>$o]),1062);
    $r['foreign_keys']=$keys;$r['unique_rejections']=4;
    DB::rollBack();
}catch(Throwable $e){if(DB::transactionLevel())DB::rollBack();$r['error']=$e->getMessage();$r['class']=$e::class;}
echo json_encode($r,JSON_THROW_ON_ERROR);exit(isset($r['error'])?2:0);
