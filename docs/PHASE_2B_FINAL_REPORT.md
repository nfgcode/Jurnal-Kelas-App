# Fase 2B — Academic Workflow Foundation & Operation Orchestration Contracts

## A. Executive Summary

Verdict: **READY FOR PHASE 3**, sebagai fondasi pengembangan, bukan production-ready atau izin deployment.

Implementasi baru tersedia pada `app/AcademicWorkflows`; tidak menghubungkan executor produksi, menambah route/UI/importer, menjalankan workflow akademik baru, atau mengaktifkan restore. Registry executor default kosong dan fail-closed. Tiga jenis operasi didefinisikan melalui `graduation`, `promotion`, `intake`; coordinator memakai operasi yang sama, bukan business logic duplikat.

Verifikasi final: **623 Unit/Feature tests / 2891 assertions PASS** dalam 14 batch disjoint, 55 file unik. **MySQL 8.0.46 disposable: 59 migration, 123 assertions PASS**, termasuk migration down/up, constraints dan concurrency teramati. PHP lint 288 file PASS; diff check PASS. Tidak ada commit, push, deployment, migration database pengguna atau penghapusan file baseline.

Prasyarat diverifikasi dari source dan artefak Fase 2A/2A.8: safe runner, roster historis, lock singleton, lifecycle, restore guard tersedia. Bukti historis 588/2664, 58 migration, tujuh skenario MySQL sesuai laporan; angka tersebut tidak disamakan dengan eksekusi baru Fase 2B.

## B. Architecture & Domain Contracts

Komponen aktual:

| File dalam app/AcademicWorkflows | Peran |
|---|---|
| WorkflowService.php | prepare, preview, validate, confirm, execute, revise, retry; auth active admin, audit dan transaction boundary |
| Readiness.php | Pure SELECT evidence/period/identity/history validation dan fingerprint; reload dengan lock saat write boundary |
| Preview.php | Version/hash, validasi, item, snapshot, aggregate counts |
| Operation.php, Item.php | Persistensi Eloquent; guard mutation hasil selesai dan invalidasi konfirmasi saat item berubah |
| Executor.php | `execute(Operation, Preview): array`; hanya extension contract, bukan implementasi akademik produksi |
| ExecutorRegistry.php | Registrasi eksplisit; default kosong |
| Coordinator.php | create, attach, status/resume, executeStage; urutan tetap, tanpa automatic dispatch |

Actor harus user admin aktif yang benar-benar terautentikasi; bukan hanya object actor yang dikirim caller. Semua admin memiliki kewenangan sama sesuai produk.

Batas domain:
- Graduation: seluruh roster aktif XII sumber masuk snapshot; keputusan yang hilang tetap unverified. Wajib official_verified, official_reference, source_content_hash dan source_version; lulus/tidak_lulus/unverified eksplisit. Tidak lulus memerlukan XII tujuan yang sah. Tidak otomatis meluluskan seluruh siswa.
- Promotion: populasi X/XI sumber harus tercakup; default keputusan naik, pengecualian tinggal_kelas, destination mapping wajib sah. Tidak mencakup XII.
- Intake: bounded candidate NIS/NISN/email; validasi konflik terhadap database dan batch, kelas tujuan valid. Exclusion eksplisit dengan indikator alasan; tanpa password, pembuatan akun, atau importer produksi.
- Metadata mengikat effective_date dan identitas sumber. Tanggal dan history ambigu tidak direkonstruksi.

Integrasi membaca master TahunAjaran/Kelas/Jurusan, siswa/users, RiwayatKelasSiswa dan alumni serta memakai singleton yang sama dengan AktivasiTahunAjaran/SiklusHidupSiswa. Tidak memanggil combined engine legacy atau aktivasi otomatis. Readiness operasi tersendiri mengadaptasi invariant terkait KesiapanTransisi/lifecycle; bukan memanggil service monolitik yang akan menggabungkan kelulusan/kenaikan. Parity histori diuji untuk tanggal terbalik, overlap, dan future start. Penyatuan helper invariant lebih jauh dapat dilakukan bila ada kebutuhan nyata; tidak diklaim shared-validator invocation yang sebenarnya tidak ada.

Koreksi draft memakai revise; koreksi akademik pasca-completed bukan edit workflow lama. Kontrak executor/koreksi individual wajib beralasan dan melalui lifecycle saat implementasi Fase 3–5; tidak ada mutasi koreksi akademik baru pada fase ini.

## C. Database/Migration Changes

Satu migration baru: `database/migrations/2026_11_01_000001_create_academic_workflows.php`.

Tabel:
1. academic_workflow_operations: kind/state, source/target year, version, source metadata, snapshot/hash, validation, actor, confirmation identity/version/hash/expiry, timestamps, result/error code.
2. academic_workflow_items: operation, student_ref literal string, decision, target class, candidate NISN/email, exclusion flags, validation/errors. Unique operation/student_ref.
3. academic_workflow_audits: operation, actor, kind/event, previous/next state, outcome, student refs, structural metadata, timestamp.
4. academic_workflow_coordinators: actor dan timestamps.
5. academic_workflow_stages: coordinator, ordered position, kind, attached operation. Unique operation attachment, coordinator/kind, coordinator/position.

Student_ref sengaja bukan FK siswa karena kandidat intake belum ada; divalidasi domain untuk existing-student operations. Semua 11 FK workflow adalah RESTRICT; empat unique constraints diuji penolakannya. State/kind menggunakan string divalidasi service, bukan klaim DB CHECK semua status.

Migration up/down/up diuji MySQL dengan fixture legacy terisi. Hash legacy tables/views sebelum/sesudah tetap sama; tidak menginterpretasi ulang transisi lama. Down menghapus tabel workflow hanya pada disposable. Migration lama tidak diedit. Schema ini belum diterapkan pada database development/produksi.

## D. State Machine & Workflow Persistence

State operasi: draft, ready, confirmed, executing, completed, failed, needs_attention. `not_started` diproyeksikan coordinator untuk stage belum memiliki operasi; tidak menambah state operasi tak berguna.

- prepare: draft + items + audit, tanpa mutasi akademik.
- preview: SELECT-only; tidak mengubah operasi, audit atau tabel akademik.
- validate: menyimpan evidence/validation; valid menjadi ready, lainnya needs_attention.
- confirm: ready harus masih sama dengan version/hash current; actor/version/hash/expiry 15 menit disimpan.
- execute: revalidasi setelah singleton dan row lock; executing lalu completed dalam transaksi yang sama.
- failed: mutasi executor rollback; error generik dan audit kegagalan disimpan melalui transaksi baru.
- retry: eksplisit; invalidasi konfirmasi, version bertambah, kembali draft, harus validate/confirm ulang.
- revise: mengganti keputusan/metadata draft secara atomik, version bertambah dan konfirmasi lama gugur.
- completed: immutable melalui service/model canonical; replay mengembalikan hasil tersimpan setelah auth, bukan memanggil executor.

Coordinator memilih minimal dua operasi; urutan graduation → promotion → intake; stage tidak dipilih tidak dibuat. Status diambil dari operasi attached dan tersimpan lintas sesi. Setiap stage commit sendiri; gagal stage berikutnya tidak menghapus stage selesai. Menyelesaikan tahap tidak otomatis mengeksekusi tahap selanjutnya. Direct WorkflowService.execute juga memeriksa predecessor attached, sehingga tidak dapat melewati coordinator ordering.

Audit operation bersifat fail-closed dan atomik. Coordinator create/attach memakai critical AdminAuditLog dalam transaksi. Metadata allowlisted struktural, bukan password/token/raw SQL/private note. Alasan exclusion hanya indikator terstruktur pada fondasi; free-text reason/correction record tahap berikutnya tidak digandakan ke metadata audit.

## E. Idempotency & Concurrency Results

Eksekusi harus top-level transaction; caller dengan transaksi aktif ditolak sebelum attempt. Ini mencegah durable failed-state sebenarnya hanya savepoint atau sukses dikembalikan sebelum outer commit.

| Skenario | Hasil |
|---|---|
| Dua proses execute operasi confirmed sama | Keduanya menerima hasil sama; marker test-double bertambah sekali |
| Dua operasi bersaing atas identitas sama | Satu berhasil; lainnya workflow_confirmation_stale |
| Master writer mendahului execute | Workflow terbukti WAITING pada koneksi master; setelah commit, confirmation stale, tidak dispatch |
| Stage pertama selesai, kedua gagal | Stage pertama dan hasil tetap utuh; retry/validate/confirm stage kedua dapat resume |
| Direct execute stage berikutnya sebelum predecessor | Ditolak workflow_stage_order; confirmation tetap; zero dispatch |
| Kegagalan sebelum commit | Mutasi rollback; status failed/audit tersimpan aman |
| Kegagalan respons setelah commit | Completed/result durable dipakai; tidak replay mutasi |
| Confirmation lama setelah perubahan evidence/items | Ditolak/diinvalidasi |

Concurrency memakai worker PHP dan koneksi MySQL berbeda. Barrier dan performance_schema data_locks/data_lock_waits membuktikan WAITING serta exact blocking edge; bukan random sleep. Executor test-double menulis marker/fixture hanya dalam disposable, bukan graduation/promotion/intake handler produksi.

Run final database `phase2b_c6386b910224ddf3a7734c7888e610dc`, UUID `d198d673-d4ce-11f1-b03a-e296b83edea3`, MySQL 8.0.46. Internal network baru, tmpfs data, tanpa published ports/persistent volume; PHP read-only repo/root dan scratch sendiri. Credential acak run, tidak dicetak dan file dihapus. Container/network owned dihapus, ketiadaannya diperiksa ulang parent. Source manifest 212 file sebelum/sesudah sama dan cocok source parent saat verifikasi.

## F. Tests & Regression Results

| Pemeriksaan | Status dan bukti |
|---|---|
| Safe prerequisite parent | PASS: 6 tests / 25 assertions |
| Workflow focused final parent | PASS: 35 tests / 227 assertions |
| Seluruh Unit/Feature | PASS: 623 tests / 2891 assertions; 14 batch, 55 file, missing/overlap/extra=0 |
| MySQL safety foundation | PASS: 25 assertions |
| MySQL migration cycle | PASS: 13 assertions; total migration=59 |
| MySQL workflow constraints | PASS: 38 assertions |
| MySQL workflow/replay/coordinator/concurrency | PASS: 47 assertions |
| Total run MySQL | PASS: 123 assertions; bukan 123 PHPUnit tests |
| PHP syntax lint | PASS: 288 file |
| git diff --check | PASS; warning CRLF pada view transisi dirty existing |
| Legacy restore guards | PASS dalam suite; tetap disabled |
| Browser/UI/importer produksi/restore pipeline | NOT RUN, di luar scope |

Review defects diikuti RED/GREEN: nested transaction ownership, persisted item ownership bypass, coordinator audit, intake identity evidence, source-roster omission, contradictory dates, direct coordinator bypass. Beberapa assertion tambahan langsung PASS karena menguji invariant yang telah tersedia; tidak diklaim tiap assertion pernah RED.

Satu fixture Foundation sebelumnya mengharapkan intake attached berhasil meskipun promotion belum dimulai. Setelah ordering diperketat, assertion diperbarui untuk menuntut penolakan; sukses top-level tetap diuji dengan operasi standalone. Tidak melemahkan ordering.

Full suite memakai batch foreground karena timeout tool. Batch selesai dipertahankan, unfinished direkam ulang; agregat tidak menghitung duplikat. Artefak tahan lama di `docs/phase2b-evidence/`: summary.json, manifest.json, batch-01.json hingga batch-14.json, mysql-results.json. Source/hash/coverage diverifikasi sebelum penutupan.

## G. Legacy Compatibility

Semua 513 file baseline fase tetap ada; file baseline tidak perlu diubah untuk menambahkan fondasi. Transisi legacy masih tersedia, tidak dimigrasikan menjadi workflow baru, dan tabel history/jurnal/presensi/alumni tidak ditulis oleh preparation/preview/coordinator. Existing suite Fase 2A/2A.8 tetap PASS. Aktivasi tetap manual dan restore legacy tetap hard-disabled.

## H. Git Diff Summary

Branch main; HEAD tetap `ae5cbee9358bf1e9e21ed1fe092d57290703db54`. Awal 51 tracked modified / 86 untracked. Fondasi fase ini berupa file baru; seluruh dirty changes sebelumnya dipertahankan. Final count mencakup dokumentasi/evidence, dicatat dalam respons penutupan. Tidak ada commit/push/reset/clean/deploy atau dependency install.

File baru utama: namespace AcademicWorkflows (8 file), satu migration, AcademicWorkflowFoundationTest/ReadinessTest/ReviewRegressionTest, Phase2B integration harness, architecture/handoff/report/evidence. Rincian file dapat dilihat lewat git status; jangan menilai seluruh working-tree diff terhadap HEAD sebagai karya Fase 2B.

## I. Remaining Risks

- Registry executor sengaja kosong. Fase 3–5 harus menambahkan executor domain nyata, audit/correction reason records, sumber file resmi dan tests spesifik sebelum fitur dapat digunakan pengguna.
- Belum ada HTTP/UI workflow; concurrency adalah service-level multi-process, bukan browser load test.
- Lock dan immutable model kooperatif. Raw SQL/bulk writes dapat melewati domain guard; FK/unique tetap menjaga relasi. Jangan menambah caller yang melakukan dependency read sebelum singleton atau memakai self-committing procedure.
- Snapshot konservatif mencakup masters dan identitas lintas populasi; aman tetapi dapat menolak konfirmasi akibat perubahan yang tidak relevan. Performance pada skala sekolah besar belum disertifikasi.
- Full-source graduation/promotion dan no-late/contiguous policies sengaja ketat; perubahan kebijakan perlu keputusan eksplisit, bukan backfill spekulatif.
- Koreksi pascakelulusan, importer, modal/UI, backup/restore tetap fase terpisah. State/history yang diketahui tidak diganti dengan dugaan.
- Migration pada database berisi data belum dijalankan. Deployment membutuhkan izin/preflight/backup/recovery terpisah; hasil disposable tidak mengizinkan rollback data pengguna.

## J. Final Verdict

**READY FOR PHASE 3** untuk implementasi kelulusan berikutnya setelah review pengguna. Kontrak tiga operasi, durable persistence, coordinator resume, audit, idempotency, lock revalidation, stale confirmation, legacy compatibility dan MySQL disposable telah diuji. Tidak ada handler akademik produksi atau Fase 3 yang dimulai otomatis.
