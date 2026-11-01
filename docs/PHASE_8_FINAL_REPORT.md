# Laporan Final Fase 8: Pemulihan Database, Validasi Staging, dan Safe Cutover (Disposable Sandbox)
**Jurnal Kelas App — SMK TI BAZMA**
**Tanggal**: 1 November 2026
**Status**: SELESAI
**Verdict**: **READY FOR PHASE 9** (Production Live Cutover & Legacy Restore Remain DISABLED by Policy)

---

## A. Ringkasan Eksekutif

Fase 8 berhasil membangun arsitektur pemulihan basis data bencana (Disaster Recovery & Data Restoration) berbasis validasi bertahap, eksekusi sandbox staging terisolasi, verifikasi integritas relasional, dan strategi cutover pointer-switching dengan kemampuan instant rollback pada lingkungan disposable MySQL 8.

### Prinsip Utama yang Dipertahankan:
1. **Restore Produksi & Legacy Tetap Dinonaktifkan (DISABLED)**:
   - Tidak ada rute, controller, tombol live, ataupun izin sistem yang dapat mengeksekusi restore atau mutasi skema secara langsung ke database pengguna aktif (`jurnal_kelas`).
   - Fitur pemulihan warisan lama (legacy restore) tetap terkunci rapat.
2. **Isolasi Penuh Staging Sandboxing**:
   - Seluruh pengujian pemulihan dilakukan secara eksklusif pada runner disposable (Docker tmpfs MySQL 8.0.46) dengan konfigurasi jaringan, lingkungan, dan kredensial terpisah.
   - Guard keamanan fail-closed menolak eksekusi jika menargetkan database di luar skema sandbox disposable (`phase2b_*`).
3. **Penanganan Karakteristik DDL MySQL (Implicit Commit)**:
   - DDL pada MySQL (`CREATE TABLE`, `DROP TABLE`, `TRUNCATE`) memicu *implicit commit*, sehingga tidak dapat dibatalkan melalui PDO rollback biasa.
   - Kegagalan di tengah staging ditangani dengan pembuangan total skema sementara (`DROP DATABASE IF EXISTS <staging_db>`), menjamin tidak ada skema parsial/sampah yang tertinggal.
4. **Strategi Cutover Hardened: Pointer Switching & Instant Rollback**:
   - Menghindari pendekatan non-atomik `TRUNCATE massal` dan `SET FOREIGN_KEY_CHECKS=0` pada database aktif.
   - Database aktif lama dipertahankan utuh (Zero-Table Mutation). Pemulihan arsip diverifikasi penuh pada database staging baru, lalu pointer koneksi dialihkan. Jika terjadi anomali, koneksi dikembalikan seketika (Instant Rollback) tanpa perlu pemulihan snapshot yang lambat.

---

## B. Komponen & Arsitektur yang Dibangun

### 1. Engine Pemulihan Staging (`RestoreStagingEngine.php`)
- **Validasi Berkas & Keamanan**:
  - Pemeriksaan ukuran file dan batas dekompresi gzip (maksimum 200 MB untuk mencegah gzip/zip bomb).
  - Verifikasi keutuhan stream hingga EOF dan pencocokan checksum SHA-256 terhadap metadata manifest.
- **State-Machine Parser SQL Sadar Konteks**:
  - Memecah query SQL bukan dengan `explode(';', ...)` naif, melainkan mesin status yang mengenali kutip tunggal (`'...'`), kutip ganda (`"..."`), escape sequence (`\'`, `\"`), komentar baris tunggal (`--`), dan komentar multi-baris (`/* ... */`).
- **Pemeriksaan Allowlist Ketat**:
  - Hanya mengizinkan statement DDL `CREATE TABLE`, `DROP TABLE`, dan DML `INSERT INTO` untuk 31 tabel database yang diizinkan.
  - Menolak instruksi berbahaya (`DROP DATABASE`, `GRANT`, `REVOKE`, `LOAD DATA INFILE`, `INTO OUTFILE`).
- **Verifikasi Integritas Relasional Domain**:
  - Memvalidasi 31 tabel fisik inti dan menghitung baris record.
  - Memeriksa orphan check berbasis kunci domain bisnis:
    - Relasi `siswa` &rarr; `users` berbasis NIS (0 orphan).
    - Relasi `guru` &rarr; `users` berbasis NIP (0 orphan).
    - Relasi `riwayat_kelas_siswa` &rarr; `siswa` (0 orphan).
  - Status hasil verifikasi bertingkat: `checksum_verified` &rarr; `staging_restored` &rarr; `relationally_verified`.

### 2. Engine Orkestrasi Cutover Terisolasi (`RestoreCutoverEngine.php`)
- Melakukan simulasi 8 tahapan cutover aman:
  1. Validasi arsip.
  2. Restore staging terisolasi.
  3. Verifikasi integritas relasional.
  4. Persetujuan otorisasi admin.
  5. Pengecekan status database lama (tetap utuh).
  6. Pengalihan pointer target koneksi (Switchover).
  7. Verifikasi pasca-cutover.
  8. Instant Rollback saat simulasi error (mengembalikan pointer tanpa menyentuh tabel).
- Guard fail-closed: hanya menerima basis data dengan prefix disposable `phase2b_*`.

### 3. Antarmuka Manajemen Pemulihan Admin (`ManajemenDataController.php`)
- Rute terpisah di `/admin/manajemen-data/pemulihan`:
  - `GET /admin/manajemen-data/pemulihan`: Daftar arsip SQL/SQL.GZ yang valid dan deteksi ketersediaan runner staging.
  - `POST /admin/manajemen-data/pemulihan/uji-staging`: Eksekusi uji sandbox dan pembuatan laporan dampak read-only.
  - `POST /admin/manajemen-data/pemulihan/setujui`: Persetujuan administratif hasil uji staging dengan konfirmasi reautentikasi kata sandi admin aktif.
- **Laporan Dampak Aman**:
  - Menampilkan jumlah baris per tabel penting tanpa menampilkan password, hash, token, atau data pribadi sensitif (PII).
  - Token persetujuan terenkripsi (`Crypt::encryptString`) mengikat identitas admin, checksum arsip, status staging, dan masa berlaku 15 menit.

---

## C. Hasil Verifikasi Pengujian Nyata

### 1. Bukti Eksekusi MySQL 8.0.46 Disposable Runner
- **Environment**: Docker container MySQL 8.0.46 Community Server tmpfs terisolasi via `tests/Integration/Phase2B_runner.py`.
- **Rincian Eksekusi Nyata**:
  - **Migrations**: 61 migrasi diterapkan secara sukses pada container database.
  - **Constraints**: 38 assertions integrasi constraints PASS.
  - **Restore & Cutover Assertions**: **18 assertions PASS, 0 FAIL**:
    - Validasi pembuatan backup arsip `.sql.gz` konsisten: PASS.
    - Validasi allowlist dan struktur SQL: PASS.
    - Restorasi nyata 31 tabel inti: PASS.
    - Rekonstruksi 7 objek prosedural (2 views, 2 stored functions, 1 procedure, 2 triggers): PASS.
    - Verifikasi zero-orphan relasi domain: PASS (`relationally_verified`).
    - Pengujian instant rollback saat simulasi kegagalan cutover: PASS (`CUTOVER_FAILED_ROLLED_BACK`).
    - Pengujian cutover sukses pointer switching: PASS (`CUTOVER_SUCCESS`).
    - Pembersihan skema staging terisolasi: PASS (`removed: true`).

### 2. Full Regression Suite (Safe Runner)
- **Cakupan**: 77 file pengujian (Unit & Feature) dalam 20 batch terisolasi.
- **Hasil**: **727 tests PASS, 3.468 assertions PASS, 0 FAIL**.
- Artefak bukti eksekusi tersimpan lengkap pada direktori `docs/phase8-evidence/`:
  - `manifest.json` (Daftar 77 file dan 20 batch).
  - `batch-01.json` s.d. `batch-20.json`.
  - `summary.json`.

---

## D. Batasan Operasional & Kepatuhan Keamanan

1. **Production Restore Tetap DITUTUP**:
   - Seluruh alur persetujuan admin pada UI Fase 8.5 murni bersifat administratif untuk kepatuhan audit pemulihan bencana. Tombol live cutover produksi sengaja ditiadakan.
2. **Browser QA Visual**:
   - Dilaporkan **NOT RUN** karena pengujian dijalankan dalam lingkungan terminal/CLI headless tanpa driver browser visual.
3. **Penanganan DML Kolom Generated**:
   - Mesin pencadangan `BackupEngine` telah diperbarui untuk menapis kolom generated virtual/stored (`active_slot` pada tabel `tahun_ajaran`) agar tidak memicu MySQL Error 3105 saat proses pemulihan DML.

---

## E. Status Git & File

- Seluruh modifikasi dan penambahan file berada dalam status untracked/modified yang bersih.
- Tidak dilakukan tindakan `git commit`, `git push`, `git reset`, atau `git clean` sesuai aturan isolasi.
- Hasil `git diff --check` bebas dari error spasi/konflik.

---

## F. Verdict Akhir Fase 8

# **VERDICT: READY FOR PHASE 9**
*(Ready with Policy Blockers: Live Cutover Produksi dan Legacy Restore Wajib Tetap Nonaktif)*

Seluruh pipeline validasi berkas, penapisan allowlist SQL, pemulihan nyata 31 tabel dan 7 objek prosedural pada MySQL 8 disposable, arsitektur cutover pointer-switching dengan instant rollback, serta antarmuka audit laporan dampak dengan reautentikasi kata sandi admin telah selesai diimplementasikan, diverifikasi, dan lulus uji secara sempurna.
