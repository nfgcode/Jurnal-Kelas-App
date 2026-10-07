# Audit browser jurusan

Pengujian ini hanya untuk server uji `http://127.0.0.1:8890` dengan database SQLite disposable. Tidak memakai data sekolah dan tidak memerlukan perubahan dependency aplikasi. Python Playwright serta Microsoft Edge harus tersedia di komputer penguji.

## Menyiapkan fixture

Jalankan dari root proyek di PowerShell:

```powershell
$env:APP_ENV='testing'
$env:APP_URL='http://127.0.0.1:8890'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE="$env:LOCALAPPDATA\Temp\opencode\jurusan-ui-20261007.sqlite"
$env:SESSION_DRIVER='database'
$env:SESSION_COOKIE='jurusan_ui_audit'
$env:CACHE_STORE='array'
$env:QUEUE_CONNECTION='sync'
$env:VIEW_COMPILED_PATH="$env:LOCALAPPDATA\Temp\opencode\jurusan-ui-views"
New-Item -ItemType Directory -Path $env:VIEW_COMPILED_PATH -Force | Out-Null
New-Item -ItemType File -Path $env:DB_DATABASE -ErrorAction SilentlyContinue | Out-Null
php tests/Browser/jurusan_fixture.php --reset
```

`--reset` menjalankan `migrate:fresh` **hanya** setelah guard driver/nama database lulus. Jangan mengganti guard agar mengarah ke database aplikasi. Akun fixture `audit.admin`, `audit.guru`, dan `audit.ketua` menggunakan sandi pengujian `password123`; bukan akun aplikasi sekolah.

## Build dan jalankan

```powershell
docker compose run --rm --no-deps bun bun run build
php -S 127.0.0.1:8890 -t public tests/Browser/jurusan_router.php
```

Router khusus menggunakan asset build meskipun `public/hot` development tersedia, dan tidak mengubah file tersebut. Cache compiled view terpisah mencegah benturan dengan feature test yang berjalan bersamaan.

Di terminal lain, dari root proyek:

```powershell
python tests/Browser/jurusan_ui.py
```

Hasil JSON dan screenshot disimpan di `%LOCALAPPDATA%\Temp\opencode\jurusan-ui-evidence`, di luar repository. Sebelum mengulang setelah pengujian terhenti saat penulisan data, jalankan fixture `--reset` kembali. Hentikan server dengan Ctrl+C setelah selesai.

## Cakupan

- Daftar/tambah/ubah jurusan pada 1440, 768, 375, dan 320px, tema terang/gelap.
- Dimensi target sentuh, overflow halaman, kontras computed style dan placeholder pada halaman jurusan.
- Native/server validation, CRUD, penolakan hapus referensi, empty state, filter, sorting, pagination, dan jumlah baris.
- Status submit dan pencegahan submit ulang diuji dengan menahan default submit pada browser, kemudian memulihkan state dan menjalankan submit jaringan asli.
- Tab/Shift+Tab, focus-visible, menu mobile, Escape, tema/font/kontras/motion.
- Informasi jurusan siswa pada detail, histori, dan laporan jurnal, dalam kedua tema dan ukuran desktop/mobile.
- Console error dan uncaught JavaScript error.

Ini audit fitur jurusan, bukan sertifikasi aksesibilitas seluruh aplikasi atau pengujian pada perangkat ponsel fisik.
