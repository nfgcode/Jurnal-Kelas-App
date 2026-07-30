<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" alt="Bootstrap 5.3">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL 8.0">
  <img src="https://img.shields.io/badge/Redis-7-DC382D?style=for-the-badge&logo=redis&logoColor=white" alt="Redis 7">
  <img src="https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
  <img src="https://img.shields.io/badge/License-Apache_2.0-blue?style=for-the-badge" alt="License">
</p>

# 📓 Jurnal Kelas App

Aplikasi **Jurnal Kelas** adalah sistem manajemen jurnal pembelajaran dan presensi siswa berbasis web untuk lingkungan sekolah menengah. Dibangun dengan Laravel 13, aplikasi ini menyediakan platform terpadu bagi **Admin**, **Guru**, dan **Siswa** untuk mengelola kegiatan belajar-mengajar secara digital.

---

## ✨ Fitur Utama

### 🔐 Multi-Role Authentication
- **Admin** — login via email
- **Guru** — login via NIP (Nomor Induk Pegawai)
- **Siswa** — login via NIS (Nomor Induk Siswa)
- Token-based API authentication menggunakan Laravel Sanctum

### 📊 Dashboard Role-Based
| Role | Fitur Dashboard |
|------|----------------|
| **Admin** | Statistik sekolah, tren pengisian jurnal, peringatan kehadiran guru, heatmap aktivitas, drill-down interaktif |
| **Guru** | Jadwal mengajar hari ini, status pengisian jurnal, metrik kehadiran per kelas, heatmap aktivitas |
| **Siswa** | Jadwal kelas hari ini, persentase kehadiran per mata pelajaran, heatmap kehadiran |

### 📝 Manajemen Jurnal Kelas
- Pencatatan materi, tugas, kegiatan, dan catatan per pertemuan
- Sistem **dual-fill**: Guru mengisi sendiri, atau **Ketua Kelas** mengisi atas nama guru yang berhalangan
- Tracking status kehadiran guru dengan satu kosakata seragam untuk kedua peran: **Hadir / Tidak Hadir – Ada Tugas / Tidak Hadir – Tanpa Tugas**
- Deteksi pengisian terlambat (>24 jam setelah tanggal pelajaran)
- Full-text search pada materi, tugas, catatan, dan kegiatan
- Audit trail otomatis via database trigger

### 📋 Presensi Siswa
- Input presensi bulk per jurnal (Hadir / Sakit / Izin / Alpa)
- Stored procedure MySQL untuk penyimpanan atomik via JSON
- Unique constraint mencegah duplikasi presensi
- Statistik kehadiran via stored function

### 🏫 Master Data
- **Kelas** — Tingkat (X/XI/XII), jurusan, ruang, kapasitas, wali kelas, tahun ajaran
- **Mata Pelajaran** — Kelompok kurikulum (Wajib / Peminatan / Muatan Lokal / Kejuruan), JP per minggu
- **Jadwal** — Penjadwalan per hari, jam pelajaran, ruang, guru pengampu
- **User Management** — CRUD admin, guru, siswa dengan role & status

### 📈 Laporan Admin
- Laporan jurnal dengan filter periode
- Laporan presensi per kelas
- Rekap kelas (via API)
- Drill-down interaktif dari dashboard
- **Ekspor Excel (.xlsx)** — file spreadsheet asli (OOXML) yang ditulis tanpa dependency tambahan (via `ZipArchive`), khusus admin

### 🎓 Mode Wali Kelas
- Toggle "Mode Wali Kelas" di topbar untuk guru yang menjadi wali kelas (mirip tombol tema)
- Ruang kerja khusus terfokus pada kelas perwaliannya: **Data Kelas, Jadwal Kelas, Jurnal Kelas, Presensi Kelas**
- Rekap kehadiran per siswa + sorot siswa dengan kehadiran terendah

### 📷 Presensi via QR Code (per ruang kelas)
- Tiap kelas punya **QR code unik** (token acak, bukan id berurutan) untuk ditempel di ruangnya
- Guru **scan QR pakai kamera HP** → diarahkan ke website (deploy lokal sekolah) → login → halaman konfirmasi kelas → langsung isi **jurnal + presensi** kelas itu
- **Khusus role guru** — siswa/admin yang membuka URL QR ditolak (403); belum login otomatis dialihkan ke login lalu kembali (`redirect()->intended()`)
- Halaman **cetak QR** untuk admin (`/admin/kelas-qr`), siap potong & tempel, dengan QR SVG (via `endroid/qr-code`, offline)
- Waktu pengisian jurnal tercatat otomatis (`created_at`) dan ditampilkan di detail jurnal ("Diisi Pada")

> **Deploy sekolah:** agar QR bisa dibuka dari HP, set `APP_URL` ke alamat LAN server (mis. `http://192.168.1.10:8888`), **bukan** `localhost`.

### 🔒 Keamanan & Otorisasi
- Otorisasi web berlapis: **middleware peran** (`CheckRole`) di route + **Policy per-record** (`Kelas`, `MataPelajaran`, `Jadwal`, `Jurnal`, `Presensi`)
- **Data scoping per peran**: guru hanya melihat kelas/mapel yang ia ampu; siswa hanya kelasnya sendiri
- Presensi tervalidasi terhadap daftar siswa rombel (mencegah injeksi `siswa_id` asing)
- Concurrency-safe: transaksi + `lockForUpdate` + unique constraint pada penyimpanan presensi

### 📱 UX & Antarmuka
- **Responsif mobile** (Android/iOS): sidebar off-canvas, grid adaptif, kontrol filter full-width, target sentuh nyaman
- **Dropdown ber-pencarian** (progressive enhancement, tanpa dependency) untuk daftar panjang
- Performa: indexing komposit, caching KPI landing, query agregat yang diringkas

### 🚑 Error Handling Ramah-Peran
- Guru & siswa **tidak pernah melihat stack trace Laravel**. Saat terjadi error mereka mendapat
  halaman ramah berbahasa Indonesia + **kode referensi**, dengan tombol **Kembali** / **Ke Dashboard**
- **Admin tetap melihat detail error penuh** — yang bertugas men-debug adalah admin
- Guru/siswa bisa **mengirim laporan error** ke admin dari halaman tersebut. Detail teknis diambil
  dari session (tidak bisa dipalsukan dari browser), bukan dari form
- **Anti-spam berlapis**: 1 laporan per **10 menit** per akun, maksimal **5/hari**, dan **dedupe** —
  error yang sama dilaporkan ulang menambah penghitung `jumlah` alih-alih membuat baris baru
- Halaman 404/403/419/429/500/503 memakai tampilan ramah yang sama (konsisten saat `APP_DEBUG=false`)

### 🩺 Sistem & Log (admin)
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

---

## 🧰 Framework & Teknologi

### Backend
| Teknologi | Versi | Peran |
|-----------|-------|-------|
| [Laravel Framework](https://laravel.com) | 13.x | Framework aplikasi utama (PHP) |
| [PHP](https://www.php.net) | 8.3 | Bahasa & runtime |
| [Laravel Sanctum](https://laravel.com/docs/sanctum) | latest | Autentikasi API berbasis token |
| [Laravel Tinker](https://github.com/laravel/tinker) | 3.x | REPL / console interaktif |
| `ext-zip` (ZipArchive) | bawaan PHP | Penulisan file `.xlsx` (OOXML) tanpa library eksternal |

### Frontend
| Teknologi | Versi | Peran |
|-----------|-------|-------|
| [Blade](https://laravel.com/docs/blade) | — | Templating server-side |
| [Bootstrap](https://getbootstrap.com) | 5.3 | Komponen UI & grid |
| [@popperjs/core](https://popper.js.org) | 2.11 | Positioning untuk dropdown/tooltip Bootstrap |
| [Sass (Dart Sass)](https://sass-lang.com) | 1.77 | Preprocessor CSS (`resources/sass/app.scss`) |
| [Vite](https://vitejs.dev) | 8.x | Build tool & dev server (HMR) |
| [laravel-vite-plugin](https://github.com/laravel/vite-plugin) | 3.x | Integrasi Vite ⇄ Laravel |
| [Bootstrap Icons](https://icons.getbootstrap.com) | 1.11 | Ikon (via CDN) |
| Vanilla JS | — | Drill-down AJAX, searchable-select, loading screen (tanpa framework JS) |

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

## 🏗️ Arsitektur & Tech Stack

```
┌─────────────────────────────────────────────────────────┐
│                      Browser                            │
├────────────────────┬────────────────────────────────────┤
│   Blade + SCSS     │         REST API (JSON)            │
│   Bootstrap 5.3    │         Sanctum Token Auth         │
├────────────────────┴────────────────────────────────────┤
│                  Laravel 13 (PHP 8.3)                   │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐ │
│  │Controller│ │  Policy  │ │  Model   │ │  Support   │ │
│  │ (Web+API)│ │ (Jurnal) │ │(Eloquent)│ │(Periode,   │ │
│  │          │ │          │ │          │ │ Ringkasan, │ │
│  │          │ │          │ │          │ │ LoginRes.) │ │
│  └──────────┘ └──────────┘ └──────────┘ └────────────┘ │
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

## 📦 Entity Relationship

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

## 🚀 Instalasi & Setup

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

## 🔧 Perintah yang Tersedia

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

## 🌐 REST API

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

## 🐳 Docker Services

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

## 📁 Struktur Project

```
Jurnal-Kelas-App/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/            # DashboardController, LaporanController, UserController
│   │   │   ├── Api/              # REST API controllers (mirror web controllers)
│   │   │   ├── Auth/             # LoginController
│   │   │   ├── DashboardController.php
│   │   │   ├── JadwalController.php
│   │   │   ├── JurnalController.php
│   │   │   ├── KelasController.php
│   │   │   ├── LandingController.php
│   │   │   ├── MataPelajaranController.php
│   │   │   └── PresensiController.php
│   │   ├── Middleware/
│   │   │   └── CheckRole.php     # Role-based access control (role:admin,guru,…)
│   │   ├── Requests/             # Form requests dipakai bersama web + API
│   │   │   └── Api/              # JurnalRequest khusus API
│   │   └── Resources/            # API resource transformers
│   ├── Models/                   # Eloquent models (7 models)
│   ├── Policies/                 # KelasPolicy, MataPelajaranPolicy,
│   │                             #   JadwalPolicy, JurnalPolicy, PresensiPolicy
│   └── Support/
│       ├── DbDriver.php          # Deteksi driver (MySQL vs SQLite fallback)
│       ├── LoginResolver.php     # NIP/NIS/Email login resolver
│       ├── Periode.php           # Date range value object
│       ├── Ringkasan.php         # Statistics aggregator
│       ├── SimpanPresensi.php    # Satu jalur simpan presensi (procedure/transaksi)
│       └── XlsxExport.php        # Penulis .xlsx (OOXML) via ZipArchive
├── database/
│   ├── migrations/               # 21 migrations (tables, indexes, views, functions, triggers)
│   └── seeders/
│       └── DemoSeeder.php        # Realistic demo data
├── docker/
│   ├── nginx/                    # Nginx configuration
│   └── php/                      # PHP Dockerfile & config
├── resources/
│   ├── sass/                     # SCSS (Bootstrap 5 + custom + breakpoint mobile)
│   ├── js/                       # JS (Bootstrap, AJAX drill-down, searchable-select)
│   └── views/                    # Blade templates
│       ├── admin/                # Admin views
│       ├── dashboard/            # Role-based dashboard views
│       ├── wali-kelas/           # Mode Wali Kelas (dashboard, data, jadwal, jurnal, presensi)
│       ├── jurnal/               # Jurnal CRUD views
│       ├── presensi/             # Presensi views
│       ├── kelas/                # Kelas views
│       ├── mata-pelajaran/       # Mata pelajaran views
│       ├── jadwal/               # Jadwal views
│       ├── layouts/              # Main layout (sidebar, nav)
│       ├── partials/             # page-loader (loading screen)
│       └── components/           # Reusable Blade components
├── routes/
│   ├── web.php                   # Web routes
│   └── api.php                   # REST API routes
├── docker-compose.yml
├── Makefile
├── setup.sh
└── vite.config.js
```

---

## 🧪 Testing

```bash
# Via Composer
composer test

# Atau langsung
php artisan test

# Di dalam Docker
make artisan cmd="test"
```

---

## ⚙️ Environment Variables

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

## 👥 Kontributor

| Nama | GitHub | Bagian |
|------|--------|--------|
| **Nurfauzan Gymnastiar** | [@nfgcode](https://github.com/nfgcode) | UI/UX, Frontend, Responsive |
| **Akmal Falah Maulana** | [@ShiroTenma](https://github.com/ShiroTenma) | Backend, Database, Responsive, Optimizing & Refactor, Fitur QR Code, Error Handling & Sistem/Healthcheck |

---

## 📄 Lisensi

Project ini dilisensikan di bawah [Apache License 2.0](LICENSE).
