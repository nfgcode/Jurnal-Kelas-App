# Fase 2A.8 — MySQL Verification, Concurrency & Stabilization

## A. Executive Summary

**Verdict: READY FOR PHASE 2B**, terbatas pada fondasi dan jalur aplikasi yang diverifikasi; bukan persetujuan deployment atau sertifikasi seluruh kemungkinan raw SQL. Fase 2B tidak dimulai.

- PASS: seluruh Unit/Feature, 588 tests / 2664 assertions, 13 batch, 52 file unik tanpa file terlewat atau overlap.
- PASS: MySQL 8.0.46 disposable, 58 migration dari kosong dan 12 stored routines terpasang.
- PASS: dua migration baru down/up; fixture historis, FK, generated column, computed value dan uniqueness diverifikasi sesudah siklus.
- PASS: concurrency multi-process menggunakan koneksi MySQL berbeda dan barrier. Overlap dibuktikan melalui performance_schema data_locks/data_lock_waits, bukan sleep sebagai satu-satunya bukti.
- PASS: penempatan efektif, retensi/tunda_lulus sampai aktivasi, preview read-only, registry clipboard, restore tetap diblokir.

Klaim laporan sebelumnya merupakan baseline historis, bukan hasil fase ini. Implementasi safe runner, roster historis, deletion guards, NIS string, lifecycle dan restore disablement ditemukan pada source aktual. Blokir tanggal dan ketidaklengkapan serialization pada laporan sebelumnya terbukti; fase ini menanganinya. Review juga menemukan status `tinggal_kelas` ditolak aktivasi serta overlap yang tersamarkan `max(today,start)`; keduanya diperbaiki dan diuji.

## B. Environment Verification

Host Windows; Docker 29.3.1; Compose v5.1.1. Compose development memiliki container jurnal-kelas-mysql dengan port 3306 dan persistent mysql-data; tidak dipakai untuk migration, rollback atau pengujian mutasi.

Run final:
- Database `phase2a8_e0730b93b9d89046f330fa144fc80a5b`.
- MySQL version 8.0.46; server UUID `56930189-d4ab-11f1-aefe-fa1add2fb797`.
- Network baru internal dengan label run ownership; tidak bergabung network development.
- MySQL data di tmpfs `/var/lib/mysql`; tanpa persistent volume atau published port.
- PHP runner terpisah memakai image existing PHP 8.3.32, repository mount read-only, root filesystem read-only, scratch/storage/cache terisolasi.
- Credentials acak khusus run; bukan credential pengguna. File credential ephemeral dihapus saat cleanup.
- Safety foundation dijalankan dahulu: 6 tests / 25 assertions PASS sebelum mutation MySQL.
- Kedua container disposable dan network dihapus berdasarkan ownership tepat; hasil cleanup dan ketiadaan resource berlabel diperiksa ulang parent.

Reusable entry: `tests/Integration/Phase2A8_runner.py`; penggunaan dan batas keamanan di `tests/Integration/Phase2A8_README.md`. Guard legacy `mysql_guard.php` tetap tertutup. Tidak ada pelonggaran safe runner SQLite.

## C. Migration Results

PASS: 58 migration dari database kosong; 12 functions/procedures ditemukan. Tidak mengedit migration lama atau menjalankan migration pada database persisten.

| Pemeriksaan | Hasil |
|---|---|
| `2026_10_30_000001_restrict_historical_master_deletion` up/down/up | PASS |
| `2026_10_31_000001_guard_academic_activation` up/down/up | PASS |
| Enam FK historis, raw delete master ditolak | PASS |
| Fixture pada sepuluh tabel tetap utuh melalui siklus | PASS |
| `riwayat_kelas_siswa.siswa_nis_aktif` virtual generated | PASS |
| `tahun_ajaran.active_slot` virtual generated | PASS |
| Duplikasi membership aktif ditolak database | PASS |
| Dua tahun aktif ditolak database | PASS |
| Generated expression, computed values dan unique indexes setelah remigrate | PASS |

Constraint harness final: 37 assertions; migration harness: 1 assertion. Pengujian dilakukan pada MySQL nyata, bukan ekstrapolasi SQLite. MySQL DDL tetap memiliki implicit commit; keberhasilan disposable tidak mengizinkan rollback sembrono di database berisi data pengguna.

## D. Concurrency Results

Harness meluncurkan worker PHP terpisah dengan koneksi DB berbeda. File barrier mengatur kesiapan; coordinator memegang lock lalu memeriksa WAITING pada singleton. Skenario master struktural mengatur urutan deterministik sehingga activation menunggu koneksi master yang belum commit; setelah master commit activation harus membaca keadaan baru dan menolak.

| Skenario | Hasil nyata | Status |
|---|---|---|
| Dua eksekusi snapshot transisi sama | Satu berhasil; lainnya DomainException snapshot kedaluwarsa; hanya satu transisi selesai | PASS |
| Aktivasi future-effective sebelum mulai | Ditolak; tidak memajukan periode diam-diam | PASS |
| Dua aktivasi tujuan berbeda | Hanya target dengan penempatan valid berhasil; tepat satu tahun aktif | PASS |
| Edit mulai periode tujuan ke 2027-07-02 mendahului aktivasi | Baseline aktivasi sah; setelah menunggu master commit, aktivasi ditolak karena membership di luar periode | PASS |
| Edit tahun kelas tujuan ke 2028/2029 mendahului aktivasi | Baseline sah; aktivasi membaca perubahan lalu menolak | PASS |
| Nonaktifkan jurusan mendahului aktivasi | Baseline sah; aktivasi membaca perubahan lalu menolak | PASS |
| Edit kapasitas kelas bertumpang tindih aktivasi | Kedua proses terbukti menunggu lock; invariants tetap sah | PASS |

Akhir run: tahun aktif hanya 2027/2028; completed_transitions=1; active_memberships=1; kelas siswa cocok kelas tujuan. Harness concurrency final: 69 assertions. Total 132 assertions pada run MySQL mencakup 25 safety foundation, 1 migration, 37 constraint, 69 concurrency; bukan 132 PHPUnit tests.

Koordinasi didukung melalui AcademicOperation, instance save/delete master, outer writer kalender/controller, lifecycle, importer dan transisi. Class/department creation memakai implementasi PHP portabel di MySQL juga; prosedur legacy memiliki START/COMMIT internal yang bisa melepas lock caller. Inventory lengkap: `docs/PHASE_2A8_4_MASTER_SERIALIZATION.md`.

Batas bukti: concurrency berjalan pada service/domain entry points dalam proses nyata, bukan dua browser/HTTP request end-to-end. Unit/Feature mencakup HTTP validation/response. Tidak diklaim semua urutan scheduler atau semua writer SQL eksternal aman.

## E. Historical Membership Results

Source utama `app/Support/TransisiAkademik.php`:
- Planned promotion/retention memakai tanggal mulai periode tujuan yang eksplisit, bukan tanggal eksekusi dini.
- Membership asal berakhir sehari sebelum tujuan mulai karena resolver memakai batas akhir inklusif.
- Periode harus lengkap, berurutan dan contiguous; gap/overlap/late execution ditolak terpisah.
- Exactly one source membership konsisten dan bertanggal diperlukan; bukti kosong/bertentangan/overlap lama ditolak, tidak dibackfill.
- Kelulusan/keluar menutup pada tanggal sah dalam periode sumber.
- Preview memakai pure loaded-evidence validator tanpa write lock; eksekusi reload dan revalidasi setelah lock.
- Aktivasi menerima membership terbuka `aktif` atau `tinggal_kelas`, tetapi kelas/tahun/tingkat/tanggal tetap wajib sah. Retention dan tunda_lulus diuji sampai aktivasi.
- Membership future-effective belum boleh mengaktifkan periode sebelum tanggal mulai. Late transition sengaja fail-closed, bukan diberi tanggal retrospektif rekaan.

Tidak ada rekonstruksi data historis atau backfill pada database pengguna. Data lama ambigu memerlukan verifikasi administratif. Planned transition langsung memperbarui kelas current siswa, sementara roster jurnal tetap memakai tanggal membership; koreksi individual sebelum future start dapat ditolak oleh guard yang disengaja.

## F. Regression Results

| Pemeriksaan | Hasil |
|---|---|
| Seluruh Unit/Feature via `php tests/run.php` | PASS: 588 tests, 2664 assertions |
| Cakupan manifest | PASS: 52 file unik, 13 batch, missing=0, overlap=0 |
| Focused parent date/review/activation | PASS: 58 tests, 451 assertions; tidak ditambah ke total unik |
| Ikon clipboard + safety parent | PASS: 11 tests, 37 assertions |
| PHP syntax lint app/config/migrations/routes/tests/bootstrap | PASS: 269 file, 0 gagal |
| MySQL migration/constraint/concurrency | PASS: 132 assertions termasuk safety |
| git diff --check | PASS; warning CRLF view transisi existing |
| Restore disabled HTTP/service regressions | PASS dalam suite |
| Restore produksi/staging pipeline | NOT RUN; di luar fase dan tetap dinonaktifkan |
| Browser/UI redesign/build | NOT RUN; tidak ada redesign |

Regresi ikon terlebih dahulu RED (5 tests, 1 FAIL: clipboard), kemudian registry ditambah path SVG Bootstrap yang sudah tersedia; test tidak dilemahkan. Perubahan tanggal/master/review mempunyai RED/GREEN pada regression masing-masing. Initial combined regression timeout tidak dihitung; batch yang tidak memiliki output direkam ulang. Source transisi/aktivasi final digunakan oleh batch terdampak dan run MySQL final; manifest source hash MySQL before/after sama.

Bukti tahan lama, tanpa credential:
- `docs/phase2a8-evidence/regression-summary.json`
- `docs/phase2a8-evidence/regression-outputs.json`
- `docs/phase2a8-evidence/regression-manifest.json`
- `docs/phase2a8-evidence/mysql-results.json`

## G. Source Changes

| Komponen | File | Alasan |
|---|---|---|
| Shared serialization | app/Support/AcademicOperation.php; app/Models/Concerns/SerializesAcademicMaster.php | Singleton sebelum mutation/dependency read |
| Master models | app/Models/Kelas.php; Jurusan.php; TahunAjaran.php | Instance save/delete termasuk quiet save |
| Master writers | app/Support/PencatatanAkademik.php; app/Services/KalenderTahunAjaran.php | Hindari self-commit procedure, kalender serialized |
| Controller writer | app/Http/Controllers/KelasController.php; Api/KelasController.php; Admin/JurusanController.php; Admin/TahunAjaranController.php | Fresh reads/checks dalam transaction boundary |
| Effective dates/preview | app/Support/TransisiAkademik.php | Periode dan evidence konsisten, read-only preview |
| Activation | app/Services/AktivasiTahunAjaran.php | Membership tinggal_kelas sah |
| Icon | app/Support/Ikon.php | Registry clipboard minimal |
| Regression baru | tests/Feature/AcademicMasterSerializationTest.php; TransitionEffectiveMembershipDatesTest.php; TransitionReviewRegressionTest.php | Lock order, tanggal, preview, retention activation |
| Fixture existing | Phase2AcademicTransitionTest.php; Phase2AReviewGuardsTest.php; TransitionValidationActivationTest.php; Phase4AcademicTransitionChecklistTest.php | Membership/periode terverifikasi, assertion perilaku dipertahankan |
| MySQL reusable | tests/Integration/Phase2A8_* | Provision/guard/migration/constraints/barrier/concurrency/cleanup |

Git main, HEAD tetap `ae5cbee9358bf1e9e21ed1fe092d57290703db54`. Baseline fase 45 modified / 68 untracked; setelah kode dan harness 51 modified / 81 untracked, sebelum laporan/evidence baru. Hash baseline 495 file tersedia di scratch; tidak ada file baseline hilang. Angka total dirty mencakup pekerjaan pengguna/fase sebelumnya, bukan seluruhnya perubahan fase ini. Tidak ada commit/push/deploy/reset/clean, tidak ada migration persistent. Final count sesudah dokumentasi dicatat dalam respons penutupan.

## H. Remaining Risks

1. Serialization kooperatif: direct SQL, query-builder bulk write di luar domain, explicit external procedure calls, dan outer transaction yang telah membaca sebelum lock tidak otomatis aman. Constraint FK/unique tetap berlaku. Jangan menambah caller tanpa boundary yang sama.
2. Assignment wali/ketua nonstruktural tertentu tetap di luar scoped master structural boundary; bukan klaim semua kolom kelas terserialisasi.
3. Bukti overlap adalah real multi-process service-level, bukan HTTP load/performance/failure-injection certification. Belum diuji deadlock setiap kombinasi skala sekolah besar.
4. Policy contiguous/no-late intentionally ketat; sekolah dengan gap kalender atau koreksi terlambat membutuhkan keputusan domain eksplisit di fase berikutnya, bukan bypass/backfill otomatis.
5. Restore pipeline tetap tanggung jawab Fase 8. Backup consistent snapshot, SQL.GZ/retensi bulanan bukan bagian fase ini.
6. Migration belum diterapkan di development/production. Deployment kelak membutuhkan izin, preflight data, backup terverifikasi dan prosedur recovery terpisah. Readiness fase bukan izin deploy.

## I. Final Verdict

**READY FOR PHASE 2B** untuk pengembangan berikutnya: isolation, MySQL up/down, constraints, overlap kritis, writer struktural yang didukung, tanggal sah, icon, regression dan restore fail-closed telah dibuktikan. Residual SQL eksternal dan operasional dijelaskan, bukan disembunyikan sebagai PASS. Fase 2B tidak dijalankan otomatis; tunggu review pengguna.
