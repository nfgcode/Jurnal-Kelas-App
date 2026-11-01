<?php

namespace Tests\Feature;

use App\AcademicWorkflows\WorkflowService;
use App\AcademicWorkflows\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicWorkflowFoundationTest extends TestCase
{
    // Execution owns the top-level commit; a test-owned transaction would mask it.
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        // Fresh isolated :memory: connection is discarded; legacy down migrations
        // are not part of this workflow regression contract.
        $this->refreshTestDatabase();
    }

    private function actor(): User
    {
        $actor = User::factory()->create(['role' => 'admin', 'status' => 'aktif']);
        $this->actingAs($actor);
        return $actor;
    }

    public function test_preview_is_select_only_and_missing_history_stays_unverified(): void
    {
        $actor = $this->actor();
        $class = $this->buatKelas(['tingkat' => 'X']);
        $student = \App\Models\Siswa::factory()->create(['nis' => '00123', 'kelas_id' => $class->id]);
        $op = (new WorkflowService)->prepare('promotion', '2026/2027', null, [['student_ref' => '00123']], [], $actor);
        DB::enableQueryLog(); DB::flushQueryLog();
        $preview = (new WorkflowService)->preview($op->id, $actor);
        $queries = DB::getQueryLog(); DB::disableQueryLog();
        foreach ($queries as $query) $this->assertStringStartsWith('select', strtolower(ltrim($query['query'])));
        $this->assertSame('unverified', $preview->items[0]['validation']);
        $this->assertSame('draft', $op->fresh()->state);
        $this->assertSame(1, DB::table('academic_workflow_audits')->count());
    }

    private function intake(WorkflowService $service, User $actor): Operation
    {
        $class = $this->buatKelas(['tingkat'=>'X']);
        \App\Models\TahunAjaran::whereKey('2026/2027')->update(['mulai'=>'2026-07-01','selesai'=>'2027-06-30']);
        return $service->prepare('intake',null,'2026/2027',[['student_ref'=>'NEW001','nisn'=>'0000000001','email'=>'new001@example.test','target_class_id'=>$class->id]],['effective_date'=>'2026-10-01'],$actor);
    }

    public function test_explicit_validation_confirmation_default_executor_is_unavailable(): void
    {
        $actor=$this->actor(); $service=new WorkflowService; $op=$this->intake($service,$actor);
        $ready=$service->validate($op->id,$actor);
        $this->assertSame('ready',$ready->state);
        $confirmation=$service->confirm($op->id,$ready->version,$ready->snapshot_hash,$actor);
        try { $service->execute($op->id,$confirmation,$actor); $this->fail('must fail closed'); }
        catch (\DomainException $e) { $this->assertSame('workflow_executor_unavailable',$e->getMessage()); }
        $this->assertSame('confirmed',$op->fresh()->state);
    }

    private function double(\Closure $callback): \App\AcademicWorkflows\Executor
    {
        return new class($callback) implements \App\AcademicWorkflows\Executor {
            public function __construct(private \Closure $callback) {}
            public function execute(Operation $operation, \App\AcademicWorkflows\Preview $preview): array { return ($this->callback)($operation,$preview); }
        };
    }
    private function confirmed(WorkflowService $service, Operation $op, User $actor): string
    {
        $ready=$service->validate($op->id,$actor);
        return $service->confirm($op->id,$ready->version,$ready->snapshot_hash,$actor);
    }
    public function test_completed_replay_returns_persisted_result_without_second_mutation(): void
    {
        $actor=$this->actor(); $calls=0;
        $registry=new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake',$this->double(function () use (&$calls) { $calls++; return ['processed'=>1]; }));
        $service=new WorkflowService($registry); $op=$this->intake($service,$actor);
        $confirmation=$this->confirmed($service,$op,$actor);
        $this->assertSame(['processed'=>1],$service->execute($op->id,$confirmation,$actor));
        $this->travel(1)->hours();
        $this->assertSame(['processed'=>1],$service->execute($op->id,'stale-response-retry',$actor));
        $this->assertSame(1,$calls);
        $this->assertSame('completed',$op->fresh()->state);
        $this->assertNotNull($op->fresh()->completed_at);
    }

    public function test_precommit_failure_rolls_back_mutation_and_records_sanitized_failure(): void
    {
        $actor=$this->actor(); $registry=new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake',$this->double(function () { DB::table('tahun_ajaran')->update(['aktif'=>false]); throw new \RuntimeException('password=secret raw SQL private note'); }));
        $service=new WorkflowService($registry); $op=$this->intake($service,$actor); $confirmation=$this->confirmed($service,$op,$actor);
        try { $service->execute($op->id,$confirmation,$actor); $this->fail('expected failure'); }
        catch (\DomainException $e) { $this->assertSame('workflow_execution_failed',$e->getMessage()); }
        $this->assertDatabaseHas('tahun_ajaran',['kode'=>'2026/2027','aktif'=>true]);
        $this->assertSame('failed',$op->fresh()->state);
        $this->assertSame('workflow_execution_failed',$op->fresh()->error_code);
        $this->assertNull($op->fresh()->result);
        $this->assertDatabaseMissing('academic_workflow_audits',['operation_id'=>$op->id,'event'=>'complete']);
        $this->assertStringNotContainsString('secret',json_encode(DB::table('academic_workflow_audits')->get()));
    }

    public function test_revision_invalidates_confirmation_and_failed_retry_is_explicit(): void
    {
        $actor=$this->actor(); $service=new WorkflowService; $op=$this->intake($service,$actor);
        $confirmation=$this->confirmed($service,$op,$actor);
        $revised=$service->revise($op->id,[['student_ref'=>'NEW002','target_class_id'=>$op->items()->first()->target_class_id]],[],$actor);
        $this->assertSame(2,$revised->version); $this->assertSame('draft',$revised->state);
        $this->assertNull($revised->confirmation_id); $this->assertNull($revised->snapshot_hash);
        $this->assertSame('NEW002',$revised->items()->first()->student_ref);
        $bad=$service->prepare('intake',null,null,[['student_ref'=>'UNKNOWN']],[],$actor);
        $this->assertSame('needs_attention',$service->validate($bad->id,$actor)->state);
        $this->assertSame('draft',$service->retry($bad->id,$actor)->state);
        $this->assertSame(2,$bad->fresh()->version);
    }

    public function test_completed_operation_and_items_are_immutable_through_models_and_service(): void
    {
        $actor=$this->actor(); $registry=new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake',$this->double(fn()=>['processed'=>1]));
        $service=new WorkflowService($registry); $op=$this->intake($service,$actor);
        $service->execute($op->id,$this->confirmed($service,$op,$actor),$actor);
        foreach ([fn()=> $service->revise($op->id,[],[],$actor), fn()=> $service->retry($op->id,$actor), fn()=> $op->fresh()->forceFill(['state'=>'draft'])->saveQuietly(), fn()=> $op->fresh()->delete(), fn()=> $op->items()->first()->forceFill(['decision'=>'naik'])->saveQuietly(), fn()=> $op->items()->first()->delete()] as $change) {
            try { $change(); $this->fail('immutable'); } catch (\DomainException $e) { $this->assertSame('workflow_immutable',$e->getMessage()); }
        }
    }

    public function test_confirmation_binds_actor_expiry_and_current_master_snapshot(): void
    {
        $actor=$this->actor(); $registry=new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake',$this->double(fn()=>['processed'=>1]));
        $service=new WorkflowService($registry); $op=$this->intake($service,$actor); $token=$this->confirmed($service,$op,$actor);
        $other=$this->actor();
        try { $service->execute($op->id,$token,$other); $this->fail('actor bound'); } catch (\DomainException $e) { $this->assertSame('workflow_confirmation_stale',$e->getMessage()); }
        $this->actingAs($actor); $this->travel(16)->minutes();
        try { $service->execute($op->id,$token,$actor); $this->fail('expired'); } catch (\DomainException $e) { $this->assertSame('workflow_confirmation_stale',$e->getMessage()); }
        $this->travelBack();
        \App\Models\TahunAjaran::whereKey('2026/2027')->update(['mulai'=>'2026-07-02']);
        try { $service->execute($op->id,$token,$actor); $this->fail('master changed'); } catch (\DomainException $e) { $this->assertSame('workflow_confirmation_stale',$e->getMessage()); }
        $this->assertSame('confirmed',$op->fresh()->state);
    }

    public function test_authentication_and_metadata_fail_closed(): void
    {
        $actor=$this->actor(); $service=new WorkflowService;
        try { $service->prepare('intake',null,null,[['student_ref'=>'NEW','password'=>'secret']],[],$actor); $this->fail('secret rejected'); } catch (\DomainException $e) { $this->assertSame('workflow_item_invalid',$e->getMessage()); }
        $this->assertSame(0,Operation::count());
        $actor->status='nonaktif'; $actor->save();
        try { $service->prepare('intake',null,null,[],[],$actor); $this->fail('inactive'); } catch (\DomainException $e) { $this->assertSame('workflow_actor_invalid',$e->getMessage()); }
    }

    public function test_intake_duplicate_requires_explicit_exclusion(): void
    {
        $actor=$this->actor(); $service=new WorkflowService; $op=$this->intake($service,$actor);
        \App\Models\Siswa::factory()->create(['nis'=>'NEW001','kelas_id'=>$op->items()->first()->target_class_id]);
        $this->assertSame('invalid',$service->preview($op->id,$actor)->validation);
        $service->revise($op->id,[['student_ref'=>'NEW001','excluded'=>true,'exclusion_reason_present'=>true]],[],$actor);
        $this->assertSame('valid',$service->preview($op->id,$actor)->validation);
    }

    public function test_coordinator_requires_two_fixed_order_stages(): void
    {
        $actor=$this->actor(); $coordinator=new \App\AcademicWorkflows\Coordinator(new WorkflowService);
        try { $coordinator->create(['intake'],$actor); $this->fail('minimum two'); } catch (\DomainException $e) { $this->assertSame('workflow_stages_invalid',$e->getMessage()); }
        $id=$coordinator->create(['intake','graduation'],$actor);
        $stages=$coordinator->status($id,$actor);
        $this->assertSame(['graduation','intake'],array_column($stages,'kind'));
        $this->assertSame(['not_started','not_started'],array_column($stages,'state'));
        $this->assertSame(0,Operation::count());
    }

    private function graduation(WorkflowService $service, User $actor): Operation
    {
        $class=$this->buatKelas(['tingkat'=>'XII']);
        \App\Models\TahunAjaran::whereKey('2026/2027')->update(['mulai'=>'2026-07-01','selesai'=>'2027-06-30']);
        $student=\App\Models\Siswa::factory()->create(['nis'=>'G001','kelas_id'=>$class->id,'status'=>'aktif']);
        \App\Models\RiwayatKelasSiswa::daftarkan($student,$class,'2026/2027','2026-07-01');
        return $service->prepare('graduation','2026/2027',null,[['student_ref'=>'G001','decision'=>'lulus']],['official_verified'=>true,'official_reference'=>'OFFICIAL-001','source_version'=>'v1','source_content_hash'=>hash('sha256','official-test-decisions'),'effective_date'=>'2026-10-01'],$actor);
    }
    public function test_coordinator_partial_failure_resumes_only_explicit_failed_stage(): void
    {
        $actor=$this->actor(); $registry=new \App\AcademicWorkflows\ExecutorRegistry; $fail=true; $graduations=0;
        $registry->register('graduation',$this->double(function() use (&$graduations) { $graduations++; return ['processed'=>1]; }));
        $registry->register('intake',$this->double(function() use (&$fail) { if ($fail) throw new \RuntimeException('private'); return ['processed'=>1]; }));
        $service=new WorkflowService($registry); $coordinator=new \App\AcademicWorkflows\Coordinator($service);
        $g=$this->graduation($service,$actor); $i=$this->intake($service,$actor);
        $id=$coordinator->create(['graduation','intake'],$actor);
        $coordinator->attach($id,'graduation',$g->id,$actor); $coordinator->attach($id,'intake',$i->id,$actor);
        $gt=$this->confirmed($service,$g,$actor); $it=$this->confirmed($service,$i,$actor);
        try { $coordinator->executeStage($id,'intake',$it,$actor); $this->fail('order'); } catch (\DomainException $e) { $this->assertSame('workflow_stage_order',$e->getMessage()); }
        $coordinator->executeStage($id,'graduation',$gt,$actor);
        try { $coordinator->executeStage($id,'intake',$it,$actor); $this->fail('failure'); } catch (\DomainException $e) { $this->assertSame('workflow_execution_failed',$e->getMessage()); }
        $this->assertSame(['completed','failed'],array_column($coordinator->status($id,$actor),'state'));
        $fail=false; $service->retry($i->id,$actor); $it=$this->confirmed($service,$i,$actor);
        $coordinator->executeStage($id,'intake',$it,$actor);
        $this->assertSame(['completed','completed'],array_column($coordinator->status($id,$actor),'state'));
        $this->assertSame(1,$graduations);
    }

    public function test_graduation_requires_official_explicit_decisions_and_retention_target(): void
    {
        $actor=$this->actor(); $service=new WorkflowService; $op=$this->graduation($service,$actor);
        $this->assertSame('valid',$service->preview($op->id,$actor)->validation);
        $service->revise($op->id,[['student_ref'=>'G001']],[],$actor);
        $this->assertSame('unverified',$service->preview($op->id,$actor)->validation);
        $service->revise($op->id,[['student_ref'=>'G001','decision'=>'tidak_lulus']],['official_verified'=>true,'official_reference'=>'OFFICIAL-001','source_version'=>'v1','source_content_hash'=>hash('sha256','official-test-decisions'),'effective_date'=>'2026-10-01'],$actor);
        $this->assertSame('invalid',$service->preview($op->id,$actor)->validation);
    }

    public function test_promotion_defaults_naik_and_retention_requires_same_grade(): void
    {
        $actor=$this->actor(); $service=new WorkflowService;
        $source=$this->buatKelas(['tingkat'=>'X']);
        \App\Models\TahunAjaran::whereKey('2026/2027')->update(['mulai'=>'2026-07-01','selesai'=>'2027-06-30']);
        \App\Models\TahunAjaran::create(['kode'=>'2027/2028','aktif'=>false,'mulai'=>'2027-07-01','selesai'=>'2028-06-30']);
        $target=$this->buatKelas(['tingkat'=>'XI','tahun_ajaran_kode'=>'2027/2028']);
        $student=\App\Models\Siswa::factory()->create(['nis'=>'P001','kelas_id'=>$source->id,'status'=>'aktif']);
        \App\Models\RiwayatKelasSiswa::daftarkan($student,$source,'2026/2027','2026-07-01');
        $op=$service->prepare('promotion','2026/2027','2027/2028',[['student_ref'=>'P001','target_class_id'=>$target->id]],['effective_date'=>'2027-07-01'],$actor);
        $this->assertSame('valid',$service->preview($op->id,$actor)->validation,json_encode($service->preview($op->id,$actor)->items));
        $this->assertSame('naik',$service->preview($op->id,$actor)->items[0]['decision']);
        $service->revise($op->id,[['student_ref'=>'P001','decision'=>'tinggal_kelas','target_class_id'=>$target->id]],['effective_date'=>'2027-07-01'],$actor);
        $this->assertSame('invalid',$service->preview($op->id,$actor)->validation);
        $target->tingkat='X'; $target->save();
        $this->assertSame('valid',$service->preview($op->id,$actor)->validation);
    }

    public function test_audit_failure_rolls_back_success_and_academic_mutation(): void
    {
        $actor=$this->actor(); $registry=new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake',$this->double(function () { DB::table('tahun_ajaran')->update(['aktif'=>false]); return ['processed'=>1]; }));
        $service=new WorkflowService($registry); $op=$this->intake($service,$actor); $token=$this->confirmed($service,$op,$actor);
        DB::unprepared("CREATE TRIGGER deny_success_audit BEFORE INSERT ON academic_workflow_audits WHEN NEW.event = 'complete' BEGIN SELECT RAISE(ABORT, 'private SQL password secret'); END");
        try { $service->execute($op->id,$token,$actor); $this->fail('audit must fail closed'); } catch (\DomainException $e) { $this->assertSame('workflow_execution_failed',$e->getMessage()); }
        $this->assertDatabaseHas('tahun_ajaran',['kode'=>'2026/2027','aktif'=>true]);
        $this->assertSame('failed',$op->fresh()->state);
        $this->assertNull($op->fresh()->result);
        $this->assertDatabaseMissing('academic_workflow_audits',['event'=>'complete']);
    }

    public function test_legacy_student_history_and_identity_mutations_invalidate_confirmation(): void
    {
        $actor=$this->actor(); $registry=new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('graduation',$this->double(fn()=>['processed'=>1]));
        $service=new WorkflowService($registry); $op=$this->graduation($service,$actor); $token=$this->confirmed($service,$op,$actor);
        DB::table('riwayat_kelas_siswa')->where('siswa_nis','G001')->update(['tanggal_mulai'=>'2026-07-02']);
        try { $service->execute($op->id,$token,$actor); $this->fail('history changed'); } catch (\DomainException $e) { $this->assertSame('workflow_confirmation_stale',$e->getMessage()); }
        $token=$this->confirmed($service,$op,$actor);
        DB::table('siswa')->where('nis','G001')->update(['nisn'=>'99887766']);
        try { $service->execute($op->id,$token,$actor); $this->fail('identity changed'); } catch (\DomainException $e) { $this->assertSame('workflow_confirmation_stale',$e->getMessage()); }
        $this->assertSame('confirmed',$op->fresh()->state);
    }

    public function test_direct_item_revision_invalidates_confirmation_and_cannot_reparent_completed_item(): void
    {
        $actor=$this->actor(); $registry=new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake',$this->double(fn()=>['processed'=>1]));
        $service=new WorkflowService($registry); $op=$this->intake($service,$actor); $token=$this->confirmed($service,$op,$actor);
        $item=$op->items()->first(); $item->student_ref='NEW003'; $item->saveQuietly();
        $this->assertNull($op->fresh()->confirmation_id);
        $this->assertSame('draft',$op->fresh()->state);
        $this->assertSame(2,$op->fresh()->version);
        $service->execute($op->id,$this->confirmed($service,$op,$actor),$actor);
        $draft=$service->prepare('intake',null,null,[],[],$actor);
        try { $item->operation_id=$draft->id; $item->saveQuietly(); $this->fail('completed reparent'); } catch (\DomainException $e) { $this->assertSame('workflow_immutable',$e->getMessage()); }
    }

    public function test_execution_acquires_singleton_before_operation_and_completed_replay_requires_active_actor(): void
    {
        $actor=$this->actor(); $registry=new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake',$this->double(fn()=>['processed'=>1]));
        $service=new WorkflowService($registry); $op=$this->intake($service,$actor); $token=$this->confirmed($service,$op,$actor);
        DB::enableQueryLog(); DB::flushQueryLog(); $service->execute($op->id,$token,$actor); $queries=DB::getQueryLog(); DB::disableQueryLog();
        $selects=array_values(array_filter($queries,fn($q)=>str_starts_with(strtolower($q['query']),'select')));
        $this->assertStringContainsString('academic_operation_lock',$selects[0]['query']);
        $actor->status='nonaktif'; $actor->save();
        try { $service->execute($op->id,'ignored',$actor); $this->fail('replay auth'); } catch (\DomainException $e) { $this->assertSame('workflow_actor_invalid',$e->getMessage()); }
    }

    public function test_execution_rejects_outer_transactions_before_attempt_for_commit_and_rollback(): void
    {
        $actor=$this->actor(); $calls=0;
        $registry=new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake',$this->double(function () use (&$calls) { $calls++; return ['processed'=>1]; }));
        $service=new WorkflowService($registry); $op=$this->intake($service,$actor); $token=$this->confirmed($service,$op,$actor);
        $coordinator=new \App\AcademicWorkflows\Coordinator($service);
        $id=$coordinator->create(['promotion','intake'],$actor);
        $coordinator->attach($id,'intake',$op->id,$actor);
        $auditCount=DB::table('academic_workflow_audits')->count();
        foreach ([true,false] as $commit) {
            foreach ([fn()=> $service->execute($op->id,$token,$actor), fn()=> $coordinator->executeStage($id,'intake',$token,$actor)] as $execute) {
                DB::beginTransaction();
                try {
                    try { $execute(); $this->fail('outer transaction accepted'); }
                    catch (\DomainException $e) { $this->assertSame('workflow_top_level_transaction_required',$e->getMessage()); }
                    $this->assertSame(1,DB::transactionLevel());
                } finally { if ($commit) DB::commit(); else DB::rollBack(); }
                $this->assertSame('confirmed',$op->fresh()->state);
                $this->assertSame($auditCount,DB::table('academic_workflow_audits')->count());
                $this->assertSame(0,$calls);
            }
        }
        // Top-level ownership does not bypass integrated stage ordering.
        try { $service->execute($op->id,$token,$actor); $this->fail('stage order bypass'); }
        catch (\DomainException $e) { $this->assertSame('workflow_stage_order',$e->getMessage()); }
        $standalone=$this->intake($service,$actor);
        $standaloneToken=$this->confirmed($service,$standalone,$actor);
        $this->assertSame(['processed'=>1],$service->execute($standalone->id,$standaloneToken,$actor));
        $this->assertSame(0,DB::transactionLevel());
        $this->assertSame('confirmed',$op->fresh()->state);
        $this->assertSame('completed',$standalone->fresh()->state);
    }

    public function test_rejected_outer_transaction_cannot_erase_later_durable_failure(): void
    {
        $actor=$this->actor(); $calls=0;
        $registry=new \App\AcademicWorkflows\ExecutorRegistry;
        $registry->register('intake',$this->double(function () use (&$calls) { $calls++; DB::table('tahun_ajaran')->update(['aktif'=>false]); throw new \RuntimeException('private'); }));
        $service=new WorkflowService($registry); $op=$this->intake($service,$actor); $token=$this->confirmed($service,$op,$actor);
        DB::beginTransaction();
        try {
            try { $service->execute($op->id,$token,$actor); $this->fail('outer transaction accepted'); }
            catch (\DomainException $e) { $this->assertSame('workflow_top_level_transaction_required',$e->getMessage()); }
        } finally { DB::rollBack(); }
        $this->assertSame(0,$calls);
        $this->assertSame('confirmed',$op->fresh()->state);
        try { $service->execute($op->id,$token,$actor); $this->fail('executor failure'); }
        catch (\DomainException $e) { $this->assertSame('workflow_execution_failed',$e->getMessage()); }
        DB::beginTransaction(); DB::rollBack();
        $this->assertSame(0,DB::transactionLevel());
        $this->assertSame('failed',$op->fresh()->state);
        $this->assertDatabaseHas('academic_workflow_audits',['operation_id'=>$op->id,'event'=>'fail']);
        $this->assertDatabaseHas('tahun_ajaran',['kode'=>'2026/2027','aktif'=>true]);
    }

    public function test_candidate_identity_edit_invalidates_confirmation(): void
    {
        $actor=$this->actor(); $service=new WorkflowService; $op=$this->intake($service,$actor);
        $this->confirmed($service,$op,$actor);
        $item=$op->items()->first(); $item->email='changed@example.test'; $item->saveQuietly();
        $this->assertSame('draft',$op->fresh()->state);
        $this->assertNull($op->fresh()->confirmation_id);
        $this->assertSame(2,$op->fresh()->version);
    }

    public function test_item_persisted_owner_blocks_tampered_delete_and_save(): void
    {
        $actor=$this->actor(); $service=new WorkflowService;
        $draft=$service->prepare('intake',null,null,[],[],$actor);
        foreach (['executing','completed'] as $state) {
            $op=$service->prepare('intake',null,null,[['student_ref'=>'PROTECTED']],[],$actor);
            DB::table('academic_workflow_operations')->where('id',$op->id)->update(['state'=>$state]);
            foreach (['delete','saveQuietly'] as $method) {
                foreach ([false,true] as $sync) {
                    $item=$op->items()->first();
                    $item->operation_id=$draft->id;
                    if ($sync) $item->syncOriginal();
                    $item->student_ref='TAMPERED';
                    try { $item->$method(); $this->fail('persisted owner bypass'); }
                    catch (\DomainException $e) { $this->assertSame('workflow_immutable',$e->getMessage()); }
                    $this->assertDatabaseHas('academic_workflow_items',['id'=>$item->id,'operation_id'=>$op->id,'student_ref'=>'PROTECTED']);
                }
            }
        }
        $mutable=$service->prepare('intake',null,null,[['student_ref'=>'DRAFT']],[],$actor);
        $item=$mutable->items()->first(); $item->operation_id=$draft->id; $item->syncOriginal();
        try { $item->delete(); $this->fail('reassignment accepted'); }
        catch (\DomainException $e) { $this->assertSame('workflow_immutable',$e->getMessage()); }
    }

    public function test_coordinator_changes_are_audited_and_audit_failure_rolls_back(): void
    {
        $actor=$this->actor(); $service=new WorkflowService;
        $coordinator=new \App\AcademicWorkflows\Coordinator($service);
        $id=$coordinator->create(['intake','graduation'],$actor);
        $op=$service->prepare('intake',null,null,[],[],$actor);
        $coordinator->attach($id,'intake',$op->id,$actor);
        $logs=\App\Models\AdminAuditLog::where('tipe_entitas','academic_workflow_coordinator')->orderBy('id')->get();
        $this->assertSame(['workflow.coordinator.create','workflow.coordinator.attach'],$logs->pluck('aksi')->all());
        $this->assertSame((string)$id,$logs[0]->entitas_id);
        $this->assertSame($actor->id,$logs[0]->user_id);
        $this->assertSame(['graduation','intake'],$logs[0]->metadata['kinds']);
        $this->assertSame($op->id,$logs[1]->metadata['operation_id']);
        $this->assertSame('intake',$logs[1]->metadata['kind']);
        DB::unprepared("CREATE TRIGGER deny_coordinator_audit BEFORE INSERT ON admin_audit_log WHEN NEW.tipe_entitas = 'academic_workflow_coordinator' BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        try { $coordinator->create(['intake','promotion'],$actor); $this->fail('unaudited create'); }
        catch (\RuntimeException) {}
        $this->assertSame(1,DB::table('academic_workflow_coordinators')->count());
        $this->assertSame(2,DB::table('academic_workflow_stages')->count());
        $graduation=$service->prepare('graduation',null,null,[],[],$actor);
        try { $coordinator->attach($id,'graduation',$graduation->id,$actor); $this->fail('unaudited attach'); }
        catch (\RuntimeException) {}
        $this->assertDatabaseHas('academic_workflow_stages',['coordinator_id'=>$id,'kind'=>'graduation','operation_id'=>null]);
        $this->assertSame(2,\App\Models\AdminAuditLog::where('tipe_entitas','academic_workflow_coordinator')->count());
    }

    public function test_prepare_persists_independent_draft_with_safe_items(): void
    {
        $actor = $this->actor();
        $op = (new WorkflowService)->prepare('intake', null, null, [['student_ref' => '00123', 'excluded' => true]], [], $actor);
        $this->assertSame('draft', $op->state);
        $this->assertSame(1, Operation::count());
        $this->assertDatabaseHas('academic_workflow_items', ['operation_id' => $op->id, 'student_ref' => '00123']);
        $this->assertDatabaseHas('academic_workflow_audits', ['operation_id' => $op->id, 'actor_id' => $actor->id, 'next_state' => 'draft']);
    }
}
