# Bukti Implementasi Backend

Sistem Informasi Jurnal Mengajar dan Presensi Siswa — SMK Negeri 2 Balikpapan.

Seluruh isi dokumen ini diambil dari working tree pada branch `main` — commit
terakhir `4d1a6b5` ditambah dua perbaikan berlingkup sempit yang belum di-commit —
dan dari eksekusi nyata di lingkungan pengembangan Docker. Tidak ada angka,
endpoint, atau nama objek database yang dikarang; setiap klaim menyebut berkas
atau perintah asalnya.

Berkas pendukung pada folder yang sama:

- `php-artisan-test-output.txt` — keluaran mentah PHPUnit (tanpa kode ANSI)
- `TESTING_REPORT.md` — hasil pengujian rinci
- `API_DOCUMENTATION.md` — daftar 37 entri route API
- `POSTMAN_AUDIT.md` — audit collection lama
- `Jurnal-Kelas-API-KP.postman_collection.json` — collection hasil audit
- `REPORT_MISMATCHES.md` — ketidaksesuaian dokumentasi lama terhadap kode final

---

## Teknologi

| Lapisan | Teknologi | Versi terverifikasi |
|---|---|---|
| Bahasa | PHP | 8.3.32 (`php -v` di container `app`) |
| Framework | Laravel | 13.20.0 (`php artisan --version`) |
| Autentikasi API | Laravel Sanctum (personal access token) | `laravel/sanctum` |
| Basis data produksi/pengembangan | MySQL | 8.0 (`docker-compose.yml`) |
| Basis data pengujian | SQLite in-memory | `phpunit.xml` |
| Cache / Session / Queue | Redis | 7-alpine |
| Web server | nginx | stable-alpine, port host **8888** |
| Build aset | Bun + Vite | `oven/bun:alpine`, port 5173 |
| Pengujian | PHPUnit | 12.5.31 |
| Gaya kode | Laravel Pint | `composer lint` / `composer quality` |
| Pustaka pendukung | `endroid/qr-code` 6, `dompdf/dompdf` 3.1 | `composer.json` |

Layanan pendukung lain pada `docker-compose.yml`: Mailpit (SMTP uji, port 8025)
dan phpMyAdmin (port 8081). Container `app` menjalankan php-fpm ditambah
supervisord untuk `queue:work` (dua pekerja) dan `schedule:run`.

## Arsitektur Backend

Aplikasi memakai pola MVC Laravel dengan tiga lapisan tambahan yang khas pada
proyek ini.

1. **Controller** — dua keluarga terpisah. `app/Http/Controllers/*` melayani
   antarmuka web (Blade), `app/Http/Controllers/Api/*` (10 controller) melayani
   REST API. Keduanya memakai Form Request dan service yang sama, sehingga tidak
   mungkin satu jalur mengizinkan sesuatu yang jalur lain tolak.
2. **Form Request** — `app/Http/Requests/*` (7 berkas + `Api/JurnalRequest`).
   Aturan validasi hanya ditulis sekali di sini.
3. **Service / Support** — `app/Support/*` (21 berkas) memuat logika yang tidak
   layak tinggal di controller: `PencatatanAkademik` dan `PendaftaranPengguna`
   (penulisan lewat stored procedure + fallback), `SimpanPresensiJurnal` dan
   `SimpanPresensiHarian` (penyimpanan presensi), `Ringkasan` (agregat laporan),
   `JamPelajaran` (perhitungan jam dari nomor JP), `Periode` (filter periode),
   `DbDriver` (percabangan MySQL/SQLite).
4. **Policy** — `app/Policies/*`: `JurnalPolicy`, `KelasPolicy`, `JadwalPolicy`,
   `MataPelajaranPolicy`.
5. **Middleware peran** — `app/Http/Middleware/CheckRole.php`, dialiaskan sebagai
   `role` pada `bootstrap/app.php`.

Ciri arsitektur yang perlu disebut di BAB 5:

- **Satu jalur simpan per jenis data.** Presensi pertemuan hanya dapat ditulis
  lewat `SimpanPresensiJurnal::simpan()`, dipanggil oleh controller web maupun
  API. Tidak ada jalur kedua.
- **Aturan ganda: aplikasi dan database.** Aturan penting ditulis dua kali dengan
  sengaja — sebagai validasi (agar pengguna menerima pesan yang bisa dibaca) dan
  sebagai constraint/prosedur database (agar tetap berlaku bagi penulis yang tidak
  lewat formulir).
- **Percabangan driver yang eksplisit.** Objek MySQL punya padanan PHP untuk
  SQLite, dan keduanya memakai satu penerjemah kode kesalahan
  (`App\Support\ProsedurTersimpan`).

## REST API

`php artisan route:list --path=api` melaporkan **37 entri route**: GET 19, POST 8,
PUT 5, PATCH 5, DELETE 5. Daftar lengkap ada pada `API_DOCUMENTATION.md`.

Karakteristik utama:

- Prefiks `/api`, seluruhnya JSON (`bootstrap/app.php` memaksa render JSON untuk
  `api/*`, sehingga kesalahan tidak pernah membalas HTML).
- Hanya `POST /api/login` yang publik; sisanya `auth:sanctum`.
- Nama route diberi awalan `api.` agar tidak bertabrakan dengan route web bernama
  sama (`kelas.index` vs `api.kelas.index`).
- Penulisan data master (`kelas`, `mata-pelajaran`, `jadwal`, `users`) dan seluruh
  laporan dibatasi `role:admin`.
- Jurnal dan presensi tidak cukup dibatasi peran, melainkan per record lewat
  policy.

**Identifier URL jurnal adalah ULID, bukan id numerik.** `Jurnal::getRouteKeyName()`
mengembalikan `public_id`. Ini berlaku untuk route web dan API sekaligus, sehingga
`/api/jurnal/12` menghasilkan `404`. Respons jurnal tetap membawa `id` numerik
(dipakai pada payload) **dan** `public_id` (dipakai menyusun URL) — dikunci oleh
`ApiJurnalKontrakTest`.

## Autentikasi dan Otorisasi

### Autentikasi

`Api\AuthController@login` memvalidasi `user`, `password`, `role` (opsional),
`device_name` (opsional), lalu menyerahkan pencarian akun ke
`App\Support\LoginResolver`. Resolver menerima **empat** identifier: `username`,
`email`, `nip`, `nis` — masing-masing dicek terpisah agar kolom `nip`/`nis` yang
NULL tidak pernah cocok dengan masukan kosong. Parameter `role` mempersempit
pencarian sehingga deretan angka yang sama tidak bisa masuk sebagai peran lain.

Kegagalan login selalu memakai satu pesan generik ("NIP/NIS atau password salah.")
agar endpoint tidak mengonfirmasi identifier mana yang terdaftar. Akun berstatus
`nonaktif` ditolak walaupun passwordnya benar.

### Otorisasi berlapis

| Lapis | Mekanisme | Contoh |
|---|---|---|
| 1. Peran | `role:admin` pada grup route | Seluruh `/api/users`, `/api/laporan/*`, penulisan data master |
| 2. Policy per record | `Gate::authorize()` di controller | `JurnalPolicy::view/create/update/delete/isiPresensi`, `KelasPolicy::lihatPresensiHarian` |
| 3. Scoping kueri | `when($user->isGuru())` dsb. di controller | `Api\JurnalController@index`, `Api\PresensiController@index` |
| 4. Pemeriksaan kepemilikan tujuan | `abort_if()` setelah validasi | `Api\JurnalController@update` memeriksa jadwal **tujuan**, bukan hanya jurnal asal |

Pembagian peran menurut `JurnalPolicy` (bukan menurut dokumentasi lama):

| Tindakan | Admin | Guru | Ketua kelas | Siswa biasa |
|---|:--:|:--:|:--:|:--:|
| Membaca jurnal | ✔ semua | ✔ pertemuan yang diampu + kelas perwalian | ✔ kelasnya | ✔ kelasnya |
| Membuat / mengubah / menghapus jurnal | ✔ | ✘ | ✔ kelasnya | ✘ |
| Menandai presensi pertemuan | ✔ | ✔ pertemuan yang diampu | ✘ | ✘ |
| Membaca presensi harian kelas | ✔ | ✔ kelas yang diampu / perwalian | ✔ kelasnya | ✔ kelasnya |

## Model Data dan Integritas

17 model Eloquent, 42 berkas migrasi, 27 tabel pada database pengembangan.

### Kunci primer natural

`guru.nip`, `siswa.nis`, `ruangan.kode`, `jurusan.kode`, `tahun_ajaran.kode`
memakai kunci primer natural (`$incrementing = false`). Alasannya: NIP dan NIS
adalah nomor yang tercetak di rapor dan disebut antar-staf, bukan detail internal.
Akibatnya baris presensi langsung menyebut NIS siswanya tanpa join tambahan.

Akun dan orang dipisah: `users` hanya memuat kredensial (`username`, `email`,
`password`, `role`, `status`) ditambah FK `nip`/`nis` ke orangnya.

### Indeks unik

| Nama | Kolom | Menjaga |
|---|---|---|
| `kelas_rombel_unique` | `tahun_ajaran_kode, tingkat, jurusan_kode, paralel` | Satu "XII TKJ 2" per tahun ajaran |
| `jurnal_pertemuan_peran_unique` | `jadwal_id, tanggal, diisi_oleh_peran` | Satu jurnal per sisi per pertemuan |
| `jurnal_public_id_unique` | `public_id` | ULID unik & NOT NULL |
| `presensi_jurnal_siswa_unique` | `jurnal_id, siswa_nis` | Satu baris presensi per siswa per pertemuan |
| `presensi_harian_unik` | `kelas_id, tanggal, siswa_nis` | Satu baris per siswa per hari sekolah |
| `jadwal_kelas_slot_unique` | `kelas_id, hari, jam_ke_mulai` | Kelas tidak dobel-pesan |
| `jadwal_guru_slot_unique` | `guru_nip, hari, jam_ke_mulai` | Guru tidak dobel-pesan |
| `jadwal_ruangan_slot_unique` | `ruangan_kode, hari, jam_ke_mulai` | Ruangan tidak dobel-pesan |
| `kelas_qr_token_unique` | `qr_token` | Token QR unik per kelas |

Indeks unik jadwal hanya membandingkan **JP mulai**. Karena itu pemeriksaan
bentrok yang sebenarnya memakai **perbandingan rentang**
(`jam_ke_mulai <= p_selesai AND jam_ke_selesai >= p_mulai`) di `JadwalRequest`
dan di `sp_tambah_jadwal`: JP 10–11 dan JP 11–12 berbagi JP 11 tanpa berbagi jam
mulai. Indeks unik tetap dipertahankan sebagai benteng terakhir terhadap balapan
antar dua admin.

### Jam pelajaran dihitung backend

Tabel `jadwal` **tidak memiliki** kolom `jam_mulai`/`jam_selesai`. Jam dinding
diturunkan dari nomor JP oleh `App\Support\JamPelajaran` memakai `config/sekolah.php`:
1 JP = 45 menit, bel pertama 07:00, istirahat 15 menit setelah JP 4 dan 30 menit
setelah JP 8, maksimal 12 JP. Menyimpan jam sekaligus nomor JP berarti dua jawaban
untuk satu pertanyaan, dan tidak ada yang tahu mana yang harus dipercaya.

## Alur Jurnal

**Penulis jurnal adalah kelas, diwakili ketua kelas — bukan guru.**

```
Ketua kelas / Admin
    │  POST /jurnal            (web)  atau  POST /api/jurnal
    ▼
JurnalPolicy::create()  →  isAdmin() || isKetuaKelas()
    │
    ├─ Pemeriksaan kepemilikan jadwal (kelas sendiri)
    ├─ Jurnal::sudahAda(jadwal, tanggal, peran)  → 422 bila sudah ada
    ├─ Deteksi jurnal 'sistem'                   → arahkan ke penyuntingan
    ▼
Jurnal::create()  →  public_id ULID dibuat pada event `creating`
```

Ketentuan penting:

- Satu pertemuan boleh memiliki **satu jurnal per sisi**: sisi kelas (`siswa`) dan
  sisi administratif (`guru`). Dijaga `jurnal_pertemuan_peran_unique`.
- **Jurnal otomatis.** Perintah terjadwal `jurnal:isi-otomatis` (00:30, per
  gelombang lewat queue) membuat jurnal `diisi_oleh_peran = 'sistem'` untuk
  pertemuan lampau yang tidak berjurnal. Jurnal itu **tidak** dihitung sebagai
  jurnal terisi (`Jurnal::scopeManusia`), dan menyuntingnya mewajibkan pernyataan
  kejujuran (`pernyataan: true`); setelah disunting, jurnal itu "diadopsi" oleh
  penyuntingnya.
- **Penanda "diedit setelah hari-H"** diset pada event `updating` model, sehingga
  berlaku sama untuk web, API, dan seeder.
- Penghapusan jurnal ikut menghapus roster presensinya (FK cascade) dan memicu
  perhitungan ulang presensi harian melalui event `deleted`.

## Alur Presensi

Ini bagian yang paling sering salah dipahami dari dokumentasi lama, sehingga
ditulis eksplisit.

```
Guru pengampu pertemuan (atau Admin)
    │  POST /jurnal/{public_id}/presensi
    ▼
JurnalPolicy::isiPresensi()
    │  guru dengan jadwal.guru_nip == user.nip, atau admin
    ▼
Validasi: presensi.*.siswa_nis ∈ daftar NIS kelas pertemuan itu
    │
    ▼
SimpanPresensiJurnal::simpan()          ── satu transaksi ──
    ├─ (1) hapus lalu tulis ulang tabel `presensi` untuk jurnal itu   ← SUMBER UTAMA
    └─ (2) segarkanHarian(): hitung ulang `presensi_harian` kelas
             untuk tanggal itu dari SELURUH pertemuan hari tersebut   ← TURUNAN
```

| Pertanyaan | Jawaban dari kode |
|---|---|
| Siapa mengisi jurnal? | Ketua kelas, atau admin (`JurnalPolicy::create`) |
| Siapa mengisi presensi? | Guru yang dijadwalkan mengajar pertemuan itu, atau admin (`JurnalPolicy::isiPresensi`) |
| Presensi sumber utama per pertemuan atau per hari? | **Per pertemuan** (tabel `presensi`, satu baris per siswa per jurnal) |
| Fungsi tabel `presensi` | Catatan kehadiran yang ditandai guru, per mata pelajaran |
| Fungsi tabel `presensi_harian` | Proyeksi satu baris per siswa per hari sekolah, agar rekap/ekspor/dashboard/stored function tidak perlu tahu adanya pertemuan |
| Bagaimana `presensi_harian` dibentuk? | Dihitung ulang dari seluruh roster hari itu; status paling berat menang (`hadir` 0 < `sakit` 1 < `izin` 2 < `alpa` 3), dan keterangan ikut status pemenang |
| Endpoint penulisan presensi | `POST /api/jurnal/{jurnal}/presensi` (web: `POST /jurnal/{jurnal}/presensi`) |
| Endpoint pembacaan presensi harian | `GET /api/presensi` dan `GET /api/presensi/{kelas}?tanggal=` (web: `GET /presensi-harian/{kelas}`) |
| Policy pembacaan harian | `KelasPolicy::lihatPresensiHarian` — admin, ketua kelas, guru pengampu/wali, siswa kelas itu |

Konsekuensi desain yang layak ditulis di pembahasan: seorang siswa dapat hadir di
Matematika JP 1 dan alpa di Fisika JP 5 pada hari yang sama, dan catatannya bisa
mengatakan keduanya. Rekap bulanan tetap menghitung siswa itu **satu kali** untuk
hari tersebut karena membaca `presensi_harian`.

Jalur tambahan: `POST /presensi/mulai` (web, guru/admin) membuka roster untuk
pertemuan yang belum berjurnal, dengan membuat jurnal placeholder berperan
`sistem` yang mencatat guru **hadir** (guru itu jelas ada di ruangan karena sedang
mengabsen).

## Stored Procedure / Function / View / Trigger

Diverifikasi langsung pada MySQL pengembangan melalui `information_schema`
(bukan dari dokumentasi). Hasil inventaris: **7 procedure, 4 function, 2 view,
8 trigger**.

### Stored Procedure

| Nama | Menulis | Fungsi | Berkas migrasi |
|---|---|---|---|
| `sp_tambah_guru` | `guru` + `users` | Menambah guru beserta akunnya dalam satu transaksi | `2026_08_25_000003` |
| `sp_tambah_siswa` | `siswa` + `users` | Menambah siswa beserta akunnya dalam satu transaksi | `2026_08_25_000003` |
| `sp_tambah_mata_pelajaran` | `mata_pelajaran` + `guru_mata_pelajaran` | Menambah mapel beserta guru pengampunya (JSON_TABLE) | `2026_08_26_000001` |
| `sp_tambah_kelas` | `kelas` | Cek tahun ajaran/jurusan/wali + rombel kembar (`FOR UPDATE`) lalu insert | `2026_08_26_000001` |
| `sp_tambah_jadwal` | `jadwal` | Cek JP terbalik, pengampu, bentrok kelas/guru/ruangan (rentang, `FOR UPDATE`) lalu insert | `2026_08_26_000001` |
| `sp_simpan_presensi_harian` | `presensi_harian` | Mengganti seluruh roster satu kelas-hari lewat JSON dalam satu transaksi | `2026_08_19_000004` |
| `sp_simpan_presensi` | `presensi` | **Peninggalan.** Objeknya masih ada, tetapi tidak ada `CALL sp_simpan_presensi` di dalam `app/` | `2026_07_23_080007` |

Kegagalan dilempar sebagai `SIGNAL SQLSTATE '45000'` dengan kode pendek
(`ROMBEL_SUDAH_ADA`, `GURU_BENTROK`, `GURU_TIDAK_DIKENAL`, …). Setiap prosedur
memasang `DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END`.
`App\Support\ProsedurTersimpan` menerjemahkan kode itu menjadi pesan validasi pada
field yang tepat.

### Stored Function

| Nama | Fungsi |
|---|---|
| `fn_persentase_kehadiran_siswa(p_nis)` | Persentase kehadiran seorang siswa, dihitung dari `presensi_harian` |
| `fn_persentase_kehadiran_kelas(p_kelas_id)` | Persentase kehadiran satu kelas, dari `presensi_harian` |
| `fn_nama_duplikat_guru(p_nama)` | Jumlah guru bernama sama (lingkup sekolah) |
| `fn_nama_duplikat_siswa(p_nama, p_kelas_id)` | Jumlah siswa bernama sama di satu kelas |

### View

| Nama | Fungsi |
|---|---|
| `v_jurnal_lengkap` | Satu baris datar per jurnal (jurnal + jadwal + kelas + mapel + guru) |
| `v_rekap_presensi_kelas` | Rekap hadir/sakit/izin/alpa per kelas, dibaca `GET /api/laporan/rekap-kelas` |

### Trigger

| Nama | Waktu | Tabel | Fungsi |
|---|---|---|---|
| `trg_jurnal_after_update` | AFTER UPDATE | `jurnal` | Menulis `jurnal_audit` bila `materi` berubah (perbandingan null-safe `<=>`) |
| `trg_jurnal_after_delete` | AFTER DELETE | `jurnal` | Menulis `jurnal_audit` untuk penghapusan |
| `trg_guru_before_insert` / `trg_guru_before_update` | BEFORE | `guru` | Normalisasi nama, menolak nama kosong / NIP terlalu pendek |
| `trg_siswa_before_insert` / `trg_siswa_before_update` | BEFORE | `siswa` | Sama, untuk NIS |
| `trg_users_before_insert` / `trg_users_before_update` | BEFORE | `users` | Menjaga aturan: akun guru menunjuk baris `guru`, akun siswa menunjuk `siswa`, admin tidak menunjuk keduanya dan membawa namanya sendiri |

### Transaction pada sisi aplikasi

Selain transaksi di dalam prosedur, `DB::transaction()` dipakai pada
`SimpanPresensiJurnal::simpan()`, `SimpanPresensiHarian::simpan()` (jalur
non-MySQL), serta seluruh fallback di `PencatatanAkademik` dan
`PendaftaranPengguna`.

**Catatan jujur untuk pembahasan.** Dari tujuh prosedur, tiga benar-benar menulis
dua tabel (`sp_tambah_guru`, `sp_tambah_siswa`, `sp_tambah_mata_pelajaran`) —
di sanalah transaksi mencegah data setengah jadi. Dua lainnya
(`sp_tambah_kelas`, `sp_tambah_jadwal`) hanya melakukan satu INSERT; nilai
transaksinya bukan atomisitas, melainkan membuat **cek dan tulis menjadi satu
langkah** lewat `FOR UPDATE`, agar dua admin tidak sama-sama lolos pemeriksaan.

## Automated Testing

Perintah yang dijalankan:

```
docker compose exec -T app php artisan test
```

Hasil yang dilaporkan PHPUnit:

```
Tests:    252 passed (903 assertions)
Duration: 708.01s
```

| Indikator | Hasil |
|---|---:|
| Total test | 252 |
| Lulus | 252 |
| Gagal | 0 |
| Dilewati | 0 |
| Assertion | 903 |
| Durasi | 708,01 detik |

Test tersebar pada 22 berkas (21 di `tests/Feature`, 1 di `tests/Unit`) yang mencakup
Authentication, Authorization, REST API, Jurnal, Presensi, Jadwal, Master Data,
Stored Procedure/Transaction, Reporting, Cadangan & Pemulihan, Penanganan Error,
dan Akses QR.

### Perbaikan yang mendahului eksekusi ini

Eksekusi pertama menghasilkan `1 failed, 244 passed (860 assertions)` dari 245
test. Dua hal diperbaiki setelahnya, keduanya berlingkup sempit:

1. **Cacat produksi — statistik kehadiran siswa.**
   `Api\StatistikController@kehadiran` memakai `users.id` sebagai identifier siswa
   pada jalur peran siswa, lalu mengembalikannya sebagai `siswa_nis` dan
   mencocokkannya ke `presensi_harian.siswa_nis`. Persentase kehadiran seorang
   siswa untuk dirinya sendiri karena itu hampir selalu `0`. Jalur siswa kini
   memakai `$user->nis`, dan `Ringkasan::persentaseKehadiranSiswa()` menerima
   `string` (bukan `int`) karena `siswa.nis` adalah `VARCHAR(20)`. Kontrak REST API
   tidak berubah.
2. **Cacat test — asumsi kalender.**
   `PeriodeFilterTest::test_a_narrower_preset_returns_no_more_rows_than_a_wider_one`
   mengandaikan "Minggu Ini" selalu himpunan bagian "Bulan Ini". Pada Sabtu
   5 September 2026 minggu berjalan dimulai 31 Agustus, sehingga asumsi itu gagal.
   Yang diperbaiki adalah **test**-nya — `App\Support\Periode` tidak disentuh
   karena definisi presetnya sudah benar.

Tujuh regression test ditambahkan (6 pada `ApiStatistikKehadiranTest`, 1 pada
`PeriodeFilterTest`), sehingga jumlah test naik dari 245 menjadi 252. Tidak ada
test yang dihapus, dinonaktifkan, atau diturunkan asersinya. Rincian lengkap ada
pada `TESTING_REPORT.md` bagian "Perbaikan Sejak Eksekusi Pertama" dan
`REPORT_MISMATCHES.md` bagian F.1.

## Keterbatasan

1. **Stored procedure MySQL tidak dieksekusi PHPUnit.** Suite berjalan pada SQLite
   in-memory, sehingga yang diuji adalah jalur fallback PHP. Keberadaan dan
   perilaku objek MySQL diverifikasi terpisah lewat `information_schema` dan
   pengujian manual, bukan lewat PHPUnit. Laporan ini **tidak** mengklaim
   sebaliknya.
2. **Trigger audit tidak aktif saat pengujian**, sehingga tabel `jurnal_audit`
   selalu kosong dalam suite dan endpoint `/api/jurnal/{jurnal}/audit` tidak
   terverifikasi otomatis.
3. **Cakupan test REST API masih terbatas.** Hanya `/api/jurnal`,
   `/api/jurnal/{public_id}`, dan `/api/statistik/kehadiran` yang benar-benar
   dipanggil test. Tidak ada test untuk `401` pada `/api/*`, maupun untuk endpoint
   presensi, kelas, jadwal, mapel, users, laporan, dan dashboard melalui API.
   Aturan bisnisnya tetap diuji, tetapi lewat controller web yang memakai service
   dan policy yang sama.
4. **Code coverage tidak tersedia.** Eksekusi dijalankan tanpa `--coverage`, dan
   `phpunit.xml` tidak mengaktifkan laporan coverage.
5. **Dokumentasi lama (README, `docs/API.md`, collection Postman lama) belum
   menyusul refactor presensi.** Perbedaannya didaftar penuh di
   `REPORT_MISMATCHES.md`; dokumentasi tersebut tidak diubah dalam pekerjaan ini.
6. **Satu temuan kode masih terbuka** dan sengaja dibiarkan karena hanya berupa
   komentar: docblock `app/Models/PresensiHarian.php` masih menggambarkan presensi
   harian sebagai catatan yang diisi ketua kelas. Lihat `REPORT_MISMATCHES.md`
   bagian F.2.
7. Pengujian beban, keamanan tingkat jaringan, dan pengujian penerimaan pengguna
   berada di luar lingkup dokumen ini.

## Saran Gambar dan Screenshot untuk BAB 5

### Gambar 1 — Terminal hasil PHPUnit

- **Yang harus terlihat:** perintah `docker compose exec -T app php artisan test`,
  beberapa baris `PASS` dengan nama test — usahakan blok
  `PASS Tests\Feature\ApiStatistikKehadiranTest` dan
  `PASS Tests\Feature\PeriodeFilterTest` ikut terbaca — dan baris penutup
  `Tests: 252 passed (903 assertions)` beserta `Duration: 708.01s`.
- **Caption:** *Gambar 5.x Hasil eksekusi automated testing dengan PHPUnit 12.5.31
  (252 test lulus, 903 assertion).*
- **Alasan:** ini satu-satunya bukti bahwa angka pada laporan berasal dari
  eksekusi nyata. Dua blok yang disarankan itu adalah regression test dari
  perbaikan terakhir, sehingga satu tangkapan layar sekaligus menunjukkan suite
  hijau dan bukti bahwa temuan audit sudah ditutup.

### Gambar 2 — `php artisan route:list --path=api`

- **Yang harus terlihat:** kolom method dan URI, minimal sampai baris
  `Showing [37] routes`. Pastikan baris `api/jurnal/{jurnal}/presensi` dan
  `api/presensi/{kelas}` ikut terbaca.
- **Caption:** *Gambar 5.x Daftar 37 entri route REST API hasil `php artisan
  route:list --path=api`.*
- **Alasan:** membuktikan jumlah dan bentuk endpoint pada BAB 5 bukan rancangan di
  atas kertas, melainkan route yang benar-benar terdaftar.

### Gambar 3 — Postman: login berhasil

- **Yang harus terlihat:** method `POST`, URL `{{base_url}}/login`, body JSON
  (password disamarkan/dummy), status `200 OK`, dan respons yang memuat
  `token_type: "Bearer"` serta `user.role`. Tab **Test Results** menunjukkan
  ketiga assertion hijau.
- **Caption:** *Gambar 5.x Autentikasi REST API dengan Laravel Sanctum
  menghasilkan bearer token.*
- **Alasan:** menunjukkan mekanisme autentikasi token bekerja dan menjadi pijakan
  seluruh gambar berikutnya.

### Gambar 4 — Postman: 401 tanpa token

- **Yang harus terlihat:** request `GET {{base_url}}/me` dengan Auth **No Auth**
  (tab Authorization terlihat), status `401 Unauthorized`, respons
  `{"message":"Unauthenticated."}`.
- **Caption:** *Gambar 5.x Endpoint terlindungi menolak permintaan tanpa token
  (HTTP 401).*
- **Alasan:** bukti lapisan pertama keamanan. Penting menampilkan setelan No Auth
  agar pembaca yakin tidak ada token yang diam-diam terkirim.

### Gambar 5 — Postman: 403 peran tidak berhak

- **Yang harus terlihat:** login sebagai guru terlebih dahulu, lalu
  `POST {{base_url}}/jurnal` menghasilkan `403 Forbidden`. Alternatif kedua:
  `GET {{base_url}}/users` sebagai guru.
- **Caption:** *Gambar 5.x Middleware peran dan JurnalPolicy menolak guru yang
  mencoba membuat jurnal (HTTP 403).*
- **Alasan:** ini pembeda paling penting antara sistem ini dan dokumentasi
  lamanya — jurnal ditulis kelas, bukan guru. Satu tangkapan layar menyelesaikan
  perdebatan itu.

### Gambar 6 — Postman: create dan show jurnal dengan `public_id`

- **Yang harus terlihat:** dua panel berdampingan atau dua tangkapan berurutan.
  (a) `POST /jurnal` status `201` dengan `data.public_id` berupa ULID tersorot.
  (b) `GET /jurnal/{public_id}` status `200`. Bila memungkinkan, tambahkan
  (c) `GET /jurnal/{id numerik}` status `404`.
- **Caption:** *Gambar 5.x Jurnal dialamatkan memakai ULID `public_id`; id numerik
  tidak dapat dipakai pada URL (HTTP 404).*
- **Alasan:** membuktikan klaim "ID disamarkan di URL" pada bab keamanan, sekaligus
  menunjukkan kontrak API yang tetap membawa id numerik di dalam body.

### Gambar 7 — Postman: simpan roster presensi satu pertemuan

- **Yang harus terlihat:** login sebagai guru pengampu,
  `POST {{base_url}}/jurnal/{{jurnal_public_id}}/presensi`, body JSON berisi array
  `presensi` dengan `siswa_nis` dan `status`, status `200`, dan respons berisi
  baris per siswa.
- **Caption:** *Gambar 5.x Penyimpanan daftar hadir satu pertemuan oleh guru
  pengampu.*
- **Alasan:** inti alur presensi versi final. Perlihatkan bahwa alamatnya lewat
  jurnal dan fieldnya `siswa_nis` — dua hal yang berbeda dari dokumentasi lama.

### Gambar 8 — Postman: pembacaan presensi harian

- **Yang harus terlihat:** `GET {{base_url}}/presensi/{{kelas_id}}?tanggal=...`
  status `200`, respons satu baris per siswa dengan `tanggal` dan `status`.
  Sebaiknya diambil **setelah** Gambar 7, memakai tanggal yang sama.
- **Caption:** *Gambar 5.x Presensi harian kelas terbentuk otomatis sebagai
  turunan dari daftar hadir tiap pertemuan.*
- **Alasan:** dipasangkan dengan Gambar 7, ini membuktikan hubungan
  sumber → turunan tanpa perlu membuka database.

### Gambar 9 — Postman: kesalahan validasi / data ganda

- **Yang harus terlihat:** salah satu dari — (a) `POST /jurnal` kedua kali dengan
  `jadwal_id` + `tanggal` sama → `422` dengan `errors.jadwal_id`; atau
  (b) `POST /jurnal/{public_id}/presensi` memakai NIS siswa kelas lain → `422`
  pada `presensi.0.siswa_nis`.
- **Caption:** *Gambar 5.x Pencegahan data ganda dan validasi keanggotaan kelas
  pada REST API (HTTP 422).*
- **Alasan:** menunjukkan integritas data dijaga sebelum menyentuh database, dan
  pesan kesalahannya menempel pada field yang benar.

### Gambar 10 — Objek database pada phpMyAdmin

- **Yang harus terlihat:** dua tangkapan — (a) daftar Routines yang memuat tujuh
  `sp_*` dan empat `fn_*`; (b) daftar Triggers yang memuat delapan trigger.
  Alternatif: hasil kueri `information_schema.ROUTINES` / `TRIGGERS`.
  **Jangan** menampilkan kredensial koneksi pada tangkapan layar.
- **Caption:** *Gambar 5.x Stored procedure, function, dan trigger yang terpasang
  pada basis data MySQL.*
- **Alasan:** ini bukti langsung untuk sub-bab basis data lanjutan. Tanpa gambar
  ini, klaim tentang objek MySQL hanya bertumpu pada berkas migrasi — dan karena
  PHPUnit berjalan di SQLite, gambar inilah yang menutup celah tersebut.
