<?php
require __DIR__.'/Phase2A8_bootstrap.php';
use Illuminate\Support\Facades\DB;
Illuminate\Support\Carbon::setTestNow('2027-07-01 10:00:00');
$job=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);
Illuminate\Support\Carbon::setTestNow($job['clock'] ?? '2027-07-01 10:00:00');
$id=(int)DB::selectOne('SELECT CONNECTION_ID() id')->id;
file_put_contents($job['ready'],(string)$id);
$deadline=microtime(true)+20;
while(!is_file($job['go'])){if(microtime(true)>$deadline)throw new RuntimeException('Barrier timeout');usleep(10000);}
try {
    switch($job['action']) {
        case 'activate': app(App\Services\AktivasiTahunAjaran::class)->aktifkan($job['target']);break;
        case 'transition': App\Support\TransisiAkademik::eksekusi('2026/2027','2027/2028',['snapshot'=>$job['snapshot'],'pengecualian'=>['QA001'=>['aksi'=>'promosi','kelas_id'=>$job['class']]],'wajib_keputusan_lengkap'=>true]);break;
        case 'structural-master':
            DB::beginTransaction();
            $model=match($job['master_type']) {
                'period'=>App\Models\TahunAjaran::findOrFail('2027/2028'),
                'class'=>App\Models\Kelas::findOrFail($job['class']),
                'department'=>App\Models\Jurusan::findOrFail('QA'),
            };
            $model->{$job['field']}=$job['value'];
            $model->save(); // Genuine production model writer; no test replacement.
            file_put_contents($job['saved'],(string)$id);
            $commitDeadline=microtime(true)+25;
            while(!is_file($job['commit'])){if(microtime(true)>$commitDeadline)throw new RuntimeException('Master commit barrier timeout');usleep(10000);}
            DB::commit();break;
        case 'master': $class=App\Models\Kelas::findOrFail($job['class']);$class->kapasitas=37;$class->save();break;
        default: throw new RuntimeException('Unknown action');
    }
    $result=['ok'=>true,'connection_id'=>$id,'action'=>$job['action']];
} catch(Throwable $e){$result=['ok'=>false,'connection_id'=>$id,'action'=>$job['action'],'class'=>$e::class,'error'=>$e->getMessage()];}
file_put_contents($job['result'],json_encode($result,JSON_THROW_ON_ERROR));
