# Laporan Ketidaksesuaian Dokumentasi terhadap Source Code Final

Bagian A-E **hanya melaporkan**: tidak ada berkas dokumentasi lama (README,
`docs/API.md`, collection Postman lama) yang diubah. Bagian F mencatat temuan pada
source code, dipisah menjadi yang sudah diperbaiki (beserta test yang menjaganya)
dan yang masih terbuka.

Sumber kebenaran: working tree pada branch `main`, commit terakhir `4d1a6b5`
("Refactor attendance management: replace journal with attendance roster").

Sebagian besar ketidaksesuaian di bawah berasal dari satu perubahan besar:
kepemilikan presensi dipindahkan dari **ketua kelas per hari** menjadi
**guru per pertemuan**, dan `presensi_harian` berubah dari sumber utama menjadi
**turunan**. Dokumentasi belum menyusul.

## A. Siapa mengisi apa

| Dokumen/Lokasi | Pernyataan Lama | Source Code Final | Rekomendasi |
|---|---|---|---|
| `README.md:33` | "Kalau gurunya berhalangan, **ketua kelas** yang mengisi jurnal" | Ketua kelas adalah **penulis utama** jurnal, bukan pengganti. `JurnalPolicy::create()` = `isAdmin() \|\| isKetuaKelas()`; guru tidak pernah boleh membuat jurnal | Tulis ulang: jurnal ditulis kelas (ketua kelas) atau admin |
| `README.md:37` | "**Hapus jurnal** cuma boleh oleh guru yang menulisnya atau admin" | `JurnalPolicy::delete()` mengikuti `update()`: admin, atau ketua kelas dari kelas jurnal itu. Guru — termasuk yang mengajar pertemuan itu — **tidak bisa** menghapus | Perbaiki daftar peran |
| `README.md:40` (judul) | "### Presensi Siswa — sekali sehari, oleh ketua kelas" | Presensi ditandai **per pertemuan oleh guru pengampu**. `JurnalPolicy::isiPresensi()` = admin, atau guru dengan `jadwal.guru_nip === user.nip` | Judul dan seluruh sub-bagian perlu ditulis ulang |
| `README.md:41` | "Presensi siswa dicatat **satu kali per kelas per hari**, bukan per pertemuan" | Kebalikannya. Sumber utama adalah tabel `presensi` (per pertemuan). `presensi_harian` dihitung ulang dari seluruh pertemuan hari itu oleh `SimpanPresensiJurnal::segarkanHarian()` | Balikkan pernyataan; jelaskan `presensi_harian` sebagai proyeksi |
| `README.md:43` | "**Yang mengisi cuma ketua kelas** kelasnya sendiri" | `AuthorizationTest::test_a_ketua_kelas_cannot_mark_attendance` dan `PresensiRosterTest::test_the_ketua_kelas_may_read_but_not_mark_attendance` membuktikan sebaliknya | Perbaiki |
| `README.md:45` | "**Guru tidak mengisi presensi sama sekali**" | Guru adalah **satu-satunya** peran non-admin yang mengisi presensi (`PresensiJurnalController`, `Api\PresensiController@store`) | Perbaiki |
| `README.md:190` | "Presensinya **baca saja** — ... yang mengisi tetap ketua kelas" | Untuk wali kelas pernyataan "baca saja" **masih benar** (`PresensiRosterTest::test_the_wali_kelas_may_read_but_not_mark_another_teachers_meeting`), tetapi anak kalimat "yang mengisi tetap ketua kelas" salah | Cukup ganti anak kalimat terakhir |
| `README.md:194` | "(presensi siswa diisi terpisah oleh ketua kelas)" | Diisi guru pengampu pertemuan | Perbaiki |
| `README.md:78` | "Sejak presensi jadi satu absensi harian yang diisi ketua kelas..." | Alasan "backfill tidak menebak presensi" **masih berlaku**, tetapi premisnya usang | Perbarui premis, pertahankan kesimpulan |

## B. Hak akses / policy

| Dokumen/Lokasi | Pernyataan Lama | Source Code Final | Rekomendasi |
|---|---|---|---|
| `README.md:205` | "`KelasPolicy::isiPresensiHarian()` (ketua kelas + admin)" | **Method itu tidak ada.** `KelasPolicy` hanya punya `view`, `lihatPresensiHarian`, `create`, `update`, `delete`. Izin menulis presensi ada di `JurnalPolicy::isiPresensi()` | Ganti nama method dan peran yang disebut |
| `README.md:205` | "Hak akses presensi nempel ke **kelasnya**, bukan ke pertemuan" | Terbalik: izin **menulis** menempel ke pertemuan (`JurnalPolicy`), izin **membaca harian** menempel ke kelas (`KelasPolicy::lihatPresensiHarian`) | Tulis ulang menjadi dua kalimat |
| `docs/jurnal-kelas.postman_collection.json` | Request `POST /jurnal` diberi label "Create jurnal (guru/admin)" | Guru ditolak `403` | Lihat `POSTMAN_AUDIT.md` |

## C. Identifier & nama kolom

| Dokumen/Lokasi | Pernyataan Lama | Source Code Final | Rekomendasi |
|---|---|---|---|
| `README.md:47`, `README.md:208` | Unique index `(kelas_id, tanggal, siswa_id)` | Nama constraint aktual `presensi_harian_unik` pada `(kelas_id, tanggal, siswa_nis)` — kolomnya `siswa_nis`, bukan `siswa_id` | Perbaiki nama kolom |
| `README.md:207` | "biar `siswa_id` asing tidak bisa diselipkan" | Validasi memakai `presensi.*.siswa_nis` dengan `Rule::in($roster)` | Perbaiki nama field |
| `README.md:377-379`, `README.md:400`, `README.md:408` | Diagram relasi memakai `guru_id`, `siswa_id`; `Presensi` disebut "arsip, tidak ditulis lagi" | Kolom aktual `guru_nip` dan `siswa_nis`. `presensi` **ditulis aktif** oleh `SimpanPresensiJurnal::simpan()` dan merupakan sumber utama | Perbarui diagram |
| `README.md:52` | "Tabel lama `presensi` (per pertemuan) **tidak dihapus**: isinya riwayat sebelum aturan ini berlaku" | `presensi` bukan arsip; ia tabel aktif | Hapus/ubah paragraf |
| `README.md:210` | "Presensi dialamatkan lewat **kelas + tanggal**, bukan lewat jurnal" | Benar untuk **pembacaan** (`GET /api/presensi/{kelas}?tanggal=`). Salah untuk **penulisan**: `POST /api/jurnal/{jurnal}/presensi` dialamatkan lewat jurnal | Pisahkan baca dan tulis |
| `docs/jurnal-kelas.postman_collection.json` | Variabel `jurnal_id = 1` dipakai pada URL | Route jurnal terikat `public_id` (ULID); id numerik → `404` | Lihat `POSTMAN_AUDIT.md` |

## D. Objek database

| Dokumen/Lokasi | Pernyataan Lama | Source Code Final | Rekomendasi |
|---|---|---|---|
| `README.md:357-367` (tabel "Objek Database") | Mendaftar 2 view, 2 function, 2 procedure, 2 trigger — total 8 objek | Database aktual berisi **4 function, 7 procedure, 2 view, 8 trigger** (diverifikasi pada MySQL dev, lihat `BAB5_BACKEND_EVIDENCE.md`). Yang hilang dari tabel: `fn_nama_duplikat_guru`, `fn_nama_duplikat_siswa`, `sp_tambah_guru`, `sp_tambah_siswa`, `sp_tambah_kelas`, `sp_tambah_mata_pelajaran`, `sp_tambah_jadwal`, dan 6 trigger `trg_guru/siswa/users_before_insert/update` | Lengkapi tabel (sebagian sudah dibahas di bagian "Stored Procedure & Transaction" `README.md:149`) |
| `README.md:365` | "`sp_simpan_presensi` *(lama)* — tidak dipakai lagi" | **Benar.** Objeknya masih ada di database dan di migrasi `2026_07_23_080007`, tetapi tidak ada satu pun `CALL sp_simpan_presensi` di `app/` | Tidak perlu diubah |
| `README.md:361` | View `v_rekap_presensi_kelas` "dari `presensi_harian`" | Benar setelah migrasi `2026_08_19_000004_repoint_mysql_objects_to_presensi_harian` | Tidak perlu diubah |

## E. Lain-lain

| Dokumen/Lokasi | Pernyataan Lama | Source Code Final | Rekomendasi |
|---|---|---|---|
| `README.md:176` | "Perhitungannya dipasang di event `saving` model" | Tabel `jadwal` tidak memiliki kolom `jam_mulai`/`jam_selesai` sama sekali (lihat komentar pada `2024_01_01_000004_create_jadwal_table.php`). Jam dinding dihitung saat dibaca, lewat accessor yang memanggil `App\Support\JamPelajaran::label()` | Ganti "event `saving`" menjadi "dihitung saat dibaca" |
| `docs/API.md:7` | Base URL `http://localhost:8888/api` | Benar untuk lingkungan pengembangan Docker (nginx pada port 8888). Untuk deploy sekolah `APP_URL` harus alamat LAN | Tambahkan catatan |
| `docs/API.md:11-15` | Tabel "Seeded logins" memuat alamat e-mail lengkap sebagai identifier | Masih dapat dipakai (`LoginResolver` menerima e-mail), tetapi seeder juga membuat `username` (`admin`, `siswa<NIS>`) dan NIP/NIS yang lebih mewakili cara sekolah login | Tambahkan kolom username/NIP/NIS |
| `docs/API.md:20-24` | Auto-login admin lewat pre-request script "supaya tidak pernah 401" | Menjadikan pengujian keamanan "tanpa token" mustahil di collection itu | Collection baru KP tidak memakainya |

## F. Temuan pada source code (bukan dokumentasi)

Bagian ini dibagi dua: temuan yang **sudah diperbaiki** beserta test yang
menjaganya, dan temuan yang **masih terbuka**.

### F.1 Temuan audit yang telah diperbaiki

| Lokasi | Temuan semula | Perbaikan | Test yang menjaganya |
|---|---|---|---|
| `app/Http/Controllers/Api/StatistikController.php` | Saat pemanggil berperan siswa, controller mengisi identifier siswa dengan `$user->id` (id akun), lalu mengembalikannya pada field respons bernama `siswa_nis` dan meneruskannya ke `Ringkasan::persentaseKehadiranSiswa()` yang mencocokkan `presensi_harian.siswa_nis`. Akibatnya persentase kehadiran seorang siswa yang bertanya tentang dirinya sendiri hampir selalu `0` | Jalur siswa kini memakai `$user->nis`; nama variabelnya diganti `$nis` dan diberi komentar alasannya. Kontrak API tidak berubah — field respons tetap `siswa_nis`, dan jalur admin/guru (`?siswa_nis=`) tetap seperti semula | `tests/Feature/ApiStatistikKehadiranTest.php` — 6 test, di antaranya `test_a_student_is_answered_from_their_nis_not_their_account_id` dan `test_a_student_cannot_ask_about_someone_else` |
| `app/Support/Ringkasan.php` | `persentaseKehadiranSiswa()` bertipe `int $siswaId` padahal yang dibandingkan adalah kolom `siswa_nis` bertipe `VARCHAR(20)` | Tipe parameter diubah menjadi `string $nis`; tidak ada cast ke integer di sepanjang jalur ini. `persentaseKehadiranKelas(int $kelasId)` **tidak** diubah karena `kelas.id` memang integer | Fixture test memakai NIS berawalan nol (`0026001`, `0026002`), sehingga cast ke integer akan membuat test gagal |
| `tests/Feature/PeriodeFilterTest.php` | `test_a_narrower_preset_returns_no_more_rows_than_a_wider_one` mengasumsikan "Minggu Ini" selalu himpunan bagian "Bulan Ini". Asumsi itu salah pada minggu yang melintasi pergantian bulan, dan test gagal pada Sabtu 5 September 2026 | Yang diperbaiki **test**-nya, bukan `App\Support\Periode`: definisi presetnya sudah benar. Asersi diganti dengan pasangan jendela yang selalu bersarang di setiap posisi kalender (`hari_ini` ⊆ `minggu_ini` ⊆ `30_hari`, dan `hari_ini` ⊆ `bulan_ini` ⊆ `tahun_ini`) — dari 2 asersi menjadi 4 | `test_the_current_week_may_reach_into_the_previous_month` membekukan waktu pada 2026-09-05 dan mengunci bahwa "Minggu Ini" mulai 2026-08-31 sementara "Bulan Ini" mulai 2026-09-01, serta bahwa jurnal 31 Agustus muncul di minggu tetapi tidak di bulan |

Perbaikan ini tidak menyentuh aturan bisnis mana pun yang sudah benar, tidak
mengubah kontrak REST API, dan tidak mengubah
`docs/kp-evidence/Jurnal-Kelas-API-KP.postman_collection.json` (collection sudah
memakai `?siswa_nis=`).

### F.2 Temuan yang masih terbuka

| Lokasi | Temuan | Dampak |
|---|---|---|
| `app/Models/PresensiHarian.php` (docblock kelas) | Komentar masih menyatakan "taken once a day by the class's ketua kelas — not per lesson" | Menyesatkan pembaca kode; perilaku sebenarnya ada di `SimpanPresensiJurnal`. Tidak diubah karena berada di luar lingkup perbaikan terbatas ini |
