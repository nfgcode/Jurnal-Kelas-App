# Phase 2A.8.4 — Academic master writer serialization

## Supported boundary

`AcademicOperation::run` opens a transaction and acquires existing singleton `academic_operation_lock.id=1` before callback queries. Missing singleton fails closed; never recreated lazily. Existing activation/lifecycle/import/transition use the same lock. `SerializesAcademicMaster` wraps instance `save`/`delete` (including `update`, `saveQuietly`, model `destroy`) before Eloquent mutation/deletion events. No global query-builder rewrite.

An outer writer transaction must acquire the singleton before its first dependency read; instance save wrapping cannot repair an already-established REPEATABLE READ snapshot. HTTP binding and FormRequest reads outside the writer transaction are advisory, not authoritative transaction snapshots. Year updates re-fetch the bound row after locking; class chair and department deletion checks now run after locking.

## Writer inventory

| Entry | Mutation | Coverage |
|---|---|---|
| Web/API KelasController store | class insert | PencatatanAkademik singleton before year/department/teacher/duplicate checks |
| Web KelasController update | class/year/grade/department and assignments | outer singleton, fresh class, chair check, instance update |
| API KelasController update | class/year/grade/department and assignments | instance update boundary; request existence/unique validation advisory; FK/unique constraints authoritative |
| Web/API KelasController destroy | class delete | instance boundary before historical relation guards |
| JurusanController store | department insert | PencatatanAkademik singleton before duplicate check |
| JurusanController update | department code/details/active; FK rename cascades | outer singleton, fresh locked row, instance update |
| JurusanController destroy | department delete | singleton before class/student dependency checks and delete |
| TahunAjaranController store/update | year insert/date bounds | singleton before overlap check; critical audit in same transaction; fresh row for update |
| KalenderTahunAjaran::siapkanBerikutnya; console/manual/scheduled callers | prepare inactive year | singleton before source/year lookup, overlap check, insert, critical audit |
| Kelas/Jurusan/TahunAjaran instance callers | save/delete | model concern, including quiet class QR-token repair; lifecycle domain validation not automatically added to arbitrary model calls |
| AktivasiTahunAjaran::aktifkan | bulk year active flags | existing outer singleton; unchanged |
| TransisiAkademik::siapkanKelasTujuan | firstOrCreate destination classes | existing outer singleton; file not edited |
| TransisiAkademik::eksekusi | transition dependencies/student/history | existing outer singleton; file not edited; parent/date agent owns lock-before-read audit |
| ImporPengguna::jalankan | class chair/homeroom bulk assignments | existing outer singleton before loop and account/student locks |
| SiklusHidupSiswa | clear class chairs | existing outer singleton |
| CadanganData::pulihkan | legacy broad table restore | remains disabled before parsing/mutation |

## Explicit residuals / no raw-SQL closure claim

- Direct SQL, query-builder bulk writes, `insert/upsert`, manually invoked stored procedures, DB console, seed/migration/import SQL bypass model concern. Supported service callers must own the boundary. No claim of database-wide serialization.
- `sp_tambah_kelas` and `sp_tambah_jurusan` contain their own `START TRANSACTION/COMMIT`. Calling them inside the PHP boundary would release the singleton through MySQL implicit commit. Supported PencatatanAkademik class/department writers now use existing portable PHP implementations on both SQLite/MySQL; stored procedures remain installed and explicit unsupported external bypasses, not silently claimed protected. Other subject/timetable procedures unchanged.
- Admin GuruController bulk `wali_kelas_nip` and Admin SiswaController bulk `ketua_nis` assignment are non-structural assignment writers outside this scoped boundary. They do not change year bounds, class/year/grade/department identity or department activity. They may invalidate the broad preview fingerprint; not claimed serialized. Parent should coordinate separately if all class-column writes must serialize.
- Arbitrary callers starting a transaction and reading mutable dependencies before instance save are not made snapshot-safe by nested lock acquisition. Use AcademicOperation at outermost entry. Direct model callers receive serialized mutation, not automatic readiness/overlap/lifecycle policy enforcement.
- SQL FK cascades caused by department rename execute under the participating rename transaction. Out-of-band parent deletes/cascades remain outside cooperative locking.

## Evidence

Safety prerequisite: `php tests/run.php tests/Feature/SafetyApplicationTest.php tests/Unit/SafetyFoundationTest.php`: PASS 6 tests / 25 assertions.

Observed REDs in AcademicMasterSerializationTest:
1. Direct year insert first query was `insert into tahun_ajaran`, not singleton: FAIL 1 test / 2 assertions.
2. Calendar preparation first query selected active year: FAIL 2 tests / 18 assertions.
3. Year overlap query preceded singleton: FAIL 3 tests / 26 assertions.

Focused GREEN after implementation: 45 tests / 226 assertions (master/calendar/department/procedure). Expanded focused regression: 119 tests / 443 assertions PASS (also lifecycle/transition/review guards). This is not a full-suite run. SQLite verifies query order, missing-lock rejection, rollback on audit failure, duplicate master conflict, historical deletion guard. It cannot prove MySQL row-lock waiting, REPEATABLE READ concurrency, or procedure/driver parity; parent owns disposable MySQL proof.

No Docker, active DB, install, commit, full suite, restore enablement, runner/integration edit, or TransisiAkademik.php edit performed.
