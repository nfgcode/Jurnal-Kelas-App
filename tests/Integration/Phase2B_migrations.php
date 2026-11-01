<?php
require __DIR__.'/Phase2B_bootstrap.php';
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
    require __DIR__.'/Phase2B_fixtures.php';
    $fixtures=phase2bFixtures();
    file_put_contents('/scratch/fixtures.json',json_encode($fixtures,JSON_THROW_ON_ERROR));
    $before=phase2bLegacy();
    $workflow=require '/repo/database/migrations/2026_11_01_000001_create_academic_workflows.php';
    $workflow->down();
    foreach(['operations','items','audits','coordinators','stages'] as $suffix) {
        if (Illuminate\Support\Facades\Schema::hasTable('academic_workflow_'.$suffix)) throw new RuntimeException('Workflow down left table: '.$suffix);
        $result['assertions']++;
    }
    if ($before!==phase2bLegacy()) throw new RuntimeException('Legacy fixtures changed during down');
    $result['assertions']++;
    $workflow->up();
    foreach(['operations','items','audits','coordinators','stages'] as $suffix) {
        if (!Illuminate\Support\Facades\Schema::hasTable('academic_workflow_'.$suffix)) throw new RuntimeException('Workflow up missing table: '.$suffix);
        $result['assertions']++;
    }
    if ($before!==phase2bLegacy()) throw new RuntimeException('Legacy fixtures changed during re-up');
    $result['assertions']++;
    $result['legacy_hashes']=$before;
    $result['cycle']='down/up with populated legacy fixtures preserved';
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
