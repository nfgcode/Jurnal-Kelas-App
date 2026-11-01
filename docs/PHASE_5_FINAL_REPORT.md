# Laporan Akhir Fase 5 — Workflow Penerimaan Siswa Baru Tingkat X (Intake)

## A. Ringkasan Eksekutif (Executive Summary)

Verdict: **READY FOR PHASE 6** (sebagai alur penerimaan siswa baru mandiri yang telah teruji penuh, bukan izin deployment produksi langsung).

Fase 5 telah mengimplementasikan workflow **Penerimaan Siswa Baru (Intake) Tingkat X** yang mandiri secara menyeluruh (end-to-end) di atas arsitektur domain `AcademicWorkflows`, advisory lock singleton, dan integrity guard dari fase sebelumnya:
- **Alur Mandiri**: Pilih Tahun Sasaran &rarr; Unggah Berkas Calon Siswa (XLSX/CSV) &rarr; Validasi Identitas & Rombel X &rarr; Gating Review Terkelompok (Masalah/Duplikasi, Dikecualikan, Siap Didaftarkan) &rarr; Konfirmasi Snapshot Binding &rarr; Eksekusi Atomik &rarr; Rekapitulasi Hasil & Riwayat Sesi.
- **Pemisahan Alur**: Proses penerimaan siswa baru berjalan mandiri secara terisolasi penuh tanpa memicu alur kelulusan kelas XII, kenaikan kelas X–XI, maupun aktivasi tahun ajaran tujuan secara otomatis.
- **Suite Pengujian Unit & Feature**: **687 tests / 3.248 assertions PASS** (17 batch disjoint terisolasi, 65 file test, 0 gagal, 0 overlap).
- **Verifikasi MySQL Disposable 8.0.46**: **59 migrations, 145 assertions PASS** (termasuk eksekusi riil `IntakeExecutor` di MySQL, pembentukan entitas siswa, pembuatan akun user, pembuatan keanggotaan awal kelas X, serta bukti data lock waits pada container sementara).
- **PHP Lint**: **440 file PHP, 0 failures**.
- **Git Diff & Whitespace**: HEAD `ae5cbee9358bf1e9e21ed1fe092d57290703db54` tidak bergeser, tidak ada commit/push/reset/clean, database lokal pengguna tidak disentuh, dan restore legacy tetap dinonaktifkan.

---

## B. Implementasi Domain & Workflow Intake

Alur kerja penerimaan siswa baru yang diimplementasikan:

1. **Pemilihan Periode Sasaran:**
   - Admin memilih tahun ajaran tujuan pendaftaran siswa baru. Sistem memuat daftar seluruh rombel tingkat X yang sudah terdaftar pada tahun tujuan tersebut.
   - Pendaftaran hanya dapat dilakukan jika sekolah telah menyiapkan rombel kelas X di tahun tujuan. Sistem dilarang membuat rombel otomatis.

2. **Parsing Berkas Resmi & Preservasi String NIS:**
   - Unggah berkas resmi dalam format XLSX atau CSV via `IntakeSpreadsheet::parse()`.
   - Menegakkan canonical headers: `NIS`, `NISN`, `Nama Lengkap`, `Email`, `Kelas X Tujuan`, `Jenis Kelamin`.
   - NIS diperlakukan sebagai literal string tanpa konversi numerik, menjaga angka nol di depan (leading zeros seperti `00301`).
   - NISN divalidasi ketat persis 10 digit angka (`/^[0-9]{10}$/`).
   - Tersedia unduhan template resmi XLSX dan panduan AI prompt generator dengan peringatan perlindungan privasi data pribadi siswa.

3. **Deteksi Duplikasi Global & Internal:**
   - Deteksi duplikasi internal di dalam berkas (`nis_duplikat_internal`, `nisn_duplikat_internal`, `email_duplikat_internal`).
   - Deteksi duplikasi terhadap database master (`nis_sudah_terdaftar`, `nisn_sudah_terdaftar`, `email_sudah_terdaftar`).
   - Tidak ada pengambilalihan akun existing secara otomatis hanya berdasarkan kesamaan email/NIS.

4. **Pengecualian Eksplisit (Exclusion Workflow):**
   - Baris berkas yang bermasalah atau duplikat dapat dikecualikan secara eksplisit oleh admin (`excluded = true`) dengan **alasan administratif tertulis wajib** (`exclusion_reason_present = true`).
   - Baris yang dikecualikan dilewati saat eksekusi dan tidak dibuatkan siswa/akun di database, namun data dan alasannya tetap tersimpan di tabel `academic_workflow_items` untuk keperluan audit.
   - Batch yang hanya berisi baris dikecualikan tidak mendaftarkan siswa baru (`processed = 0, skipped = N`).

5. **Pratinjau Read-Only Terkelompok (Gated Review):**
   - Tampilan review dikelompokkan secara terurut:
     1. **Kelompok 1 (Prioritas)**: Masalah & Duplikasi (Wajib diselesaikan atau dikecualikan sebelum finalisasi).
     2. **Kelompok 2**: Baris Dikecualikan (Dilewati saat eksekusi).
     3. **Kelompok 3**: Calon Siswa Siap Didaftarkan (Valid).
   - Tombol finalisasi dikunci (`disabled`) dan banner peringatan muncul selama masih terdapat baris bermasalah (`masalah > 0`).

6. **Konfirmasi Snapshot Binding & Eksekusi Atomik:**
   - Konfirmasi mengikat SHA-256 hash dari snapshot seluruh populasi kandidat dan master data.
   - Perubahan master data atau pendaftaran user/siswa lain di luar alur langsung membatalkan konfirmasi (`workflow_confirmation_stale`).
   - Eksekusi atomik memanggil `IntakeExecutor` di bawah advisory lock singleton `AcademicOperation::run()`:
     - Membuat entitas baru di tabel `siswa` (`nis`, `nisn`, `nama`, `jenis_kelamin`, `kelas_id`, `status = 'aktif'`).
     - Membuat riwayat keanggotaan awal di `riwayat_kelas_siswa` (`aktif = true`, `status = 'aktif'`, `tingkat = 'X'`, `tanggal_mulai = effective_date`).
     - Membuat akun login baru di tabel `users` (`role = 'siswa'`, `status = 'aktif'`, password acak kuat Bcrypt).
     - **Tahun tujuan tetap nonaktif** dan tidak teraktivasi otomatis.

7. **Rekapitulasi Hasil & Jejak Audit:**
   - Halaman show menampilkan metrik jumlah siswa baru terdaftar vs baris dikecualikan, tab detail siswa, dan riwayat jejak audit lengkap (peristiwa, status outcome, perubahan state).

---

## C. Security Review & Kredensial Akses Awal Siswa

1. **Mekanisme Kredensial Awal:**
   - Password acak kuat berkriptografi 32 karakter (`Str::random(32)`) di-generate dan langsung di-hash menggunakan Bcrypt (`Hash::make()`).
   - Tidak ada password default bersama (seperti `password123`) yang digunakan.
   - Tidak ada teks password terbuka (plain text) yang disimpan di database, diekspor ke Excel, dicatat dalam log, ditampilkan pada HTML, atau disimpan pada tabel `academic_workflow_audits`.
2. **Status Akses Akun & Reset Password Admin (`/admin/akun`):**
   - Aplikasi saat ini **belum** memiliki fitur self-service reset password publik via web/email (`/forgot-password`).
   - Oleh karena itu, status akun siswa pada tampilan intake secara transparan diberi label: **"Perlu Pengaturan Admin"**.
   - Admin sekolah dapat menetapkan/mereset password akun siswa secara aman dan terisolasi melalui rute resource `PUT /admin/akun/{id}` tanpa mengubah profil kesiswaan. Jalur ini telah teruji penuh pada `Phase5SecurityAndAccountAccessTest`.
3. **Keamanan Username Siswa:**
   - Format username dibersihkan menggunakan regex strictly allowlisted: `siswa_<nis>` (`/^[A-Za-z0-9._-]+$/`).
   - Panjang maksimum dibatasi aman ($\le 50$ karakter).
   - Penanganan tabrakan nama (collision): jika username sudah digunakan, sistem menambahkan suffix acak unik secara otomatis.
4. **Keamanan Unggah Berkas & Ekspor:**
   - Batas ukuran unggah berkas dibatasi maksimal 10 MB (`max:10240`).
   - Proteksi formula injection: pembacaan dan parsing berkas memperlakukan nilai sel sebagai literal string dan menolak formula berbahaya.
   - Proteksi double-submit: penguncian tabel singleton advisory lock dan pengecekan replay idempotency pada eksekusi completed.

---

## D. Hasil Pengujian & Bukti Eksekusi

### 1. Suite Unit & Feature Test (SQLite Sandbox In-Memory)
- **Total file test**: 65 file.
- **Total tests**: **687 tests**.
- **Total assertions**: **3.248 assertions**.
- **Hasil**: **687 PASS, 0 FAIL** (100% lulus, 17 batch terisolasi).
- Artefak bukti tersimpan di `docs/phase5-evidence/`:
  - `manifest.json` (Daftar 65 file test per batch)
  - `batch-01.json` s.d. `batch-17.json`
  - `summary.json`

### 2. Verifikasi MySQL 8.0.46 Disposable & Concurrency
- Runner: `tests/Integration/Phase2B_runner.py` pada container Docker terisolasi (internal network, storage tmpfs, tanpa port publik atau volume persisten).
- Migrasi: **59 migrasi PASS**.
- Total assertions: **145 assertions PASS**.
- Kasus yang teruji:
  1. `same-confirmed-operation`: Replay permintaan konfirmasi identik terbukti idempotent.
  2. `competing-identity-snapshots`: Persaingan dua snapshot terdeteksi konflik dan snapshot kalah ditolak `workflow_confirmation_stale`.
  3. `master-before-execute`: Modifikasi master akademik saat antrean transaksi membatalkan konfirmasi usang.
  4. `coordinator-resume`: Kegagalan pada salah satu tahap terkoordinasi dapat dilanjutkan tanpa mengulang tahap yang sudah selesai.
  5. `real-graduation-mysql`: Eksekusi nyata kelulusan XII pada instance MySQL.
  6. `real-promotion-mysql`: Eksekusi nyata kenaikan kelas X–XI pada instance MySQL.
  7. `real-intake-mysql`: **Eksekusi nyata `IntakeExecutor` pada instance MySQL**:
     - Siswa baru NIS `00999` terbukti dibuat di tabel `siswa`.
     - Akun siswa baru terbukti dibuat di tabel `users` dengan `role = 'siswa'`.
     - Riwayat keanggotaan kelas pertama terbukti dibuka di `riwayat_kelas_siswa` dengan `tingkat = 'X'` dan `aktif = 1`.
- Artefak MySQL tersimpan di `docs/phase5-evidence/mysql-results.json`.

---

## E. UI QA & Browser Tooling Status

- **Status Browser QA**: **NOT RUN** (lingkungan CLI server headless/API runner tanpa display X11/Wayland lokal). Aksesibilitas belum diuji secara visual di browser sungguhan.
- **Audit Markup & Kontrak Template**:
  - Target interaksi tombol/form memenuhi standar $\ge 44\text{px}$.
  - Trap fokus modal dan penanganan tombol Escape pada dialog konfirmasi.
  - Dukungan variabel CSS tema terang/gelap (`data-theme="light"` / `data-theme="dark"`).
  - Tata letak responsif tabel (`table-responsive`) untuk pembesaran zoom 200%.

---

## F. Perubahan File Sumber (Source Modifications)

1. **Implementasi Domain & Service**:
   - `app/AcademicWorkflows/IntakeExecutor.php` (Baru: Executor pendaftaran siswa baru).
   - `app/AcademicWorkflows/IntakeSpreadsheet.php` (Baru: Parser XLSX/CSV, template, prompt AI).
   - `app/AcademicWorkflows/WorkflowService.php` (Dimodifikasi: Registrasi executor).
   - `app/Providers/AppServiceProvider.php` (Dimodifikasi: Singleton binding IntakeExecutor).
2. **Controller & Rute**:
   - `app/Http/Controllers/Admin/IntakeController.php` (Baru: Controller intake siswa baru).
   - `routes/web.php` (Dimodifikasi: Rute admin `/admin/penerimaan-siswa`).
3. **Tampilan Blade Antarmuka**:
   - `resources/views/admin/intake/index.blade.php` (Baru).
   - `resources/views/admin/intake/review.blade.php` (Baru).
   - `resources/views/admin/intake/show.blade.php` (Baru).
   - `resources/views/layouts/app.blade.php` (Dimodifikasi: Navigasi sidebar admin).
4. **Pengujian & Verifikasi**:
   - `tests/Unit/IntakeSpreadsheetTest.php` (Baru).
   - `tests/Feature/IntakeWorkflowExecutionTest.php` (Baru).
   - `tests/Feature/IntakeControllerTest.php` (Baru).
   - `tests/Feature/Phase5SecurityAndAccountAccessTest.php` (Baru).
   - `tests/Integration/Phase2B_concurrency.php` (Dimodifikasi: Penambahan kasus MySQL real-intake).

---

## G. Remaining Risks & Batasan

1. **Ketiadaan Self-Service Password Reset**:
   - Siswa baru belum dapat mengatur password mandiri via email; kredensial awal harus dibagikan oleh admin setelah diatur di `/admin/akun`.
2. **Kesiapan Rombel Tingkat X**:
   - Siswa baru tidak dapat didaftarkan jika sekolah belum membuat rombel kelas tingkat X pada tahun ajaran tujuan.
3. **Restore Legacy Tetap Dinonaktifkan**:
   - Jalur restore data lama tetap ditutup demi keselamatan data historis sekolah sampai Fase 8.

---

## H. Final Verdict

**READY FOR PHASE 6**

Workflow Penerimaan Siswa Baru Tingkat X (Intake) telah selesai dibangun secara mandiri, lengkap dengan validasi NIS string, deteksi duplikasi komprehensif, penanganan baris dikecualikan, UI pratinjau gating, mekanisme kredensial acak aman, serta pengujian menyeluruh di SQLite dan MySQL disposable (687 tests PASS, 145 assertions MySQL).

Fase 6 (Orkestrasi Transisi Akademik Terpadu / Coordinator & Roadmap Lanjutan) **tidak** dimulai secara otomatis dan menunggu arahan berikutnya.
