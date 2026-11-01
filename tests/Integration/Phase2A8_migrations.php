<?php
require __DIR__.'/Phase2A8_bootstrap.php';
use Illuminate\Support\Facades\DB;
$result=['assertions'=>0,'applied'=>[]];
try {
    $migrator=$app->make('migrator');
    if (! $migrator->getRepository()->repositoryExists()) { $migrator->getRepository()->createRepository(); }
    foreach (glob('/repo/database/migrations/*.php') as $path) {
        $migration=require $path;
        $migration->up();
        $name=basename($path,'.php');
        $migrator->getRepository()->log($name,1);
        $result['applied'][]=$name;
    }
    $result['migration_count']=count($result['applied']);
    $expected=count(glob('/repo/database/migrations/*.php'));
    if ($expected!==$result['migration_count']) { throw new RuntimeException('Migration count mismatch'); }
    $result['assertions']++;
    $result['routines']=DB::select('SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()');
    echo json_encode($result,JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $result['failed_migration']=isset($path)?basename($path):null;
    $result['error_class']=$e::class;
    // SQL definitions contain no credentials; exception message is retained for real blocker evidence.
    $result['error']=$e->getMessage();
    echo json_encode($result,JSON_THROW_ON_ERROR);
    exit(2);
}
