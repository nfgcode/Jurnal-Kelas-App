# Audit Postman Collection Lama

Berkas yang diaudit: `docs/jurnal-kelas.postman_collection.json`
(Postman Collection v2.1, **39 request dalam 9 folder**).

Pembanding (sumber kebenaran): `php artisan route:list --path=api` pada HEAD,
ditambah controller, Form Request, Policy, dan model yang benar-benar dipanggil.

Collection lama **tidak dihapus dan tidak diubah**. Hasil audit ini menjadi dasar
collection baru `docs/kp-evidence/Jurnal-Kelas-API-KP.postman_collection.json`.

## Ringkasan

| Kategori | Jumlah |
|---|---:|
| Request pada collection lama | 39 |
| Request yang masih valid apa adanya | 20 |
| Request yang bermasalah | 19 |
| Endpoint aktual yang belum diwakili sama sekali | 1 (`POST /api/jurnal/{jurnal}/presensi`) |

Rincian 19 request bermasalah menurut jenis masalah (satu request dapat memiliki
lebih dari satu jenis, sehingga jumlah baris berikut melebihi 19):

| Jenis masalah | Jumlah request |
|---|---:|
| URL / identifier salah | 8 |
| Field body usang atau tidak ada | 9 |
| Query parameter tidak ada pada validasi | 4 |
| Label hak akses salah | 5 |
| Endpoint tidak ada sama sekali | 1 |

Sebaran per folder (valid / bermasalah): Auth 5/0, Dashboard & Statistik 1/1,
Kelas 3/2, Mata Pelajaran 4/1, Jadwal 2/3, Jurnal 1/5, Presensi 0/3, Users 3/2,
Laporan 1/2.

## Temuan Rinci

| Request Lama | Masalah | Implementasi Aktual | Tindakan |
|---|---|---|---|
| `POST /login` (admin/guru/siswa) | Body `urlencoded`; tidak mengirim `device_name`; identifier ditulis sebagai e-mail demo langsung di body | `Api\AuthController@login` menerima `user`, `password`, `role` (nullable, in:admin,guru,siswa), `device_name` (nullable, max:255). `App\Support\LoginResolver` menerima username / e-mail / NIP / NIS | Dipertahankan; diubah ke JSON, memakai variabel kredensial, ditambah `device_name` |
| `GET /me`, `POST /logout` | — | Sesuai | Dipertahankan |
| `GET /dashboard` | — | Sesuai (`Api\DashboardController@index`, bentuk respons berbeda per peran) | Dipertahankan |
| `GET /statistik/kehadiran?siswa_id=&kelas_id=` | Parameter `siswa_id` **tidak ada** | `Api\StatistikController@kehadiran` memvalidasi `siswa_nis` (`exists:siswa,nis`) dan `kelas_id` (`exists:kelas,id`) | Diganti `siswa_nis` |
| `GET /kelas`, `GET /kelas/{{kelas_id}}` | — | Sesuai; filter `tingkat` (X/XI/XII), `jurusan`, `q` | Dipertahankan |
| `POST /kelas` | Field `jurusan`, `ruang`, `tahun_ajaran`, `wali_kelas_id` **tidak ada**; field wajib `paralel` **tidak dikirim** | `KelasRequest`: `nama_kelas`, `tingkat`, `jurusan_kode`, `paralel` (required, unik terhadap tahun ajaran + tingkat + jurusan), `ruangan_kode`, `kapasitas`, `tahun_ajaran_kode`, `wali_kelas_nip`, `ketua_nis` | Body ditulis ulang seluruhnya |
| `PUT /kelas/{{kelas_id}}` | Sama seperti di atas | Idem | Body ditulis ulang seluruhnya |
| `GET /mata-pelajaran`, `GET /mata-pelajaran/{id}` | — | Sesuai; filter `kelompok`, `q` | Dipertahankan |
| `POST /mata-pelajaran` | Tidak mengirim `guru_nip[]`, sehingga mapel lahir tanpa pengampu dan formulir jadwal menolak semua guru untuknya | `MataPelajaranRequest` menerima `guru_nip` (array) dan `guru_nip.*` (`exists:guru,nip`); ditulis bersama mapel dalam satu transaksi (`sp_tambah_mata_pelajaran`) | Ditambah `guru_nip[]` |
| `GET /jadwal?...&guru_id=` | Parameter `guru_id` **tidak ada** | `Api\JadwalController@index` memvalidasi `kelas_id`, `guru_nip` (`exists:guru,nip`), `hari`, `q` | Diganti `guru_nip` |
| `POST /jadwal`, `PUT /jadwal/{id}` | Mengirim `guru_id`, `jam_mulai`, `jam_selesai`, `ruang` — keempatnya **tidak diterima**. Tabel `jadwal` bahkan tidak memiliki kolom jam dinding | `JadwalRequest`: `kelas_id`, `mata_pelajaran_id`, `guru_nip` (+ rule `GuruMengampuMapel`), `hari`, `jam_ke_mulai`, `jam_ke_selesai` (`gte:jam_ke_mulai`), `ruangan_kode`. Jam dinding dihitung backend melalui `App\Support\JamPelajaran` (1 JP = 45 menit) | Body ditulis ulang seluruhnya |
| `GET /jurnal/{{jurnal_id}}` dengan `jurnal_id = 1` | Route binding jurnal memakai `public_id` (ULID), bukan id numerik. Id numerik menghasilkan **404** | `Jurnal::getRouteKeyName()` mengembalikan `public_id`; dikunci oleh `ApiJurnalKontrakTest::test_the_numeric_id_is_not_a_url_key` | Variabel diganti `{{jurnal_public_id}}` |
| `GET /jurnal/{{jurnal_id}}/audit` | Identifier sama-salah; label tidak menyebut hak akses | Identifier `public_id`. Otorisasi `Gate::authorize('update', $jurnal)`, jadi hanya admin atau ketua kelas — bukan guru | URL dan label diperbaiki |
| `POST /jurnal` — label "Create jurnal (guru/admin)" | Label **salah**: guru tidak memiliki izin membuat jurnal sama sekali | `JurnalPolicy::create()` = `isAdmin() \|\| isKetuaKelas()`. Diuji oleh `JurnalGandaTest::test_a_guru_cannot_file_a_journal_at_all` dan `RolePagesTest::test_a_guru_cannot_author_a_journal` | Label diganti "(admin / ketua kelas)" |
| `PUT /jurnal/{id}` — label "(owner/admin)" | Label **salah**: "owner" bukan guru penulis | `JurnalPolicy::update()` = admin, atau ketua kelas dari kelas jurnal tersebut | Label diperbaiki |
| `DELETE /jurnal/{id}` — label "(owner/admin)" | Sama | `JurnalPolicy::delete()` mengikuti `update()` | Label diperbaiki |
| `GET /presensi?jurnal_id=` | Parameter `jurnal_id` **tidak ada** | `Api\PresensiController@index` membaca tabel `presensi_harian`; memvalidasi `kelas_id`, `tanggal`, `mulai`, `selesai`; hasilnya di-scope per peran | Parameter ditulis ulang |
| `GET /presensi/{{jurnal_id}}` — "Show roster for a jurnal" | URL **salah arti**: segmen itu adalah **kelas**, bukan jurnal | `GET /api/presensi/{kelas}` → `PresensiController@show`; otorisasi `KelasPolicy::lihatPresensiHarian`; query `?tanggal=` (default hari ini) | Diganti `GET /presensi/{{kelas_id}}?tanggal=` |
| `POST /presensi` — "Save roster (stored procedure)" | Endpoint **tidak ada**. Tidak ada route `POST /api/presensi` | `POST /api/jurnal/{jurnal}/presensi` → `PresensiController@store`; otorisasi `JurnalPolicy::isiPresensi` (guru yang dijadwalkan mengajar pertemuan itu, atau admin) | Diganti seluruhnya |
| `POST /presensi` body `presensi[0][siswa_id]` | Field `siswa_id` **tidak ada** | Validasi aktual: `presensi` (required, array, min:1), `presensi.*.siswa_nis` (`Rule::in` roster kelas), `presensi.*.status` (`hadir`/`sakit`/`izin`/`alpa`), `presensi.*.keterangan` (nullable, max:500) | Diganti `siswa_nis`, dikirim sebagai JSON |
| `POST /presensi` disebut "stored procedure" | Menyesatkan | Roster pertemuan disimpan melalui `DB::transaction` di `App\Support\SimpanPresensiJurnal`. Yang memakai stored procedure adalah turunan hariannya (`sp_simpan_presensi_harian`, dipanggil `SimpanPresensiHarian` hanya pada MySQL) | Keterangan diperbaiki |
| `GET /users`, `GET /users/{id}` | — | Sesuai; filter `role`, `kelas_id`, `status`, `q` | Dipertahankan |
| `POST /users` — "Create user (guru)" | Skenario **ditolak sistem** | `Api\Admin\UserController@store` mengembalikan **422** bila `role !== 'admin'`; akun guru/siswa dibuat bersama data orangnya | Diubah menjadi pembuatan akun admin; skenario guru dipindah ke folder bukti negatif |
| `POST /users` body `name`, `nip` | Field **tidak ada** | `AkunRequest`: `username` (min:3, regex `^[A-Za-z0-9._-]+$`, unik), `email` (unik), `password` (min:8), `status` (aktif/nonaktif/pending), `nama` (required_if role=admin), `role` | Body ditulis ulang |
| `PUT /users/{id}` body `name`, `nip` | Sama | Idem; `password` boleh kosong saat update | Body ditulis ulang |
| `GET /laporan/jurnal?guru_id=` | Parameter `guru_id` **tidak ada** | `Api\Admin\LaporanController@jurnal` memvalidasi `kelas_id`, `guru_nip`, `status` (`terisi`/`telat`), `q`, ditambah parameter periode dari `App\Support\Periode` | Diganti `guru_nip` |
| `GET /laporan/presensi?guru_id=` | Sama | `kelas_id`, `guru_nip`, `q` + periode | Diganti `guru_nip` |
| `GET /laporan/rekap-kelas` | — | Sesuai; membaca view `v_rekap_presensi_kelas` pada MySQL, agregat query builder pada SQLite | Dipertahankan |
| Pre-request script auto-login admin (level collection) | Membuat pengujian "tanpa token" mustahil: setiap request otomatis memperoleh token admin | — | Dihapus. Collection baru memakai Login eksplisit; folder bukti negatif memakai **No Auth** |
| Variabel `{{jurnal_id}} = 1` | Numerik, tidak dapat dipakai sebagai URL jurnal | — | Diganti `{{jurnal_public_id}}` |

## Endpoint aktual yang belum diwakili collection lama

| Method | URI | Keterangan |
|---|---|---|
| POST | `/api/jurnal/{jurnal}/presensi` | Satu-satunya jalur penulisan presensi pada REST API |

## Catatan

Nama request lama seperti `Save roster (stored procedure)` dan
`Show roster for a jurnal` mencerminkan arsitektur presensi sebelum refactor pada
commit `4d1a6b5` ("Refactor attendance management: replace journal with attendance
roster"). Rincian perbedaannya ada di `REPORT_MISMATCHES.md`.
