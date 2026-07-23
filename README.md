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
- Tracking status kehadiran guru (Hadir / Sakit / Izin / Alpa)
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
```

---

## 🌐 REST API

Semua endpoint API menggunakan prefix `/api` dan autentikasi via **Laravel Sanctum** bearer token.

### Autentikasi

```bash
# Login - dapatkan bearer token
POST /api/login
Body: { "login": "admin@jurnalkelas.app", "password": "password" }

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
│   │   │   └── CheckRole.php     # Role-based access control
│   │   ├── Requests/Api/         # Form request validation
│   │   └── Resources/            # API resource transformers
│   ├── Models/                   # Eloquent models (7 models)
│   ├── Policies/
│   │   └── JurnalPolicy.php      # Authorization rules
│   └── Support/
│       ├── LoginResolver.php     # NIP/NIS/Email login resolver
│       ├── Periode.php           # Date range value object
│       └── Ringkasan.php         # Statistics aggregator
├── database/
│   ├── migrations/               # 20 migrations (tables, views, functions, triggers)
│   └── seeders/
│       └── DemoSeeder.php        # Realistic demo data
├── docker/
│   ├── nginx/                    # Nginx configuration
│   └── php/                      # PHP Dockerfile & config
├── resources/
│   ├── sass/                     # SCSS (Bootstrap 5 + custom)
│   ├── js/                       # JavaScript (Bootstrap + AJAX drill-downs)
│   └── views/                    # Blade templates
│       ├── admin/                # Admin views
│       ├── dashboard/            # Role-based dashboard views
│       ├── jurnal/               # Jurnal CRUD views
│       ├── presensi/             # Presensi views
│       ├── kelas/                # Kelas views
│       ├── mata-pelajaran/       # Mata pelajaran views
│       ├── jadwal/               # Jadwal views
│       ├── layouts/              # Main layout (sidebar, nav)
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
| `APP_URL` | `http://localhost:8888` | URL aplikasi |
| `APP_LOCALE` | `id` | Bahasa Indonesia |
| `DB_CONNECTION` | `mysql` | Driver database |
| `DB_HOST` | `mysql` | Host DB (nama container Docker) |
| `DB_DATABASE` | `jurnal_kelas` | Nama database |
| `SESSION_DRIVER` | `redis` | Session storage |
| `CACHE_STORE` | `redis` | Cache backend |
| `QUEUE_CONNECTION` | `redis` | Queue backend |
| `MAIL_HOST` | `mailpit` | SMTP server (Mailpit di Docker) |

> Untuk development lokal tanpa Docker, ubah `DB_CONNECTION=sqlite` dan comment konfigurasi MySQL.

---

## 📄 Lisensi

Project ini dilisensikan di bawah [Apache License 2.0](LICENSE).
