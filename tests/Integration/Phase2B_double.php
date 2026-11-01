<?php
use App\AcademicWorkflows\{Executor,Operation,Preview,ExecutorRegistry,WorkflowService};
use Illuminate\Support\Facades\DB;
// Transactional writes only; no DDL, I/O, commits or after-commit callbacks.
final class Phase2BExecutor implements Executor {
    public function __construct(private string $mode='marker',private bool $fail=false){}
    public function execute(Operation $operation,Preview $preview): array {
        DB::table('phase2b_markers')->insert(['operation_id'=>$operation->id,'student_ref'=>$preview->items[0]['student_ref'],'mode'=>$this->mode]);
        if($this->mode==='identity') DB::table('siswa')->insert(['nis'=>$preview->items[0]['student_ref'],'nama'=>'Disposable intake','kelas_id'=>$preview->items[0]['target_class_id'],'status'=>'aktif','jurusan_kode'=>'QA']);
        if($this->fail) throw new RuntimeException('private-test-only-failure');
        return ['processed'=>1,'skipped'=>0,'failed'=>0];
    }
}
function phase2bService(string $mode='marker',bool $fail=false): WorkflowService {
    $registry=new ExecutorRegistry;
    foreach(['intake','graduation','promotion'] as $kind) $registry->register($kind,new Phase2BExecutor($mode,$fail && $kind==='intake'));
    return new WorkflowService($registry);
}
