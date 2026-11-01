<?php
require __DIR__.'/Phase2B_bootstrap.php';
require __DIR__.'/Phase2B_double.php';
use Illuminate\Support\Facades\{DB,Auth};
$job=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);
Illuminate\Support\Carbon::setTestNow('2026-10-31 10:00:00');
$actor=App\Models\User::findOrFail($job['actor']);Auth::login($actor);
$id=(int)DB::selectOne('SELECT CONNECTION_ID() id')->id;
file_put_contents($job['ready'],(string)$id);
$deadline=microtime(true)+25;
while(!is_file($job['go'])){if(microtime(true)>$deadline)throw new RuntimeException('Barrier timeout');usleep(10000);}
try {
    if(($job['action']??'execute')==='master'){
        DB::beginTransaction();
        $year=App\Models\TahunAjaran::findOrFail('2026/2027');$year->mulai='2026-07-02';$year->save();
        file_put_contents($job['saved'],(string)$id);
        while(!is_file($job['commit'])){if(microtime(true)>$deadline)throw new RuntimeException('Commit barrier timeout');usleep(10000);}
        DB::commit();$value=null;
    }else $value=phase2bService($job['mode']??'marker')->execute($job['operation'],$job['confirmation'],$actor);
    $r=['ok'=>true,'connection_id'=>$id,'value'=>$value];
}catch(Throwable $e){if(DB::transactionLevel())DB::rollBack();$r=['ok'=>false,'connection_id'=>$id,'class'=>$e::class,'error'=>$e->getMessage()];}
file_put_contents($job['result'],json_encode($r,JSON_THROW_ON_ERROR));
