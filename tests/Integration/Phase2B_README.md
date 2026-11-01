# Phase 2B disposable MySQL integration

Run from repository root: `python tests/Integration/Phase2B_runner.py` (allow 600 seconds on this Windows host). No install, application writes, development DB connection or commit required. Existing local PHP/MySQL images only, pinned by image ID. Runner binds repository read-only; MySQL data lives in tmpfs on a random ownership-labelled internal network, without published ports or persistent volumes. Random credentials exist only in temporary env files, removed after use. Cleanup verifies exact owned containers/network disappear. Source SHA-256 hashes captured before/after.

## Final executed result

Artifact: `C:/Users/Tenma/AppData/Local/hermes/cache/scratch/phase2b-c6386b910224ddf3a7734c7888e610dc/results.json`

- MySQL 8.0.46; 59 migrations applied.
- Safety foundation: 6 tests / 25 assertions.
- Migration checks: 13 assertions. Phase2B down/up removes/recreates five tables, preserving hashes of populated legacy fixtures and all other legacy tables/views.
- Constraint checks: 38 assertions. Eleven workflow FKs: RESTRICT metadata, actual invalid-reference UPDATE rejection (1452), actual parent DELETE rejection (1451). Four unique constraints: actual duplicate rejection (1062).
- Workflow integration: 47 assertions. Total including safety: 123 assertions.
- Same confirmed operation: two independently bootstrapped authenticated active-admin worker connections simultaneously WAITING on singleton, exact blocking edges recorded; both return equivalent persisted result; one marker, one completion audit, durable completed state. Marker table deliberately lacks uniqueness so duplicate dispatch cannot hide behind a constraint.
- Competing identity snapshots: both confirmed beforehand, simultaneous singleton WAITING proof; one committed identity/marker/completion; loser rejects `workflow_confirmation_stale` before executor dispatch.
- Genuine `TahunAjaran::save()` writer: first observed WAITING, then holds singleton after save; workflow observed WAITING specifically on writer; committed change invalidates workflow, no marker.
- Coordinator: canonical order enforced, including direct `WorkflowService::execute()` bypass rejection with confirmation preserved and no dispatch; first stage's operation/marker persist when second stage's marker rolls back; durable separate failure audit; completed stage replay does not dispatch; explicit retry/validation/new confirmation resumes second stage only; first operation unchanged.
- Source hashes unchanged. Both containers and network removed, independently rechecked; no remaining Phase2B-labelled resources. `git diff --check -- tests/Integration` passed.

Earlier attempts retained scratch artifacts: first host command interrupted at 240 seconds, exact owned resources subsequently removed; two attempts caught concurrent core changes plus evolving required identity/date metadata; another exposed JSON object key-order mismatch in harness comparison (fixed to semantic map equality). These are not final success evidence.

Integration-only files: `Phase2B_runner.py`, `Phase2B_migrations.php`, `Phase2B_fixtures.php`, `Phase2B_constraints.php`, `Phase2B_double.php`, `Phase2B_worker.php`, `Phase2B_concurrency.php`, this README. Existing bootstrap reused unchanged. Parent owns application review/full suite.
