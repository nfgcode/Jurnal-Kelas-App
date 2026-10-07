# Backend Jurusan — daftar kerja untuk Akmal

Frontend halaman **Data Jurusan** dan kolom **jurusan siswa** sudah dibuat (branch `fitur-jurusan`).
Yang belum ada: route, controller, validasi, dan logika sinkronisasi. Dokumen ini berisi semua
yang perlu dibuat supaya halamannya hidup, plus kontrak data yang diharapkan view.

## Yang sudah selesai (jangan dibuat ulang)

| Bagian | Berkas |
|---|---|
| Tabel `jurusan` (kode PK, nama, bidang, deskripsi, aktif) | sudah ada sejak awal — `2024_01_01_000000_create_master_akademik_tables.php` |
| Model `Jurusan` + relasi `kelas()`, **`siswa()`** (baru), scope `aktif()`, `cari()`, `label()` | `app/Models/Jurusan.php` |
| Kolom **`siswa.jurusan_kode`** (FK → `jurusan.kode`, `ON UPDATE CASCADE`, `ON DELETE SET NULL`) + isi awal dari kelas | `database/migrations/2026_10_08_000001_add_jurusan_kode_to_siswa.php` |
| `Siswa::jurusan()` + `jurusan_kode` di `$fillable` | `app/Models/Siswa.php` |
| Seeder Demo/Smk mengisi `jurusan_kode` dari kelas | `database/seeders/DemoSeeder.php`, `SmkSeeder.php` |
| View: daftar, tambah, ubah (+hapus), form bersama | `resources/views/admin/jurusan/{index,create,edit,form}.blade.php` |
| Link sidebar "Jurusan" — **muncul otomatis begitu route `admin.jurusan.index` terdaftar** | `resources/views/layouts/app.blade.php` |
| Kolom Jurusan di tabel Data Siswa + baris Jurusan di detail siswa | `resources/views/admin/siswa/{index,show}.blade.php` |
| Test kontrak tampilan (route tiruan) | `tests/Feature/JurusanTampilanTest.php` |

## Wajib (supaya halaman jalan)

### 1. Route
Di `routes/web.php`, di dalam grup `Route::prefix('admin')->name('admin.')->middleware('role:admin')`:

```php
Route::resource('jurusan', JurusanController::class)->except('show');
```

Nama route yang dipakai view: `admin.jurusan.index|create|store|edit|update|destroy`.
Parameter `{jurusan}` di-bind lewat primary key `kode` (mis. `/admin/jurusan/TKJ/edit`) — tidak perlu `getRouteKeyName()`.

### 2. `App\Http\Requests\JurusanRequest`
| Field | Aturan |
|---|---|
| `kode` | `required`, `string`, `max:20`, tanpa spasi (mis. `regex:/^[A-Za-z0-9-]+$/`), **unik** `Rule::unique('jurusan', 'kode')->ignore($this->route('jurusan')?->kode, 'kode')` |
| `nama` | `required`, `string`, `max:255` |
| `bidang` | `nullable`, `string`, `max:255` |
| `deskripsi` | `nullable`, `string` |
| `aktif` | `required`, `boolean` — form selalu mengirim `0` (hidden) lalu `1` bila dicentang |

Di `prepareForValidation()`: `kode` di-`trim` dan di-`strtoupper` (input sudah tampil kapital, tapi nilai yang dikirim tetap apa adanya).
Pakai **`$request->validated()`** saja, jangan `$request->all()` (aturan mass assignment proyek ini).

### 3. `App\Http\Controllers\Admin\JurusanController`

**`index(Request $request)`** → `view('admin.jurusan.index', [...])`
- Validasi filter: `q` (nullable string max 255), `bidang` (nullable string max 255), `status` (`nullable|in:aktif,nonaktif`).
- Query: `Jurusan::withCount(['kelas', 'siswa'])`, lalu `->cari($q)`, `->where('bidang', …)`, `->where('aktif', $status === 'aktif')`.
- Urutan lewat `App\Support\Urutan::terapkan($query, $request, $peta, $bawaan)` dengan kunci:
  `kode`, `nama`, `bidang`, `kelas` (`orderBy('kelas_count')`), `siswa` (`orderBy('siswa_count')`); bawaan `orderBy('kode')`.
- `->paginate(Halaman::perHalaman())->withQueryString()`.

| Variabel | Isi |
|---|---|
| `$jurusan` | paginator `Jurusan` dengan `kelas_count` & `siswa_count` |
| `$filters` | array hasil validasi filter |
| `$bidangList` | `Collection<string>` bidang unik yang tidak kosong, urut abjad |
| `$statistik` | `['total' => int, 'aktif' => int, 'bidang' => int, 'kelas' => int, 'siswa' => int]` — `kelas` = jumlah kelas yang punya jurusan, `siswa` = jumlah siswa yang punya jurusan |

**`create()`** → `view('admin.jurusan.create', ['bidangList' => …])`

**`store(JurusanRequest $request)`** → `Jurusan::create($request->validated())`, redirect ke `admin.jurusan.index` dengan `->with('success', 'Jurusan TKJ ditambahkan.')`.

**`edit(Jurusan $jurusan)`** → `view('admin.jurusan.edit', ['jurusan' => $jurusan->loadCount(['kelas', 'siswa']), 'bidangList' => …])`.
View memakai `kelas_count`/`siswa_count` untuk mengunci tombol Hapus — jangan lupa `loadCount`.

**`update(JurusanRequest $request, Jurusan $jurusan)`** → `$jurusan->update($request->validated())`, redirect ke index + `success`.
Lihat **poin 4** soal mengganti kode.

**`destroy(Jurusan $jurusan)`** → **tolak** bila masih dipakai (`$jurusan->kelas()->exists() || $jurusan->siswa()->exists()`):
`return back()->with('error', 'Jurusan masih dipakai … — nonaktifkan saja.')`. Tombolnya memang sudah dikunci di UI,
tapi request buatan tangan tetap harus ditolak di server. Kalau tidak dipakai: `delete()`, redirect ke index + `success`.

> Penting: tanpa penolakan ini, menghapus jurusan akan **diam-diam mengosongkan** jurusan semua kelas & siswanya
> (FK `ON DELETE SET NULL`).

### 4. Foreign key `kelas.jurusan_kode` belum ikut berubah saat kode diganti
Dicek langsung di MySQL dev:

```
kelas:kelas_jurusan_kode_foreign  update=NO ACTION  delete=SET NULL
siswa:siswa_jurusan_kode_foreign  update=CASCADE    delete=SET NULL
```

Artinya mengganti kode `TKJ` → `TJKT` lewat form akan **gagal (500)** selama masih ada kelas yang memakainya.
Pilih salah satu:
- **(disarankan)** migrasi baru yang mengganti FK kelas: `dropForeign(['jurusan_kode'])` lalu
  `->foreign('jurusan_kode')->references('kode')->on('jurusan')->cascadeOnUpdate()->nullOnDelete()`.
  Kode baru Kurikulum Merdeka (mis. TKJ → TJKT) memang bisa terjadi, jadi ini kasus nyata.
- atau kunci kode di form ubah bila sudah dipakai (validasi + jadikan input `readonly`), dan ubah teks bantuan
  di `form.blade.php` ("Mengubah kode ikut mengganti kode di kelas dan siswa…").

### 5. Test
Buat `tests/Feature/JurusanControllerTest.php` (jalankan di container: `docker compose exec -T app php artisan test`):
- admin: daftar, tambah, ubah, hapus jurusan yang tidak dipakai;
- validasi: kode kembar ditolak, kode huruf kecil disimpan kapital, `aktif` tidak dicentang tersimpan `false`;
- hapus jurusan yang dipakai kelas/siswa ditolak dan datanya utuh;
- ganti kode ikut mengubah `kelas.jurusan_kode` & `siswa.jurusan_kode` (setelah poin 4);
- guru & siswa mendapat 403 di semua route `admin.jurusan.*`;
- filter `q`/`bidang`/`status` dan urutan `?sort=kelas&dir=desc`.

Setelah route asli ada, `JurusanTampilanTest` **otomatis di-skip** — hapus saja berkasnya.

## Sinkronisasi jurusan siswa ("menyesuaikan kembali")

Sekarang `siswa.jurusan_kode` hanya diisi sekali (oleh migrasi & seeder). Siswa yang **ditambah, dipindah kelas,
atau diimpor** setelah ini akan berjurusan kosong / basi sampai salah satu cara di bawah dibuat.
Perlu diputuskan dulu bersama:

**A. Otomatis ikut kelas (disarankan bila jurusan siswa selalu = jurusan kelasnya)**
- `Siswa::booted()` → `static::saving(fn ($s) => $s->isDirty('kelas_id') && $s->jurusan_kode = $s->kelas?->jurusan_kode)`.
  Ini otomatis menutup form Tambah/Ubah Siswa (`Admin\SiswaController@store/update`) dan impor Excel
  (`App\Support\ImporPengguna::simpan`, memakai `updateOrCreate` sehingga event model jalan).
- `KelasController@update`: bila `jurusan_kode` kelas berubah → `Siswa::where('kelas_id', $kelas->id)->update(['jurusan_kode' => $kelas->jurusan_kode])`.
- Proses lain yang memindah siswa memakai query builder / stored procedure (tidak memicu event model) — cek ulang bila ada fitur naik kelas.

**B. Dipilih manual di form siswa (bila jurusan bisa beda dari kelas, mis. kelas X sebelum penjurusan)**
- `SiswaRequest`: tambah `'jurusan_kode' => ['nullable', 'exists:jurusan,kode']` (hanya jurusan `aktif()` untuk siswa baru).
- Form siswa (`resources/views/admin/siswa/form.blade.php`) belum punya field Jurusan — tambahkan `<select name="jurusan_kode">` seperti di `kelas/form.blade.php`.
- Kolom impor Excel siswa (`ImporPengguna::kolom('siswa')`) ditambah "Jurusan" (kode atau nama).

## Opsional / menyusul
- **Filter Data Siswa**: `x-filter-tingkat-jurusan` saat ini memfilter lewat `kelas.jurusan_kode`. Kalau jurusan siswa
  bisa beda dari kelas (opsi B), ganti ke `siswa.jurusan_kode`. Header kolom Jurusan di Data Siswa juga bisa diberi
  `<x-th-sort kolom="jurusan">` setelah peta urutannya ditambah di `Admin\SiswaController`.
- **API**: `SiswaResource` belum mengeluarkan `jurusan_kode`; tambahkan bila aplikasi mobile/Postman membutuhkannya,
  lalu perbarui `docs/API.md` dan koleksi Postman.
- **Jurusan nonaktif di form kelas**: `KelasController` hanya memuat `Jurusan::aktif()`. Kelas lama yang jurusannya
  dinonaktifkan akan kehilangan pilihan jurusannya saat diedit — sertakan jurusan kelas itu sendiri di daftar
  (`->orWhere('kode', $kelas->jurusan_kode)`).
- `JurusanPolicy` (admin saja) bila ingin konsisten dengan `MataPelajaranPolicy`; middleware `role:admin` sudah cukup untuk sekarang.
