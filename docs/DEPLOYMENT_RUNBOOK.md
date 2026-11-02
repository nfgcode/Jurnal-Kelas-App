# RUNBOOK DEPLOYMENT, HARDENING & DISASTER RECOVERY (JURNAL KELAS APP)

**Status Dokumen**: Pre-Deployment Hardening (Fase 9 Post-Audit)
**Target Rilis**: Lingkungan Server Sekolah (Produksi)
**Versi Stack**: PHP 8.3 / Laravel 13.20.0 / MySQL 8.0.46 / Redis 7.x / Nginx

---

## 1. Arsitektur Infrastruktur & Konfigurasi Server

### A. Izin Berkas & Direktori (File Permissions)
Pada server web Linux/Unix (asumsi user aplikasi `deploy` dan grup webserver `www-data`):
```bash
# Set kepemilikan direktori proyek
chown -R deploy:www-data /var/www/jurnal-kelas

# Set izin standar berkas (644) dan direktori (755)
find /var/www/jurnal-kelas -type f -exec chmod 644 {} \;
find /var/www/jurnal-kelas -type d -exec chmod 755 {} \;

# Berikan izin tulis HANYA pada direktori runtime Laravel
chmod -R 775 /var/www/jurnal-kelas/storage
chmod -R 775 /var/www/jurnal-kelas/bootstrap/cache
```

### B. Persistent Storage untuk Cadangan (Private Storage)
- Seluruh cadangan database SQL/GZ disimpan di direktori privat:
  `storage/app/private/backups/`
- **Karakteristik Format Cadangan**:
  Format cadangan `.sql.gz` adalah arsip terkompresi GZIP dengan integritas SHA-256. Enkripsi data-at-rest diterapkan pada level volume/filesystem penyimpanan server.
- **Persetujuan Staged Restore Sekali Pakai (Anti-Replay)**:
  Tabel `staged_restore_approvals` (dimigrasikan via `2026_11_03_000002_create_staged_restore_approvals_table.php`) mencatat hash SHA-256 token persetujuan, mencegah submit ganda/replay secara kriptografis.
- **Aturan Keamanan**: Direktori ini mutlak **tidak boleh di-symlink ke `public/`** atau dapat diakses langsung melalui browser via HTTP URL. Unduhan arsip hanya dilayani melalui otentikasi admin controller dengan stream download aman.

### C. Konfigurasi HTTPS, HSTS, dan Trusted Proxy
Pada konfigurasi Nginx:
```nginx
server {
    listen 443 ssl http2;
    server_name jurnalkelas.sekolah.sch.id;

    # SSL hardening
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    root /var/www/jurnal-kelas/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### D. Supervisor Queue Worker & Cron Scheduler
1. **Cron Scheduler (host crontab untuk user `deploy`)**:
   ```cron
   * * * * * cd /var/www/jurnal-kelas && php artisan schedule:run >> /dev/null 2>&1
   ```
2. **Supervisor Worker (`/etc/supervisor/conf.d/jurnal-worker.conf`)**:
   ```ini
   [program:jurnal-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /var/www/jurnal-kelas/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   user=deploy
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/var/log/supervisor/jurnal-worker.log
   ```

---

## 2. Kebijakan Tugas Akademik Otomatis (Scheduler Review)

Tinjauan terhadap tugas terjadwal (`routes/console.php`):
1. `jurnal:isi-otomatis` (00:30 harian): Mengisi status kehadiran guru kosong pada sesi lampau secara pasif tanpa mengubah data rombel atau memutasikan siswa.
2. `tahun-ajaran:siapkan-berikutnya` (00:15 harian): **Hanya membuat draf usulan tanggal** untuk periode kalender berikutnya (`aktif = false`). **Tidak pernah mengaktifkan tahun ajaran secara otomatis**.
3. `cadangan:buat-bulanan` (01:00 tanggal 1): Membuat snapshot cadangan bulanan otomatis (idempotent).
4. `cadangan:bersihkan-retensi` (02:00 tanggal 1): Membersihkan arsip > 12 bulan dengan dua lapis proteksi: file berlabel `penting` (pinned) dan berkas cadangan valid terakhir (`last-valid`) mutlak dilindungi dari penghapusan.

**Audit Status**: **AMAN**. Tidak ada scheduler yang dapat memutasikan status siswa, menaikkan tingkat, atau mengaktifkan tahun ajaran tanpa intervensi manual admin.

---

## 3. Runbook Deployment Produksi (Standard Deployment)

> **Catatan**: Eksekusi HANYA dilakukan saat jendela pemeliharaan resmi sekolah disetujui.

1. **Persiapan & Notifikasi**:
   - Beritahu guru dan staf bahwa sistem akan memasuki jendela pemeliharaan singkat (10–15 menit).
2. **Aktifkan Maintenance Mode**:
   ```bash
   php artisan down --render="errors::503"
   ```
3. **Snapshot Pra-Deployment Mutlak (Pre-Deployment Native Dump)**:
   ```bash
   mysqldump -u <DB_USER> -p \
     --single-transaction \
     --quick \
     --routines \
     --triggers \
     --events \
     jurnal_kelas > /secure/backups/jurnal_kelas_pre_deploy_$(date +%Y%m%d_%H%M%S).sql
   ```
4. **Deploy Kode & Dependensi**:
   ```bash
   git pull origin main
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan optimize
   ```
5. **Verifikasi Smoke Testing Cepat**:
   - GET `/login` &rarr; HTTP 200
   - GET `/admin/tahun-ajaran` &rarr; HTTP 200
   - Pastikan Redis dan Queue aktif (`php artisan queue:restart`).
6. **Buka Kembali Aplikasi**:
   ```bash
   php artisan up
   ```

---

## 4. Runbook Rollback & Pemulihan Bencana (Disaster Recovery)

> **PRINSIP KESELAMATAN DATA**:
> 1. Rollback KODE dipisahkan secara tegas dari Recovery DATABASE.
> 2. **DILARANG KERAS** mengimpor SQL dump mentah secara langsung ke database aktif yang sedang berjalan tanpa rehearsal di disposable staging.
> 3. Impor SQL dump mentah dapat memicu `implicit commit` parsial pada MySQL dan merusak relasi foreign key jika terputus di tengah jalan.

### Skenario A: Rollback Kode Murni (Dengan Pemeriksaan Kompatibilitas Skema)
Jika kegagalan hanya terjadi pada UI, Blade template, atau bug pada lapisan PHP yang tidak mengubah skema:
1. **Periksa Kompatibilitas Skema Terlebih Dahulu**:
   Pastikan commit tujuan yang akan di-checkout kompatibel dengan skema database saat ini. Jangan melakukan revert kode secara otomatis jika kode versi lama tidak mengenali kolom atau tabel baru yang sudah terlanjur ada di database.
2. Jika terbukti aman dan kompatibel ke belakang (backward-compatible):
   ```bash
   git checkout <PREVIOUS_STABLE_TAG>
   composer install --no-dev --optimize-autoloader
   php artisan optimize:clear
   php artisan optimize
   php artisan queue:restart
   ```
3. Jika kode lama TIDAK kompatibel dengan skema saat ini, JANGAN revert kode sebelum prosedur database recovery yang berizin disiapkan dan disetujui.

### Skenario B: Recovery Database Akibat Kerusakan Data Kritis (Disaster Recovery)
Jika terjadi anomali integritas data serius yang mengharuskan pemulihan ke snapshot pra-deployment:
1. **Aplikasi Tetap di Mode Pemeliharaan**:
   `php artisan down --render="errors::503"`
2. **Hentikan Seluruh Worker & Scheduler**:
   Hentikan supervisor worker dan pastikan tidak ada koneksi penulisan aktif ke database `jurnal_kelas`.
3. **Rehearsal di MySQL Disposable / Skema Staging Terpisah**:
   - Buat database pengujian sementara: `CREATE DATABASE dr_rehearsal_check;`
   - Pulihkan file snapshot pra-deploy ke database sementara tersebut:
     ```bash
     mysql -u <DB_USER> -p dr_rehearsal_check < /secure/backups/jurnal_kelas_pre_deploy_*.sql
     ```
   - Verifikasi bahwa 38 tabel, 2 views, 2 functions, 1 procedure, 2 triggers, serta jumlah record akademik (1.441 users, 1.350 siswa, 90 guru) utuh dan konsisten di database rehearsal.
4. **Eksekusi Recovery Berizin (Administrative Approval)**:
   - Setelah rehearsal dinyatakan 100% valid, lakukan cutover database atau impor terkontrol ke database produksi dengan pengawasan tim IT sekolah.
   - Bersihkan database rehearsal (`DROP DATABASE dr_rehearsal_check;`).
5. **Clear Cache & Verifikasi**:
   ```bash
   php artisan optimize:clear
   php artisan optimize
   php artisan up
   ```

---

## 5. Matriks Status Pre-Deployment Hardening

| Item Pemeriksaan | Status | Bukti / Catatan |
|---|:---:|---|
| **Pembersihan .gitignore** | **PASS** | File ZIP, TAR.GZ, script test runner, scratch files dikecualikan dari Git tracking |
| **Template .env.production** | **PASS** | `.env.production.example` tersedia tanpa kredensial nyata |
| **Separasi Rollback & DB Recovery** | **PASS** | Runbook rollback memisahkan code revert dan database rehearsal sebelum restore |
| **Audit Scheduler Akademik** | **PASS** | Zero automated transition; aktivasi tahun ajaran tetap 100% manual |
| **Staged Restore Approval Anti-Replay** | **PASS** | Approval terikat fingerprint hasil staging, admin ID, dan single-use expiry |
| **Coordinator Context Persistence** | **PASS** | `resolveValidCoordinatorId` memvalidasi auth, kind, periode, dan relasi stage |
| **Live Restore Produksi** | **DISABLED** | Live restore dinonaktifkan secara fail-closed pada level backend dan antarmuka |

**VERDICT AKHIR**: **READY FOR DEPLOYMENT APPROVAL**
Seluruh langkah pre-deployment hardening telah lengkap, dokumentasi runbook telah dikoreksi dengan standar keselamatan data mutlak, dan sistem siap untuk diajukan ke tahap deployment terjadwal.
