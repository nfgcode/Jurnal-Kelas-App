<?php
namespace App\AcademicWorkflows;
use App\Models\User;
use App\Support\AcademicOperation;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
final class WorkflowService
{
    public const KINDS = ['graduation','promotion','intake'];
    public function __construct(private ?ExecutorRegistry $registry = null)
    {
        if (! $this->registry) {
            $this->registry = new ExecutorRegistry;
            $this->registry->register('graduation', new GraduationExecutor);
            $this->registry->register('promotion', new PromotionExecutor);
            // IntakeExecutor didaftarkan melalui service provider AppServiceProvider
            // atau instance registry kustom untuk menjaga kontrak fail-closed testing
        }
    }
    public function authorize(User $actor): User
    {
        $fresh = User::find($actor->getKey());
        if (!$fresh || !Auth::check() || (string) Auth::id() !== (string) $fresh->id || $fresh->role !== 'admin' || $fresh->status !== 'aktif') throw new DomainException('workflow_actor_invalid');
        return $fresh;
    }
    public function prepare(string $kind, ?string $source, ?string $target, array $items, array $metadata, User $actor): Operation
    {
        return AcademicOperation::run(function () use ($kind,$source,$target,$items,$metadata,$actor) {
            $this->authorize($actor);
            if (!in_array($kind,self::KINDS,true)) throw new DomainException('workflow_kind_invalid');
            $op = new Operation;
            $op->forceFill(['kind'=>$kind,'state'=>'draft','source_year'=>$source,'target_year'=>$target,'source_metadata'=>$this->metadata($metadata),'actor_id'=>$actor->id,'version'=>1,'validation'=>'unverified'])->save();
            $this->storeItems($op,$items);
            $this->audit($op,$actor,'prepare',null,'success');
            return $op;
        });
    }
    public function preview(int $id, User $actor): Preview
    {
        $this->authorize($actor);
        return (new Readiness)->evaluate(Operation::findOrFail($id));
    }
    private function locked(int $id, User $actor): Operation
    {
        $this->authorize($actor);
        return Operation::whereKey($id)->lockForUpdate()->firstOrFail();
    }
    private function mutable(Operation $op): void
    {
        if (in_array($op->state,['completed','executing'],true)) throw new DomainException('workflow_immutable');
    }
    public function validate(int $id, User $actor): Operation
    {
        return AcademicOperation::run(function () use ($id,$actor) {
            $op=$this->locked($id,$actor); $this->mutable($op);
            if (!in_array($op->state,['draft','ready','confirmed'],true)) throw new DomainException('workflow_retry_required');
            $previous=$op->state; $preview=(new Readiness)->evaluate($op,true);
            foreach ($preview->items as $item) Item::whereKey($item['id'])->update(['validation'=>$item['validation'],'errors'=>json_encode($item['errors'])]);
            $this->clearConfirmation($op);
            $op->forceFill(['snapshot'=>$preview->snapshot,'snapshot_hash'=>$preview->hash,'validation'=>$preview->validation,'state'=>$preview->validation==='valid'?'ready':'needs_attention','validated_at'=>now()])->save();
            $this->audit($op,$actor,'validate',$previous,$preview->validation==='valid'?'success':'blocked');
            return $op;
        });
    }
    private function clearConfirmation(Operation $op): void
    {
        $op->forceFill(['confirmation_id'=>null,'confirmed_by'=>null,'confirmed_version'=>null,'confirmed_hash'=>null,'confirmed_at'=>null,'confirmation_expires_at'=>null]);
    }
    public function confirm(int $id, int $version, string $hash, User $actor): string
    {
        return AcademicOperation::run(function () use ($id,$version,$hash,$actor) {
            $op=$this->locked($id,$actor);
            $preview=(new Readiness)->evaluate($op,true);
            if ($op->state!=='ready' || $preview->validation!=='valid' || $version!==$op->version || !hash_equals($op->snapshot_hash ?? '',$hash) || !hash_equals($preview->hash,$hash)) throw new DomainException('workflow_confirmation_stale');
            $token=bin2hex(random_bytes(24));
            $op->forceFill(['state'=>'confirmed','confirmation_id'=>$token,'confirmed_by'=>$actor->id,'confirmed_version'=>$version,'confirmed_hash'=>$hash,'confirmed_at'=>now(),'confirmation_expires_at'=>now()->addMinutes(15)])->save();
            $this->audit($op,$actor,'confirm','ready','success');
            return $token;
        });
    }
    public function execute(int $id, string $confirmation, User $actor): array
    {
        // Failure evidence and completed results require an owned, durable commit.
        if (DB::transactionLevel() !== 0) throw new DomainException('workflow_top_level_transaction_required');
        $attempted = false;
        try {
        return AcademicOperation::run(function () use ($id,$confirmation,$actor,&$attempted) {
            $op=$this->locked($id,$actor);
            if ($op->state==='completed') return $op->result;
            // Attachment and predecessor state are resolved under the same singleton
            // transaction as execution, including calls that bypass Coordinator.
            $attached=DB::table('academic_workflow_stages')->where('operation_id',$op->id)->lockForUpdate()->first();
            if ($attached) {
                $stages=DB::table('academic_workflow_stages')->where('coordinator_id',$attached->coordinator_id)->orderBy('position')->lockForUpdate()->get();
                foreach ($stages as $stage) {
                    if ((int)$stage->id===(int)$attached->id) break;
                    $prior=$stage->operation_id ? Operation::whereKey($stage->operation_id)->lockForUpdate()->first() : null;
                    if (!$prior || $prior->state!=='completed') throw new DomainException('workflow_stage_order');
                }
            }
            $executor=$this->registry->get($op->kind);
            $preview=(new Readiness)->evaluate($op,true);
            if ($op->state!=='confirmed' || (string)$op->confirmed_by!==(string)$actor->id || !hash_equals($op->confirmation_id ?? '',$confirmation) || $op->confirmed_version!=$op->version || !$op->confirmation_expires_at || $op->confirmation_expires_at->lte(now()) || !hash_equals($op->confirmed_hash ?? '',$preview->hash) || $preview->validation!=='valid') throw new DomainException('workflow_confirmation_stale');
            $attempted = true;
            $op->forceFill(['state'=>'executing','started_at'=>now()])->save();
            $this->audit($op,$actor,'execute','confirmed','started');
            $result=$executor->execute($op,$preview);
            foreach ($result as $key=>$value) if (!in_array($key,['processed','skipped','failed'],true) || !is_int($value) || $value<0) throw new DomainException('workflow_result_invalid');
            $op->forceFill(['state'=>'completed','result'=>$result,'completed_at'=>now(),'error_code'=>null])->save();
            $this->audit($op,$actor,'complete','executing','success');
            return $result;
        });
        } catch (\Throwable $error) {
            if (!$attempted) {
                if ($error instanceof DomainException) throw $error;
                throw new DomainException('workflow_execution_rejected');
            }
            // Read durable state after transaction exit: an after-commit response failure
            // must never relabel an already committed operation as failed.
            try {
                $completed = Operation::find($id);
                if ($completed?->state === 'completed') return $completed->result;
                AcademicOperation::run(function () use ($id,$actor) {
                    $op=$this->locked($id,$actor);
                    if ($op->state!=='confirmed') return;
                    $this->clearConfirmation($op);
                    $op->forceFill(['state'=>'failed','result'=>null,'error_code'=>'workflow_execution_failed','failed_at'=>now()])->save();
                    $this->audit($op,$actor,'fail','confirmed','failure');
                });
            } catch (\Throwable) {
                throw new DomainException('workflow_failure_record_unavailable');
            }
            throw new DomainException('workflow_execution_failed');
        }
    }
    public function revise(int $id, array $items, array $metadata, User $actor): Operation
    {
        return AcademicOperation::run(function () use ($id,$items,$metadata,$actor) {
            $op=$this->locked($id,$actor); $this->mutable($op); $previous=$op->state;
            $op->items()->delete(); $this->storeItems($op,$items);
            $this->reset($op); $op->source_metadata=$this->metadata($metadata); $op->save();
            $this->audit($op,$actor,'revise',$previous,'success'); return $op;
        });
    }
    public function retry(int $id, User $actor): Operation
    {
        return AcademicOperation::run(function () use ($id,$actor) {
            $op=$this->locked($id,$actor); $this->mutable($op);
            if (!in_array($op->state,['failed','needs_attention'],true)) throw new DomainException('workflow_retry_invalid');
            $previous=$op->state; $this->reset($op); $op->save();
            $op->items()->update(['validation'=>'unverified','errors'=>null]);
            $this->audit($op,$actor,'retry',$previous,'success'); return $op;
        });
    }
    private function reset(Operation $op): void
    {
        $this->clearConfirmation($op);
        $op->forceFill(['state'=>'draft','version'=>$op->version+1,'validation'=>'unverified','snapshot'=>null,'snapshot_hash'=>null,'validated_at'=>null,'started_at'=>null,'failed_at'=>null,'completed_at'=>null,'result'=>null,'error_code'=>null]);
    }
    private function metadata(array $metadata): array
    {
        if (array_diff(array_keys($metadata), ['official_verified','official_reference','effective_date','source_content_hash','source_version'])) throw new DomainException('workflow_metadata_invalid');
        if (isset($metadata['official_verified']) && !is_bool($metadata['official_verified'])) throw new DomainException('workflow_metadata_invalid');
        if (isset($metadata['official_reference']) && (!is_string($metadata['official_reference']) || !preg_match('/\A[A-Za-z0-9_.\/-]{1,80}\z/D',$metadata['official_reference']))) throw new DomainException('workflow_metadata_invalid');
        foreach (['source_content_hash'=>'/\A[a-f0-9]{64}\z/D','source_version'=>'/\A[A-Za-z0-9_.-]{1,40}\z/D','effective_date'=>'/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D'] as $key=>$pattern) if (isset($metadata[$key]) && (!is_string($metadata[$key]) || !preg_match($pattern,$metadata[$key]))) throw new DomainException('workflow_metadata_invalid');
        return $metadata;
    }
    private function storeItems(Operation $op, array $items): void
    {
        foreach ($items as $item) {
            if (array_diff(array_keys($item),['student_ref','decision','target_class_id','excluded','nisn','email','exclusion_reason_present']) || !is_string($item['student_ref'] ?? null) || !preg_match('/\A[A-Za-z0-9_-]{1,20}\z/D',$item['student_ref'])) throw new DomainException('workflow_item_invalid');
            if (isset($item['decision']) && !in_array($item['decision'],['lulus','tidak_lulus','unverified','naik','tinggal_kelas'],true)) throw new DomainException('workflow_decision_invalid');
            if (isset($item['excluded']) && !is_bool($item['excluded'])) throw new DomainException('workflow_item_invalid');
            foreach (['nisn'=>20,'email'=>254] as $key=>$max) if (isset($item[$key]) && (!is_string($item[$key]) || strlen($item[$key])>$max)) throw new DomainException('workflow_item_invalid');
            if (isset($item['exclusion_reason_present']) && !is_bool($item['exclusion_reason_present'])) throw new DomainException('workflow_item_invalid');
            $row = new Item;
            $row->forceFill($item + ['operation_id'=>$op->id])->save();
        }
    }
    private function audit(Operation $op, User $actor, string $event, ?string $previous, string $outcome): void
    {
        DB::table('academic_workflow_audits')->insert(['operation_id'=>$op->id,'actor_id'=>$actor->id,'kind'=>$op->kind,'event'=>$event,'previous_state'=>$previous,'next_state'=>$op->state,'outcome'=>$outcome,'student_refs'=>json_encode($op->items()->orderBy('student_ref')->pluck('student_ref')->all()),'metadata'=>json_encode(['version'=>$op->version,'item_count'=>$op->items()->count()]),'created_at'=>now()]);
    }
}
