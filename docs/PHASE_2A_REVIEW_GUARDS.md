# Fase 2A — perbaikan review lifecycle / activation

## Kontrak

- Registrasi siswa aktif berkelas dan koreksi penempatan bertanggal memakai validator yang sama: periode harus ada, kedua batas lengkap, berurutan, mencakup tanggal efektif hari ini. Expired/future/undated ditolak atomik, bukan disulap menjadi bukti tanggal hari ini. Impor siswa baru memakai boundary sama; tidak ada bypass fixture/import. Intake masa depan tidak didukung sebagai penempatan bertanggal melalui registrasi umum ini; lengkapi periode dan lakukan pada tanggal yang sah. Tidak dibuat workflow intake baru.
- Aktivasi memerlukan tepat satu membership aktif per siswa aktif: kelas, tahun, tingkat cocok; status aktif; tanggal akhir null; tanggal mulai terverifikasi di dalam periode tujuan dan tidak setelah hari ini. Alumni tetap ditolak. Tidak membuat atau memperbaiki riwayat saat readiness.
- Transisi legacy masih mencatat tanggal eksekusi. Bila eksekusi sebelum periode tujuan, hasilnya bukan bukti tanggal mulai yang sah untuk aktivasi. Aktivasi ditolak; data harus diverifikasi melalui prosedur koreksi berwenang. Tidak menormalkan tanggal legacy menjadi awal periode secara otomatis. Test sukses transisi/aktivasi sekarang berjalan dalam periode tujuan; assertion audit/rollback/aktivasi tetap dipertahankan.
- Lifecycle update/registration, registrasi portable, dan seluruh transaksi impor mengambil singleton `academic_operation_lock` sebelum query/lock siswa atau akun. Import mengambil singleton sebelum `identitasAkun(..., true)` sehingga nested lifecycle tidak membalik urutan account/student terhadap aktivasi. Singleton mengurutkan writer yang berpartisipasi; bukan klaim semua writer aman.
- Exception eksekusi controller hanya menampilkan pesan DomainException/ValidationException. Throwable lain mendapat pesan tetap; tidak memanggil `report($e)`, tidak menyimpan SQL/bindings ke session/audit/log. Regression memaksa QueryException saat insert membership berisi catatan privat dan membuktikan rollback serta tidak bocor di session/log.

## Bukti TDD aktual

Semua perintah melalui `php tests/run.php`, SQLite sandbox; tanpa DB primer, install, commit, deploy, migrasi persistent.

- Safety prerequisite: 6 tests / 25 assertions GREEN.
- RED tanggal: 12 tests, 9 failures (6 registrasi, 3 batas tanggal koreksi null). GREEN dengan lifecycle: 31 tests / 122 assertions.
- RED readiness: 22 tests, 7 accepted-invalid memberships; satu assertion snapshot test diperbaiki memakai fresh raw attributes. Guard menolak no history, wrong year/grade/class, unknown date, before/after target period, closed active history, wrong status. Positive evidence unchanged.
- RED lock order: ketiga writer gagal karena query pertama siswa/users, bukan singleton.
- RED privacy tervalidasi: QueryException aktual mencapai catch; session berisi `SQL: insert into riwayat_kelas_siswa (catatan) values (PRIVATE-CATATAN-991)` sebelum fix.
- GREEN fokus+related: 120 tests / 484 assertions, exit 0, 15.330 s. Paths: Phase2AReviewGuardsTest, StudentLifecycleIntegrityTest, TransitionValidationActivationTest, ImportRestoreSafetyTest, TransitionPeriodWorkflowTest, AcademicYearCalendarTest, Phase2AcademicTransitionTest, Phase2AHistoricalIntegrityTest.
- Related batch pertama: 104 tests, 103 pass, 1 legacy activation fixture error (tanggal 2026 di membership target 2027). Fixture diperbaiki menjadi tanggal target terverifikasi. Rerun batch lengkap GREEN: **104 tests / 415 assertions**, exit 0, 113.396 s. Paths: ImporPenggunaTest, Phase4ExcelImportAndAiAssistanceTest, CrudPagesTest, ProsedurAkademikTest, Phase2AcademicTransitionTest, Phase3LifecycleAndRetentionTest, Phase4AcademicTransitionChecklistTest, Phase1DatabaseIntegrityAndAuditTest.
- Final focused rerun setelah cleanup test: **26 tests / 86 assertions**, exit 0. `git diff --check` exit 0 (warning existing CRLF Blade); kelima source PHP `php -l` bersih.

## Batas / risiko residual

- MySQL concurrency / deadlock stress NOT RUN. SQLite tidak membuktikan row locking. Master kelas/periode/jurusan dan SQL langsung di luar singleton masih bisa berlomba dengan validasi. Perubahan master tersebut belum dilindungi universal; jangan menyatakan sistem race-free. Perlu pekerjaan terpisah dengan semua writer mengikuti singleton serta tes MySQL disposable.
- No-history/unknown-date/early legacy transition sekarang menghambat aktivasi secara sengaja; tidak ada silent repair atau data fabrication.
- Full suite milik parent; IkonTest missing clipboard existing HEAD di luar scope. Restore tetap hard-disabled; scheduler/UI backup tidak disentuh.
- Dirty tree awal dipertahankan, patch terbatas. Files source: SiklusHidupSiswa, AktivasiTahunAjaran, PendaftaranPengguna, ImporPengguna, TransisiController. Tests: new Phase2AReviewGuardsTest; fixtures TransitionValidationActivationTest, AcademicYearCalendarTest, Phase2AcademicTransitionTest.
