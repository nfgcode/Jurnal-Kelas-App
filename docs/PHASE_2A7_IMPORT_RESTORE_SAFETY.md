# Fase 2A.7 — existing import/restore guards

## Safety contract

- Legacy restore disabled unconditionally: HTTP 503 before validation, file access, gzip decoding, JSON parsing, audit writes or restore-service calls. Direct `CadanganData::pulihkan()` throws before Schema/DB access, regardless of environment/configuration. No environment opt-in. Removed legacy write helper. Ordinary authentication middleware can still update `users.last_active_at`; this is not restore work.
- No staging/complete restore pipeline implemented. Deferred to Fase 7–8: isolated staging, format/schema/dependency validation, identity/lifecycle reconciliation, transaction/trigger/FK verification, audited cutover, recovery proof. Existing JSON.GZ is sensitive archival data, NOT SQL and NOT a currently supported restoration route. No rollback guarantee displayed.
- Export HTTP requires nonempty valid distinct `tabel[]` or explicit `semua=1`, never both. Empty/unknown/mixed selections rejected. Service `null` explicitly means all; `[]`/unknown values rejected before file creation. Downloads private/no-store/nosniff; readable XLSX continues omitting password/token columns. JSON deliberately retains credentials/token hashes for archival fidelity: private admin download only, never public storage.
- Import default password removed from request before validation can flash input. Password field masked, never repopulated. Preview excludes password and internal password value. Session stores authenticated encrypted preview metadata, not plaintext password: owner, session-held random binding, six-hour expiry, SHA-256 file hash, immutable kind/update/password options. Confirmation ignores submitted option changes, consumes metadata, verifies ownership/hash/expiry, atomically claims private local file using rename before parsing/writing. A failed confirmation requires upload again. Existing identity/lifecycle validation remains unchanged.
- Parser/file/write exceptions use generic UI/audit messages; raw exception, SQL, bindings, payload or passwords never reported by these handlers. Existing per-row domain validation remains visible. Success audit remains outside import transaction; no false rollback claim if a later audit fails.

## Resource ceilings

Reader enforces 10 MiB source, 128 ZIP parts, 4 MiB per inflated part, 16 MiB aggregate, 100:1 compression ratio (all entries checked before getFromName), 5,000 physical rows, 64 columns, 100,000 worksheet cells/shared strings, 32 KiB cell/shared-string value. Excess rows rejected, not truncated. DTD/entity declarations rejected; libxml NONET/internal errors prevent parser diagnostics leaking. CSV source is bounded before parsing; row/column/cell limits apply. XML parsing remains bounded SimpleXML, not a new streaming parser. The 4 MiB XML ceiling bounds tree parsing before cell checks. No gzip parser reachable: gzip restore blocked outright, including malformed/expansion payloads.

Private import files may contain user-supplied row passwords until consumed/expired cleanup; default password only encrypted in session. Cleanup occurs on existing import page/preview access, not a new scheduler. Local filesystem is required for atomic file claim. APP_KEY/server-side session confidentiality remain deployment prerequisites; no deployment/configuration changed.

## Verification

Only `php tests/run.php tests/...` used: owned scratch, SQLite `:memory:`. No active database, Artisan, migration command, restore execution, install, commit or deploy.

Observed RED: restore controller reached validation; service reached Schema; extreme column accepted; ZIP ratio/part count/DTD and CSV excess accepted; password hidden input/flashed plaintext; confirmation trusted browser/default and unowned file; empty export broadened to all; backup response Cache-Control was public. GREEN checks exercise the revised contracts.

Focused/related command (final execution result recorded by parent-facing report):

```sh
php tests/run.php tests/Feature/ImportRestoreSafetyTest.php tests/Unit/XlsxReaderSafetyTest.php tests/Feature/CadanganTest.php tests/Feature/JurusanTest.php tests/Feature/Phase1DatabaseIntegrityAndAuditTest.php tests/Feature/Phase4ExcelImportAndAiAssistanceTest.php tests/Feature/ImporPenggunaTest.php tests/Feature/StudentLifecycleIntegrityTest.php tests/Feature/TransitionValidationActivationTest.php tests/Feature/TransitionPeriodWorkflowTest.php
```

Existing restore-success tests updated to explicit disabled/no-mutation expectations, not a test-only unsafe bypass. MySQL integration remains hardblocked/unexecuted. Parent runs full suite separately.
