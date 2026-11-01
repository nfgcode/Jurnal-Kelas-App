<?php
namespace App\AcademicWorkflows;

use App\Models\User;
use App\Support\AcademicOperation;
use Illuminate\Support\Facades\DB;
use DomainException;

final class Coordinator
{
    public function __construct(private WorkflowService $workflows) {}
    public function create(array $kinds, User $actor, ?string $sourceYear = null, ?string $targetYear = null): int
    {
        return AcademicOperation::run(function () use ($kinds, $actor, $sourceYear, $targetYear) {
            $this->workflows->authorize($actor);
            if (count($kinds) < 2 || count($kinds) > 3 || count(array_unique($kinds)) !== count($kinds) || array_diff($kinds, WorkflowService::KINDS)) {
                throw new DomainException('workflow_stages_invalid');
            }

            // Validasi periode jika disediakan
            if ($sourceYear !== null && ! \App\Models\TahunAjaran::whereKey($sourceYear)->exists()) {
                throw new DomainException('workflow_source_year_invalid');
            }
            if ($targetYear !== null && ! \App\Models\TahunAjaran::whereKey($targetYear)->exists()) {
                throw new DomainException('workflow_target_year_invalid');
            }
            if ($sourceYear !== null && $targetYear !== null && strcmp($targetYear, $sourceYear) <= 0) {
                throw new DomainException('workflow_period_order_invalid');
            }

            // Cegah duplikasi sesi aktif pada pasangan periode yang sama di bawah shared lock
            if ($sourceYear !== null && $targetYear !== null) {
                $hasActiveSession = DB::table('academic_workflow_coordinators as c')
                    ->join('academic_workflow_stages as s', 's.coordinator_id', '=', 'c.id')
                    ->leftJoin('academic_workflow_operations as o', 'o.id', '=', 's.operation_id')
                    ->where('c.source_year', $sourceYear)
                    ->where('c.target_year', $targetYear)
                    ->where(function ($q) {
                        $q->whereNull('s.operation_id')
                            ->orWhere('o.state', '!=', 'completed');
                    })
                    ->lockForUpdate()
                    ->exists();

                if ($hasActiveSession) {
                    throw new DomainException('workflow_coordinator_active_session_exists');
                }
            }

            $id = DB::table('academic_workflow_coordinators')->insertGetId([
                'actor_id' => $actor->id,
                'source_year' => $sourceYear,
                'target_year' => $targetYear,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (WorkflowService::KINDS as $position => $kind) {
                if (in_array($kind, $kinds, true)) {
                    DB::table('academic_workflow_stages')->insert([
                        'coordinator_id' => $id,
                        'position' => $position,
                        'kind' => $kind,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            \App\Support\AuditAdmin::catatKritis(
                aksi: 'workflow.coordinator.create',
                tipeEntitas: 'academic_workflow_coordinator',
                entitasId: (string) $id,
                deskripsi: 'Membuat koordinasi alur akademik.',
                metadata: [
                    'kinds' => array_values(array_intersect(WorkflowService::KINDS, $kinds)),
                    'source_year' => $sourceYear,
                    'target_year' => $targetYear,
                ],
                user: $actor,
            );

            return $id;
        });
    }
    public function attach(int $id, string $kind, int $operationId, User $actor): void
    {
        AcademicOperation::run(function () use ($id,$kind,$operationId,$actor) {
            $this->workflows->authorize($actor);
            $coord = DB::table('academic_workflow_coordinators')->where('id', $id)->lockForUpdate()->first();
            if (! $coord) {
                throw new DomainException('workflow_coordinator_missing');
            }

            $stage = DB::table('academic_workflow_stages')->where('coordinator_id', $id)->where('kind', $kind)->lockForUpdate()->first();
            $op = Operation::whereKey($operationId)->lockForUpdate()->firstOrFail();

            if (! $stage || $stage->operation_id !== null || $op->kind !== $kind || $op->state === 'completed') {
                throw new DomainException('workflow_stage_attachment_invalid');
            }

            // Validasi kecocokan periode koordinator dan operasi
            if ($coord->source_year !== null && $op->source_year !== null && $coord->source_year !== $op->source_year) {
                throw new DomainException('workflow_stage_attachment_invalid');
            }
            if ($coord->target_year !== null && $op->target_year !== null && $coord->target_year !== $op->target_year) {
                throw new DomainException('workflow_stage_attachment_invalid');
            }

            // Pastikan operasi belum pernah di-attach ke tahap coordinator mana pun (unique constraint check)
            if (DB::table('academic_workflow_stages')->where('operation_id', $operationId)->exists()) {
                throw new DomainException('workflow_stage_attachment_invalid');
            }

            DB::table('academic_workflow_stages')->where('id', $stage->id)->update(['operation_id' => $operationId, 'updated_at' => now()]);
            \App\Support\AuditAdmin::catatKritis(
                aksi: 'workflow.coordinator.attach', tipeEntitas: 'academic_workflow_coordinator',
                entitasId: (string) $id, deskripsi: 'Menghubungkan operasi ke tahap alur akademik.',
                metadata: ['stage_id' => $stage->id, 'kind' => $kind, 'previous_operation_id' => null, 'operation_id' => $operationId], user: $actor,
            );
        });
    }
    public function executeStage(int $id, string $kind, string $confirmation, User $actor): array
    {
        if (DB::transactionLevel() !== 0) throw new DomainException('workflow_top_level_transaction_required');
        // Do not wrap execution in an outer transaction: failed operation evidence
        // must remain durable independently of earlier completed stages.
        $operationId=AcademicOperation::run(function () use ($id,$kind,$actor) {
            $this->workflows->authorize($actor);
            $stages=$this->status($id,$actor);
            foreach ($stages as $stage) {
                if ($stage['state']==='completed') {
                    if ($stage['kind']===$kind) return (int)$stage['operation_id'];
                    continue;
                }
                if ($stage['kind']!==$kind || !$stage['operation_id']) throw new DomainException('workflow_stage_order');
                return (int)$stage['operation_id'];
            }
            throw new DomainException('workflow_stage_order');
        });
        return $this->workflows->execute($operationId,$confirmation,$actor);
    }
    public function status(int $id, User $actor): array
    {
        $this->workflows->authorize($actor);
        $coord = DB::table('academic_workflow_coordinators')->where('id', $id)->first();
        if (! $coord) {
            throw new DomainException('workflow_coordinator_missing');
        }

        return DB::table('academic_workflow_stages as s')
            ->leftJoin('academic_workflow_operations as o', 'o.id', '=', 's.operation_id')
            ->where('s.coordinator_id', $id)
            ->orderBy('s.position')
            ->get([
                's.kind',
                's.position',
                's.operation_id',
                'o.state',
                'o.source_year',
                'o.target_year',
                'o.error_code',
            ])
            ->map(function ($r) use ($coord) {
                $a = (array) $r;
                $a['coordinator_source_year'] = $coord->source_year;
                $a['coordinator_target_year'] = $coord->target_year;
                $a['state'] ??= 'not_started';
                return $a;
            })
            ->all();
    }

    /**
     * Memvalidasi apakah coordinator_id cocok dengan jenis workflow dan periode yang diminta.
     * Mengembalikan ID koordinator yang sah, atau null jika tidak cocok / fiktif.
     */
    public function resolveValidCoordinatorId(?int $coordinatorId, string $kind, ?string $sourceYear = null, ?string $targetYear = null): ?int
    {
        if (! $coordinatorId) {
            return null;
        }

        $session = DB::table('academic_workflow_coordinators as c')
            ->join('academic_workflow_stages as s', 's.coordinator_id', '=', 'c.id')
            ->where('c.id', $coordinatorId)
            ->where('s.kind', $kind)
            ->when($sourceYear, fn($q) => $q->where(fn($sub) => $sub->whereNull('c.source_year')->orWhere('c.source_year', $sourceYear)))
            ->when($targetYear, fn($q) => $q->where(fn($sub) => $sub->whereNull('c.target_year')->orWhere('c.target_year', $targetYear)))
            ->select('c.id')
            ->first();

        return $session ? (int) $session->id : null;
    }
}
