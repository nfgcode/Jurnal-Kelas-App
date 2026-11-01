# Phase 2B core handoff

## Stable API for integration

All symbols in `App\AcademicWorkflows`; actors `App\Models\User`. Integration must authenticate the passed persisted active admin (`Auth::login($actor)`); passing an ID/model alone is not authorization.

```php
$registry = new ExecutorRegistry; // empty by default
$registry->register(string $kind, Executor $executor): void;
$service = new WorkflowService(?ExecutorRegistry $registry = null);
$service->prepare(string $kind, ?string $source, ?string $target, array $items, array $metadata, User $actor): Operation;
$service->preview(int $id, User $actor): Preview;
$service->validate(int $id, User $actor): Operation;
$service->confirm(int $id, int $version, string $hash, User $actor): string;
$service->execute(int $id, string $confirmation, User $actor): array;
$service->revise(int $id, array $items, array $metadata, User $actor): Operation;
$service->retry(int $id, User $actor): Operation;
```

`Executor::execute(Operation $operation, Preview $preview): array` returns only nonnegative integer `processed`, `skipped`, `failed` counts. Caller owns transaction. No executor may commit, run DDL, external I/O or register after-commit effects. No real executor registered anywhere. For concurrency doubles, perform transactional domain writes/barrier waits inside this method. `Preview` readonly fields: version:int, hash:string, validation:string, items:array, snapshot:array. `Operation` Eloquent fields directly available, `items()` relationship.

Kinds `graduation`, `promotion`, `intake`. Item keys `student_ref` literal string max20, nullable `decision`, nullable `target_class_id`, boolean `excluded`. Metadata only boolean `official_verified`, bounded opaque `official_reference`. Decision `lulus`, `tidak_lulus`, `unverified`, `naik`, `tinggal_kelas`; promotion omitted decision resolves naik, graduation omitted resolves unverified. Graduation sample metadata `['official_verified'=>true,'official_reference'=>'OFFICIAL-001']`.

```php
$coordinator = new Coordinator(WorkflowService $workflows);
$coordinator->create(array $kinds, User $actor): int;
$coordinator->attach(int $id, string $kind, int $operationId, User $actor): void;
$coordinator->status(int $id, User $actor): array;
$coordinator->executeStage(int $id, string $kind, string $confirmation, User $actor): array;
```

At least two distinct kinds, canonical order graduation/promotion/intake. Status stage fields kind, position, operation_id, state (not_started if unattached), error_code. Completed stages replay without dispatching later stages. Failed stage retry requires service retry + validate + confirm. No outer coordinator transaction spanning independent operations.

## Schema

Only new migration `database/migrations/2026_11_01_000001_create_academic_workflows.php`. Five new tables `academic_workflow_operations`, `academic_workflow_items`, `academic_workflow_audits`, `academic_workflow_coordinators`, `academic_workflow_stages`; FK RESTRICT. No existing migration altered. See migration for complete columns. Operations source_year/target_year nullable FK tahun_ajaran.kode; actor_id/confirmed_by FK users; JSON source_metadata/snapshot/result; state/version/validation/snapshot_hash; confirmation_id/confirmed_version/confirmed_hash/expiry; validated/confirmed/started/completed/failed timestamps; sanitized error_code. Items unique(operation_id,student_ref), nullable target_class_id FK kelas; decisions/exclusion/validation/errors. Audits operation/actor/kind/event/previous_state/next_state/outcome/student_refs/allowlisted version+item_count metadata. Stages unique(coordinator_id,kind), unique(coordinator_id,position), unique operation_id.

## Real execution evidence

Command only `php tests/run.php tests/Feature/AcademicWorkflowFoundationTest.php` (SQLite sandbox). Final: **18 tests, 85 assertions PASS**. No full suite, MySQL, persistent migrations, install or commit.

Observed sequential RED/GREEN: missing WorkflowService (1 test RED, then 1/4 GREEN); missing preview (2 RED, 2/17 GREEN); missing validate (3 RED, 3/20 GREEN); missing ExecutorRegistry (4 RED, 4/25 GREEN); raw exception leak (5 RED, 5/32 GREEN); missing revision APIs (7 RED, 7/46 GREEN); missing Coordinator (11 RED, 11/59 GREEN); missing attach (13 RED, 13/67 GREEN); period date-storage mismatch (16 RED, 16/79 GREEN); direct item confirmation not invalidated (focused RED, 17/83 GREEN); final lock/auth scenario 18/85 GREEN. Some added coverage passed immediately; not every assertion has independently observed RED.

Covered pure SELECT preview; unknown history; standalone draft; default executors unavailable; completed replay after expiry/response retry; rollback+sanitized separate failure; audit insertion failure rolls back academic mutation; explicit retry/revision; quiet model completed guards; actor binding+expiry+period changes; intake duplicates/exclusions; graduation official decisions/retention prerequisite; promotion default/retention; coordinator minimum/order/partial failure/resume; legacy history+NISN snapshot invalidation; singleton first; inactive actor rejects completed replay.

## Verification boundaries / review cautions

- MySQL schema cycles, deterministic concurrent replay, master/legacy writer overlap: parent-owned, NOT RUN here. Use existing Phase2A8 disposable harness, separate DB connections and observed singleton waits. Execute starts via AcademicOperation::run then operation lock then locking source reads.
- Snapshot is deliberately conservative: all years/classes/departments plus selected students, NISN identity, accounts role/status, histories and alumni. Unrelated structural master edits may invalidate confirmation. No credentials, names, free notes copied.
- Existing KesiapanTransisi is not invoked: its combined/global checklist would incorrectly gate independent graduation/intake. Readiness adapter implements operation-specific year/history/lifecycle consistency. SiklusHidupSiswa::validasiBatch is mutating-lock-oriented and not called by pure preview; adapter mirrors its no-active-alumni/matching-history checks. Review this integration boundary before treating it as shared-validator reuse.
- Instance model guards include quiet saves; bulk query builder/raw SQL remains trusted internal boundary, not database-level immutable trigger protection. Item direct save invalidates confirmations; service revision is canonical audited revision path. Operation source/target currently fixed at preparation; create replacement draft to change period pair.
- Executor contract prohibits after-commit effects; completion replay simulates response retry after successful commit, not a real HTTP transport test.
- Durable failure separate transaction may itself fail (database/audit unavailable): sanitized workflow_failure_record_unavailable, not a false persisted-failure claim.
- Existing dirty baseline untouched; only new namespace/migration/test/docs created. git diff --check PASS, pre-existing CRLF warning in transisi view.
