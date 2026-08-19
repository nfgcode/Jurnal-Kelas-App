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

Aplikasi web untuk mencatat jurnal mengajar dan presensi siswa di sekolah menengah. Dibuat pakai Laravel 13 (PHP 8.3). Ada tiga jenis pengguna: **Admin**, **Guru**, dan **Siswa**, masing-masing punya tampilan dan hak aksesnya sendiri.

---

## Fitur Utama

### Login Tiga Peran
- **Admin** login pakai email
- **Guru** login pakai NIP
- **Siswa** login pakai NIS
- Untuk API, autentikasinya pakai token (Laravel Sanctum)

### Dashboard per Peran
| Peran | Isi Dashboard |
|------|----------------|
| **Admin** | Statistik sekolah, tren pengisian jurnal, peringatan kehadiran guru, heatmap aktivitas, drill-down, halaman Sistem |
| **Guru** | Jadwal mengajar hari ini, status pengisian jurnal, kehadiran tiap kelas yang diampu, heatmap aktivitas |
| **Siswa** | Jadwal hari ini, kehadiran per bulan, kalender kehadiran. Ketua kelas juga dapat pengingat "presensi hari ini belum diisi" |

### Jurnal Kelas
- Catat materi, tugas, kegiatan, dan catatan untuk tiap pertemuan
- Kalau gurunya berhalangan, **ketua kelas** yang mengisi jurnal
- Status kehadiran guru: **Hadir / Tidak Hadir – Ada Tugas / Tidak Hadir – Tanpa Tugas**. Istilahnya sama, mau diisi guru atau ketua kelas
- Jurnal yang diisi lewat dari 24 jam setelah tanggal pelajaran ditandai terlambat
- Pencarian teks di materi, tugas, catatan, dan kegiatan
- **Hapus jurnal** cuma boleh oleh guru yang menulisnya atau admin. Wali kelas boleh *membaca* semua jurnal kelas asuhannya, tapi tidak boleh menghapus jurnal guru lain. Presensi harian kelas **tidak** ikut terhapus — presensi milik harinya, bukan milik pertemuan
- Semua perubahan dan penghapusan tercatat otomatis lewat trigger database (`trg_jurnal_after_delete`)

### Presensi Siswa — sekali sehari, oleh ketua kelas
Presensi siswa dicatat **satu kali per kelas per hari**, bukan per pertemuan. Satu kelas dengan enam jam pelajaran dulu punya enam daftar hadir sehari — enam kesempatan untuk saling bertentangan soal siapa yang masuk. Sekarang cuma ada satu.

- **Yang mengisi cuma ketua kelas** kelasnya sendiri, dan **cuma untuk hari itu**. Dia yang seharian ada di ruangan; menempelkan pengisian ke hari berjalan bikin catatannya jadi absensi beneran, bukan rekonstruksi
- **Selama masih hari yang sama** ketua kelas boleh membetulkan isinya. Begitu ganti hari, koreksi cuma bisa lewat **admin**
- **Guru tidak mengisi presensi sama sekali** — guru hanya mengisi jurnal per kelas, lalu **membaca dan mengekspor** rekapnya. Wali kelas pun begitu: memantau, bukan mengisi
- Statusnya tetap **Hadir / Sakit / Izin / Alpa** plus keterangan opsional
- Tabelnya `presensi_harian`, dengan unique index `(kelas_id, tanggal, siswa_id)` — aturan "sekali sehari" dijaga database, bukan cuma formulir
- Penyimpanannya lewat stored procedure MySQL (`sp_simpan_presensi_harian`) supaya sekali jalan, tidak setengah-setengah. Menyimpan ulang **mengganti** catatan hari itu, bukan menambah
- Tiap penyimpanan dicatat di `presensi_harian_log` (siapa, kapan, berapa siswa, dan apakah itu pengisian awal atau koreksi) — dibaca admin di **Log Presensi**
- Statistik kehadiran dihitung lewat stored function

> Tabel lama `presensi` (per pertemuan) **tidak dihapus**: isinya riwayat sebelum aturan ini berlaku, dan tetap ikut dicadangkan. Migrasi `backfill_presensi_harian` menyalinnya ke tabel harian dengan aturan "pelajaran paling pagi menang", jadi grafik dan laporan tidak mulai dari nol.

### Ekspor Excel Rekap Presensi (Guru)
Guru punya tombol ekspor sendiri di halaman **Presensi**, dua mode karena keduanya menjawab pertanyaan yang berbeda:

- **Per hari** — pilih tanggalnya, hasilnya satu baris per siswa: Tanggal, Kelas, NIS, Nama, Status, Keterangan. Ini daftar hadir hari itu
- **Per bulan** — pilih bulannya, hasilnya dua lembar: **Rekap** (satu baris per siswa: H/S/I/A, total hari, persentase) dan **Detail Harian** (tabel siswa × tanggal berisi huruf H/S/I/A, seperti buku absensi)
- Keduanya bisa dipersempit ke satu kelas, dan **cuma memuat kelas yang boleh dilihat guru itu** (yang dia ampu + kelas perwaliannya). Admin dapat semuanya
- Filenya `.xlsx` asli (OOXML), ditulis sendiri pakai `ZipArchive` tanpa library tambahan

### Impor Data Siswa & Guru (Admin)
Buat pendaftaran awal atau siswa baru satu angkatan, admin tidak perlu mengetik satu per satu di formulir.

- **Unduh template `.xlsx`** per jenis (siswa / guru): baris judul, dua baris contoh, dan lembar **Petunjuk** berisi penjelasan tiap kolom plus **daftar nama kelas yang benar-benar terdaftar** — sumber kesalahan nomor satu
- Kolom siswa: Nama Lengkap, Email, NIS, Kelas, Ketua Kelas, Password, Status. Kolom guru: Nama Lengkap, Email, NIP, Wali Kelas, Password, Status
- **Unggah lalu ditinjau dulu.** Layar pratinjau menampilkan verdict tiap baris (Baru / Perbarui / Gagal) beserta alasannya, dan **belum menulis apa pun**. Selembar isian seratus siswa itu justru jenis masukan yang percobaan pertamanya pasti ada tiga salah ketik — menemukannya setelah akunnya jadi jauh lebih repot
- Yang diperiksa: kolom wajib, format email, Status yang sah, nama kelas yang ada (tidak peduli huruf besar-kecil), **email/NIS/NIP kembar di dalam berkasnya sendiri**, dan bentrok dengan akun yang sudah ada
- **Password bawaan** di formulir dipakai untuk baris yang kolom Password-nya dikosongkan, jadi tidak perlu mengetik string yang sama seratus kali
- **Menimpa akun lama harus disengaja**: tanpa mencentang "Perbarui data yang sudah ada", email yang sudah terdaftar dianggap salah, bukan diam-diam ditimpa
- Berkas unggahan dihapus begitu impornya dikonfirmasi, dan pratinjau yang ditinggalkan disapu otomatis setelah 6 jam — biar spreadsheet berisi data pribadi tidak menumpuk di server
- Menerima `.xlsx` maupun `.csv` (pemisah koma atau titik koma), dibaca sendiri lewat `ZipArchive` + `SimpleXML`, tanpa library tambahan

### Jurnal Otomatis
Tujuannya mengurangi kerjaan berulang guru, tapi tetap jelas mana yang diisi orang dan mana yang diisi sistem.

- **Pengisian jurnal kosong** — tiap ganti hari (jam **00:30**, lewat scheduler), perintah `jurnal:isi-otomatis` mencari pertemuan lampau yang belum punya jurnal sama sekali (default 14 hari ke belakang), lalu membuatkan jurnalnya: guru tercatat **tidak hadir · tanpa tugas**
- **Presensi siswa tidak ikut ditebak.** Sejak presensi jadi satu absensi harian yang diisi ketua kelas, satu hari itu entah punya absensi atau tidak. Mengarangnya dari pelajaran sebelah justru memalsukan catatan yang aturan harian ini bikin bisa dipercaya. Hari tanpa absensi terbaca "belum diisi", dan itu memang benar
- **Diproses bertahap** biar server tidak berat: 200 pertemuan sekali gelombang, dijadwalkan tiap 2 menit lewat queue. Aman dijalankan berulang, pertemuan yang sudah punya jurnal tidak akan dobel
- **Statusnya ditulis "Otomatis", bukan "Terisi"**, jadi di rekap tetap kelihatan mana yang belum diisi guru sendiri
- **Angkanya juga tidak ikut digelembungkan.** Semua hitungan "jurnal terisi" cuma menghitung yang ditulis orang, dan Rekap Jurnal memecahnya jadi tiga yang totalnya selalu pas sama jumlah pertemuan terjadwal: **Diisi Guru + Diisi Otomatis + Belum Diisi**. Angka kelengkapan cuma menghitung tulisan guru, dan **"Terlambat Isi" tidak menghitung jurnal otomatis** (yang secara teknis memang selalu telat) supaya guru yang benar-benar telat tetap kelihatan
- **Bukan buat menilai guru.** Tulisan "tidak hadir · tanpa tugas" di jurnal otomatis itu cuma penanda kosong, bukan hasil pengamatan. Makanya tidak ikut dihitung di rekap Kehadiran Guru, "Guru Perlu Perhatian", maupun "Guru Teraktif"
- **Masih bisa diubah semua peran.** Begitu jurnal otomatis dibuka lalu diedit, jurnalnya jadi milik yang mengedit (guru/ketua kelas), jadi tidak ada dua jurnal untuk satu pertemuan
- **Wajib centang pernyataan** kalau mau mengubah jurnal otomatis: isinya menyatakan perubahan dibuat sesuai keadaan sebenarnya. Tanpa itu perubahannya ditolak
- **Label "Diedit setelah hari-H"** buat jurnal yang diubah di hari setelah tanggal pelajarannya. Labelnya kelihatan oleh semua peran dan beda dari label "Telat" (telat *mengisi*). Admin bisa memfilter dan lihat jumlahnya di Rekap Jurnal

```bash
# Biasanya jalan otomatis lewat scheduler, tapi bisa juga manual (misal buat ngisi tunggakan lama)
php artisan jurnal:isi-otomatis --lookback=60          # antre per gelombang
php artisan jurnal:isi-otomatis --sekarang --lookback=60  # langsung diproses, tanpa antrean
```

> Perintah ini butuh **queue worker** dan **scheduler** yang hidup di container `app` (lewat supervisord), plus `APP_TIMEZONE` yang benar supaya "ganti hari"-nya sesuai waktu setempat.

### Master Data
- **Kelas** — tingkat (X/XI/XII), jurusan, ruang, kapasitas, wali kelas, tahun ajaran
- **Mata Pelajaran** — kelompok kurikulum (Wajib / Peminatan / Muatan Lokal / Kejuruan), JP per minggu
- **Jadwal** — per hari, jam pelajaran, ruang, dan guru pengampu
- **Pengguna** — tambah/ubah/hapus admin, guru, siswa lengkap dengan peran & status

### Laporan Admin
- Laporan jurnal dengan filter periode
- Laporan presensi per kelas
- **Impor data siswa/guru dari Excel** (lihat bagian di atas)
- Rekap kelas (lewat API)
- Drill-down dari kartu di dashboard
- **Ekspor Excel (.xlsx)** — file spreadsheet asli (OOXML), ditulis sendiri pakai `ZipArchive` tanpa library tambahan. Khusus admin

### Mode Wali Kelas
- Tombol "Mode Wali Kelas" di topbar buat guru yang jadi wali kelas (cara pakainya mirip tombol tema)
- Isinya khusus kelas perwaliannya: **Data Kelas, Jadwal Kelas, Jurnal Kelas, Presensi Kelas**
- Ada rekap kehadiran per siswa, siswa dengan kehadiran paling rendah disorot
- Presensinya **baca saja** — wali kelas memantau dan mengekspor, yang mengisi tetap ketua kelas

### Isi Jurnal lewat QR Code
- Tiap kelas punya **QR code sendiri** (isinya token acak, bukan id berurutan) buat ditempel di ruangannya
- Alurnya: guru **scan QR pakai kamera HP** → diarahkan ke website (server lokal sekolah) → login → halaman konfirmasi kelas → langsung isi **jurnal** kelas itu (presensi siswa diisi terpisah oleh ketua kelas)
- **Cuma buat guru.** Siswa atau admin yang membuka URL QR bakal ditolak (403). Kalau belum login, diarahkan ke halaman login dulu terus balik lagi (`redirect()->intended()`)
- Ada halaman **cetak QR** buat admin (`/admin/kelas-qr`), tinggal potong dan tempel. QR-nya SVG (pakai `endroid/qr-code`, jalan offline)
- **Bisa pilih kelas mana yang dicetak.** Defaultnya semua kelas, tapi bisa dipersempit lewat daftar centang. Berguna kalau cuma satu ruang yang pindah, satu QR sobek, atau ada rombel baru, jadi tidak perlu cetak ulang semuanya. Pilihannya ikut masuk URL, jadi bisa di-bookmark atau dikirim ke yang mau mencetak
- **Ada pencarian & filter kelas.** Kotak cari nama kelas plus filter **Tingkat/Jurusan** langsung menyaring daftar centangnya (client-side). Membantu kalau sekolahnya punya puluhan kelas
- **Ukuran cetak A6.** Baik lewat browser maupun **Ekspor PDF** (`/admin/kelas-qr/pdf`), satu QR = satu A6, jadi empat kartu per lembar A4, lengkap sama garis potong dan ikut kelas yang dipilih. PDF-nya dirender `dompdf` (murni PHP, tetap jalan tanpa internet). Di dalam PDF QR-nya pakai PNG karena dukungan SVG dompdf suka bermasalah, sementara di layar tetap SVG
- Waktu pengisian jurnal tercatat otomatis (`created_at`) dan ditampilkan di detail jurnal sebagai "Diisi Pada"

> **Buat deploy di sekolah:** supaya QR bisa dibuka dari HP, isi `APP_URL` dengan alamat LAN server (misal `http://192.168.1.10:8888`), **jangan** `localhost`.

### Keamanan & Hak Akses
- Pengamanannya dua lapis: **middleware peran** (`CheckRole`) di route, plus **policy per data** (`KelasPolicy`, `MataPelajaranPolicy`, `JadwalPolicy`, `JurnalPolicy`). Hak akses presensi nempel ke **kelasnya**, bukan ke pertemuan: `KelasPolicy::isiPresensiHarian()` (ketua kelas + admin) dan `KelasPolicy::lihatPresensiHarian()` (admin, ketua, guru pengampu/wali, siswa kelas itu). Jadi aturan "siapa boleh mengisi kehadiran" cuma ditulis di satu tempat
- **Data disaring per peran**: guru cuma lihat kelas/mapel yang dia ampu, siswa cuma kelasnya sendiri
- Presensi dicek dulu ke daftar siswa rombelnya, biar `siswa_id` asing tidak bisa diselipkan
- Aman dari tabrakan data: transaksi + unique constraint `(kelas_id, tanggal, siswa_id)` waktu menyimpan presensi
- **Berkas impor divalidasi ketat**: hanya `.xlsx`/`.csv` maksimal 10 MB, XML-nya dibaca tanpa entity eksternal (biar spreadsheet tidak bisa mengintip berkas server), dan nama berkas yang dipakai di langkah konfirmasi harus berpola ULID — bukan nama dari browser
- **ID jurnal/presensi disamarkan di URL.** Route key-nya **ULID** (`jurnal.public_id`, unik & `NOT NULL`), bukan id berurutan, jadi orang tidak bisa jalan-jalan ke data lain dengan ganti-ganti angka di alamat. Pengaman utamanya tetap otorisasi, ID ini cuma pelengkap. **Berlaku juga di REST API**: alamat seperti `/api/jurnal/{...}` di-resolve pakai `public_id`. Isi body dan responsnya tetap bawa `id` angka supaya kontrak lama tidak berubah, dan respons jurnal ikut bawa `public_id` biar klien bisa menyusun alamatnya sendiri. Presensi dialamatkan lewat **kelas + tanggal**, bukan lewat jurnal
- **Semua input dari URL pakai whitelist**: `?per=` (25/50/75/100), `?sort=`/`?dir=` (daftar kolom per halaman), dan `?preset=`/rentang periode. Nilai yang tidak dikenal jatuh ke default, bukan error, dan tidak ada nilai dari URL yang langsung masuk ke SQL

### Tampilan & Cara Pakai
- **Enak dipakai di HP** (Android/iOS): sidebar geser, grid menyesuaikan, kontrol filter selebar layar. Tombol kehadiran H/S/I/A ikut ukuran layar (ukuran fontnya bisa diatur sendiri, lihat **Tampilan & Aksesibilitas** di bawah)
- **Dropdown yang bisa dicari** (tanpa library tambahan) buat daftar panjang: kelas, guru, mata pelajaran, jurusan, wali kelas. Dropdown yang pilihannya sedikit (tingkat, status, hari) sengaja dibiarkan biasa, karena kotak cari malah tidak berguna di situ
- **Filter periode** di histori jurnal, riwayat siswa, dan presensi: Hari Ini / Minggu Ini / Minggu Lalu / Bulan Ini / Bulan Lalu / 30 Hari / Tahun Ini, plus rentang bebas. Kartu statistiknya ikut periode biar angkanya nyambung sama tabelnya, dan filter lain tidak hilang waktu periodenya diganti
- **Filter Tingkat & Jurusan** di Jurnal, Presensi, Rekap Jurnal, dan Rekap Presensi. Satu komponen `<x-filter-tingkat-jurusan>` yang daftar jurusannya diambil otomatis dari kelas yang boleh dilihat peran itu (guru cuma dapat jurusan yang dia ampu). Filternya ikut sampai ke **ekspor Excel** dan ke **drill-down** kartu KPI di halaman rekap. Kepakai banget kalau sekolahnya punya banyak rombel/jurusan (misal SMK)
- **Sortir kolom** pakai panah ↑/↓, kolom yang aktif disorot. Total **81 kolom di 10 tabel** (jurnal guru & siswa, presensi, wali kelas, laporan admin, pengguna, kelas, mata pelajaran): tanggal, jam ke, kelas, mapel, guru, materi, tugas, H/S/I/A, persentase, kehadiran guru, status. Kolom yang isinya bar persentase diurutkan pakai rasionya, jadi pertemuan 20/20 tidak kalah dari 25/40. Kolom yang dihitung di PHP **setelah** paginasi (Kelengkapan, Status kelas, Guru Pengampu) sengaja tidak dikasih sortir, karena yang teracak cuma baris yang terlanjur diambil. Kelihatan jalan, padahal hasilnya salah
- **Jumlah baris per halaman** bisa dipilih (25/50/75/100), plus lompat ke halaman tertentu
- **Pilihan tidak saling menghapus.** Mencari atau ganti filter tidak bikin sortir, periode, dan ukuran halaman hilang (`<x-query-hidden>` bawa semuanya sebagai hidden field). Sebelumnya, ngetik satu kata pencarian diam-diam mereset ketiganya
- **Tombol "Lihat" di kolom Aksi** — aksinya ditulis pakai teks, bukan ikon doang, jadi tidak perlu ditebak-tebak
- **Dropdown jadwal ikut tanggal** yang dipilih (bukan semua jadwal), slot yang sudah diisi ditandai, dan kalau hari itu memang tidak ada jadwal muncul pesan "hubungi admin"
- Sisi performa: composite index, KPI landing di-cache, query agregat diringkas

### Tampilan & Aksesibilitas
Tombol **Tampilan** (ikon ◐) di pojok kanan atas membuka panel pengaturan. Semua pilihannya **ikut setelan perangkat secara default**, tapi bisa diatur manual.

| Pilihan | Isi | Default |
|---------|-----|--------|
| **Tema** | Sistem · Terang · **Gelap** | ikut sistem (`prefers-color-scheme`) |
| **Ukuran Font** | Kecil · Normal · Besar · Ekstra | Normal |
| **Kontras tinggi** | garis, teks samar, dan cincin fokus keyboard dipertegas | ikut sistem (`prefers-contrast`) |
| **Kurangi gerak** | animasi & transisi dimatikan | ikut sistem (`prefers-reduced-motion`) |

- **Disimpan di perangkat masing-masing** (`localStorage`), jadi tiap guru/siswa punya setelan sendiri di HP atau komputernya, tanpa akun dan tanpa nyentuh database
- **Diterapkan sebelum halaman digambar**, jadi tidak ada kedip putih waktu buka halaman dalam mode gelap
- **Mode gelapnya tidak pakai stylesheet kedua.** Seluruh tampilan pakai token warna, jadi mode gelap cukup memetakan ulang tokennya, tidak perlu nulis ulang tiap komponen
- **Ukuran font menskalakan seluruh tampilan**, bukan cuma teksnya, jadi tata letaknya tetap proporsional

### Ukuran Aset & Mode Offline
Sekolah bisa saja menjalankan aplikasi ini di jaringan lokal tanpa internet keluar. Jadi tidak ada satu pun permintaan ke luar waktu halaman dibuka: font dan ikon ikut dibundel, bukan diambil dari CDN.

- **Font Inter** cuma subset **Latin**. Paket lengkapnya bawa Cyrillic/Greek/Vietnamese yang tidak akan pernah kepakai: dari 58 berkas font jadi **8**
- **Bootstrap** diimpor per bagian. Yang dipakai cuma 42 utility class dan dua komponen (dropdown, modal). Grid, tabel, form, tombol, navbar, alert, carousel, tooltip, dan lainnya tidak dipakai jadi tidak ikut dikirim
- **Ikon ditulis sebagai SVG inline** (`App\Support\Ikon`), bukan icon font. Bootstrap Icons aslinya mengirim stylesheet berisi 2078 class plus webfont ~134 KB cuma buat 30 ikon yang kepakai. Sekarang ketiganya tidak ikut sama sekali, dan ikon tidak bakal muncul jadi kotak kosong gara-gara fontnya gagal dimuat. Ada `IkonTest` juga: kalau ada ikon yang dipakai tapi belum terdaftar, testnya langsung gagal
- **Halaman error berdiri sendiri**: CSS-nya inline, lambangnya SVG inline, jadi tetap tampil justru waktu yang rusak adalah pipeline asetnya

Hasil optimasinya kira-kira segini:

| | Sebelum | Sesudah |
|---|---|---|
| CSS | 321,3 KB | **124,8 KB** |
| JS | 85,7 KB | **58,7 KB** |
| Webfont ikon | 134 KB woff2 + 180 KB woff | **tidak ada** |
| Berkas font total | 58 | **8** (Inter Latin saja) |

Angka di atas diambil dari berkas asli di `public/build/assets/` setelah `bun run build`.

### Halaman Error yang Menyesuaikan Peran
- Guru & siswa **tidak pernah lihat stack trace Laravel**. Kalau ada error, yang muncul halaman berbahasa Indonesia yang enak dibaca plus **kode referensi**, lengkap sama tombol **Kembali** / **Ke Dashboard**
- **Admin tetap lihat detail error lengkap**, karena yang men-debug memang admin
- Guru/siswa bisa **kirim laporan error** ke admin langsung dari halaman itu. Detail teknisnya diambil dari session (jadi tidak bisa dipalsukan dari browser), bukan dari isian form
- **Anti-spam berlapis**: 1 laporan tiap **10 menit** per akun, maksimal **5 per hari**, plus dedupe. Error yang sama kalau dilaporkan lagi cuma menambah penghitung `jumlah`, tidak bikin baris baru
- Halaman 404/403/419/429/500/503 tampilannya sama-sama ramah (konsisten waktu `APP_DEBUG=false`)

### Sistem & Log (admin)
- **Status komponen** langsung: database + latensinya, **migrasi yang tertunda** (biar bug "tabel belum ada" ketahuan), Cache/Redis, storage bisa ditulis atau tidak, ukuran log, ada tidaknya **objek DB lanjutan** (view/function/procedure/trigger), sampai kewajaran konfigurasi (`APP_DEBUG`, dan **`APP_URL` bukan localhost** yang penting buat QR kelas)
- **Log viewer**: cuma bagian akhir berkas log yang dibaca, jadi aman buat log besar. Bisa difilter per level dan dibersihkan
- **Kotak masuk laporan error** dari guru/siswa, lengkap sama status: baru → diproses → selesai
- **Pengelola pengumuman**: banner buat guru & siswa waktu ada pemeliharaan atau gangguan, tanpa harus `artisan down` yang malah mematikan akses admin juga. Banner gangguan juga muncul sendiri (isinya umum, tanpa detail teknis) kalau healthcheck mendeteksi masalah

### Cadangan & Pemulihan Data (admin)
Buat **pindah server** atau **memulihkan data** waktu server bermasalah, tanpa perlu akses shell ke database (`/admin/cadangan`, khusus admin).

- **Ekspor JSON** (format buat restore) — semua tabel beserta id & relasinya, **ditulis tabel per tabel** lalu **di-gzip** (`.json.gz`, sekitar 20× lebih kecil), jadi arsip presensi yang ratusan ribu baris tidak pernah dimuat sekaligus ke memori
- **Ekspor XLSX** yang bisa dibaca/diedit (satu sheet per tabel master, termasuk `presensi_harian`; arsip `presensi` per pertemuan dan kolom sensitif seperti password dibuang) buat sekadar melihat atau mengolah data di spreadsheet
- **Pilih tabel** yang mau dicadangkan lewat centang, kalau cuma mau backup sebagian
- **Restore** dari berkas JSON atau `.json.gz` (gzip dideteksi dari *magic byte*, bukan dari ekstensinya), ada dua mode: **Gabung** (upsert per id, tidak menghapus apa pun) atau **Ganti total** (dikosongkan dulu baru diisi persis isi backup). Mode ganti total wajib centang konfirmasi
- Seluruh restore jalan **dalam satu transaksi dengan FK dimatikan sementara** (biar siklus FK `users` ↔ `kelas` bisa ditangani). Kalau gagal di tengah jalan, tidak ada perubahan yang tersimpan

---

## Framework & Teknologi

### Backend
| Teknologi | Versi | Dipakai buat |
|-----------|-------|-------|
| [Laravel Framework](https://laravel.com) | 13.x | Framework utama (PHP) |
| [PHP](https://www.php.net) | 8.3 | Bahasa & runtime |
| [Laravel Sanctum](https://laravel.com/docs/sanctum) | latest | Autentikasi API pakai token |
| [Laravel Tinker](https://github.com/laravel/tinker) | 3.x | REPL / console interaktif |
| `ext-zip` (ZipArchive) | bawaan PHP | Nulis file `.xlsx` (OOXML) tanpa library luar |
| [dompdf/dompdf](https://github.com/dompdf/dompdf) | 3.x | Ekspor PDF lembar QR kelas. Murni PHP, tanpa binary luar, tetap jalan offline |

### Frontend
| Teknologi | Versi | Dipakai buat |
|-----------|-------|-------|
| [Blade](https://laravel.com/docs/blade) | — | Templating di sisi server |
| [Bootstrap](https://getbootstrap.com) | 5.3 | Diimpor **per bagian**, bukan sebagai bundel utuh: utility API + dropdown + modal saja. Grid, tabel, form, tombol, navbar, alert, carousel, tooltip tidak dipakai jadi tidak ikut dikirim |
| [@popperjs/core](https://popper.js.org) | 2.11 | Posisi dropdown Bootstrap (tooltip/popover tidak ikut dibundel) |
| [@fontsource/inter](https://fontsource.org/fonts/inter) | 5.x | Font Inter **self-hosted**, subset Latin. Tidak ada permintaan ke Google Fonts |
| [Sass (Dart Sass)](https://sass-lang.com) | 1.77 | Preprocessor CSS (`resources/sass/app.scss`) |
| [Vite](https://vitejs.dev) | 8.x | Build tool & dev server (HMR) |
| [laravel-vite-plugin](https://github.com/laravel/vite-plugin) | 3.x | Nyambungin Vite ⇄ Laravel |
| [Bootstrap Icons](https://icons.getbootstrap.com) | 1.13 | **Cuma diambil jalur SVG-nya**, bukan dependensi runtime. Ikonnya di-inline lewat `App\Support\Ikon`; stylesheet & webfont-nya tidak ikut dibundel |
| Vanilla JS | — | Drill-down AJAX, searchable-select, pengaturan tampilan (tema/font/kontras/gerak). Tanpa framework JS |

### Database & Infrastruktur
| Teknologi | Versi | Dipakai buat |
|-----------|-------|-------|
| [MySQL](https://www.mysql.com) | 8.0 | Database utama (+ view, function, procedure, trigger, FULLTEXT) |
| [SQLite](https://www.sqlite.org) | — | Database buat test suite (in-memory) |
| [Redis](https://redis.io) | 7 | Session, cache, & queue |
| [Docker](https://www.docker.com) / Compose | — | Ngatur banyak container |
| [Nginx](https://nginx.org) | Alpine | Web server / reverse proxy |
| [Mailpit](https://github.com/axllent/mailpit) | — | Nangkep email buat testing |
| [Bun](https://bun.sh) | Alpine | Runtime & package manager buat build frontend |
| [phpMyAdmin](https://www.phpmyadmin.net) | 5 | Kelola database (profile `dev`) |

### Dev Tools & Testing
| Teknologi | Dipakai buat |
|-----------|-------|
| [PHPUnit](https://phpunit.de) 12 | Test suite (Feature/Unit) |
| [Laravel Pint](https://laravel.com/docs/pint) | Rapiin code style (PSR-12) |
| [Mockery](https://github.com/mockery/mockery) | Mocking buat test |
| [FakerPHP](https://fakerphp.github.io) | Data dummy buat seeder/factory |
| [Laravel Pail](https://github.com/laravel/pail) | Lihat log real-time |
| [Nunomaduro Collision](https://github.com/nunomaduro/collision) | Tampilan error CLI yang rapi |

---

## Arsitektur

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

### Objek Database

| Tipe | Nama | Keterangan |
|------|------|------------|
| View | `v_jurnal_lengkap` | Gabungan jurnal + jadwal + kelas + mapel + guru |
| View | `v_rekap_presensi_kelas` | Rekap kehadiran per kelas (dari `presensi_harian`) |
| Function | `fn_persentase_kehadiran_siswa` | Persentase kehadiran siswa |
| Function | `fn_persentase_kehadiran_kelas` | Persentase kehadiran kelas |
| Procedure | `sp_simpan_presensi_harian` | Simpan presensi sekelas untuk satu hari lewat JSON |
| Procedure | `sp_simpan_presensi` | *(lama)* Simpan presensi per pertemuan — tidak dipakai lagi |
| Trigger | `trg_jurnal_after_update` | Catat perubahan jurnal |
| Trigger | `trg_jurnal_after_delete` | Catat penghapusan jurnal |

---

## Relasi Antar Tabel

```
User (admin/guru/siswa)
 ├── belongsTo → Kelas (siswa saja, via kelas_id)
 ├── hasMany   → Jadwal (guru saja, via guru_id)
 ├── hasMany   → Jurnal (guru saja, via guru_id)
 ├── hasMany   → PresensiHarian (siswa saja, via siswa_id)
 └── hasMany   → Presensi (arsip per pertemuan, via siswa_id)

Kelas
 ├── belongsTo → User (wali_kelas_id)
 ├── hasMany   → User (siswa)
 ├── hasMany   → PresensiHarian
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
 ├── hasMany   → Presensi (arsip; presensi sekarang per hari, bukan per jurnal)
 └── hasMany   → JurnalAudit

PresensiHarian                       ← satu baris per siswa per hari sekolah
 ├── belongsTo → Kelas               unique (kelas_id, tanggal, siswa_id)
 ├── belongsTo → User (siswa)
 └── belongsTo → User (diisi_oleh / ketua kelas)

Presensi (arsip, tidak ditulis lagi)
 ├── belongsTo → Jurnal
 └── belongsTo → User (siswa)
```

---

## Instalasi & Setup

### Yang Perlu Disiapkan

- [Docker](https://docs.docker.com/get-docker/) & [Docker Compose](https://docs.docker.com/compose/install/)
- Git

### Cara Cepat (Docker)

```bash
# 1. Clone repository
git clone https://github.com/your-username/Jurnal-Kelas-App.git
cd Jurnal-Kelas-App

# 2. Build & jalankan container
docker compose up -d --build

# 3. Jalankan setup otomatis
make setup
# atau langsung: bash setup.sh
```

Script setup-nya bakal:
1. Install dependency Composer
2. Bikin `.env` dari `.env.example` & generate app key
3. Jalankan migrasi database (termasuk view, function, procedure, trigger)
4. Isi data demo (admin, 12 guru, 18 mapel, 12 kelas, 360 siswa, jadwal lengkap, jurnal & presensi lama)
5. Install dependency JS lewat Bun
6. Build aset produksi

### Alamat Akses

| Service | URL | Keterangan |
|---------|-----|------------|
| 🌐 Aplikasi | http://localhost:8888 | Web utama |
| 📧 Mailpit | http://localhost:8025 | Tempat lihat email test |
| 🗄️ phpMyAdmin | http://localhost:8081 | Kelola database (profile `dev`) |
| ⚡ Vite HMR | http://localhost:5173 | Hot Module Replacement (profile `dev`) |

### Akun Bawaan

| Peran | Username | Password |
|------|----------|----------|
| Admin | `admin@jurnalkelas.app` | `password` |
| Guru | `budi.santoso@jurnalkelas.app` | `password` |

> **Catatan:** guru juga bisa login pakai NIP, siswa pakai NIS.

### Setup Lokal (Tanpa Docker)

```bash
# 1. Install dependency
composer install
npm install  # atau: bun install

# 2. Siapkan environment
cp .env.example .env
php artisan key:generate

# 3. Atur database
# Edit .env: uncomment DB_CONNECTION=sqlite (atau pakai MySQL lokal)

# 4. Migrasi & isi data
php artisan migrate
php artisan db:seed

# 5. Jalankan server
composer dev
# Atau manual:
php artisan serve
npm run dev  # di terminal terpisah
```

### Checklist Sebelum Dipakai di Server Sekolah

Hal-hal yang beda antara komputer development sama server sekolah. Dicek satu-satu sebelum dipakai mengajar:

- [ ] **`APP_URL` diisi alamat LAN server** (misal `http://192.168.1.10:8888`), **bukan** `localhost`. Kalau tidak, QR kelas yang sudah dicetak tidak bisa dibuka dari HP guru
- [ ] **`APP_TIMEZONE` sesuai lokasi sekolah** (WIB/WITA/WIT). Ini yang menentukan kapan hari berganti buat jurnal otomatis dan penanda telat
- [ ] **`APP_DEBUG=false`** dan **`APP_ENV=production`**, biar detail error tertutup buat semua peran
- [ ] **Pakai aset produksi, bukan dev server**: jalankan `bun run build` dan pastikan berkas `public/hot` **sudah tidak ada**. Kalau `public/hot` ketinggalan, halaman bakal nyari Vite di `localhost:5173` dan tampilannya rusak di komputer lain
- [ ] **Queue worker & scheduler hidup** (sudah otomatis lewat supervisord di container `app`). Dua-duanya wajib buat jurnal otomatis tiap malam. Cek pakai `php artisan schedule:list`
- [ ] **Migrasi sudah dijalankan**: `php artisan migrate --force`
- [ ] **Batas upload dinaikkan** kalau mau restore cadangan besar: `upload_max_filesize` / `post_max_size` (PHP) dan `client_max_body_size` (nginx)
- [ ] **Cadangan pertama sudah diunduh & disimpan di luar server** lewat menu *Cadangan Data*
- [ ] **Password admin bawaan sudah diganti** kalau data demo dipakai sebagai titik awal

---

## Daftar Perintah

### Makefile (Docker)

```bash
make help           # Lihat semua perintah
make build          # Build Docker image
make up             # Nyalain semua container
make up-dev         # Nyalain semua container + dev tools (phpMyAdmin, Vite HMR)
make down           # Matikan semua container
make restart        # Restart semua container
make logs           # Lihat log container (follow)
make shell          # Masuk shell di container app
make artisan cmd="" # Jalankan perintah artisan
make migrate        # Jalankan migrasi
make seed           # Jalankan seeder
make fresh          # Migrasi ulang dari nol + seed
make dev            # Nyalain Vite dev server
make build-assets   # Build aset produksi
make setup          # Jalankan setup awal
```

### Composer Scripts

```bash
composer setup      # Setup lengkap: install, env, key, migrate, npm, build
composer dev        # Jalankan server + queue + pail + vite sekaligus
composer test       # Jalankan test suite (PHPUnit)
composer lint       # Rapiin code style otomatis (Laravel Pint)
composer quality    # Cek code style (pint --test) + jalankan test suite
```

---

## REST API

Semua endpoint pakai prefix `/api` dan autentikasi lewat bearer token **Laravel Sanctum**.

### Autentikasi

```bash
# Login - dapetin bearer token
POST /api/login
Body: { "user": "admin@jurnalkelas.app", "password": "password", "role": "admin" }

# Logout
POST /api/logout
Header: Authorization: Bearer {token}

# Info user yang lagi login
GET /api/me
```

### Endpoint

> **Soal ID jurnal:** alamat jurnal pakai **`public_id`** (ULID), bukan id angka. Jadi `PUT /api/jurnal/123` bakal balas **404**. Nilainya ada di tiap respons jurnal (`data.public_id`), jadi klien tinggal pakai apa yang dikembalikan API.
>
> **Soal presensi:** presensi bukan milik jurnal lagi, jadi alamatnya pakai **id kelas + `?tanggal=`** (default hari ini). Yang boleh menulis cuma **ketua kelas** kelas itu (hari ini saja) dan **admin**.

| Method | Endpoint | Akses | Keterangan |
|--------|----------|-------|------------|
| `GET` | `/api/dashboard` | Semua | Ringkasan KPI sesuai peran |
| `GET` | `/api/kelas` | Semua | Daftar kelas |
| `GET` | `/api/mata-pelajaran` | Semua | Daftar mata pelajaran |
| `GET` | `/api/jadwal` | Semua | Daftar jadwal |
| `GET` | `/api/jurnal` | Semua | Daftar jurnal (disaring per peran) |
| `POST` | `/api/jurnal` | Guru | Buat jurnal baru |
| `PUT` | `/api/jurnal/{public_id}` | Guru/Admin | Ubah jurnal |
| `DELETE` | `/api/jurnal/{public_id}` | Guru/Admin | Hapus jurnal |
| `GET` | `/api/jurnal/{public_id}/audit` | Semua | Riwayat perubahan jurnal |
| `GET` | `/api/presensi` | Semua | Daftar presensi harian (disaring per peran; filter `kelas_id`, `tanggal`, `mulai`, `selesai`) |
| `GET` | `/api/presensi/{kelas}` | Semua | Presensi satu kelas pada satu hari (`?tanggal=`, default hari ini) |
| `POST` | `/api/presensi/{kelas}` | Ketua kelas / Admin | Isi presensi sekelas untuk satu hari |
| `GET` | `/api/statistik/kehadiran` | Semua | Statistik kehadiran |
| `POST/PUT/DELETE` | `/api/kelas/*` | Admin | Kelola kelas |
| `POST/PUT/DELETE` | `/api/mata-pelajaran/*` | Admin | Kelola mata pelajaran |
| `POST/PUT/DELETE` | `/api/jadwal/*` | Admin | Kelola jadwal |
| `GET/POST/PUT/DELETE` | `/api/users/*` | Admin | Kelola user |
| `GET` | `/api/laporan/jurnal` | Admin | Laporan jurnal |
| `GET` | `/api/laporan/presensi` | Admin | Laporan presensi |
| `GET` | `/api/laporan/rekap-kelas` | Admin | Rekap per kelas |

---

## Container Docker

| Container | Image | Port | Keterangan |
|-----------|-------|------|------------|
| `jurnal-kelas-app` | PHP 8.3-FPM Alpine | — | Aplikasi Laravel + Supervisord |
| `jurnal-kelas-nginx` | Nginx Alpine | `8888` | Web server |
| `jurnal-kelas-mysql` | MySQL 8.0 | `3306` | Database |
| `jurnal-kelas-redis` | Redis 7 Alpine | `6379` | Session, cache, queue |
| `jurnal-kelas-mailpit` | Mailpit | `8025` / `1025` | Email testing |
| `jurnal-kelas-bun` | Bun Alpine | `5173` | Vite HMR (profile `dev`) |
| `jurnal-kelas-phpmyadmin` | phpMyAdmin 5 | `8081` | Kelola DB (profile `dev`) |

> Container `bun` dan `phpmyadmin` cuma nyala kalau pakai profile `dev`:
> ```bash
> docker compose --profile dev up -d
> # atau: make up-dev
> ```

---

## Struktur Project

```
Jurnal-Kelas-App/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── IsiJurnalOtomatis.php   # Isi jurnal kosong tiap malam (dijadwalkan 00:30)
│   ├── Jobs/
│   │   └── IsiJurnalGelombang.php      # Satu gelombang pengisian otomatis (lewat antrean)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/            # Dashboard, Laporan, User, Impor (siswa/guru dari Excel),
│   │   │   │                     #   Sistem, Cadangan (backup/restore), KelasQr (cetak/PDF), PresensiLog
│   │   │   ├── Api/              # Controller REST API (mirip controller web)
│   │   │   ├── Auth/             # LoginController
│   │   │   ├── DashboardController.php
│   │   │   ├── JadwalController.php
│   │   │   ├── JurnalController.php
│   │   │   ├── KelasController.php
│   │   │   ├── LandingController.php
│   │   │   ├── LaporanErrorController.php   # Laporan error dari guru/siswa
│   │   │   ├── MataPelajaranController.php
│   │   │   ├── PresensiController.php       # Baca rekap presensi + ekspor Excel harian/bulanan
│   │   │   ├── PresensiHarianController.php # Isi presensi sekali sehari (ketua kelas / admin)
│   │   │   ├── QrController.php             # Halaman tujuan setelah scan QR ruang kelas
│   │   │   └── WaliKelasController.php      # Mode Wali Kelas
│   │   ├── Middleware/
│   │   │   └── CheckRole.php     # Cek hak akses per peran (role:admin,guru,…)
│   │   ├── Requests/             # Form request dipakai bareng web + API
│   │   │   └── Api/              # JurnalRequest khusus API
│   │   └── Resources/            # Transformer respons API
│   ├── Models/                   # 10 model Eloquent
│   ├── Policies/                 # KelasPolicy, MataPelajaranPolicy, JadwalPolicy, JurnalPolicy
│   │                             #   (presensi harian diatur lewat KelasPolicy)
│   └── Support/
│       ├── CadanganData.php      # Ekspor/impor seluruh data (backup JSON gzip + restore, XLSX)
│       ├── DbDriver.php          # Deteksi driver (MySQL vs SQLite)
│       ├── Halaman.php           # Whitelist ukuran halaman (25/50/75/100)
│       ├── Ikon.php              # Ikon SVG inline (pengganti icon font)
│       ├── LoginResolver.php     # Resolver login NIP/NIS/Email
│       ├── PembacaLog.php        # Baca bagian akhir berkas log (aman buat log besar)
│       ├── Periode.php           # Object rentang tanggal
│       ├── PesanError.php        # Teks halaman error per peran
│       ├── ImporPengguna.php     # Template impor + validasi & simpan siswa/guru dari spreadsheet
│       ├── RekapPresensi.php     # Bentuk kueri rekap presensi harian (per kelas-hari / siswa / matriks)
│       ├── Ringkasan.php         # Penghitung statistik
│       ├── SimpanPresensiHarian.php # Satu jalur simpan presensi harian (procedure/transaksi)
│       ├── SistemStatus.php      # Healthcheck buat halaman Sistem & Log
│       ├── Urutan.php            # Sortir kolom ber-whitelist (?sort=/?dir=)
│       ├── XlsxExport.php        # Penulis .xlsx (OOXML) lewat ZipArchive
│       └── XlsxReader.php        # Pembaca .xlsx/.csv buat impor (ZipArchive + SimpleXML)
├── database/
│   ├── migrations/               # 36 migrasi (tabel, index, view, function, trigger)
│   └── seeders/
│       ├── DemoSeeder.php        # Data demo default (dipakai make setup & test suite)
│       └── SmkSeeder.php         # Simulasi SMK besar (10 jurusan, ~45 rombel), opsional, dev-only
├── docker/
│   ├── nginx/                    # Konfigurasi Nginx
│   └── php/                      # Dockerfile & config PHP
├── resources/
│   ├── sass/                     # SCSS (lapisan Bootstrap terpilih + custom + breakpoint mobile)
│   ├── js/                       # JS (dropdown/modal Bootstrap, drill-down AJAX, searchable-select)
│   └── views/                    # Template Blade
│       ├── admin/                # Halaman admin (users, impor, laporan, sistem, kelas-qr, presensi-log, cadangan)
│       ├── dashboard/            # Dashboard per peran
│       ├── wali-kelas/           # Mode Wali Kelas (dashboard, data, jadwal, jurnal, presensi)
│       ├── jurnal/               # Halaman jurnal
│       ├── presensi/             # Rekap presensi (guru/admin) & rekap pribadi siswa
│       ├── presensi-harian/      # Isi & lihat presensi satu kelas satu hari
│       ├── kelas/                # Halaman kelas
│       ├── mata-pelajaran/       # Halaman mata pelajaran
│       ├── jadwal/               # Halaman jadwal
│       ├── qr/                   # Halaman konfirmasi setelah scan QR
│       ├── errors/               # ramah.blade.php + view 403/404/419/429/500/503
│       ├── layouts/              # Layout utama (sidebar, nav)
│       └── components/           # 27 komponen Blade, a.l. x-ikon, x-th-sort, x-pager,
│                                 #   x-periode-filter, x-query-hidden, x-filter-tingkat-jurusan,
│                                 #   x-jurnal-attestasi, x-jurnal-edit-badge
├── routes/
│   ├── web.php                   # Route web
│   ├── api.php                   # Route REST API
│   └── console.php               # Penjadwalan (jurnal:isi-otomatis tiap 00:30)
├── docker-compose.yml
├── Makefile
├── setup.sh
└── vite.config.js
```

---

## Testing

```bash
# Lewat Composer
composer test

# Pint + seluruh suite sekaligus (dijalankan sebelum commit)
composer quality

# Atau langsung
php artisan test

# Di dalam Docker
make artisan cmd="test"
```

Suite-nya jalan di **SQLite in-memory**, jadi objek DB khusus MySQL (view, function, procedure, trigger, FULLTEXT) dilewati lewat cabang `DbDriver::mysql()`. Test tetap bisa dijalankan tanpa MySQL.

Tiap berkas test menjaga satu jenis kesalahan yang pernah benar-benar kejadian:

| Berkas | Yang dijaga |
|--------|--------------|
| `AuthorizationTest` | Batas peran: master data tertutup buat siswa, guru tidak bisa nyentuh kelas/jurnal orang lain, wali kelas boleh **baca** tapi tidak boleh **hapus**, ekspor khusus admin, id angka lama sudah tidak resolve |
| `JurnalGandaTest` | Satu jurnal per sisi per pertemuan (guru + ketua), termasuk waktu unique index-nya menolak |
| `PresensiRosterTest` | Presensi sekali sehari per kelas: cuma ketua kelas yang mengisi (guru & wali cuma baca), cuma untuk hari ini, simpan ulang mengganti bukan menambah, siswa kelas lain ditolak, plus audit log |
| `ImporPenggunaTest` | Impor siswa/guru dari spreadsheet: template yang diunduh bisa dibaca balik, pratinjau tidak menulis apa pun, baris bermasalah dilewati, menimpa akun harus disengaja, dan nama berkas hasil karangan ditolak |
| `JadwalFormTest` | Dropdown jadwal ikut tanggal, slot terisi ditandai, hari kosong kasih penjelasan |
| `PeriodeFilterTest` | Preset periode beneran menyaring, tidak melebarkan akses peran, dan filter form tidak membuang sortir/periode/ukuran halaman |
| `PaginationTest` | Whitelist `?per=`; ukuran halaman tidak bisa dipakai buat melebarkan akses |
| `QrAksesTest` | QR cuma buat guru, bisa pilih sebagian kelas, unduhan PDF beneran PDF & khusus admin |
| `JurnalOtomatisTest` | Pengisian otomatis bikin jurnal "sistem", bisa diulang tanpa dobel, dan tidak nyentuh hari ini/masa depan; jurnal otomatis wajib centang pernyataan waktu diubah lalu diadopsi; label "diedit setelah hari-H" + filternya |
| `ApiJurnalKontrakTest` | API mengembalikan identitas yang dipakai alamatnya sendiri (`public_id`): bisa baca → update cuma bermodal respons, dan id angka memang bukan kunci URL |
| `CadanganTest` | Backup/restore khusus admin: ekspor memuat semua tabel & bisa dipilih sebagian, unduhan ter-gzip dan bisa dipulihkan lagi (gabung/ganti), berkas asing ditolak |
| `IkonTest` | Semua ikon yang dipakai memang ada, biar ikon tidak kosong diam-diam |
| `ErrorHandlingTest` | Guru/siswa tidak pernah lihat stack trace; admin tetap lihat detail |
| `RolePagesTest`, `CrudPagesTest`, `AdminSectionTest`, `DashboardPeriodeTest`, `LoginTest` | Tiap halaman per peran kerender, form CRUD jalan, login tiap peran jalan |

---

## Environment Variables

Variabel penting di `.env`:

| Variable | Default | Keterangan |
|----------|---------|------------|
| `APP_NAME` | `Jurnal Kelas` | Nama aplikasi |
| `APP_URL` | `http://localhost:8888` | URL aplikasi. **Isi pakai alamat LAN sekolah** (misal `http://192.168.1.10:8888`) waktu deploy beneran, biar QR kelas bisa dibuka dari HP guru |
| `APP_TIMEZONE` | `Asia/Makassar` | Zona waktu sekolah (`Asia/Jakarta` WIB / `Asia/Makassar` WITA / `Asia/Jayapura` WIT). **Menentukan kapan hari berganti**, dipakai buat jurnal otomatis 00:30, penanda telat, dan label "diedit setelah hari-H" |
| `APP_LOCALE` | `id` | Bahasa Indonesia |
| `APP_DEBUG` | `true` | Detail error buat admin. **Isi `false` di produksi.** Guru/siswa memang sudah selalu dapat halaman ramah, tapi `false` menutup detailnya buat semua peran |
| `DB_CONNECTION` | `mysql` | Driver database |
| `DB_HOST` | `mysql` | Host DB (nama container Docker) |
| `DB_DATABASE` | `jurnal_kelas` | Nama database |
| `SESSION_DRIVER` | `redis` | Penyimpanan session |
| `CACHE_STORE` | `redis` | Backend cache |
| `QUEUE_CONNECTION` | `redis` | Backend queue |
| `MAIL_HOST` | `mailpit` | SMTP server (Mailpit di Docker) |

> Buat development lokal tanpa Docker, ganti `DB_CONNECTION=sqlite` dan comment konfigurasi MySQL-nya.

---

## Kontributor

| Nama | GitHub | Bagian |
|------|--------|--------|
| **Nurfauzan Gymnastiar** | [@nfgcode](https://github.com/nfgcode) | UI/UX, Frontend, Responsive |
| **Akmal Falah Maulana** | [@ShiroTenma](https://github.com/ShiroTenma) | Backend, Database, Responsive, Optimizing & Refactor, Fitur QR Code (+ cetak/ekspor PDF), Error Handling, Otorisasi & ID abstrak, Filter periode / sortir / paginasi, Optimasi bobot aset & mode offline |

---

## 📄 Lisensi

Project ini dilisensikan di bawah [Apache License 2.0](LICENSE).
