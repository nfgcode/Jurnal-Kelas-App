<?php
namespace Tests\Feature;

use App\AcademicWorkflows\{WorkflowService,Operation,Coordinator,Executor,ExecutorRegistry,Preview};
use App\Models\{User,Siswa,RiwayatKelasSiswa,TahunAjaran};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicWorkflowReviewRegressionTest extends TestCase
{
    use DatabaseMigrations;
    public function runDatabaseMigrations(): void { $this->refreshTestDatabase(); }

    private function graduation(): array
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-31 12:00:00'));
        $actor=User::factory()->create(['role'=>'admin','status'=>'aktif']);
        $this->actingAs($actor);
        $class=$this->buatKelas(['tingkat'=>'XII']);
        TahunAjaran::whereKey('2026/2027')->update(['mulai'=>'2026-07-01','selesai'=>'2027-06-30']);
        $student=Siswa::factory()->create(['nis'=>'G001','kelas_id'=>$class->id,'status'=>'aktif']);
        $member=RiwayatKelasSiswa::daftarkan($student,$class,'2026/2027','2026-07-01');
        $service=new WorkflowService;
        $op=$service->prepare('graduation','2026/2027',null,[['student_ref'=>'G001','decision'=>'lulus']],['official_verified'=>true,'official_reference'=>'OFFICIAL','source_version'=>'v1','source_content_hash'=>str_repeat('a',64),'effective_date'=>'2026-10-01'],$actor);
        $this->assertSame('valid',$service->preview($op->id,$actor)->validation);
        return [$service,$op,$actor,$member];
    }

    private function oldHistory(RiwayatKelasSiswa $member, string $start, string $end): void
    {
        RiwayatKelasSiswa::create(['siswa_nis'=>$member->siswa_nis,'kelas_id'=>$member->kelas_id,'tahun_ajaran_kode'=>$member->tahun_ajaran_kode,'tingkat'=>$member->tingkat,'status'=>'pindah','aktif'=>false,'tanggal_mulai'=>$start,'tanggal_selesai'=>$end]);
    }

    private function confirm(WorkflowService $service, Operation $op, User $actor): string
    {
        $ready=$service->validate($op->id,$actor);
        return $service->confirm($op->id,$ready->version,$ready->snapshot_hash,$actor);
    }

    public function test_direct_attached_intake_rejects_bypass_then_allows_prior_completion_and_replay(): void
    {
        [, $graduation,$actor]=$this->graduation();
        $calls=[];
        $registry=new ExecutorRegistry;
        foreach (['graduation','intake'] as $kind) $registry->register($kind,new class($calls) implements Executor {
            public function __construct(private array &$calls) {}
            public function execute(Operation $operation, Preview $preview): array {
                $this->calls[]=$operation->kind;
                return ['processed'=>1];
            }
        });
        $service=new WorkflowService($registry);
        $intake=$service->prepare('intake',null,'2026/2027',[['student_ref'=>'NEW','excluded'=>true,'exclusion_reason_present'=>true]],[],$actor);
        $coordinator=new Coordinator($service);
        $id=$coordinator->create(['graduation','intake'],$actor);
        $coordinator->attach($id,'intake',$intake->id,$actor);
        $token=$this->confirm($service,$intake,$actor);
        $auditCount=DB::table('academic_workflow_audits')->count();
        // Missing and attached-but-incomplete predecessors both block direct execution.
        foreach ([false,true] as $attach) {
            if ($attach) $coordinator->attach($id,'graduation',$graduation->id,$actor);
            try { $service->execute($intake->id,$token,$actor); $this->fail('direct bypass accepted'); }
            catch (\DomainException $error) { $this->assertSame('workflow_stage_order',$error->getMessage()); }
            $this->assertSame([],$calls);
            $this->assertSame('confirmed',$intake->fresh()->state);
            $this->assertSame($auditCount,DB::table('academic_workflow_audits')->count());
        }
        $service->execute($graduation->id,$this->confirm($service,$graduation,$actor),$actor);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertSame(['processed'=>1],$service->execute($intake->id,$token,$actor));
        $queries=DB::getQueryLog(); DB::disableQueryLog();
        $selects=array_values(array_filter($queries,fn($q)=>str_starts_with(strtolower($q['query']),'select')));
        $this->assertStringContainsString('academic_operation_lock',$selects[0]['query']);
        $this->assertSame(['processed'=>1],$service->execute($intake->id,'expired-replay',$actor));
        $this->assertSame(['graduation','intake'],$calls);
    }

    public function test_history_changed_after_confirmation_blocks_execution_without_executor_or_writes(): void
    {
        [, $op,$actor,$member]=$this->graduation();
        $calls=0;
        $registry=new ExecutorRegistry;
        $registry->register('graduation',new class($calls) implements Executor {
            public function __construct(private int &$calls) {}
            public function execute(Operation $operation, Preview $preview): array { $this->calls++; return ['processed'=>1]; }
        });
        $service=new WorkflowService($registry);
        $token=$this->confirm($service,$op,$actor);
        $this->oldHistory($member,'2026-06-01','2026-07-01');
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertSame('unverified',$service->preview($op->id,$actor)->validation);
        $queries=DB::getQueryLog(); DB::disableQueryLog();
        foreach ($queries as $query) $this->assertStringStartsWith('select',strtolower(ltrim($query['query'])));
        $audits=DB::table('academic_workflow_audits')->count();
        try { $service->execute($op->id,$token,$actor); $this->fail('changed history accepted'); }
        catch (\DomainException $error) { $this->assertSame('workflow_confirmation_stale',$error->getMessage()); }
        $this->assertSame(0,$calls);
        $this->assertSame('confirmed',$op->fresh()->state);
        $this->assertSame($audits,DB::table('academic_workflow_audits')->count());
    }

    public function test_date_cast_membership_on_effective_boundary_and_nonoverlapping_history_remains_valid(): void
    {
        [$service,$op,$actor,$member]=$this->graduation();
        $member->tanggal_mulai='2026-10-01'; $member->save();
        $this->oldHistory($member,'2026-07-01','2026-09-30');
        $this->assertSame('valid',$service->preview($op->id,$actor)->validation);
    }

    public function test_active_history_start_after_effective_date_is_unverified(): void
    {
        [$service,$op,$actor,$member]=$this->graduation();
        DB::table('riwayat_kelas_siswa')->where('id',$member->id)->update(['tanggal_mulai'=>'2026-10-02']);
        $this->assertSame('unverified',$service->preview($op->id,$actor)->validation);
    }

    public function test_active_history_start_after_today_is_unverified_for_planned_promotion(): void
    {
        [$service,$op,$actor,$member]=$this->graduation();
        DB::table('kelas')->where('id',$member->kelas_id)->update(['tingkat'=>'X']);
        DB::table('riwayat_kelas_siswa')->where('id',$member->id)->update(['tingkat'=>'X','tanggal_mulai'=>'2026-11-01']);
        TahunAjaran::create(['kode'=>'2027/2028','aktif'=>false,'mulai'=>'2027-07-01','selesai'=>'2028-06-30']);
        $dest=$this->buatKelas(['tingkat'=>'XI','tahun_ajaran_kode'=>'2027/2028']);
        $promotion=$service->prepare('promotion','2026/2027','2027/2028',[['student_ref'=>'G001','target_class_id'=>$dest->id]],['effective_date'=>'2027-07-01'],$actor);
        $this->assertSame('unverified',$service->preview($promotion->id,$actor)->validation);
    }

    public function test_inactive_history_touching_active_start_is_unverified(): void
    {
        [$service,$op,$actor,$member]=$this->graduation();
        $this->oldHistory($member,'2026-06-01','2026-07-01');
        $this->assertSame('unverified',$service->preview($op->id,$actor)->validation);
    }

    public function test_complete_but_reversed_inactive_history_is_unverified(): void
    {
        [$service,$op,$actor,$member]=$this->graduation();
        $this->oldHistory($member,'2026-06-20','2026-06-01');
        $this->assertSame('unverified',$service->preview($op->id,$actor)->validation);
        $this->assertSame('needs_attention',$service->validate($op->id,$actor)->state);
    }
}
