# Ringkasan REST API

Sumber: `php artisan route:list --path=api` yang dijalankan pada source code HEAD
(branch `main`, commit `4d1a6b5`), dijalankan di dalam container `app`.

```
Showing [37] routes
```

| Method | Jumlah entri |
|---|---:|
| GET (beserta HEAD) | 19 |
| POST | 8 |
| PUT | 5 |
| PATCH | 5 |
| DELETE | 5 |
| **Total entri route** | **37** |

`PUT` dan `PATCH` terdaftar pada baris route yang sama (`PUT\|PATCH`), sehingga
jumlah baris unik pada keluaran adalah 37, bukan 42.

## Dasar Teknis

| Aspek | Implementasi |
|---|---|
| Autentikasi | Laravel Sanctum, personal access token (`auth:sanctum`) |
| Header | `Authorization: Bearer <token>`, `Accept: application/json` |
| Prefix | Semua route berawalan `/api` |
| Format error | JSON pada seluruh `api/*` (`bootstrap/app.php` → `shouldRenderJsonWhen`) |
| Middleware peran | `role:admin` (`App\Http\Middleware\CheckRole`) → HTTP 403 |
| Otorisasi per record | `JurnalPolicy`, `KelasPolicy` melalui `Gate::authorize()` |
| Identifier jurnal pada URL | `jurnal.public_id` (ULID) — `Jurnal::getRouteKeyName()` |
| Identifier kelas / jadwal / mapel / user pada URL | id numerik (route key bawaan) |

## Endpoint

| No | Modul | Method | Endpoint | Auth | Hak Akses | Fungsi |
|---|---|---|---|---|---|---|
| 1 | Authentication | POST | `/api/login` | — | Publik | Menukar kredensial (`user`, `password`, opsional `role`, `device_name`) dengan bearer token |
| 2 | Authentication | POST | `/api/logout` | Bearer | Semua peran | Mencabut token yang sedang dipakai |
| 3 | Authentication | GET | `/api/me` | Bearer | Semua peran | Profil akun yang sedang login |
| 4 | Dashboard | GET | `/api/dashboard` | Bearer | Semua peran (bentuk respons berbeda per peran) | Ringkasan KPI admin / guru / siswa |
| 5 | Statistik | GET | `/api/statistik/kehadiran` | Bearer | Semua peran; siswa dipaksa ke datanya sendiri | Persentase kehadiran siswa/kelas (stored function pada MySQL) |
| 6 | Kelas | GET | `/api/kelas` | Bearer | Semua peran | Daftar kelas + jumlah siswa & jadwal; filter `tingkat`, `jurusan`, `q` |
| 7 | Kelas | GET | `/api/kelas/{kelas}` | Bearer | Semua peran | Detail satu kelas |
| 8 | Kelas | POST | `/api/kelas` | Bearer | `role:admin` | Membuat rombel melalui `sp_tambah_kelas` |
| 9 | Kelas | PUT/PATCH | `/api/kelas/{kelas}` | Bearer | `role:admin` | Mengubah data kelas |
| 10 | Kelas | DELETE | `/api/kelas/{kelas}` | Bearer | `role:admin` | Menghapus kelas |
| 11 | Mata Pelajaran | GET | `/api/mata-pelajaran` | Bearer | Semua peran | Daftar mapel; filter `kelompok`, `q` |
| 12 | Mata Pelajaran | GET | `/api/mata-pelajaran/{mataPelajaran}` | Bearer | Semua peran | Detail satu mapel |
| 13 | Mata Pelajaran | POST | `/api/mata-pelajaran` | Bearer | `role:admin` | Membuat mapel + guru pengampu (`sp_tambah_mata_pelajaran`, satu transaksi) |
| 14 | Mata Pelajaran | PUT/PATCH | `/api/mata-pelajaran/{mataPelajaran}` | Bearer | `role:admin` | Mengubah mapel |
| 15 | Mata Pelajaran | DELETE | `/api/mata-pelajaran/{mataPelajaran}` | Bearer | `role:admin` | Menghapus mapel |
| 16 | Jadwal | GET | `/api/jadwal` | Bearer | Semua peran | Daftar jadwal; filter `kelas_id`, `guru_nip`, `hari`, `q` |
| 17 | Jadwal | GET | `/api/jadwal/{jadwal}` | Bearer | Semua peran | Detail satu slot jadwal |
| 18 | Jadwal | POST | `/api/jadwal` | Bearer | `role:admin` | Membuat slot melalui `sp_tambah_jadwal` (4 pemeriksaan bentrok) |
| 19 | Jadwal | PUT/PATCH | `/api/jadwal/{jadwal}` | Bearer | `role:admin` | Mengubah slot |
| 20 | Jadwal | DELETE | `/api/jadwal/{jadwal}` | Bearer | `role:admin` | Menghapus slot |
| 21 | Jurnal | GET | `/api/jurnal` | Bearer | Di-scope controller: guru → jurnal pertemuan yang diampu, siswa → kelasnya, admin → semua | Daftar jurnal + rekap presensi pertemuan; `q`, `sort`, `dir` |
| 22 | Jurnal | GET | `/api/jurnal/{jurnal}` | Bearer | `JurnalPolicy::view` | Detail jurnal (URL memakai `public_id`) |
| 23 | Jurnal | POST | `/api/jurnal` | Bearer | `JurnalPolicy::create` → **admin atau ketua kelas** | Membuat jurnal pertemuan |
| 24 | Jurnal | PUT/PATCH | `/api/jurnal/{jurnal}` | Bearer | `JurnalPolicy::update` → admin atau ketua kelas dari kelas itu | Mengubah jurnal |
| 25 | Jurnal | DELETE | `/api/jurnal/{jurnal}` | Bearer | `JurnalPolicy::delete` (mengikuti `update`) | Menghapus jurnal |
| 26 | Jurnal | GET | `/api/jurnal/{jurnal}/audit` | Bearer | `JurnalPolicy::update` | Riwayat perubahan dari trigger `trg_jurnal_after_update` / `..._after_delete` |
| 27 | Presensi | GET | `/api/presensi` | Bearer | Di-scope controller: siswa → dirinya, ketua kelas → kelasnya, guru → kelas yang diampu, admin → semua | Presensi **harian**; filter `kelas_id`, `tanggal`, `mulai`, `selesai` |
| 28 | Presensi | GET | `/api/presensi/{kelas}` | Bearer | `KelasPolicy::lihatPresensiHarian` | Presensi harian satu kelas pada satu tanggal (`?tanggal=`) |
| 29 | Presensi | POST | `/api/jurnal/{jurnal}/presensi` | Bearer | `JurnalPolicy::isiPresensi` → **guru pengampu pertemuan, atau admin** | Menyimpan/mengganti roster satu pertemuan (idempoten) |
| 30 | Users | GET | `/api/users` | Bearer | `role:admin` | Daftar akun; filter `role`, `kelas_id`, `status`, `q` |
| 31 | Users | GET | `/api/users/{user}` | Bearer | `role:admin` | Detail akun |
| 32 | Users | POST | `/api/users` | Bearer | `role:admin` | Membuat akun **admin saja**; `role` lain ditolak 422 |
| 33 | Users | PUT/PATCH | `/api/users/{user}` | Bearer | `role:admin` | Mengubah akun |
| 34 | Users | DELETE | `/api/users/{user}` | Bearer | `role:admin` | Menghapus akun (data orangnya tetap) |
| 35 | Laporan | GET | `/api/laporan/jurnal` | Bearer | `role:admin` | Laporan jurnal + statistik kelengkapan; filter periode |
| 36 | Laporan | GET | `/api/laporan/presensi` | Bearer | `role:admin` | Rekap presensi per periode |
| 37 | Laporan | GET | `/api/laporan/rekap-kelas` | Bearer | `role:admin` | Rekap kehadiran per kelas dari view `v_rekap_presensi_kelas` |

## Endpoint Utama untuk Bukti KP

Delapan endpoint berikut cukup untuk menunjukkan seluruh alur inti sistem.

### 1. `POST /api/login`

Request (JSON):

```json
{
  "user": "admin",
  "password": "<password akun demo>",
  "role": "admin",
  "device_name": "postman-kp"
}
```

Response `200`:

```json
{
  "token": "<plain text token>",
  "token_type": "Bearer",
  "user": { "id": 1, "username": "admin", "role": "admin", "nip": null, "nis": null }
}
```

Kredensial salah → `422` dengan `errors.user = ["NIP/NIS atau password salah."]`.
Akun berstatus `nonaktif` juga ditolak `422`.

### 2. `GET /api/me`

Mengembalikan `UserResource`: `id`, `username`, `nama`, `email`, `role`, `status`,
`nip`, `nis`, `kelas_id`, `is_ketua_kelas`, `last_active_at`.

### 3. `POST /api/jurnal` — membuat jurnal

Hak akses: **admin atau ketua kelas** (`JurnalPolicy::create`). Guru ditolak `403`.

Body wajib: `jadwal_id`, `tanggal`, `materi`.
Opsional: `tugas`, `kegiatan`, `catatan`, `kehadiran_guru_status`
(`hadir`/`tidak_hadir`), `kehadiran_guru_alasan` (`sakit`/`izin`/`alpa`),
`kehadiran_guru_ada_tugas`, `kehadiran_guru_keterangan`.

Response `201` berisi `data.public_id` — identifier yang dipakai URL berikutnya.
Jurnal kedua untuk pertemuan dan sisi yang sama → `422`.

### 4. `GET /api/jurnal/{public_id}` — membaca jurnal

URL memakai ULID. Memakai id numerik menghasilkan `404`
(dikunci `ApiJurnalKontrakTest::test_the_numeric_id_is_not_a_url_key`).

### 5. `PUT /api/jurnal/{public_id}` — mengubah jurnal

Field sama dengan store. Bila jurnal itu dibuat otomatis oleh sistem
(`diisi_oleh_peran = 'sistem'`), request wajib menyertakan `"pernyataan": true`;
tanpa itu ditolak `422`.

### 6. `POST /api/jurnal/{public_id}/presensi` — menyimpan roster pertemuan

Hak akses: **guru yang dijadwalkan mengajar pertemuan itu, atau admin**
(`JurnalPolicy::isiPresensi`).

```json
{
  "presensi": [
    { "siswa_nis": "20261001", "status": "hadir" },
    { "siswa_nis": "20261002", "status": "sakit", "keterangan": "surat dokter" }
  ]
}
```

`siswa_nis` divalidasi terhadap daftar siswa kelas itu; NIS dari kelas lain → `422`.
Menyimpan ulang **mengganti** roster, bukan menambah.

### 7. `GET /api/presensi/{kelas_id}?tanggal=YYYY-MM-DD` — presensi harian

Satu baris per siswa per hari sekolah, hasil turunan dari roster tiap pertemuan.
Otorisasi `KelasPolicy::lihatPresensiHarian`.

### 8. `GET /api/dashboard` dan `GET /api/laporan/rekap-kelas`

`dashboard` menyesuaikan bentuk respons dengan peran pemanggil.
`laporan/rekap-kelas` khusus admin dan membaca view `v_rekap_presensi_kelas`.

## Catatan Implementasi yang Perlu Diketahui Penguji

1. `GET /api/kelas/{kelas}`, `GET /api/jadwal/{jadwal}`, dan
   `GET /api/mata-pelajaran/{mataPelajaran}` **tidak** memanggil policy; data
   master ini terbuka untuk setiap pengguna terautentikasi. Yang dibatasi policy
   adalah jurnal dan presensi.
2. `POST /api/users` hanya membuat akun admin. Akun guru dan siswa dibuat
   bersama data orangnya lewat halaman web admin (`sp_tambah_guru` /
   `sp_tambah_siswa`); tidak ada route API untuk itu — karena itu collection
   Postman ini tidak memiliki folder "Guru" atau "Siswa".
3. Pada `GET /api/statistik/kehadiran`, ketika pemanggil berperan siswa,
   endpoint selalu menjawab tentang **dirinya sendiri**: parameter `siswa_nis`
   dari query diabaikan dan diganti dengan NIS akun yang sedang login. Untuk
   admin dan guru, `siswa_nis` pada query dipakai apa adanya (divalidasi
   `exists:siswa,nis`). Kontrak responsnya tetap keempat field yang sama.

   Audit pertama menemukan jalur siswa memakai `users.id`, bukan NIS, sehingga
   persentasenya hampir selalu `0`. Cacat itu **sudah diperbaiki** dan dijaga
   `tests/Feature/ApiStatistikKehadiranTest.php` (6 test). Rinciannya ada pada
   `REPORT_MISMATCHES.md` bagian F.1. NIS diperlakukan sebagai `string` di
   sepanjang jalur ini — `siswa.nis` adalah `VARCHAR(20)` dan boleh berawalan nol.
