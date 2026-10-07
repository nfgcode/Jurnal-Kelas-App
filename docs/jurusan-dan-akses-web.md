# Jurusan, siswa, dan akses web

## Perilaku

- Admin mengelola jurusan lewat `/admin/jurusan`, dengan pencarian, filter bidang/status, sorting, dan pagination.
- Kode wajib unik, maksimal 20 karakter, tanpa whitespace, dan disimpan kapital. Nama wajib; bidang/deskripsi opsional; aktif berupa boolean.
- Jurusan yang dipakai kelas atau siswa tidak dapat dihapus. Nonaktifkan jika tidak lagi ditawarkan untuk kelas baru. Jurusan nonaktif yang sedang dipakai tetap tersedia di form edit kelas.
- `siswa.jurusan_kode` adalah referensi langsung yang dikendalikan database. Sumbernya tetap `kelas.jurusan_kode`, bukan input pengguna.
- Trigger MySQL dan SQLite menangani insert/update siswa, perpindahan kelas, perubahan jurusan kelas, dan penghapusan kelas. Penggantian kode jurusan memakai FK cascade pada kelas dan siswa.
- `jurnal.siswa_nis` menunjuk siswa pengisi. Server menentukan NIS saat ketua membuat jurnal dan mempertahankannya saat guru/admin mengedit.
- Jurusan jurnal dibaca melalui `jurnal.siswa.jurusan`. Tanpa siswa atau tanpa jurusan siswa, nilainya `null`; tidak ada fallback ke jurusan kelas jadwal.
- Jurnal lama mengikuti jurusan siswa terkini. Nama/bidang jurusan tidak diduplikasi ke siswa atau jurnal.
- Akun siswa biasa tidak boleh login web atau menggunakan sesi lama untuk membuka route web terlindungi. Pencabutan ketua diperiksa pada setiap request. Logout tetap tersedia.
- Ketua membuat dan membaca jurnal kelasnya untuk hari ini, tidak mengubah atau menghapus. Guru CRUD pada pelajaran yang diampunya; admin seluruh jurnal. Akses wali kelas tambahan tetap read-only.
- Kelayakan login API siswa biasa tidak diubah. Ketua pada daftar API jurnal tetap dibatasi hari ini, konsisten dengan policy detail.

## Database

Migrasi baru, tanpa membuat ulang tabel `jurusan` yang sudah ada:

1. `2026_10_07_000003_add_jurusan_siswa_and_siswa_jurnal`: kolom/FK/index dan backfill data.
2. `2026_10_07_000004_create_jurusan_sync_triggers`: sinkronisasi di database untuk MySQL/SQLite.
3. `2026_10_07_000005_create_sp_tambah_jurusan`: procedure MySQL dengan transaksi, rollback/resignal, dan pesan validasi.

Penambahan jurusan melalui `PencatatanAkademik::jurusan()`: `CALL sp_tambah_jurusan` pada MySQL, `DB::transaction()` pada SQLite. Procedure memiliki transaksi sendiri seperti procedure akademik lain; jangan memanggilnya di dalam transaksi luar MySQL yang diharapkan tetap terbuka.

FK jurusan memakai update cascade/delete restrict. FK jurnal-siswa memakai delete restrict. Index pendukung non-unique tersedia pada `siswa.jurusan_kode` dan `jurnal.siswa_nis`.

## Cadangan dan restore

- JSON otomatis membawa kolom baru; urutan tabel menempatkan jurusan/kelas/siswa sebelum jurnal.
- Restore gabung memakai primary key aktual, termasuk `kode`, `nis`, dan primary key gabungan, bukan selalu `id`.
- Restore menghitung ulang jurusan siswa setelah memulihkan kelas/siswa.
- Cadangan lama tanpa `jurnal.siswa_nis` menurunkan referensi dari akun pengisi yang masih tersedia. Jika tidak dapat ditentukan, nilainya `null`.
- Restore ganti hanya tabel kelas mempertahankan enrolment siswa untuk ID kelas yang dipulihkan; kelas yang tidak dipulihkan melepas referensi kelas/jurusan siswa.
- Penghapusan akun tidak menghapus siswa dan referensi jurusan jurnalnya. Penghapusan siswa yang masih dirujuk jurnal ditolak.

## Pengujian

Suite Laravel memakai SQLite in-memory:

```powershell
php artisan test --no-ansi
```

Integrasi MySQL harus memakai database kosong dan terpisah, dengan `_jurusan_test_` pada namanya. Jangan menjalankan `migrate:fresh` pada database aplikasi. Contoh setelah database disposable dibuat dan user Laravel diberi izin:

```powershell
docker compose exec -T -e DB_DATABASE=jurnal_kelas_jurusan_test_20261007 -e APP_ENV=testing app php artisan migrate --no-interaction
docker compose exec -T -e DB_DATABASE=jurnal_kelas_jurusan_test_20261007 -e APP_ENV=testing app php tests/Integration/jurusan_mysql.php
```

Script menolak driver/database yang tidak sesuai, lalu memeriksa procedure, rollback, trigger, FK, restore, index/EXPLAIN, serta rollback/reapply tiga migrasi dengan data terisi. Script menulis fixture dan hanya untuk database disposable; untuk mengulang, gunakan database disposable baru atau kosongkan database test yang sama secara eksplisit.

Hasil verifikasi pada 7 Oktober 2026:

- Suite lengkap terakhir setelah perbaikan tampilan: 331 test lulus, 1.314 assertion.
- Regresi terarah terakhir (`JurusanTest`, `AksesWebTest`, `CadanganTest`, `ApiJurnalKontrakTest`): 42 test lulus, 204 assertion, termasuk laporan API jurusan siswa terkini.
- Integrasi MySQL disposable: 40 pemeriksaan lulus, termasuk impor/transfer siswa, restore penuh dan rollback restore gagal.
- Laravel Pint pada file perubahan: lulus. `git diff --check`: lulus.
- Build frontend Docker: berhasil. Build host Windows belum berhasil karena native binding rolldown tidak tersedia; dependency tidak diubah.
- Smoke test HTTP pada aplikasi Docker setelah login admin: daftar/tambah/ubah jurusan, jurnal, dan laporan jurnal menghasilkan HTTP 200.

## Penerapan pada lingkungan lokal

- Sebelum migrasi, cadangan data tersimpan di `storage/app/private/sebelum-jurusan-20261007.json.gz` (berisi data sensitif; jangan commit atau publikasikan).
- Tiga migrasi sudah diterapkan ke Docker database `jurnal_kelas`.
- Jumlah register tidak berubah: 1.350 siswa dan 9.492 jurnal. Ketidaksesuaian jurusan siswa/kelas: 0. Tiga jurnal lama memperoleh referensi siswa dari akun pengisi.
- Audit tampilan awal: `anti-slop/audit-001-2026-10-07.md`. Pengguna menyetujui temuan 1–4 lewat "do it"; laporan perbaikan dan click-through ada di `anti-slop/audit-002-2026-10-07.md`.

## Perbaikan tampilan yang disetujui

- Kontras teks bantu/error/hapus/placeholder, warna sidebar dan zebra table disesuaikan melalui token tema bersama.
- Kontrol, pagination, sorting, dan menu memakai target minimal 44px; filter dan footer membungkus di mobile.
- Form jurusan/filter/pager memiliki status pemrosesan, `aria-busy`, indikator tombol, dan guard submit ulang. Pager memakai `requestSubmit()` agar event submit berjalan.
- Fokus keyboard terlihat; sidebar mobile tertutup tidak dapat difokuskan, menu terbuka membungkus Tab dan dapat ditutup dengan Escape.
- Perubahan shared component ikut berlaku pada layar lama yang menggunakan komponen tersebut; pola visual aplikasi dipertahankan.
- Pengujian browser memakai Edge headless, SQLite disposable, compiled view terpisah, dan asset build. Petunjuk pengulangan: `tests/Browser/README.md`. Database sekolah tidak diubah selama audit ini.
- Regresi terarah setelah perbaikan: 58 test lulus, 312 assertion (`JurusanTest`, `AksesWebTest`, `AuditHalamanTest`, `RolePagesTest`, `PeriodeFilterTest`).
- Browser terakhir: 72 pemeriksaan lulus pada Edge headless, kedua tema, jurusan pada 320/375/768/1440px dan informasi jurusan jurnal pada 320/375/1440px; tidak ada console error/uncaught JavaScript error.
- Temuan 1–4 selesai; Delivery Gate fitur jurusan/perbaikannya lulus. Ini bukan audit seluruh UI aplikasi atau pengujian perangkat fisik.
