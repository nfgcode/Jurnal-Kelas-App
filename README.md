<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" alt="Bootstrap 5.3">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL 8.0">
  <img src="https://img.shields.io/badge/Redis-7-DC382D?style=for-the-badge&logo=redis&logoColor=white" alt="Redis 7">
  <img src="https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
  <img src="https://img.shields.io/badge/License-Apache_2.0-blue?style=for-the-badge" alt="License">
</p>

# Jurnal Kelas App

Aplikasi **Jurnal Kelas** adalah sistem manajemen jurnal pembelajaran dan presensi siswa berbasis web untuk lingkungan sekolah menengah. Dibangun dengan PHP dengan framework Laravel 13, aplikasi ini menyediakan platform terpadu bagi **Admin**, **Guru**, dan **Siswa** untuk mengelola dan memonitoring kegiatan belajar-mengajar secara digital.

---

## Fitur Utama

### Multi-Role Authentication
- **Admin** — login via email
- **Guru** — login via NIP (Nomor Induk Pegawai)
- **Siswa** — login via NIS (Nomor Induk Siswa)
- Token-based API authentication menggunakan Laravel Sanctum

### Dashboard Role-Based
| Role | Fitur Dashboard |
|------|----------------|
| **Admin** | Statistik sekolah, tren pengisian jurnal, peringatan kehadiran guru, heatmap aktivitas, drill-down interaktif, Sistem Monitor |
| **Guru** | Jadwal mengajar hari ini, status pengisian jurnal, metrik kehadiran per kelas, heatmap aktivitas |
| **Siswa** | Jadwal kelas hari ini, persentase kehadiran per mata pelajaran, heatmap kehadiran |

### Manajemen Jurnal Kelas
- Pencatatan materi, tugas, kegiatan, dan catatan per pertemuan
- Sistem **dual-fill**: Guru mengisi sendiri dan **Ketua Kelas** mengisi jika guru tersebut berhalangan hadir
- Tracking status kehadiran guru dengan satu kosakata seragam untuk kedua peran: **Hadir / Tidak Hadir – Ada Tugas / Tidak Hadir – Tanpa Tugas**
- Deteksi pengisian terlambat (>24 jam setelah tanggal pelajaran)
- Full-text search pada materi, tugas, catatan, dan kegiatan
- **Hapus jurnal** — hanya oleh penulisnya (guru bersangkutan) atau admin. Wali kelas boleh
  *membaca* seluruh jurnal kelas asuhannya tetapi **tidak** boleh menghapus catatan guru lain.
  Dialog konfirmasi menyebutkan **berapa siswa** presensinya ikut terhapus, karena `presensi` dan
  `presensi_log` ber-`ON DELETE CASCADE` pada jurnal — angka kehadiran kelas akan berubah
- Audit trail otomatis via database trigger, termasuk saat penghapusan (`trg_jurnal_after_delete`)

### Presensi Siswa
- Input presensi bulk per jurnal (Hadir / Sakit / Izin / Alpa)
- Stored procedure MySQL untuk penyimpanan atomik via JSON
- Unique constraint mencegah duplikasi presensi
- Statistik kehadiran via stored function

### Master Data
- **Kelas** — Tingkat (X/XI/XII), jurusan, ruang, kapasitas, wali kelas, tahun ajaran
- **Mata Pelajaran** — Kelompok kurikulum (Wajib / Peminatan / Muatan Lokal / Kejuruan), JP per minggu
- **Jadwal** — Penjadwalan per hari, jam pelajaran, ruang, guru pengampu
- **User Management** — CRUD admin, guru, siswa dengan role & status

### Laporan Admin
- Laporan jurnal dengan filter periode
- Laporan presensi per kelas
- Rekap kelas (via API)
- Drill-down interaktif dari dashboard
- **Ekspor Excel (.xlsx)** — file spreadsheet asli (OOXML) yang ditulis tanpa dependency tambahan (via `ZipArchive`), khusus admin

### Mode Wali Kelas
- Toggle "Mode Wali Kelas" di topbar untuk guru yang menjadi wali kelas (mirip tombol tema)
- Ruang kerja khusus terfokus pada kelas perwaliannya: **Data Kelas, Jadwal Kelas, Jurnal Kelas, Presensi Kelas**
- Rekap kehadiran per siswa + sorot siswa dengan kehadiran terendah

### Pengisian Jurnal dan Presensi via QR Code (per ruang kelas)
- Tiap kelas punya **QR code unik** (token acak, bukan id berurutan) untuk ditempel di ruangnya
- Alur kerja pengisian via QR Code:
Guru **scan QR pakai kamera HP** → diarahkan ke website (deploy lokal sekolah) → login → halaman konfirmasi kelas → langsung isi **jurnal + presensi** kelas tersebut
- **Khusus role guru** — siswa/admin yang membuka URL QR ditolak (403); belum login otomatis dialihkan ke login lalu kembali (`redirect()->intended()`)
- Halaman **cetak QR** untuk admin (`/admin/kelas-qr`), siap potong & tempel, dengan QR SVG (via `endroid/qr-code`, offline)
- **Pilih kelas yang dicetak**: bawaannya seluruh kelas, tetapi bisa dipersempit ke satu atau
  beberapa rombel lewat daftar centang — dipakai saat satu ruang pindah, satu QR sobek, atau ada
  rombel baru, tanpa harus mencetak ulang semuanya. Pilihan ikut di URL sehingga bisa di-bookmark
  atau dikirim ke orang yang mencetak
- **Cari & saring kelas**: kotak pencarian nama kelas + filter **Tingkat/Jurusan** menyaring daftar
  centang secara langsung (client-side) — memudahkan menemukan rombel di sekolah dengan puluhan kelas
- **Ukuran cetak A6**: baik cetak lewat browser maupun **Ekspor PDF** (`/admin/kelas-qr/pdf`) menyusun
  **satu QR per A6** (2×2 = empat kartu per lembar A4) lengkap dengan garis potong, mengikuti kelas
  yang dipilih. Dirender `dompdf` (murni PHP, tetap jalan tanpa internet); QR di dalam PDF memakai PNG
  karena dukungan SVG dompdf tidak dapat diandalkan, sementara tampilan layar tetap SVG
- Waktu pengisian jurnal tercatat otomatis (`created_at`) dan ditampilkan di detail jurnal ("Diisi Pada")

> **Deploy sekolah:** agar QR bisa dibuka dari HP, set `APP_URL` ke alamat LAN server (mis. `http://192.168.1.10:8888`), **bukan** `localhost`.

### Keamanan & Otorisasi
- Otorisasi web berlapis: **middleware peran** (`CheckRole`) di route + **Policy per-record**
  (`KelasPolicy`, `MataPelajaranPolicy`, `JadwalPolicy`, `JurnalPolicy`). Presensi tidak punya
  policy sendiri — haknya melekat pada pertemuannya lewat `JurnalPolicy::markRoster()` /
  `viewRoster()`, sehingga "siapa boleh menandai kehadiran" hanya ditulis di satu tempat
- **Data scoping per peran**: guru hanya melihat kelas/mapel yang ia ampu; siswa hanya kelasnya sendiri
- Presensi tervalidasi terhadap daftar siswa rombel (mencegah injeksi `siswa_id` asing)
- Concurrency-safe: transaksi + `lockForUpdate` + unique constraint pada penyimpanan presensi
- **ID jurnal/presensi diabstraksi** di URL web: route key berupa **ULID** (`jurnal.public_id`,
  unik & `NOT NULL`), bukan id berurutan — anti-enumerasi dan tidak memancing orang mengutak-atik
  angka di alamat. Otorisasi tetap lapisan utama; ID abstrak hanya pelengkap. REST API sengaja
  tetap memakai id numerik agar kontraknya tidak berubah
- **Whitelist untuk seluruh input URL**: `?per=` (25/50/75/100), `?sort=`/`?dir=` (peta kolom per
  layar), dan `?preset=`/rentang periode. Nilai tak dikenal jatuh ke default, bukan error — dan
  tidak ada nilai dari URL yang masuk ke SQL secara langsung

### UX & Antarmuka
- **Responsif Perangkat Mobile** (Android/iOS): sidebar off-canvas, grid adaptif, kontrol filter full-width.
  Tombol kehadiran H/S/I/A menyesuaikan dengan besar layar, serta opsi memilih besar font
- **Dropdown ber-pencarian** (progressive enhancement, tanpa dependency) untuk daftar panjang
- **Filter periode** di histori jurnal, riwayat siswa, dan presensi: preset Hari Ini / Minggu Ini /
  Minggu Lalu / Bulan Ini / Bulan Lalu / 30 Hari / Tahun Ini + rentang kustom. Kartu statistik ikut
  periode agar angka bisa konsisten dengan tabel, dan filter yang lain tetap terbawa saat periode diganti
- **Filter Tingkat & Jurusan** di Jurnal, Presensi, Rekap Jurnal, dan Rekap Presensi — satu komponen
  `<x-filter-tingkat-jurusan>` yang menurunkan daftar jurusan otomatis dari kelas yang boleh dilihat
  peran itu (guru hanya jurusan yang ia ampu). Filter ikut sampai ke **ekspor Excel** dan ke
  **drill-down** kartu KPI di halaman rekap — berguna saat sekolah punya banyak rombel/jurusan (mis. SMK)
- **Sortir kolom** dengan panah ↑/↓ dan kolom aktif ter-highlight — **81 kolom di 10 tabel**
  (jurnal guru & siswa, presensi, wali kelas, laporan admin, pengguna, kelas, mata pelajaran):
  tanggal, jam ke, kelas, mapel, guru, materi, tugas, H/S/I/A, persentase, kehadiran guru, status.
  Kolom yang menampilkan **bar persentase diurutkan menurut rasionya**, pertemuan 20/20 tidak boleh kalah dari 25/40 sehingga tidak sepenuhnya semua data asli di tampilkan.
  Kolom yang dihitung di PHP **setelah** paginasi (Kelengkapan, Status kelas, Guru Pengampu)
  sengaja **tidak** diberi sortir: mengurutkannya hanya akan mengacak baris yang sudah terlanjur
  diambil — terlihat berfungsi padahal salah
- **Jumlah baris tabel** dapat dipilih (25/50/75/100) plus **lompat ke halaman** tertentu
- **Pilihan tidak saling menghapus**: mencari atau mengganti filter tetap mempertahankan sortir,
  periode, dan ukuran halaman (`<x-query-hidden>` membawanya sebagai hidden field). Sebelumnya
  mengetik satu kata pencarian diam-diam mereset ketiganya
- **Tombol "Lihat" di kolom Aksi** — Mempermudah User Experience/UX bagi pengguna yang memiliki umur diatas 30 tahun atau orang yang tidak begitu faham.
- **Dropdown jadwal mengikuti tanggal** yang dipilih (bukan seluruh jadwal), ditandai bila slot
  sudah diisi, dan menampilkan pesan "hubungi admin" bila hari itu memang tidak ada jadwal
- Performa: Composite indexing, caching KPI landing, Agregate query yang diringkas

### Bobot Aset & Mode Offline
Sekolah bisa saja menjalankan aplikasi (atau mendeploy project) ini secara intranet atau Local deploy yang hanya terhubung melalui cakupan internet LAN sekolah (tanpa terakses internet keluar) jadi tidak ada satu pun permintaan ke luar saat halaman dibuka — font dan ikon ikut di-bundle, bukan diambil dari CDN.

- **Font Inter** hanya subset **Latin** (paket penuh membawa Cyrillic/Greek/Vietnamese yang tidak
  akan pernah dirender): 58 → **8** berkas font
- **Bootstrap** diimpor per-lapisan. Aplikasi memakai 42 kelas utilitas dan dua komponen (dropdown, modal); grid, tabel, form, tombol, navbar, alert, carousel, tooltip
  dan lainnya tidak dirujuk
- **Ikon sebagai SVG inline** (`App\Support\Ikon`), bukan menggunakan icon font. Bootstrap Icons mengirim
  stylesheet 2078 kelas plus webfont ~134 KB untuk 30 glyph yang dipakai; ketiganya kini tidak
  di masukkan sama sekali, dan ikon tidak akan tampil sebagai kotak
  kosong karena font gagal dimuat. Hal ini dikarenakan adanya `IkonTest` yang jika di website tersebut memakai ikon yang belum terdaftar maka secara otomatis akan
  **menggagalkan test**
- Halaman error tetap **berdiri sendiri**: CSS inline dan lambangnya SVG inline, supaya tetap
  tampil justru ketika yang rusak adalah pipeline aset

Kira kira seperti ini hasil dari optimalisasinya

| | Sebelum | Sesudah |
|---|---|---|
| CSS | 321,3 KB | **124,8 KB** |
| JS | 85,7 KB | **58,7 KB** |
| Webfont ikon | 134 KB woff2 + 180 KB woff | **tidak ada** |
| Berkas font total | 58 | **8** (Inter Latin saja) |

Ukuran di atas adalah berkas nyata di `public/build/assets/` setelah `bun run build`.

### Error Handling Ramah-Peran
- Guru & siswa **tidak pernah melihat stack trace Laravel**. Saat terjadi error, mereka mendapat
  halaman ramah berbahasa Indonesia + **kode referensi**, dengan tombol **Kembali** / **Ke Dashboard**
- **Admin tetap melihat detail error penuh** — yang bertugas men-debug adalah admin
- Guru/siswa bisa **mengirim laporan error** ke admin dari halaman tersebut. Detail teknis diambil
  dari session (tidak bisa dipalsukan dari browser), bukan dari form
- **Anti-spam berlapis**: 1 laporan per **10 menit** per akun, maksimal **5/hari**, dan **dedupe** —
  error yang sama dilaporkan ulang menambah penghitung `jumlah` alih-alih membuat baris baru
- Halaman 404/403/419/429/500/503 memakai tampilan ramah yang sama (konsisten saat `APP_DEBUG=false`)

### Sistem & Log (admin)
- **Status komponen** langsung: database + latensi, **migrasi tertunda** (menangkap kelas bug
  "tabel belum ada"), Cache/Redis, storage writable, ukuran log, keberadaan **objek DB lanjutan**
  (view/function/procedure/trigger), serta kewajaran konfigurasi (`APP_DEBUG`, **`APP_URL` bukan
  localhost** — penting untuk QR kelas)
- **Log viewer**: hanya bagian akhir berkas log yang dibaca (aman untuk log besar), difilter per level,
  dengan aksi bersihkan log
- **Inbox laporan error** dari guru/siswa beserta triase status (baru → diproses → selesai)
- **Pengelola pengumuman**: banner untuk guru & siswa saat pemeliharaan/gangguan — tanpa perlu
  `artisan down` yang juga akan mematikan akses admin. Banner gangguan juga muncul otomatis
  (generik, tanpa detail teknis) bila healthcheck ringkas mendeteksi masalah

### Cadangan & Pemulihan Data (admin)
Untuk **pindah server** atau **pemulihan** saat server bermasalah, tanpa perlu akses shell ke database
(`/admin/cadangan`, khusus admin).
- **Ekspor JSON** (format restore) — seluruh tabel beserta id & relasi, **di-stream tabel-per-tabel**
  lalu **di-gzip** (`.json.gz`, ±20× lebih kecil) sehingga tabel presensi ratusan ribu baris tidak
  pernah dimuat utuh ke memori
- **Ekspor XLSX** yang bisa dibaca/diedit (satu sheet per tabel master; presensi & kolom sensitif
  seperti password di-strip) — untuk sekadar melihat/mengolah data di spreadsheet
- **Pilih tabel** yang dicadangkan lewat centang per tabel, untuk backup sebagian
- **Pemulihan (restore)** dari berkas JSON atau `.json.gz` (gzip dideteksi dari *magic byte*, bukan
  ekstensi), dua mode: **Gabung** (upsert per id, tidak menghapus apa pun) atau **Ganti total**
  (kosongkan lalu isi ulang persis isi backup) — mode merusak wajib mencentang konfirmasi
- Seluruh restore berjalan **dalam satu transaksi dengan FK dimatikan sementara** (menangani siklus
  FK `users` ↔ `kelas`); bila gagal di tengah jalan, tidak ada perubahan yang tersimpan

---

## Framework & Teknologi

### Backend
| Teknologi | Versi | Peran |
|-----------|-------|-------|
| [Laravel Framework](https://laravel.com) | 13.x | Framework aplikasi utama (PHP) |
| [PHP](https://www.php.net) | 8.3 | Bahasa & runtime |
| [Laravel Sanctum](https://laravel.com/docs/sanctum) | latest | Autentikasi API berbasis token |
| [Laravel Tinker](https://github.com/laravel/tinker) | 3.x | REPL / console interaktif |
| `ext-zip` (ZipArchive) | bawaan PHP | Penulisan file `.xlsx` (OOXML) tanpa library eksternal |
| [dompdf/dompdf](https://github.com/dompdf/dompdf) | 3.x | Ekspor PDF lembar QR kelas — murni PHP, tanpa binary eksternal, tetap jalan offline |

### Frontend
| Teknologi | Versi | Peran |
|-----------|-------|-------|
| [Blade](https://laravel.com/docs/blade) | — | Templating server-side |
| [Bootstrap](https://getbootstrap.com) | 5.3 | Diimpor **per-lapisan**, bukan sebagai bundel: utility API + dropdown + modal saja. Grid, tabel, form, tombol, navbar, alert, carousel, tooltip tidak dipakai sehingga tidak ikut dikirim |
| [@popperjs/core](https://popper.js.org) | 2.11 | Positioning dropdown Bootstrap (dependensi dropdown; tooltip/popover tidak dibundel) |
| [@fontsource/inter](https://fontsource.org/fonts/inter) | 5.x | Font Inter **self-hosted**, subset Latin — tidak ada permintaan ke Google Fonts |
| [Sass (Dart Sass)](https://sass-lang.com) | 1.77 | Preprocessor CSS (`resources/sass/app.scss`) |
| [Vite](https://vitejs.dev) | 8.x | Build tool & dev server (HMR) |
| [laravel-vite-plugin](https://github.com/laravel/vite-plugin) | 3.x | Integrasi Vite ⇄ Laravel |
| [Bootstrap Icons](https://icons.getbootstrap.com) | 1.13 | **Sumber jalur SVG saja** — bukan dependensi runtime. Ikon di-inline lewat `App\Support\Ikon`; stylesheet & webfont-nya tidak ikut dibundel |
| Vanilla JS | — | Drill-down AJAX, searchable-select (tanpa framework JS) |

### Database & Infrastruktur
| Teknologi | Versi | Peran |
|-----------|-------|-------|
| [MySQL](https://www.mysql.com) | 8.0 | Database utama (+ view, function, procedure, trigger, FULLTEXT) |
| [SQLite](https://www.sqlite.org) | — | Database test suite (in-memory) |
| [Redis](https://redis.io) | 7 | Session, cache, & queue |
| [Docker](https://www.docker.com) / Compose | — | Orkestrasi multi-container |
| [Nginx](https://nginx.org) | Alpine | Web server / reverse proxy |
| [Mailpit](https://github.com/axllent/mailpit) | — | Penangkap email untuk pengujian |
| [Bun](https://bun.sh) | Alpine | Runtime & package manager untuk build frontend |
| [phpMyAdmin](https://www.phpmyadmin.net) | 5 | Manajemen database (profile `dev`) |

### Dev Tools & Testing
| Teknologi | Peran |
|-----------|-------|
| [PHPUnit](https://phpunit.de) 12 | Test suite (Feature/Unit) |
| [Laravel Pint](https://laravel.com/docs/pint) | Code style fixer (PSR-12) |
| [Mockery](https://github.com/mockery/mockery) | Mocking untuk test |
| [FakerPHP](https://fakerphp.github.io) | Data dummy untuk seeder/factory |
| [Laravel Pail](https://github.com/laravel/pail) | Tail log real-time |
| [Nunomaduro Collision](https://github.com/nunomaduro/collision) | Error reporting CLI yang rapi |

---

## Arsitektur & Tech Stack

```
┌─────────────────────────────────────────────────────────┐
│                      Browser                            │
├────────────────────┬────────────────────────────────────┤
│   Blade + SCSS     │         REST API (JSON)            │
│   Bootstrap 5.3    │         Sanctum Token Auth         │
├────────────────────┴────────────────────────────────────┤
│                  Laravel 13 (PHP 8.3)                   │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐  │
│  │Controller│ │  Policy  │ │  Model   │ │  Support   │  │
│  │ (Web+API)│ │ (Jurnal) │ │(Eloquent)│ │(Periode,   │  │
│  │          │ │          │ │          │ │ Ringkasan, │  │
│  │          │ │          │ │          │ │ LoginRes.) │  │
│  └──────────┘ └──────────┘ └──────────┘ └────────────┘  │
├─────────────────────────────────────────────────────────┤
│                     MySQL 8.0                           │
│  Views │ Stored Functions │ Stored Procedures │Triggers │
├─────────────────────────────────────────────────────────┤
│       Redis 7 (Session / Cache / Queue)                 │
└─────────────────────────────────────────────────────────┘
```

### Database Objects

| Tipe | Nama | Keterangan |
|------|------|------------|
| View | `v_jurnal_lengkap` | Flatten jurnal + jadwal + kelas + mapel + guru |
| View | `v_rekap_presensi_kelas` | Agregasi kehadiran per kelas |
| Function | `fn_persentase_kehadiran_siswa` | Persentase kehadiran siswa |
| Function | `fn_persentase_kehadiran_kelas` | Persentase kehadiran kelas |
| Procedure | `sp_simpan_presensi` | Simpan presensi bulk via JSON atomik |
| Trigger | `trg_jurnal_after_update` | Audit log perubahan jurnal |
| Trigger | `trg_jurnal_after_delete` | Audit log penghapusan jurnal |

---

## Entity Relationship

```
User (admin/guru/siswa)
 ├── belongsTo → Kelas (siswa only, via kelas_id)
 ├── hasMany   → Jadwal (guru only, via guru_id)
 ├── hasMany   → Jurnal (guru only, via guru_id)
 └── hasMany   → Presensi (siswa only, via siswa_id)

Kelas
 ├── belongsTo → User (wali_kelas_id)
 ├── hasMany   → User (siswa)
 └── hasMany   → Jadwal

MataPelajaran
 └── hasMany   → Jadwal

Jadwal
 ├── belongsTo → Kelas
 ├── belongsTo → MataPelajaran
 ├── belongsTo → User (guru)
 └── hasMany   → Jurnal

Jurnal
 ├── belongsTo → Jadwal
 ├── belongsTo → User (guru)
 ├── belongsTo → User (diisi_oleh / ketua kelas)
 ├── hasMany   → Presensi
 └── hasMany   → JurnalAudit

Presensi
 ├── belongsTo → Jurnal
 └── belongsTo → User (siswa)
```

---

## Instalasi & Setup

### Prasyarat

- [Docker](https://docs.docker.com/get-docker/) & [Docker Compose](https://docs.docker.com/compose/install/)
- Git

### Quick Start (Docker)

```bash
# 1. Clone repository
git clone https://github.com/your-username/Jurnal-Kelas-App.git
cd Jurnal-Kelas-App

# 2. Build & start containers
docker compose up -d --build

# 3. Jalankan setup otomatis
make setup
# atau langsung: bash setup.sh
```

Setup script akan otomatis:
1. Install Composer dependencies
2. Membuat `.env` dari `.env.example` & generate app key
3. Menjalankan migrasi database (termasuk views, functions, procedures, triggers)
4. Seed data demo (admin, 12 guru, 18 mapel, 12 kelas, 360 siswa, jadwal lengkap, jurnal & presensi historis)
5. Install JS dependencies via Bun
6. Build production assets

### Akses Aplikasi

| Service | URL | Keterangan |
|---------|-----|------------|
| 🌐 Aplikasi | http://localhost:8888 | Web utama |
| 📧 Mailpit | http://localhost:8025 | Email testing UI |
| 🗄️ phpMyAdmin | http://localhost:8081 | Database management (profile `dev`) |
| ⚡ Vite HMR | http://localhost:5173 | Hot Module Replacement (profile `dev`) |

### Default Login

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin@jurnalkelas.app` | `password` |
| Guru | `budi.santoso@jurnalkelas.app` | `password` |

> **Tip:** Guru juga bisa login menggunakan NIP, dan Siswa menggunakan NIS.

### Setup Lokal (Tanpa Docker)

```bash
# 1. Install dependencies
composer install
npm install  # atau: bun install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Konfigurasi database
# Edit .env: uncomment DB_CONNECTION=sqlite (atau atur MySQL lokal)

# 4. Migrasi & seed
php artisan migrate
php artisan db:seed

# 5. Jalankan development server
composer dev
# Atau manual:
php artisan serve
npm run dev  # di terminal terpisah
```

---

## Perintah - Perintah

### Makefile (Docker)

```bash
make help           # Tampilkan semua perintah
make build          # Build Docker images
make up             # Start semua container
make up-dev         # Start semua container + dev tools (phpMyAdmin, Vite HMR)
make down           # Stop semua container
make restart        # Restart semua container
make logs           # Lihat log container (follow mode)
make shell          # Buka shell di app container
make artisan cmd="" # Jalankan artisan command
make migrate        # Jalankan migrasi database
make seed           # Jalankan seeder
make fresh          # Fresh migration + seed
make dev            # Start Vite dev server
make build-assets   # Build production assets
make setup          # Jalankan initial setup
```

### Composer Scripts

```bash
composer setup      # Full setup: install, env, key, migrate, npm, build
composer dev        # Jalankan server + queue + pail + vite secara bersamaan
composer test       # Jalankan test suite (PHPUnit)
composer lint       # Rapikan code style otomatis (Laravel Pint)
composer quality    # Gate: cek code style (pint --test) + jalankan test suite
```

---

## REST API

Semua endpoint API menggunakan prefix `/api` dan autentikasi via **Laravel Sanctum** bearer token.

### Autentikasi

```bash
# Login - dapatkan bearer token
POST /api/login
Body: { "user": "admin@jurnalkelas.app", "password": "password", "role": "admin" }

# Logout
POST /api/logout
Header: Authorization: Bearer {token}

# Info user yang sedang login
GET /api/me
```

### Endpoints

| Method | Endpoint | Akses | Keterangan |
|--------|----------|-------|------------|
| `GET` | `/api/dashboard` | All | KPI summary sesuai role |
| `GET` | `/api/kelas` | All | Daftar kelas |
| `GET` | `/api/mata-pelajaran` | All | Daftar mata pelajaran |
| `GET` | `/api/jadwal` | All | Daftar jadwal |
| `GET` | `/api/jurnal` | All | Daftar jurnal (scoped by role) |
| `POST` | `/api/jurnal` | Guru | Buat jurnal baru |
| `PUT` | `/api/jurnal/{id}` | Guru/Admin | Update jurnal |
| `DELETE` | `/api/jurnal/{id}` | Guru/Admin | Hapus jurnal |
| `GET` | `/api/jurnal/{id}/audit` | All | Audit trail jurnal |
| `GET` | `/api/presensi` | All | Daftar presensi |
| `POST` | `/api/presensi` | Guru | Input presensi bulk |
| `GET` | `/api/presensi/{jurnal}` | All | Presensi per jurnal |
| `GET` | `/api/statistik/kehadiran` | All | Statistik kehadiran |
| `POST/PUT/DELETE` | `/api/kelas/*` | Admin | Kelola kelas |
| `POST/PUT/DELETE` | `/api/mata-pelajaran/*` | Admin | Kelola mata pelajaran |
| `POST/PUT/DELETE` | `/api/jadwal/*` | Admin | Kelola jadwal |
| `GET/POST/PUT/DELETE` | `/api/users/*` | Admin | Kelola user |
| `GET` | `/api/laporan/jurnal` | Admin | Laporan jurnal |
| `GET` | `/api/laporan/presensi` | Admin | Laporan presensi |
| `GET` | `/api/laporan/rekap-kelas` | Admin | Rekap per kelas |

---

## Docker Services (Containers yang dibuat)

| Container | Image | Port | Keterangan |
|-----------|-------|------|------------|
| `jurnal-kelas-app` | PHP 8.3-FPM Alpine | — | Aplikasi Laravel + Supervisord |
| `jurnal-kelas-nginx` | Nginx Alpine | `8888` | Web server |
| `jurnal-kelas-mysql` | MySQL 8.0 | `3306` | Database server |
| `jurnal-kelas-redis` | Redis 7 Alpine | `6379` | Session, cache, queue |
| `jurnal-kelas-mailpit` | Mailpit | `8025` / `1025` | Email testing |
| `jurnal-kelas-bun` | Bun Alpine | `5173` | Vite HMR (profile: `dev`) |
| `jurnal-kelas-phpmyadmin` | phpMyAdmin 5 | `8081` | DB admin (profile: `dev`) |

> Container `bun` dan `phpmyadmin` hanya aktif dengan profile `dev`:
> ```bash
> docker compose --profile dev up -d
> # atau: make up-dev
> ```

---

## Struktur Project

```
Jurnal-Kelas-App/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/            # Dashboard, Laporan, User, Sistem, Cadangan (backup/restore),
│   │   │   │                     #   KelasQr (cetak/PDF), PresensiLog
│   │   │   ├── Api/              # REST API controllers (mirror web controllers)
│   │   │   ├── Auth/             # LoginController
│   │   │   ├── DashboardController.php
│   │   │   ├── JadwalController.php
│   │   │   ├── JurnalController.php
│   │   │   ├── KelasController.php
│   │   │   ├── LandingController.php
│   │   │   ├── LaporanErrorController.php   # Laporan error dari guru/siswa
│   │   │   ├── MataPelajaranController.php
│   │   │   ├── PresensiController.php
│   │   │   ├── QrController.php             # Landing hasil scan QR ruang kelas
│   │   │   └── WaliKelasController.php      # Mode Wali Kelas
│   │   ├── Middleware/
│   │   │   └── CheckRole.php     # Role-based access control (role:admin,guru,…)
│   │   ├── Requests/             # Form requests dipakai bersama web + API
│   │   │   └── Api/              # JurnalRequest khusus API
│   │   └── Resources/            # API resource transformers
│   ├── Models/                   # 10 Eloquent models
│   ├── Policies/                 # KelasPolicy, MataPelajaranPolicy, JadwalPolicy, JurnalPolicy
│   │                             #   (presensi diotorisasi lewat JurnalPolicy::markRoster)
│   └── Support/
│       ├── CadanganData.php      # Ekspor/impor seluruh data (backup JSON gzip + restore, XLSX)
│       ├── DbDriver.php          # Deteksi driver (MySQL vs SQLite fallback)
│       ├── Halaman.php           # Whitelist ukuran halaman (25/50/75/100)
│       ├── Ikon.php              # Ikon sebagai SVG inline (pengganti icon font)
│       ├── LoginResolver.php     # NIP/NIS/Email login resolver
│       ├── PembacaLog.php        # Pembaca ekor berkas log (aman untuk log besar)
│       ├── Periode.php           # Date range value object
│       ├── PesanError.php        # Kata-kata halaman error ramah-peran
│       ├── Ringkasan.php         # Statistics aggregator
│       ├── SimpanPresensi.php    # Satu jalur simpan presensi (procedure/transaksi)
│       ├── SistemStatus.php      # Healthcheck komponen untuk halaman Sistem & Log
│       ├── Urutan.php            # Sortir kolom ber-whitelist (?sort=/?dir=)
│       └── XlsxExport.php        # Penulis .xlsx (OOXML) via ZipArchive
├── database/
│   ├── migrations/               # 31 migrations (tables, indexes, views, functions, triggers)
│   └── seeders/
│       ├── DemoSeeder.php        # Data demo default (dipakai make setup & test suite)
│       └── SmkSeeder.php         # Simulasi SMK besar (10 jurusan, ~45 rombel) — dev-only, opsional
├── docker/
│   ├── nginx/                    # Nginx configuration
│   └── php/                      # PHP Dockerfile & config
├── resources/
│   ├── sass/                     # SCSS (lapisan Bootstrap terpilih + custom + breakpoint mobile)
│   ├── js/                       # JS (dropdown/modal Bootstrap, AJAX drill-down, searchable-select)
│   └── views/                    # Blade templates
│       ├── admin/                # Admin views (users, laporan, sistem, kelas-qr, presensi-log, cadangan)
│       ├── dashboard/            # Role-based dashboard views
│       ├── wali-kelas/           # Mode Wali Kelas (dashboard, data, jadwal, jurnal, presensi)
│       ├── jurnal/               # Jurnal CRUD views
│       ├── presensi/             # Presensi views
│       ├── kelas/                # Kelas views
│       ├── mata-pelajaran/       # Mata pelajaran views
│       ├── jadwal/               # Jadwal views
│       ├── qr/                   # Halaman konfirmasi setelah scan QR
│       ├── errors/               # ramah.blade.php + view konvensi 403/404/419/429/500/503
│       ├── layouts/              # Main layout (sidebar, nav)
│       └── components/           # 25 komponen Blade — a.l. x-ikon, x-th-sort, x-pager,
│                                 #   x-periode-filter, x-query-hidden, x-filter-tingkat-jurusan
├── routes/
│   ├── web.php                   # Web routes
│   └── api.php                   # REST API routes
├── docker-compose.yml
├── Makefile
├── setup.sh
└── vite.config.js
```

---

## Testing

```bash
# Via Composer
composer test

# Pint + seluruh suite sekaligus (gerbang sebelum commit)
composer quality

# Atau langsung
php artisan test

# Di dalam Docker
make artisan cmd="test"
```

Suite berjalan di **SQLite in-memory**, sehingga objek DB khusus MySQL (view, function,
procedure, trigger, FULLTEXT) dilewati lewat cabang `DbDriver::mysql()` dan test tetap
bisa dijalankan tanpa MySQL.

Yang dijaga suite — tiap berkas mengunci satu kelas kesalahan yang pernah benar-benar terjadi:

| Berkas | Yang dikunci |
|--------|--------------|
| `AuthorizationTest` | Batas peran: master data tertutup untuk siswa, guru tidak menyentuh kelas/jurnal orang lain, wali kelas boleh **baca** tapi tidak boleh **hapus**, ekspor khusus admin, id angka lama tidak lagi resolve |
| `JurnalGandaTest` | Satu jurnal per sisi per pertemuan (guru + ketua), termasuk saat unique index yang menolaknya |
| `PresensiRosterTest` | Satu roster per pertemuan agar kehadiran tidak terhitung dua kali + audit log |
| `JadwalFormTest` | Dropdown jadwal mengikuti tanggal, slot terisi ditandai, hari kosong menjelaskan diri |
| `PeriodeFilterTest` | Preset periode benar-benar menyaring, tidak melebarkan cakupan peran, dan filter form tidak membuang sortir/periode/ukuran halaman |
| `PaginationTest` | Whitelist `?per=`; ukuran halaman tidak bisa dipakai memperlebar akses |
| `QrAksesTest` | QR guru-only, pilih sebagian kelas, unduhan PDF benar-benar PDF & admin-only |
| `IkonTest` | Setiap ikon yang dipakai ada — mencegah ikon tampil kosong diam-diam |
| `ErrorHandlingTest` | Guru/siswa tidak pernah melihat stack trace; admin tetap melihat detail |
| `RolePagesTest`, `CrudPagesTest`, `AdminSectionTest`, `DashboardPeriodeTest`, `LoginTest` | Setiap halaman per peran merender, form CRUD bekerja, login per peran |

---

## Environment Variables

Variabel penting di `.env`:

| Variable | Default | Keterangan |
|----------|---------|------------|
| `APP_NAME` | `Jurnal Kelas` | Nama aplikasi |
| `APP_URL` | `http://localhost:8888` | URL aplikasi — **set ke alamat LAN sekolah** (mis. `http://192.168.1.10:8888`) di deploy nyata agar QR code kelas bisa dibuka dari HP guru |
| `APP_LOCALE` | `id` | Bahasa Indonesia |
| `APP_DEBUG` | `true` | Detail error untuk admin. **Set `false` di produksi** — guru/siswa sudah selalu mendapat halaman ramah, tapi `false` menutup detail untuk semua peran |
| `DB_CONNECTION` | `mysql` | Driver database |
| `DB_HOST` | `mysql` | Host DB (nama container Docker) |
| `DB_DATABASE` | `jurnal_kelas` | Nama database |
| `SESSION_DRIVER` | `redis` | Session storage |
| `CACHE_STORE` | `redis` | Cache backend |
| `QUEUE_CONNECTION` | `redis` | Queue backend |
| `MAIL_HOST` | `mailpit` | SMTP server (Mailpit di Docker) |

> Untuk development lokal tanpa Docker, ubah `DB_CONNECTION=sqlite` dan comment konfigurasi MySQL.

---

## Kontributor

| Nama | GitHub | Bagian |
|------|--------|--------|
| **Nurfauzan Gymnastiar** | [@nfgcode](https://github.com/nfgcode) | UI/UX, Frontend, Responsive |
| **Akmal Falah Maulana** | [@ShiroTenma](https://github.com/ShiroTenma) | Backend, Database, Responsive, Optimizing & Refactor, Fitur QR Code (+ cetak/ekspor PDF), Error Handling, Otorisasi & ID abstrak, Filter periode / sortir / paginasi, Optimasi bobot aset & mode offline |

---

## 📄 Lisensi

Project ini dilisensikan di bawah [Apache License 2.0](LICENSE).
