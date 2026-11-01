# 2A.5 — shared student lifecycle integrity

## Boundary / existing workflows

- `app/Support/SiklusHidupSiswa.php`: individual corrections lock the student, memberships and alumni inside a transaction. Class/status changes require `alasan`, close membership today, open membership today only for active students, record critical before/after audit. Future starts, mismatched active class, unsafe ungraduation and existing alumni conflicts are rejected. Historical rows/alumni are never deleted to repair state. Reasons stay in domain membership/alumni notes; audit stores a presence indicator, not free text.
- `SiswaController` retains the existing form/action. A single reason field is added. Profile updates retain lifecycle, account state and password; nonblank password remains an explicit password change. Graduation creates alumni using verified existing last class and its dated period, not a guessed entry cohort. Missing period dates reject graduation.
- `PendaftaranPengguna::siswa()` uses its existing portable registration implementation on both engines, inside the membership transaction. The old `sp_tambah_siswa` owns START TRANSACTION/COMMIT and cannot safely nest inside the lifecycle transaction. Teacher registration remains unchanged. No stored procedure/schema migration is applied by this change.
- `ImporPengguna`: NIS/NIP first, never email as identity; preview and write independently reject role/identity/email/pointer conflicts. Existing students permit profile only; class/status change is rejected even with a supplied reason. Existing alumni can update profile using blank status/class. Blank password preserves existing hash, fallback initializes new accounts only, explicitly populated Password still changes password. New active students gain membership today. Alphanumeric/leading-zero string NIS remain literal.
- API inventory: `routes/api.php` has no student-register create/update endpoint. `/api/users` only manages accounts; it does not write student class/status. No new endpoint/workflow added.
- `TransisiAkademik` remains the existing bulk lifecycle writer: student locks, shared batch consistency validation, close/open memberships, alumni inserts and existing critical audit stay in one transaction. No per-student correction loop or new UI. Existing alumni now reject rather than being silently skipped by `insertOrIgnore`. Batch dates/options retain legacy semantics; period-policy redesign is outside 2A.5.
- Backfill maps nonactive to supported membership enum `pindah`, not invalid `keluar`; unknown historical start remains null.

## Verification

Only `php tests/run.php tests/...` with owned scratch and SQLite `:memory:`. New regression file: `tests/Feature/StudentLifecycleIntegrityTest.php`.

Observed RED before fixes: missing reason rejection; unchanged membership after correction; missing alumni; future membership closed before start; critical audit namespace error; fallback overwrites password; cross-role account reassignment reaches SQL constraint; generic import academic mutation accepted; identity-first preview rejected legitimate email/profile update; missing UI reason; backfill CHECK enum violation; future destination accepted; existing alumni silently ignored by transition; lost nonactivation reason; service leaves access active; profile reactivates pending account; graduation accepts undated period; alumni profile preview rejects blank class.

Related suite includes ImporPenggunaTest, AdminSectionTest, ProsedurAkademikTest, Phase1DatabaseIntegrityAndAuditTest, Phase2AcademicTransitionTest, Phase3LifecycleAndRetentionTest, Phase4AcademicTransitionChecklistTest, Phase4ExcelImportAndAiAssistanceTest, TransitionPeriodWorkflowTest, Phase2AHistoricalIntegrityTest. Final verified result: 144 tests, 612 assertions GREEN (including 6 safety tests). Focus-only rerun: 19 tests, 74 assertions GREEN. `git diff --check` exits 0; pre-existing CRLF advisory for transition view only.

MySQL NOT RUN: no dedicated disposable MySQL verified. SQLite verifies behavior/rollback, not production pessimistic locking or MySQL collation/concurrency. No active DB, commit, install or deploy.
