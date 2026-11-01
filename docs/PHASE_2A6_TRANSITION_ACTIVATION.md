# 2A.6 — validation transisi dan aktivasi

## Kontrak

- Pengecualian bertingkat divalidasi di controller dan service. NIS harus anggota roster aktif tahun asal; aksi harus sesuai tingkat; struktur, catatan, ID kelas harus valid. Kelas tujuan harus sesuai tahun, tingkat, jurusan; jurusan nonaktif ditolak. Tidak ada kolom status kelas pada skema saat ini.
- `validasiKeputusan` dipakai preview dan execute, menormalkan keputusan default hanya untuk entri yang memang tidak diberikan oleh pemanggil service legacy. Entri eksplisit invalid tidak pernah memakai fallback. HTTP wajib seluruh keputusan roster; roster kosong ditolak.
- GET legacy tetap editor keputusan, bukan janji keputusan immutable. Snapshot terenkripsi/authenticated v1 berlaku satu jam; mengikat konteks periode, fingerprint roster/kelas/riwayat aktif/jurusan, versi kebijakan editable. Tanggal kelulusan, nonaktifkan akun, aksi tetap editable dan divalidasi ulang; snapshot tidak mengklaim menyegel nilai opsi yang belum dipilih. HTTP wajib snapshot, service legacy boleh tanpa snapshot tetapi tetap semua validasi, transaksi, lock dan idempotensi.
- Execute mengunci singleton DB, periode, kelas, siswa; revalidasi snapshot dan periode di transaksi. Semua tujuan dari sumber yang sudah selesai/diproses ditolak, bukan hanya pasangan sama. Validasi lifecycle batch 2A.5 tetap dipanggil.
- Bulk `aktifkan_tahun_tujuan=true` ditolak. UI menunjuk endpoint aktivasi Tahun Ajaran khusus.
- Aktivasi service transaksi: sumber aktif jika ada harus lebih lama, tujuan nonaktif format valid dan tanggal lengkap; minimal satu siswa aktif, seluruh siswa aktif wajib punya penempatan kelas tujuan valid. Tidak ada kebijakan aktivasi sekolah kosong. Tidak wajib ada sumber aktif untuk bootstrap sekolah yang sudah memiliki penempatan nyata.
- Audit aktivasi kritis atomik. Singleton sama dengan transisi. Generated nullable unique `active_slot` menolak multi-active bahkan raw SQL; saved hook lama yang memperbaiki diam-diam dihapus. `TahunAjaran::aktifkan()` mendelegasikan service readiness, bukan bypass.
- Migrasi additive diuji up/down di SQLite sandbox saja. Tidak diterapkan ke persistent DB.

## TDD / hasil aktual

Semua dari root repo, PHP lokal, runner sandbox SQLite:

1. `php tests/run.php tests/Unit/SafetyFoundationTest.php tests/Feature/SafetyApplicationTest.php`: PASS 6 tests / 25 assertions sebelum mutation.
2. `php tests/run.php tests/Feature/TransitionValidationActivationTest.php`: RED aksi typo 1 failed; GREEN 1/1. RED validasi keputusan 5 failed dari 8; GREEN bersama legacy 15 tests / 82 assertions.
3. RED snapshot/aktivasi/invariant 6 failed dari 14. GREEN regresi terkait 48 tests / 216 assertions setelah memperbarui kontrak fixture legacy.
4. RED historical-source preparation dan edited-preview 2 failed dari 17; GREEN regresi 51 / 219.
5. RED opsi service invalid 1 failed dari 23; diperbaiki validasi boolean/date/shape.
6. RED null destination 1 failed dari 26; diperbaiki pemeriksaan keberadaan key, bukan isset.
7. Final command:

```sh
php tests/run.php tests/Feature/TransitionValidationActivationTest.php tests/Feature/Phase2AcademicTransitionTest.php tests/Feature/AcademicYearCalendarTest.php tests/Feature/TransitionPeriodWorkflowTest.php tests/Feature/Phase4AcademicTransitionChecklistTest.php tests/Feature/StudentLifecycleIntegrityTest.php
```

PASS **80 tests / 314 assertions**, exit 0. Ini focused+related, bukan full suite. New focused file mencakup 27 tests, termasuk data-provider, snapshot stale/tamper/expiry, replay beda tujuan, placement, audit rollback dan migration up/down. Pint scope 7 PHP files sukses. `git diff --check` sukses sebelum formatting terakhir; final dijalankan ulang terpisah.

## Perubahan kontrak test lama

- `Phase2AcademicTransitionTest`: activation memerlukan placement nyata; POST transition mengambil snapshot GET dan keputusan eksplisit, tidak meminta aktivasi implisit.
- `AcademicYearCalendarTest`: activation fixture menyediakan siswa dengan penempatan tujuan; confirmation saja bukan readiness.
- `StudentLifecycleIntegrityTest`: dua fixture future year dibuat nonaktif sebelum helper kelas, sehingga tidak melanggar unique active invariant. Assertions lifecycle tidak dilemahkan.

## Scope / batas

Baseline sudah dirty: user awal 11 modified + 44 untracked (konteks parent), ditambah 2A.1–5 dan perubahan agent terdahulu. Tidak ada reset, checkout, commit, install, deploy atau migrasi persistent. Task ini menyentuh controller transisi/tahun, model tahun, support transisi, preview Blade, tiga test existing; menambah service aktivasi, migration, focused test dan dokumen ini. Tidak mengubah source lifecycle service, checklist, redesign, kartu, Excel atau workflow terintegrasi.

MySQL disposable tidak tersedia: migration MySQL, locking/race concurrent MySQL **NOT RUN**. SQLite membuktikan unique invariant dan serial replay, bukan kemampuan lockForUpdate MySQL. Full suite milik parent, **NOT RUN**. Writer SQL langsung masih bisa mengubah `aktif` di luar endpoint; unique invariant membatasi cardinality, bukan membuktikan readiness untuk SQL administrator. Service activation merupakan boundary aplikasi yang diubah di fase ini.
