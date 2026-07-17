# Jurnal Kelas App

[![Laravel Version](https://img.shields.io/badge/Laravel-v10.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-v8.1+-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![MySQL Version](https://img.shields.io/badge/MySQL-v8.0+-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com)

Aplikasi **Jurnal Kelas** adalah platform berbasis web yang dirancang untuk mendokumentasikan kegiatan belajar-mengajar harian untuk guru, dan kehadiran siswa, serta catatan penting kelas lainnya secara terstruktur dan efisien.

---

## 🚀 Fitur Utama
- **Autentikasi & Otorisasi:** Sistem login multi-role (Admin, Guru, Siswa).
- **Manajemen Kelas:** Pengelolaan data kelas dan mata pelajaran.
- **Jurnal Harian:** Pencatatan materi pembelajaran harian, tugas, dan catatan kelas.
- **Kehadiran/Presensi:** Pencatatan kehadiran siswa (Hadir, Sakit, Izin, Alpa).
- **Laporan/Rekapitulasi:** Unduh rekap jurnal dan absensi (PDF/Excel).

---

## 🛠️ Teknologi yang Digunakan
- **Framework:** [Laravel](https://laravel.com/) (PHP)
- **Database:** [MySQL](https://www.mysql.com/)
- **Frontend/Styling:** HTML, CSS, JavaScript (TailwindCSS / Bootstrap)
- **Package Manager:** Composer (PHP) & NPM (JavaScript)

---

## 📋 Prasyarat Sistem
Sebelum memulai instalasi, pastikan sistem Anda telah terpasang:
- PHP >= 8.1
- Composer >= 2.x
- MySQL >= 8.0
- Node.js & NPM (untuk aset frontend)

---

## ⚙️ Panduan Instalasi

Ikuti langkah-langkah di bawah ini untuk menjalankan proyek di komputer lokal Anda:

### 1. Klon Repositori
```bash
git clone https://github.com/username/Jurnal-Kelas-App.git
cd Jurnal-Kelas-App
```

### 2. Instal Dependensi PHP
```bash
composer install
```

### 3. Instal Dependensi Frontend (NPM)
```bash
npm install
```

### 4. Salin Berkas Lingkungan (.env)
Salin berkas `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
*(Di Windows/PowerShell, gunakan: `copy .env.example .env`)*

### 5. Konfigurasi Database
Buka berkas `.env` yang baru dibuat, lalu sesuaikan konfigurasi database Anda:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jurnal_kelas_db
DB_USERNAME=root
DB_PASSWORD=
```
> **Catatan:** Pastikan Anda telah membuat database kosong bernama `jurnal_kelas_db` di server MySQL Anda (misal menggunakan phpMyAdmin).

### 6. Generate Application Key
```bash
php artisan key:generate
```

### 7. Migrasi Database dan Seed Data
Jalankan migrasi untuk membuat tabel beserta data awal (jika ada):
```bash
php artisan migrate --seed
```

### 8. Build Aset Frontend
Jalankan perintah berikut untuk mengompilasi aset frontend (seperti CSS/JS):
```bash
npm run dev
# atau untuk produksi: npm run build
```

### 9. Jalankan Server Lokal
Mulai server pengembangan lokal Laravel:
```bash
php artisan serve
```
Aplikasi Anda sekarang dapat diakses melalui browser di alamat [http://127.0.0.1:8000](http://127.0.0.1:8000).

---

## 📂 Struktur Direktori Proyek (Utama)
- `app/` - Berisi logika inti aplikasi (Controller, Models, Middleware).
- `config/` - Konfigurasi aplikasi.
- `database/` - Migrasi, seeder, dan factory database.
- `public/` - Titik masuk aplikasi (index.php) dan aset publik.
- `resources/` - Tampilan (Blade templates), aset mentah (CSS/JS), dan berkas bahasa.
- `routes/` - Definisi rute aplikasi (web.php, api.php).

---

## 📄 Lisensi
Proyek ini dilisensikan di bawah [MIT License](LICENSE).