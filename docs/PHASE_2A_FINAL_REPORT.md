# Laporan Fase 2A — Integritas Data dan Safe Testing

## A. Executive Summary

Verdict: READY WITH BLOCKERS. Implementasi 2A.1–2A.7 tersedia dan diverifikasi pada PHP lokal 8.4.0, SQLite :memory:, filesystem sandbox unik melalui `php tests/run.php`. Tidak ada migration pada database persisten, restore database aktif, instalasi, commit, push, atau deploy. Docker PHP 8.3.32 digunakan untuk pemeriksaan safety foundation, bukan sertifikasi integrasi MySQL.

Seluruh suite Unit/Feature dijalankan dalam batch foreground karena pemanggilan tunggal melewati timeout tool. Dua percobaan background gagal `stdin is not a tty`; tidak dihitung PASS. Hasil akhir per batch yang relevan: 552 tests, 551 PASS, 1 FAIL; 2228 assertions. Satu kegagalan existing HEAD: `Tests\Feature\IkonTest::test_every_icon_the_app_renders_exists`, ikon `clipboard` belum terdaftar. Tidak diperbaiki karena di luar cakupan integritas. Ini bukan full-suite GREEN.

## B. Subphase Results

| Subfase | Status | Komponen utama | Verifikasi |
|---|---|---|---|
| 2A.1 | PASS SQLite/non-DB | tests/run.php, bootstrap.php, TestCase.php, phpunit.xml, composer scripts, MySQL guard | Safety 6 tests / 25 assertions; guard sebelum boot, sandbox storage/cache/temp, cleanup milik run |
| 2A.2 | PASS SQLite | RosterPresensi, controller web/API, SimpanPresensiJurnal | Membership bertanggal dan roster tersimpan; histori tidak lengkap tidak direkonstruksi; koreksi parsial mempertahankan baris sah |
| 2A.3 | PASS SQLite; MySQL NOT RUN | MelindungiRiwayat, model master, migration RESTRICT | Guard model dan FK; deliberate journal deletion tetap dipertahankan |
| 2A.4 | PASS SQLite | Presensi web/API/support | NIS string max20, leading zero, alfanumerik legacy, JSON/XLSX |
| 2A.5 | PASS SQLite; concurrency terbuka | SiklusHidupSiswa, SiswaController, PendaftaranPengguna, ImporPengguna | Koreksi beralasan/audit before-after/transaksi; impor tidak memindahkan identitas atau lifecycle; password default hanya akun baru |
| 2A.6 | PASS SQLite; MySQL NOT RUN | AktivasiTahunAjaran, TransisiAkademik/Controller, TahunAjaran, migration singleton/unique | Nested validation, snapshot HTTP, stale/replay rejection, aktivasi manual dengan membership terverifikasi |
| 2A.7 | PASS guards; restore sengaja dinonaktifkan | ImporController, XlsxReader, CadanganController/Data, Blade minimal | Kuota parsing, preview terenkripsi/terikat pengguna/session/hash/TTL/single-use, secret tidak di-echo/flash, scope export eksplisit |

## C. Data Integrity Findings

Ditutup pada jalur yang diuji: roster historis berbasis kelas sekarang, hilangnya baris saat koreksi parsial, cascade master tidak sengaja, cast integer NIS, lifecycle update tanpa alasan, pengalihan identitas melalui email impor, reset password fallback akun existing, aksi transisi tidak dikenal, kelas tujuan tidak sah, aktivasi tanpa membership, periode penempatan tidak terverifikasi, kebocoran QueryException transisi, resource parsing tak terbatas, secret HTML/session, scope export kosong menjadi semua.

Restore legacy HTTP dan direct `CadanganData::pulihkan` diblokir sebelum parsing/mutasi. Tidak ada env bypass untuk mengaktifkan kembali secara tidak sengaja. Ini pengamanan sementara yang disengaja, bukan implementasi restore staging. Klaim rollback palsu di jalur legacy tidak lagi reachable.

Terbuka: writer master kelas/periode/jurusan belum semuanya memakai singleton yang sama; transaksi individual tidak membuktikan bebas race. Snapshot backup lintas tabel belum point-in-time consistent. Tanggal membership transisi legacy sebelum periode tujuan akan ditolak readiness aktivasi; jangan menormalisasi otomatis atau memalsukan histori.

## D. Database & Migration Changes

Migration baru, migration lama tidak diedit:

1. `2026_10_30_000001_restrict_historical_master_deletion.php`: enam FK jadwal/jurnal/presensi harian/log menjadi RESTRICT. Up/down diuji SQLite; MySQL belum.
2. `2026_10_31_000001_guard_academic_activation.php`: tabel singleton academic_operation_lock dan generated active_slot unique pada tahun_ajaran. Menolak baseline dengan lebih dari satu tahun aktif; tidak memilih/memperbaiki data diam-diam.

Belum diterapkan pada database pengguna. MySQL DDL memiliki implicit commit; perlu rehearsal migration/rollback pada instance disposable dan diagnostik orphan/multiple active sebelum deployment berizin. Kode baru membutuhkan schema baru; workspace ini belum boleh dianggap siap deployment.

## E. Regression Test Results

Semua command menggunakan `php tests/run.php` di sandbox SQLite. Rangkaian suite akhir, tiap berkas termasuk tepat satu kali pada agregat:

| Batch | Kelompok | Tests | Assertions | Hasil |
|---|---|---:|---:|---|
| 1 | AcademicYearCalendar, AdminSection, AksesWeb, AkunPantau, ApiJurnalKontrak, ApiStatistikKehadiran, AuditHalaman, Authorization | 89 | 393 | PASS |
| 2 | Barchart, Cadangan, CrudPages, DashboardHarian, DashboardPeriode, ErrorHandling, Example, HariLibur | 93 | 301 | PASS |
| 3 | Ikon, ImporPengguna, ImportRestoreSafety, JadwalForm, JurnalGanda, JurnalOtomatis, Jurusan, Keamanan | 114 | 512 | 113 PASS / 1 FAIL existing clipboard |
| 4 | Login, Pagination, PeriodeFilter, Phase1DatabaseIntegrityAndAudit, Phase2AHistoricalIntegrity, Phase2AcademicTransition, Phase3LifecycleAndRetention, Phase4AcademicTransitionChecklist | 79 | 407 | PASS |
| 5 | Phase4AdminActivityAudit, Phase4AnnualMaintenanceReminder, Phase4ExcelImportAndAiAssistance, Phase4FinalUiPolishAndTheme, PresensiRoster, ProsedurAkademik, QrAkses, RolePages | 76 | 317 | PASS |
| 6 | SafetyApplication, StatusSesi, StudentLifecycleIntegrity, TransitionPeriodWorkflow, TransitionValidationActivation, Phase2AReviewGuards, seluruh tests/Unit | 101 | 298 | PASS |

Batch 2 juga dijalankan ulang bersama AdminSection setelah perbaikan fixture: 119 tests / 400 assertions PASS. Agregat memakai 93/301 untuk menghindari menghitung AdminSection dua kali.

Verifikasi tambahan parent: focused historical 12/53; lifecycle 19/74; transition 27/50; setelah review focused guards+transition+lifecycle+import+history 99/344 PASS. Angka tambahan tidak dijumlahkan sebagai suite unik.

Fixture lama yang tidak sesuai invariant diperbarui tanpa melemahkan assertion: tahun historis retensi dibuat nonaktif; registrasi siswa memiliki tanggal periode efektif lengkap; activation fixtures memiliki membership yang benar. Tests perilaku restore lama diganti kontrak penolakan restore yang kini disengaja.

PHP lint akhir: 259 file PASS. `git diff --check` PASS, warning CRLF pada view transisi yang sudah dirty sebelumnya. Browser/build NOT RUN (tidak diminta redesign); MySQL migration/concurrency/integration NOT RUN karena belum ada dedicated disposable target yang diverifikasi. Guard integration sengaja hard-block.

## F. Git Diff Summary

Branch main; HEAD tetap `ae5cbee9358bf1e9e21ed1fe092d57290703db54`.
Awal: 11 tracked modified / 44 untracked. Akhir sebelum laporan ini: 45 tracked modified / 67 untracked. Laporan ini menambah satu untracked. Angka mencakup perubahan pengguna sebelum fase; bukan seluruhnya perubahan agent. Tidak ada reset/clean/commit. File source utama baru: RosterPresensi, SiklusHidupSiswa, AktivasiTahunAjaran, model concern, dua migration, safe runner/bootstrap, regression tests. Blade diubah hanya guard/konfirmasi/alasan/secret, bukan redesign empat workflow.

## G. Remaining Blockers

1. MySQL migration up/down, generated-column constraint, collation, procedures, lock/concurrency belum diuji di disposable instance.
2. Writer master kelas/periode/jurusan di luar singleton masih berpotensi race; jangan menyatakan acceptance concurrency selesai penuh.
3. Tanggal transisi legacy belum menyelesaikan kebijakan efektif tahun berikutnya; readiness kini fail-closed. Perlu keputusan domain dan verifikasi historis, bukan tanggal rekaan.
4. Suite belum seluruhnya hijau karena ikon clipboard existing HEAD.
5. Restore unavailable sampai safety pipeline Fase 7–8; SQL backup/retensi/consistent snapshot belum diimplementasikan.
6. Shutdown cleanup tidak menjamin penghapusan sandbox ketika parent SIGKILL/host hilang; file tetap terisolasi, bukan di storage aplikasi.

## H. Phase Verdict

READY WITH BLOCKERS. Fondasi dan hardening tersedia; tidak disertifikasi production-ready atau race-free. Sebelum readiness penuh: disposable MySQL verification, penyelesaian serialization writer master/tanggal efektif, dan review blocker. Tidak melanjutkan Fase 2B otomatis.

Dokumentasi pendukung: tests/SAFE_TESTING.md; docs/PHASE_2A5_LIFECYCLE.md; docs/PHASE_2A6_TRANSITION_ACTIVATION.md; docs/PHASE_2A7_IMPORT_RESTORE_SAFETY.md; docs/PHASE_2A_REVIEW_GUARDS.md.
