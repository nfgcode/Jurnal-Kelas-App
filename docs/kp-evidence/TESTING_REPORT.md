# Hasil Pengujian Backend

Dokumen ini melaporkan hasil **eksekusi nyata** PHPUnit terhadap working tree pada
branch `main` — commit terakhir `4d1a6b5`, ditambah dua perbaikan berlingkup sempit
yang belum di-commit dan diuraikan pada bagian "Perbaikan Sejak Eksekusi Pertama".
Seluruh angka pada bagian "Ringkasan Test" diambil dari keluaran PHPUnit yang
tersimpan lengkap di `docs/kp-evidence/php-artisan-test-output.txt` (tanpa kode
warna ANSI).

## Lingkungan Pengujian

| Komponen | Hasil |
|---|---|
| PHP | 8.3.32 (di dalam container `app`; `composer.json` mensyaratkan `^8.3`) |
| Laravel | Laravel Framework 13.20.0 (`composer.json`: `laravel/framework ^13.8`) |
| PHPUnit | PHPUnit 12.5.31 (`composer.json`: `phpunit/phpunit ^12.5.12`) |
| Database Testing | SQLite in-memory (`phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) |
| Queue | `sync` (`phpunit.xml`: `QUEUE_CONNECTION=sync`) |
| Cache | `array` (`phpunit.xml`: `CACHE_STORE=array`); Session `array` |
| Command | `docker compose exec -T app php artisan test` (setara `composer test`, yang menjalankan `artisan config:clear` lalu `artisan test`) |

Catatan tambahan dari `phpunit.xml`: `APP_ENV=testing`, `APP_TIMEZONE=UTC`
(dipatok agar deterministik), `BCRYPT_ROUNDS=4`, `MAIL_MAILER=array`,
`BROADCAST_CONNECTION=null`. Test suite terdiri atas dua suite: `Unit`
(`tests/Unit`) dan `Feature` (`tests/Feature`).

## Ringkasan Test

| Indikator | Hasil |
|---|---:|
| Total Test | 252 |
| Passed | 252 |
| Failed | 0 |
| Skipped | 0 |
| Assertions | 903 |
| Duration | 708,01 detik (± 11 menit 48 detik) |

Baris ringkasan yang dicetak PHPUnit, apa adanya:

```
  Tests:    252 passed (903 assertions)
  Duration: 708.01s
```

PHPUnit tidak mencetak baris *failed*, *skipped*, maupun *incomplete* pada
eksekusi ini, sehingga ketiganya nol.

Eksekusi sebelumnya menghasilkan `1 failed, 244 passed (860 assertions)` dari 245
test. Dua perbaikan berlingkup sempit dilakukan setelahnya, ditambah tujuh
regression test; keduanya diuraikan pada bagian "Perbaikan Sejak Eksekusi Pertama"
di bawah. Tidak ada test yang dihapus atau dinonaktifkan.

Jumlah *method* bertanda `test_` pada berkas sumber ada **227**. Angka total di
atas lebih besar karena dua method memakai `#[DataProvider]`:
`CrudPagesTest::test_static_page_renders` (20 kasus halaman) dan
`DashboardPeriodeTest::test_dashboard_renders_for_each_preset` (7 kasus preset
periode). Angka yang dipakai pada laporan tetap angka yang dilaporkan PHPUnit,
bukan hasil hitung method.

## Perbaikan Sejak Eksekusi Pertama

Eksekusi pertama laporan ini menghasilkan **1 gagal dari 245 test**. Dua hal
diperbaiki setelahnya, keduanya berlingkup sempit dan masing-masing dijaga
regression test. Tidak ada test yang dihapus atau dinonaktifkan, tidak ada
asersi yang diturunkan, dan tidak ada aturan bisnis yang diubah.

### 1. Bug statistik kehadiran siswa (cacat produksi)

| Aspek | Isi |
|---|---|
| Lokasi | `app/Http/Controllers/Api/StatistikController.php`, `app/Support/Ringkasan.php` |
| Akar masalah | Pada jalur peran siswa, controller memakai `users.id` sebagai identifier siswa, lalu mengembalikannya pada field respons `siswa_nis` dan meneruskannya ke `Ringkasan::persentaseKehadiranSiswa()` yang mencocokkan kolom `presensi_harian.siswa_nis`. Kolom itu mengacu ke `siswa.nis`, bukan ke id akun |
| Akibat | Persentase kehadiran seorang siswa yang bertanya tentang dirinya sendiri hampir selalu `0` — dan `0` adalah jawaban yang masuk akal, sehingga kesalahan itu tidak terlihat |
| Perbaikan | Jalur siswa memakai `$user->nis`. Tipe parameter `Ringkasan::persentaseKehadiranSiswa()` diubah dari `int` menjadi `string`, karena `siswa.nis` adalah `VARCHAR(20)` dan boleh berawalan nol. `persentaseKehadiranKelas(int $kelasId)` tidak diubah — `kelas.id` memang integer |
| Kontrak API | **Tidak berubah.** Field respons tetap `siswa_nis`, `persentase_kehadiran_siswa`, `kelas_id`, `persentase_kehadiran_kelas`; jalur admin/guru (`?siswa_nis=`) tetap seperti semula |

Regression test: `tests/Feature/ApiStatistikKehadiranTest.php` (6 test, memanggil
`GET /api/statistik/kehadiran` sungguhan). Fixture-nya memakai NIS berawalan nol
(`0026001`, `0026002`) sehingga cast ke integer di titik mana pun akan menggagalkan
test. Test juga mengunci bahwa `users.id` dan NIS memang berbeda, agar test tidak
lulus karena kebetulan.

Bukti bahwa test ini benar-benar menangkap regresinya: dengan kode lama
dikembalikan sementara, `test_a_student_is_answered_from_their_nis_not_their_account_id`
gagal dengan `Failed asserting that 1 is identical to '0026001'`.

### 2. Test periode yang bergantung posisi kalender (cacat test, bukan cacat kode)

| Aspek | Isi |
|---|---|
| Test | `Tests\Feature\PeriodeFilterTest::test_a_narrower_preset_returns_no_more_rows_than_a_wider_one` |
| Pesan PHPUnit semula | `Failed asserting that 13 is equal to 11 or is less than 11.` |
| Asersi semula | `assertLessThanOrEqual($hitung('bulan_ini'), $hitung('minggu_ini'))` |
| Akar masalah | Asersi itu mengandaikan "Minggu Ini" selalu himpunan bagian "Bulan Ini". Pada Sabtu 5 September 2026 minggu berjalan dimulai Senin **31 Agustus**, sedangkan bulan berjalan dimulai **1 September** — jadi jendela minggu memuat satu hari milik bulan sebelumnya |
| Yang diperbaiki | **Test**-nya, bukan `App\Support\Periode`. Definisi presetnya sudah benar dan tidak disentuh |
| Perbaikan | Asersi diganti dengan pasangan jendela yang bersarang di **setiap** posisi kalender: `hari_ini` ⊆ `minggu_ini` ⊆ `30_hari`, dan `hari_ini` ⊆ `bulan_ini` ⊆ `tahun_ini`. Jumlah asersi naik dari 2 menjadi 4 |

Pasangan yang sengaja **tidak** dipakai, karena juga dapat gagal:
`minggu_ini` ⊆ `tahun_ini` (minggu berjalan bisa dimulai pada Desember tahun lalu)
dan `30_hari` ⊆ `tahun_ini` (jendela 30 hari bisa mundur melewati 1 Januari).

Regression test batas bulan: `test_the_current_week_may_reach_into_the_previous_month`
membekukan waktu pada **2026-09-05** dengan `Carbon::setTestNow()` (dibersihkan di
blok `finally`), lalu mengunci bahwa `minggu_ini` mulai `2026-08-31` dan `bulan_ini`
mulai `2026-09-01`, serta bahwa jurnal bertanggal 31 Agustus muncul pada daftar
"Minggu Ini" tetapi tidak pada "Bulan Ini". Test ini ada supaya kegagalan lama
tidak "diperbaiki" dengan memotong minggu di awal bulan.

## Pengujian Berdasarkan Modul

Kategori berikut hanya memuat modul yang benar-benar memiliki test. Kolom
"Skenario Aktual" ditulis apa adanya sesuai nama method pada berkas test,
sehingga dapat ditelusuri kembali.

### 1. Authentication — `tests/Feature/LoginTest.php` (11 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 1 | Authentication | `test_login_form_asks_for_nip_or_nis` | Formulir login meminta NIP/NIS, bukan e-mail | Sesuai | Lulus |
| 2 | Authentication | `test_guru_signs_in_with_nip` | Guru dapat masuk memakai NIP | Sesuai | Lulus |
| 3 | Authentication | `test_siswa_signs_in_with_nis` | Siswa dapat masuk memakai NIS | Sesuai | Lulus |
| 4 | Authentication | `test_admin_falls_back_to_email` | Admin dapat masuk memakai e-mail | Sesuai | Lulus |
| 5 | Authentication | `test_wrong_password_is_rejected` | Password salah ditolak | Sesuai | Lulus |
| 6 | Authentication | `test_unknown_identifier_is_rejected` | Identifier tak dikenal ditolak | Sesuai | Lulus |
| 7 | Authentication | `test_blank_identifier_never_matches_a_null_column` | Identifier kosong tidak boleh cocok dengan kolom `nip`/`nis` yang NULL | Sesuai | Lulus |
| 8 | Authentication | `test_a_siswa_nis_cannot_be_used_to_log_in_as_a_guru` | NIS tidak dapat dipakai masuk sebagai guru | Sesuai | Lulus |
| 9 | Authentication | `test_nip_must_be_unique_across_guru` | NIP unik antar guru | Sesuai | Lulus |
| 10 | Authentication | `test_username_is_also_a_login_identifier` | Username juga diterima sebagai identifier | Sesuai | Lulus |
| 11 | Authentication | `test_user_can_log_out` | Logout mengakhiri sesi | Sesuai | Lulus |

### 2. Authorization — `tests/Feature/AuthorizationTest.php` (20 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 12 | Authorization | `test_siswa_is_denied_master_data_screens` | Siswa ditolak (403) pada layar data master | Sesuai | Lulus |
| 13 | Authorization | `test_siswa_cannot_write_master_data` | Siswa tidak dapat menulis data master | Sesuai | Lulus |
| 14 | Authorization | `test_siswa_may_still_see_their_own_timetable_and_journals` | Siswa tetap melihat jadwal & jurnal kelasnya | Sesuai | Lulus |
| 15 | Authorization | `test_siswa_cannot_open_another_classs_journal_or_attendance` | Siswa tidak dapat membuka jurnal/presensi kelas lain | Sesuai | Lulus |
| 16 | Authorization | `test_a_student_with_no_class_sees_no_journals` | Siswa tanpa kelas tidak melihat jurnal apa pun (gagal tertutup) | Sesuai | Lulus |
| 17 | Authorization | `test_a_guru_cannot_edit_another_gurus_journal` | Guru tidak dapat menyunting jurnal guru lain | Sesuai | Lulus |
| 18 | Authorization | `test_a_guru_cannot_view_a_class_they_do_not_teach` | Guru tidak dapat melihat kelas yang tidak diampu | Sesuai | Lulus |
| 19 | Authorization | `test_a_guru_marks_only_the_meetings_they_teach` | Guru hanya menandai presensi pertemuan yang diampunya | Sesuai | Lulus |
| 20 | Authorization | `test_a_ketua_kelas_cannot_mark_attendance` | Ketua kelas tidak boleh menandai presensi | Sesuai | Lulus |
| 21 | Authorization | `test_a_guru_outside_the_class_cannot_even_read_its_attendance` | Guru luar kelas tidak dapat membaca presensinya | Sesuai | Lulus |
| 22 | Authorization | `test_a_regular_siswa_cannot_author_a_journal` | Siswa biasa (bukan ketua) tidak dapat menulis jurnal | Sesuai | Lulus |
| 23 | Authorization | `test_a_ketua_cannot_write_against_another_classs_schedule` | Ketua kelas tidak dapat menulis pada jadwal kelas lain | Sesuai | Lulus |
| 24 | Authorization | `test_the_ketua_flow_reaches_the_roster_after_saving` | Alur ketua kelas berlanjut ke daftar presensi setelah menyimpan | Sesuai | Lulus |
| 25 | Authorization | `test_a_guru_cannot_write_against_another_gurus_schedule` | Guru tidak dapat menulis pada jadwal guru lain | Sesuai | Lulus |
| 26 | Authorization | `test_only_admin_can_export_the_reports` | Ekspor laporan hanya untuk admin | Sesuai | Lulus |
| 27 | Authorization | `test_a_wali_kelas_reads_but_cannot_edit_another_gurus_journal_in_their_class` | Wali kelas membaca, tidak menyunting, jurnal guru lain di kelasnya | Sesuai | Lulus |
| 28 | Authorization | `test_a_guru_outside_the_class_cannot_read_its_journal` | Guru luar kelas tidak dapat membaca jurnalnya | Sesuai | Lulus |
| 29 | Authorization | `test_the_numeric_journal_id_no_longer_resolves_in_web_routes` | Id numerik jurnal tidak dapat dipakai pada route web | Sesuai | Lulus |
| 30 | Authorization | `test_only_the_class_or_admin_may_delete_a_journal` | Hanya kelas (ketua) atau admin yang boleh menghapus jurnal | Sesuai | Lulus |
| 31 | Authorization | `test_deleting_a_journal_removes_its_own_roster_only` | Menghapus jurnal hanya menghapus roster miliknya | Sesuai | Lulus |

### 3. Authorization (halaman per peran) — `tests/Feature/RolePagesTest.php` (9 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 32 | Authorization | `test_guru_screens_render` | Seluruh layar guru dapat dirender | Sesuai | Lulus |
| 33 | Authorization | `test_siswa_screens_render` | Seluruh layar siswa dapat dirender | Sesuai | Lulus |
| 34 | Authorization | `test_wali_kelas_screens_render` | Layar mode wali kelas dapat dirender | Sesuai | Lulus |
| 35 | Authorization | `test_the_wali_kelas_attendance_screen_offers_no_way_to_fill_it` | Layar presensi wali kelas tidak menyediakan aksi pengisian | Sesuai | Lulus |
| 36 | Authorization | `test_the_admin_import_screen_renders` | Layar impor admin dapat dirender | Sesuai | Lulus |
| 37 | Authorization | `test_siswa_cannot_access_admin_section` | Siswa ditolak pada seluruh bagian admin | Sesuai | Lulus |
| 38 | Authorization | `test_a_guru_cannot_author_a_journal` | Guru tidak memiliki izin membuat jurnal | Sesuai | Lulus |
| 39 | Authorization | `test_siswa_reports_the_guru_attendance` | Siswa melaporkan kehadiran guru pada jurnal | Sesuai | Lulus |
| 40 | Authorization | `test_both_authors_share_one_attendance_vocabulary` | Guru dan ketua kelas memakai kosakata kehadiran guru yang sama | Sesuai | Lulus |

### 4. REST API — `tests/Feature/ApiJurnalKontrakTest.php` (3 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 41 | REST API | `test_the_api_exposes_the_public_id_it_binds_on` | Respons `/api/jurnal` dan `/api/jurnal/{public_id}` memuat `public_id` | Sesuai | Lulus |
| 42 | REST API | `test_a_client_can_update_using_only_what_the_api_returned` | Klien dapat melakukan PUT hanya berbekal `public_id` dari respons GET | Sesuai | Lulus |
| 43 | REST API | `test_the_numeric_id_is_not_a_url_key` | `/api/jurnal/{id numerik}` menghasilkan 404 | Sesuai | Lulus |

### 5. Jurnal — `tests/Feature/JurnalGandaTest.php` (8 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 44 | Jurnal | `test_an_admin_cannot_file_two_journals_for_one_meeting` | Admin tidak dapat mengisi dua jurnal untuk satu pertemuan | Sesuai | Lulus |
| 45 | Jurnal | `test_a_guru_cannot_file_a_journal_at_all` | Guru sama sekali tidak dapat mengisi jurnal | Sesuai | Lulus |
| 46 | Jurnal | `test_a_ketua_cannot_file_two_journals_for_one_meeting` | Ketua kelas tidak dapat mengisi dua jurnal untuk satu pertemuan | Sesuai | Lulus |
| 47 | Jurnal | `test_an_admin_and_a_ketua_may_each_file_one_for_the_same_meeting` | Satu pertemuan boleh punya satu jurnal per sisi (admin/guru dan siswa) | Sesuai | Lulus |
| 48 | Jurnal | `test_the_database_itself_refuses_a_duplicate` | Indeks unik `jurnal_pertemuan_peran_unique` menolak duplikat di tingkat database | Sesuai | Lulus |
| 49 | Jurnal | `test_the_api_rejects_a_duplicate_with_422` | API menolak jurnal ganda dengan 422 | Sesuai | Lulus |
| 50 | Jurnal | `test_a_doubled_meeting_still_derives_one_row_per_student` | Pertemuan dengan dua jurnal tetap menghasilkan satu baris presensi harian per siswa | Sesuai | Lulus |
| 51 | Jurnal | `test_a_doubled_meeting_is_counted_once` | Pertemuan dengan dua jurnal dihitung sekali pada statistik | Sesuai | Lulus |

### 6. Jurnal Otomatis — `tests/Feature/JurnalOtomatisTest.php` (18 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 52 | Jurnal | `test_backfill_creates_a_system_journal` | Backfill membuat jurnal berperan `sistem` | Sesuai | Lulus |
| 53 | Jurnal | `test_backfill_never_invents_student_attendance` | Backfill tidak pernah mengarang presensi siswa | Sesuai | Lulus |
| 54 | Jurnal | `test_backfill_is_idempotent` | Backfill aman dijalankan berulang | Sesuai | Lulus |
| 55 | Jurnal | `test_backfill_leaves_today_and_the_future_alone` | Backfill tidak menyentuh hari ini dan masa depan | Sesuai | Lulus |
| 56 | Jurnal | `test_editing_a_system_journal_requires_the_honesty_checkbox` | Menyunting jurnal otomatis wajib mencentang pernyataan | Sesuai | Lulus |
| 57 | Jurnal | `test_editing_a_system_journal_with_attestation_adopts_it` | Dengan pernyataan, jurnal otomatis diadopsi penyuntingnya | Sesuai | Lulus |
| 58 | Jurnal | `test_filling_a_meeting_with_a_system_journal_redirects_to_editing_it` | Mengisi pertemuan yang sudah punya jurnal otomatis diarahkan ke penyuntingan | Sesuai | Lulus |
| 59 | Jurnal | `test_filing_a_journal_beside_a_system_one_is_refused` | Membuat jurnal baru di samping jurnal otomatis ditolak | Sesuai | Lulus |
| 60 | Jurnal | `test_a_system_journal_never_counts_as_filled_in` | Jurnal otomatis tidak dihitung sebagai jurnal terisi | Sesuai | Lulus |
| 61 | Jurnal | `test_the_recap_splits_written_automatic_and_missing` | Rekap memisahkan Diisi / Otomatis / Belum | Sesuai | Lulus |
| 62 | Jurnal | `test_a_backfilled_journal_is_not_reported_as_a_late_teacher` | Jurnal otomatis tidak dihitung sebagai guru terlambat | Sesuai | Lulus |
| 63 | REST API | `test_api_refuses_to_file_a_journal_beside_a_system_one` | API menolak jurnal baru di samping jurnal otomatis (422) | Sesuai | Lulus |
| 64 | REST API | `test_api_editing_a_system_journal_requires_the_attestation` | API mensyaratkan `pernyataan` saat menyunting jurnal otomatis | Sesuai | Lulus |
| 65 | REST API | `test_api_editing_a_system_journal_with_attestation_adopts_it` | Dengan `pernyataan`, API mengadopsi jurnal otomatis | Sesuai | Lulus |
| 66 | REST API | `test_api_update_cannot_move_a_journal_onto_another_class` | API menolak pemindahan jurnal ke kelas lain (403) | Sesuai | Lulus |
| 67 | REST API | `test_api_editing_an_ordinary_journal_needs_no_attestation` | Jurnal biasa tidak memerlukan `pernyataan` | Sesuai | Lulus |
| 68 | Jurnal | `test_editing_after_the_lesson_day_flags_and_badges_the_journal` | Penyuntingan setelah hari-H ditandai `diedit_setelah_hari` | Sesuai | Lulus |
| 69 | Jurnal | `test_admin_can_filter_journals_edited_after_the_day` | Admin dapat memfilter jurnal yang diedit setelah hari-H | Sesuai | Lulus |

### 7. Presensi — `tests/Feature/PresensiRosterTest.php` (12 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 70 | Presensi | `test_the_teaching_guru_may_mark_their_own_meeting` | Guru pengampu boleh menandai presensi pertemuannya | Sesuai | Lulus |
| 71 | Presensi | `test_the_ketua_kelas_may_read_but_not_mark_attendance` | Ketua kelas boleh membaca, tidak menandai | Sesuai | Lulus |
| 72 | Presensi | `test_the_wali_kelas_may_read_but_not_mark_another_teachers_meeting` | Wali kelas boleh membaca, tidak menandai pertemuan guru lain | Sesuai | Lulus |
| 73 | Presensi | `test_a_regular_student_may_not_mark_attendance` | Siswa biasa tidak boleh menandai presensi | Sesuai | Lulus |
| 74 | Presensi | `test_a_guru_may_not_mark_another_gurus_meeting` | Guru tidak boleh menandai pertemuan guru lain | Sesuai | Lulus |
| 75 | Presensi | `test_marking_stores_one_row_per_student_and_derives_the_day` | Penyimpanan menghasilkan satu baris `presensi` per siswa dan menurunkan `presensi_harian` | Sesuai | Lulus |
| 76 | Presensi | `test_marking_twice_replaces_rather_than_duplicates` | Penyimpanan ulang mengganti, bukan menduplikasi | Sesuai | Lulus |
| 77 | Presensi | `test_two_subjects_on_one_day_keep_separate_rosters` | Dua mata pelajaran pada satu hari memiliki roster terpisah | Sesuai | Lulus |
| 78 | Presensi | `test_a_student_from_another_class_is_rejected` | NIS siswa kelas lain ditolak validasi | Sesuai | Lulus |
| 79 | Presensi | `test_a_guru_can_open_a_roster_for_a_meeting_with_no_journal_yet` | Guru dapat membuka roster meski jurnal pertemuan belum ada | Sesuai | Lulus |
| 80 | Presensi | `test_a_guru_cannot_open_a_roster_for_another_gurus_slot` | Guru tidak dapat membuka roster slot guru lain | Sesuai | Lulus |
| 81 | Presensi | `test_presensi_log_page_is_admin_only` | Halaman Log Presensi hanya untuk admin | Sesuai | Lulus |

### 8. Stored Procedure / Transaction — `tests/Feature/ProsedurAkademikTest.php` (9 test)

Berjalan pada jalur **fallback SQLite** (`App\Support\PencatatanAkademik`), bukan
pada stored procedure MySQL. Lihat bagian "Keterbatasan Testing".

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 82 | Stored Procedure / Transaction | `test_a_subject_and_its_teachers_are_written_together` | Mata pelajaran dan guru pengampunya ditulis bersama | Sesuai | Lulus |
| 83 | Stored Procedure / Transaction | `test_an_unknown_teacher_rolls_the_whole_subject_back` | NIP tak dikenal membatalkan seluruh penyimpanan (rollback) | Sesuai | Lulus |
| 84 | Stored Procedure / Transaction | `test_a_duplicate_subject_code_is_refused` | Kode mapel kembar ditolak | Sesuai | Lulus |
| 85 | Stored Procedure / Transaction | `test_a_duplicate_rombel_is_refused_on_the_paralel_field` | Rombel kembar ditolak, pesan menempel pada field `paralel` | Sesuai | Lulus |
| 86 | Stored Procedure / Transaction | `test_a_class_cannot_name_a_school_year_that_does_not_exist` | Tahun ajaran tak terdaftar ditolak | Sesuai | Lulus |
| 87 | Stored Procedure / Transaction | `test_a_class_cannot_be_booked_twice_in_one_period` | Kelas tidak dapat dijadwalkan dua kali pada jam yang sama | Sesuai | Lulus |
| 88 | Stored Procedure / Transaction | `test_a_teacher_cannot_be_in_two_classes_at_once` | Guru tidak dapat mengajar dua kelas pada jam yang sama | Sesuai | Lulus |
| 89 | Stored Procedure / Transaction | `test_an_overlapping_period_range_is_refused` | Rentang JP yang tumpang-tindih ditolak (JP 3–4 vs JP 4–5) | Sesuai | Lulus |
| 90 | Stored Procedure / Transaction | `test_a_teacher_cannot_be_scheduled_for_a_subject_they_do_not_hold` | Guru yang tidak mengampu mapel ditolak | Sesuai | Lulus |

### 9. Jadwal — `tests/Feature/JadwalFormTest.php` (11 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 91 | Jadwal | `test_a_clashing_slot_is_refused_with_a_field_error` | Slot bentrok ditolak dengan pesan pada field | Sesuai | Lulus |
| 92 | Jadwal | `test_a_teacher_cannot_be_booked_into_two_classes_at_once` | Guru tidak dapat dipesan pada dua kelas bersamaan | Sesuai | Lulus |
| 93 | Jadwal | `test_an_overlapping_period_range_is_refused_too` | Rentang JP tumpang-tindih juga ditolak | Sesuai | Lulus |
| 94 | Jurnal | `test_the_dropdown_only_offers_that_days_lessons` | Dropdown hanya menawarkan pelajaran hari itu | Sesuai | Lulus |
| 95 | Jurnal | `test_changing_the_date_changes_the_list` | Mengubah tanggal mengubah daftar pertemuan | Sesuai | Lulus |
| 96 | Jurnal | `test_a_day_without_lessons_explains_itself_instead_of_offering_nothing` | Hari tanpa pelajaran menampilkan penjelasan | Sesuai | Lulus |
| 97 | Jurnal | `test_slots_already_written_up_are_marked` | Slot yang sudah berjurnal ditandai | Sesuai | Lulus |
| 98 | Authorization | `test_a_guru_is_not_offered_the_journal_form_at_all` | Guru tidak ditawari formulir jurnal | Sesuai | Lulus |
| 99 | Authorization | `test_a_ketua_is_only_offered_their_own_class` | Ketua kelas hanya ditawari kelasnya sendiri | Sesuai | Lulus |
| 100 | Jurnal | `test_a_malformed_date_is_refused` | Tanggal tidak sah ditolak | Sesuai | Lulus |
| 101 | Jurnal | `test_the_duplicate_message_names_the_meeting` | Pesan duplikat menyebutkan pertemuan yang dimaksud | Sesuai | Lulus |

### 10. Master Data — `tests/Feature/CrudPagesTest.php` (10 method, 29 kasus)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 102 | Master Data | `test_static_page_renders` (`#[DataProvider]`, 20 kasus URL) | 20 halaman admin/master dapat dirender dengan data seeder nyata | Sesuai | Lulus |
| 103 | Master Data | `test_record_pages_render` | Halaman detail record dapat dirender | Sesuai | Lulus |
| 104 | Master Data | `test_kelas_can_be_created_from_the_form_fields` | Kelas dapat dibuat dari field formulir aktual | Sesuai | Lulus |
| 105 | Database Integrity | `test_a_duplicate_rombel_is_refused` | Rombel kembar ditolak | Sesuai | Lulus |
| 106 | Master Data | `test_kelas_index_shows_the_class_name` | Daftar kelas menampilkan nama kelas | Sesuai | Lulus |
| 107 | Jadwal | `test_jadwal_can_be_created` | Jadwal dapat dibuat | Sesuai | Lulus |
| 108 | Jadwal | `test_jadwal_create_form_lists_the_dropdown_options` | Formulir jadwal memuat pilihan dropdown | Sesuai | Lulus |
| 109 | Presensi | `test_presensi_can_be_submitted_with_the_form_field_names` | Presensi dapat disimpan memakai nama field formulir aktual | Sesuai | Lulus |
| 110 | Presensi | `test_presensi_form_lists_the_students_and_a_submit_button` | Formulir presensi memuat daftar siswa dan tombol simpan | Sesuai | Lulus |
| 111 | Reporting | `test_the_guru_attendance_export_downloads_for_both_modes` | Ekspor presensi guru terunduh untuk kedua mode | Sesuai | Lulus |

### 11. Master Data (admin) — `tests/Feature/AdminSectionTest.php` (25 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 112 | Master Data | `test_landing_page_is_publicly_accessible` | Halaman depan dapat diakses publik | Sesuai | Lulus |
| 113 | Master Data | `test_admin_can_view_dashboard` | Admin dapat membuka dashboard | Sesuai | Lulus |
| 114 | Master Data | `test_generic_dashboard_redirects_admin_to_admin_dashboard` | Dashboard umum mengarahkan admin ke dashboard admin | Sesuai | Lulus |
| 115 | Authorization | `test_guru_cannot_access_admin_section` | Guru ditolak pada bagian admin | Sesuai | Lulus |
| 116 | Authorization | `test_guest_is_redirected_to_login` | Tamu diarahkan ke halaman login | Sesuai | Lulus |
| 117 | Stored Procedure / Transaction | `test_creating_a_guru_creates_the_person_and_the_account_together` | Membuat guru menulis baris `guru` dan `users` sekaligus | Sesuai | Lulus |
| 118 | Database Integrity | `test_a_duplicate_nip_is_refused_and_leaves_no_orphan_account` | NIP kembar ditolak tanpa menyisakan akun yatim | Sesuai | Lulus |
| 119 | Database Integrity | `test_a_duplicate_guru_name_is_refused_unless_explicitly_allowed` | Nama guru kembar ditolak kecuali dikonfirmasi | Sesuai | Lulus |
| 120 | Master Data | `test_admin_can_list_and_filter_guru` | Admin dapat menelusuri dan memfilter guru | Sesuai | Lulus |
| 121 | Master Data | `test_admin_can_update_a_guru_without_changing_the_password` | Guru dapat diubah tanpa mengganti password | Sesuai | Lulus |
| 122 | Master Data | `test_admin_can_assign_and_release_wali_from_the_guru_form` | Wali kelas dapat ditetapkan dan dilepas dari formulir guru | Sesuai | Lulus |
| 123 | Database Integrity | `test_a_guru_with_history_cannot_be_deleted` | Guru yang punya riwayat mengajar tidak dapat dihapus | Sesuai | Lulus |
| 124 | Stored Procedure / Transaction | `test_creating_a_siswa_creates_the_person_and_the_account_together` | Membuat siswa menulis baris `siswa` dan `users` sekaligus | Sesuai | Lulus |
| 125 | Database Integrity | `test_creating_a_siswa_requires_a_nis` | NIS wajib diisi | Sesuai | Lulus |
| 126 | Database Integrity | `test_promoting_a_ketua_kelas_demotes_the_previous_one` | Mengangkat ketua kelas baru menurunkan yang lama (satu ketua per kelas) | Sesuai | Lulus |
| 127 | Master Data | `test_admin_can_filter_siswa_by_class` | Siswa dapat difilter per kelas | Sesuai | Lulus |
| 128 | Master Data | `test_admin_can_list_and_filter_accounts` | Akun dapat ditelusuri dan difilter | Sesuai | Lulus |
| 129 | Authorization | `test_the_account_page_only_creates_admins` | Halaman akun hanya membuat akun admin | Sesuai | Lulus |
| 130 | Master Data | `test_admin_can_create_another_admin_account` | Admin dapat membuat akun admin lain | Sesuai | Lulus |
| 131 | Database Integrity | `test_an_accounts_role_cannot_be_changed_away_from_its_person` | Peran akun tidak dapat dilepas dari data orangnya | Sesuai | Lulus |
| 132 | Authorization | `test_admin_cannot_demote_their_own_account` | Admin tidak dapat menurunkan perannya sendiri | Sesuai | Lulus |
| 133 | Authorization | `test_admin_cannot_delete_their_own_account` | Admin tidak dapat menghapus akunnya sendiri | Sesuai | Lulus |
| 134 | Database Integrity | `test_deleting_an_account_leaves_the_person_on_the_register` | Menghapus akun tidak menghapus data orangnya | Sesuai | Lulus |
| 135 | Master Data | `test_admin_can_view_the_guru_detail_page` | Halaman detail guru dapat dibuka | Sesuai | Lulus |
| 136 | Reporting | `test_admin_can_open_reports` | Halaman laporan dapat dibuka admin | Sesuai | Lulus |

### 12. Master Data (impor) — `tests/Feature/ImporPenggunaTest.php` (10 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 137 | Master Data | `test_the_template_downloads_as_a_real_xlsx_with_the_expected_columns` | Templat impor terunduh sebagai XLSX dengan kolom yang benar | Sesuai | Lulus |
| 138 | Master Data | `test_the_template_is_offered_for_guru_too` | Templat juga tersedia untuk data guru | Sesuai | Lulus |
| 139 | Authorization | `test_the_import_screen_and_template_are_admin_only` | Layar dan templat impor hanya untuk admin | Sesuai | Lulus |
| 140 | Master Data | `test_a_valid_student_file_previews_then_creates_the_accounts` | Berkas siswa sah dipratinjau lalu membuat akun | Sesuai | Lulus |
| 141 | Database Integrity | `test_rows_with_problems_are_reported_and_never_written` | Baris bermasalah dilaporkan dan tidak pernah ditulis | Sesuai | Lulus |
| 142 | Master Data | `test_the_form_password_fills_in_for_rows_that_leave_it_blank` | Password formulir mengisi baris yang kosong | Sesuai | Lulus |
| 143 | Database Integrity | `test_existing_accounts_are_only_overwritten_when_asked` | Akun yang sudah ada hanya ditimpa bila diminta | Sesuai | Lulus |
| 144 | Database Integrity | `test_a_duplicate_nis_inside_the_same_file_is_caught` | NIS kembar di dalam satu berkas terdeteksi | Sesuai | Lulus |
| 145 | Database Integrity | `test_a_file_with_the_wrong_headers_is_rejected_outright` | Berkas dengan header salah ditolak | Sesuai | Lulus |
| 146 | Authorization | `test_the_confirm_step_refuses_a_crafted_filename` | Langkah konfirmasi menolak nama berkas rekayasa | Sesuai | Lulus |

### 13. Reporting — `tests/Feature/DashboardPeriodeTest.php` (16 method, 22 kasus)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 147 | Reporting | `test_dashboard_renders_for_each_preset` (`#[DataProvider]`, 7 preset) | Dashboard dirender untuk tujuh preset periode | Sesuai | Lulus |
| 148 | Reporting | `test_custom_range_is_accepted` | Rentang tanggal bebas diterima | Sesuai | Lulus |
| 149 | Reporting | `test_custom_range_with_end_before_start_is_rejected` | Rentang terbalik ditolak | Sesuai | Lulus |
| 150 | Reporting | `test_reports_also_take_the_period` | Laporan mengikuti filter periode | Sesuai | Lulus |
| 151 | Reporting | `test_detail_returns_journals_for_a_populated_date` | Drill-down menampilkan jurnal pada tanggal terisi | Sesuai | Lulus |
| 152 | Reporting | `test_detail_reports_empty_for_a_date_without_journals` | Drill-down kosong untuk tanggal tanpa jurnal | Sesuai | Lulus |
| 153 | Reporting | `test_detail_lists_a_teachers_journals` | Drill-down menampilkan jurnal seorang guru | Sesuai | Lulus |
| 154 | Reporting | `test_dashboard_marks_up_the_drill_targets` | Dashboard menandai target drill-down | Sesuai | Lulus |
| 155 | Reporting | `test_detail_lists_student_attendance_for_a_status` | Drill-down menampilkan siswa per status kehadiran | Sesuai | Lulus |
| 156 | Reporting | `test_detail_lists_one_class_on_one_date` | Drill-down menampilkan satu kelas pada satu tanggal | Sesuai | Lulus |
| 157 | Reporting | `test_detail_lists_filled_journals` | Drill-down menampilkan jurnal terisi | Sesuai | Lulus |
| 158 | Reporting | `test_detail_lists_unfilled_meetings_with_their_teacher` | Drill-down menampilkan pertemuan belum terisi beserta gurunya | Sesuai | Lulus |
| 159 | Reporting | `test_detail_lists_completeness_per_class` | Drill-down menampilkan kelengkapan per kelas | Sesuai | Lulus |
| 160 | Reporting | `test_detail_late_journals_render` | Drill-down jurnal terlambat dapat dirender | Sesuai | Lulus |
| 161 | Reporting | `test_reports_mark_up_their_card_drills_and_teacher_links` | Kartu laporan menandai tautan drill-down dan guru | Sesuai | Lulus |
| 162 | Authorization | `test_detail_is_forbidden_for_non_admin` | Drill-down ditolak untuk non-admin | Sesuai | Lulus |

### 14. Reporting (filter & penyajian) — `PeriodeFilterTest.php` (9 dari 10) + `PaginationTest.php` (5)

Test kesepuluh pada `PeriodeFilterTest` — `test_the_current_week_may_reach_into_the_previous_month`
— didaftar pada bagian 19 bersama regression test perbaikan lainnya.

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 163 | Reporting | `test_a_preset_narrows_the_journal_rows_to_its_window` | Preset periode mempersempit baris jurnal | Sesuai | Lulus |
| 164 | Reporting | `test_a_narrower_preset_returns_no_more_rows_than_a_wider_one` | Jendela yang bersarang di setiap posisi kalender tidak menghasilkan baris lebih banyak daripada jendela yang memuatnya (4 pasangan) | Sesuai | Lulus |
| 165 | Reporting | `test_a_custom_range_is_honoured_and_an_incomplete_one_refused` | Rentang bebas dihormati; yang tidak lengkap ditolak | Sesuai | Lulus |
| 166 | Reporting | `test_an_invented_preset_is_refused` | Preset karangan ditolak (whitelist) | Sesuai | Lulus |
| 167 | Authorization | `test_the_period_never_widens_a_role_scope` | Filter periode tidak pernah memperluas cakupan peran | Sesuai | Lulus |
| 168 | Presensi | `test_attendance_screens_follow_the_period_too` | Layar presensi mengikuti filter periode | Sesuai | Lulus |
| 169 | Reporting | `test_other_filters_survive_a_change_of_period` | Filter lain bertahan saat periode diganti | Sesuai | Lulus |
| 170 | Reporting | `test_a_filter_form_carries_the_sort_period_and_page_size` | Formulir filter membawa sort, periode, dan ukuran halaman | Sesuai | Lulus |
| 171 | Reporting | `test_sorting_still_applies_alongside_a_search` | Pengurutan tetap berlaku bersama pencarian | Sesuai | Lulus |
| 172 | Reporting | `test_the_offered_sizes_change_the_page_size` | Ukuran halaman yang ditawarkan berfungsi | Sesuai | Lulus |
| 173 | Reporting | `test_a_size_outside_the_whitelist_falls_back_to_the_default` | Ukuran di luar whitelist jatuh ke default | Sesuai | Lulus |
| 174 | Reporting | `test_the_default_applies_when_no_size_is_asked_for` | Default berlaku bila tidak diminta | Sesuai | Lulus |
| 175 | Authorization | `test_a_bigger_page_does_not_widen_what_a_guru_can_see` | Halaman lebih besar tidak memperluas data yang dilihat guru | Sesuai | Lulus |
| 176 | Reporting | `test_the_admin_tables_take_the_size_too` | Tabel admin juga menerima parameter ukuran | Sesuai | Lulus |

### 15. Akses QR Kelas — `tests/Feature/QrAksesTest.php` (13 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 177 | Database Integrity | `test_every_class_has_a_unique_qr_token` | Setiap kelas punya token QR unik | Sesuai | Lulus |
| 178 | Database Integrity | `test_a_new_class_gets_a_token_automatically` | Kelas baru otomatis memperoleh token | Sesuai | Lulus |
| 179 | Authorization | `test_a_guru_teaching_the_class_sees_the_confirmation` | Guru pengampu melihat halaman konfirmasi | Sesuai | Lulus |
| 180 | Authorization | `test_the_qr_page_is_guru_only` | Halaman QR hanya untuk guru (403 bagi peran lain) | Sesuai | Lulus |
| 181 | Authorization | `test_scanning_while_logged_out_redirects_to_login` | Memindai saat belum login diarahkan ke login | Sesuai | Lulus |
| 182 | Authorization | `test_an_unknown_token_is_not_found` | Token tak dikenal menghasilkan 404 | Sesuai | Lulus |
| 183 | Authorization | `test_a_guru_who_does_not_teach_the_class_gets_an_empty_state` | Guru yang tidak mengampu mendapat empty state | Sesuai | Lulus |
| 184 | Authorization | `test_the_confirmation_only_offers_the_scanning_gurus_own_subjects` | Konfirmasi hanya menawarkan mapel guru itu sendiri | Sesuai | Lulus |
| 185 | Authorization | `test_the_printable_qr_sheet_is_admin_only` | Lembar cetak QR hanya untuk admin | Sesuai | Lulus |
| 186 | Master Data | `test_the_sheet_covers_every_class_until_some_are_picked` | Lembar mencakup semua kelas sampai ada yang dipilih | Sesuai | Lulus |
| 187 | Master Data | `test_an_unknown_class_id_is_dropped_rather_than_failing` | Id kelas tak dikenal diabaikan, bukan error | Sesuai | Lulus |
| 188 | Authorization | `test_the_sheet_downloads_as_a_pdf_for_admin_only` | PDF hanya terunduh untuk admin | Sesuai | Lulus |
| 189 | Master Data | `test_the_pdf_shrinks_when_fewer_classes_are_picked` | PDF mengecil bila kelas yang dipilih lebih sedikit | Sesuai | Lulus |

### 16. Cadangan & Pemulihan Data — `tests/Feature/CadanganTest.php` (11 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 190 | Authorization | `test_the_backup_page_is_admin_only` | Halaman cadangan hanya untuk admin | Sesuai | Lulus |
| 191 | Database Integrity | `test_json_export_downloads_and_carries_every_table` | Ekspor JSON memuat seluruh tabel | Sesuai | Lulus |
| 192 | Database Integrity | `test_export_can_be_limited_to_a_chosen_subset_of_tables` | Ekspor dapat dibatasi pada subset tabel | Sesuai | Lulus |
| 193 | Authorization | `test_exports_are_admin_only` | Ekspor hanya untuk admin | Sesuai | Lulus |
| 194 | Database Integrity | `test_xlsx_export_is_a_real_workbook` | Ekspor XLSX adalah workbook sah | Sesuai | Lulus |
| 195 | Database Integrity | `test_merge_restore_puts_back_changed_rows_but_keeps_newer_ones` | Restore mode merge mengembalikan baris berubah, mempertahankan yang lebih baru | Sesuai | Lulus |
| 196 | Database Integrity | `test_replace_restore_wipes_rows_absent_from_the_snapshot` | Restore mode replace menghapus baris di luar snapshot | Sesuai | Lulus |
| 197 | Database Integrity | `test_restore_accepts_the_gzipped_download` | Restore menerima berkas `.json.gz` | Sesuai | Lulus |
| 198 | Database Integrity | `test_restore_requires_the_confirmation_box` | Restore mensyaratkan kotak konfirmasi | Sesuai | Lulus |
| 199 | Database Integrity | `test_a_file_that_is_not_a_backup_is_rejected_cleanly` | Berkas bukan cadangan ditolak dengan rapi | Sesuai | Lulus |
| 200 | Authorization | `test_restore_is_admin_only` | Restore hanya untuk admin | Sesuai | Lulus |

### 17. Penanganan Error & Log — `tests/Feature/ErrorHandlingTest.php` (13 test)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 201 | Authorization | `test_a_guru_sees_a_friendly_page_instead_of_a_stack_trace` | Guru melihat halaman ramah, bukan stack trace | Sesuai | Lulus |
| 202 | Authorization | `test_a_siswa_also_sees_the_friendly_page` | Siswa juga melihat halaman ramah | Sesuai | Lulus |
| 203 | Authorization | `test_an_admin_keeps_the_full_laravel_error_output` | Admin tetap menerima keluaran error Laravel penuh | Sesuai | Lulus |
| 204 | Penanganan Error | `test_a_missing_page_is_a_friendly_404_not_a_500` | Halaman tidak ada menghasilkan 404 ramah, bukan 500 | Sesuai | Lulus |
| 205 | Penanganan Error | `test_a_validation_failure_still_returns_to_the_form_with_errors` | Kegagalan validasi kembali ke formulir beserta pesan | Sesuai | Lulus |
| 206 | Penanganan Error | `test_a_guest_is_redirected_to_login_not_shown_an_error` | Tamu diarahkan ke login, bukan halaman error | Sesuai | Lulus |
| 207 | Penanganan Error | `test_a_report_stores_the_technical_details_from_the_session` | Laporan error menyimpan detail teknis dari session | Sesuai | Lulus |
| 208 | Penanganan Error | `test_a_second_report_within_the_window_is_throttled` | Laporan kedua dalam jendela waktu dibatasi | Sesuai | Lulus |
| 209 | Penanganan Error | `test_reporting_the_same_fault_again_increments_the_counter` | Laporan kesalahan yang sama menambah penghitung | Sesuai | Lulus |
| 210 | Authorization | `test_the_sistem_page_is_admin_only` | Halaman Sistem hanya untuk admin | Sesuai | Lulus |
| 211 | Penanganan Error | `test_an_admin_can_triage_a_report` | Admin dapat menindaklanjuti laporan | Sesuai | Lulus |
| 212 | Penanganan Error | `test_an_announcement_banner_shows_to_guru_and_siswa_but_not_admin` | Banner pengumuman tampil ke guru dan siswa, tidak ke admin | Sesuai | Lulus |
| 213 | Penanganan Error | `test_a_switched_off_announcement_does_not_show` | Pengumuman nonaktif tidak tampil | Sesuai | Lulus |

### 18. Lain-lain — `IkonTest.php` (5), `Feature/ExampleTest.php` (1), `Unit/ExampleTest.php` (1)

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 214 | Antarmuka | `test_every_icon_the_app_renders_exists` | Semua ikon yang dipakai aplikasi tersedia | Sesuai | Lulus |
| 215 | Antarmuka | `test_an_icon_renders_as_inline_svg_sized_to_the_text` | Ikon dirender sebagai SVG inline | Sesuai | Lulus |
| 216 | Antarmuka | `test_the_bi_prefix_is_accepted_because_stored_values_still_carry_it` | Prefiks `bi-` pada nilai tersimpan tetap diterima | Sesuai | Lulus |
| 217 | Antarmuka | `test_an_unknown_name_renders_nothing_instead_of_failing` | Nama ikon tak dikenal tidak menyebabkan error | Sesuai | Lulus |
| 218 | Antarmuka | `test_the_font_is_gone_from_the_build_input` | Font ikon sudah tidak ada pada input build | Sesuai | Lulus |
| 219 | Smoke | `Feature\ExampleTest::test_the_application_returns_a_successful_response` | Halaman utama merespons sukses | Sesuai | Lulus |
| 220 | Smoke | `Unit\ExampleTest::test_that_true_is_true` | Sanity check suite Unit | Sesuai | Lulus |

### 19. Regression test perbaikan audit — `ApiStatistikKehadiranTest.php` (6) + `PeriodeFilterTest.php` (1)

Tujuh test ini ditambahkan setelah eksekusi pertama, untuk menjaga dua perbaikan
yang dijelaskan pada bagian "Perbaikan Sejak Eksekusi Pertama".

| No | Modul | Skenario Aktual | Expected | Actual | Status |
|---|---|---|---|---|---|
| 221 | REST API | `test_a_student_is_answered_from_their_nis_not_their_account_id` | `GET /api/statistik/kehadiran` sebagai siswa menjawab `siswa_nis` = NIS siswa (bukan `users.id`) dan persentase 75% dari fixture 3 hadir / 4 hari | Sesuai | Lulus |
| 222 | Authorization | `test_a_student_cannot_ask_about_someone_else` | `?siswa_nis=` milik siswa lain diabaikan; respons tetap NIS akun yang login | Sesuai | Lulus |
| 223 | REST API | `test_an_admin_may_ask_by_nis` | Admin dapat menanyakan NIS mana pun; persentase 50% dari fixture 1 hadir / 2 hari | Sesuai | Lulus |
| 224 | REST API | `test_a_guru_may_ask_by_nis` | Guru dapat menanyakan NIS; hasilnya sama dengan yang dilihat siswa itu sendiri | Sesuai | Lulus |
| 225 | REST API | `test_an_unknown_nis_is_refused` | NIS tak terdaftar ditolak 422 pada field `siswa_nis` (`exists:siswa,nis`) | Sesuai | Lulus |
| 226 | REST API | `test_asking_for_nothing_answers_nothing_rather_than_zero` | Tanpa parameter, ketiga field bernilai `null` — bukan `0` | Sesuai | Lulus |
| 227 | Reporting | `test_the_current_week_may_reach_into_the_previous_month` | Waktu dibekukan 2026-09-05: `minggu_ini` mulai 2026-08-31, `bulan_ini` mulai 2026-09-01, dan jurnal 31 Agustus muncul di minggu tetapi tidak di bulan | Sesuai | Lulus |

Fixture statistik memakai NIS berawalan nol (`0026001`, `0026002`), sehingga cast
NIS ke integer di titik mana pun pada jalur ini akan menggagalkan test 221-224.

## Pengujian Keamanan Akses

Seluruh bukti di bawah berasal dari test yang benar-benar dieksekusi.

### Unauthenticated access

| Bukti | Berkas |
|---|---|
| Tamu yang membuka bagian admin diarahkan ke halaman login | `AdminSectionTest::test_guest_is_redirected_to_login` |
| Tamu yang memicu error diarahkan ke login, bukan diberi halaman error | `ErrorHandlingTest::test_a_guest_is_redirected_to_login_not_shown_an_error` |
| Memindai QR saat belum login diarahkan ke login lalu kembali (`redirect()->intended()`) | `QrAksesTest::test_scanning_while_logged_out_redirects_to_login` |

**Tidak ada** automated test yang memeriksa `401 Unauthenticated` pada endpoint
`/api/*`. Perilaku itu berasal dari middleware `auth:sanctum` dan konfigurasi
`shouldRenderJsonWhen('api/*')` di `bootstrap/app.php`, tetapi belum ditutup test.
Bukti manualnya disediakan pada folder `Evidence - Negative Cases` di collection
Postman.

### Forbidden role (403)

| Bukti | Berkas |
|---|---|
| Guru ditolak pada seluruh bagian admin | `AdminSectionTest::test_guru_cannot_access_admin_section` |
| Siswa ditolak pada layar dan penulisan data master | `AuthorizationTest::test_siswa_is_denied_master_data_screens`, `..._cannot_write_master_data` |
| Guru tidak dapat membuat jurnal sama sekali | `JurnalGandaTest::test_a_guru_cannot_file_a_journal_at_all`, `RolePagesTest::test_a_guru_cannot_author_a_journal` |
| Ketua kelas tidak dapat menandai presensi | `AuthorizationTest::test_a_ketua_kelas_cannot_mark_attendance` |
| Siswa biasa tidak dapat menandai presensi | `PresensiRosterTest::test_a_regular_student_may_not_mark_attendance` |
| Halaman QR hanya untuk guru; lembar cetak QR hanya untuk admin | `QrAksesTest::test_the_qr_page_is_guru_only`, `..._the_printable_qr_sheet_is_admin_only` |
| Ekspor laporan, halaman Sistem, Log Presensi, impor, dan cadangan hanya untuk admin | `AuthorizationTest::test_only_admin_can_export_the_reports`, `ErrorHandlingTest::test_the_sistem_page_is_admin_only`, `PresensiRosterTest::test_presensi_log_page_is_admin_only`, `ImporPenggunaTest::test_the_import_screen_and_template_are_admin_only`, `CadanganTest::test_the_backup_page_is_admin_only` |

Terdapat **51 asersi** `assertForbidden()`/`assertStatus(403)` yang tersebar pada
13 berkas test.

### Ownership / resource policy

| Bukti | Berkas |
|---|---|
| Guru hanya menandai presensi pertemuan yang benar-benar diampunya | `AuthorizationTest::test_a_guru_marks_only_the_meetings_they_teach`, `PresensiRosterTest::test_a_guru_may_not_mark_another_gurus_meeting` |
| Ketua kelas tidak dapat menulis pada jadwal kelas lain | `AuthorizationTest::test_a_ketua_cannot_write_against_another_classs_schedule` |
| Guru tidak dapat menulis pada jadwal guru lain | `AuthorizationTest::test_a_guru_cannot_write_against_another_gurus_schedule` |
| API menolak pemindahan jurnal ke kelas lain | `JurnalOtomatisTest::test_api_update_cannot_move_a_journal_onto_another_class` |
| Hanya kelas (ketua) atau admin yang boleh menghapus jurnal | `AuthorizationTest::test_only_the_class_or_admin_may_delete_a_journal` |
| Admin tidak dapat menurunkan atau menghapus akunnya sendiri | `AdminSectionTest::test_admin_cannot_demote_their_own_account`, `..._cannot_delete_their_own_account` |
| Siswa yang memanggil statistik kehadiran selalu dijawab tentang dirinya sendiri; `?siswa_nis=` milik orang lain diabaikan | `ApiStatistikKehadiranTest::test_a_student_cannot_ask_about_someone_else` |

### Akses ke data kelas / guru lain

| Bukti | Berkas |
|---|---|
| Siswa tidak dapat membuka jurnal atau presensi kelas lain | `AuthorizationTest::test_siswa_cannot_open_another_classs_journal_or_attendance` |
| Siswa tanpa kelas tidak melihat jurnal apa pun (gagal tertutup) | `AuthorizationTest::test_a_student_with_no_class_sees_no_journals` |
| Guru tidak dapat melihat kelas yang tidak diampunya | `AuthorizationTest::test_a_guru_cannot_view_a_class_they_do_not_teach` |
| Guru luar kelas tidak dapat membaca jurnal maupun presensi kelas itu | `AuthorizationTest::test_a_guru_outside_the_class_cannot_read_its_journal`, `..._cannot_even_read_its_attendance` |
| Wali kelas hanya membaca, tidak menyunting, jurnal guru lain di kelasnya | `AuthorizationTest::test_a_wali_kelas_reads_but_cannot_edit_another_gurus_journal_in_their_class` |
| Filter periode dan ukuran halaman tidak memperluas cakupan peran | `PeriodeFilterTest::test_the_period_never_widens_a_role_scope`, `PaginationTest::test_a_bigger_page_does_not_widen_what_a_guru_can_see` |
| Id numerik jurnal tidak dapat dipakai menebak data lain (route memakai ULID) | `AuthorizationTest::test_the_numeric_journal_id_no_longer_resolves_in_web_routes`, `ApiJurnalKontrakTest::test_the_numeric_id_is_not_a_url_key` |

## Pengujian Integritas Data

### Duplicate prevention

| Bukti | Berkas |
|---|---|
| Satu pertemuan hanya boleh punya satu jurnal per sisi | `JurnalGandaTest::test_an_admin_cannot_file_two_journals_for_one_meeting`, `..._a_ketua_cannot_file_two_journals_for_one_meeting`, `..._an_admin_and_a_ketua_may_each_file_one_for_the_same_meeting` |
| Penolakan duplikat juga terjadi di tingkat database | `JurnalGandaTest::test_the_database_itself_refuses_a_duplicate` |
| API menolak duplikat dengan 422 | `JurnalGandaTest::test_the_api_rejects_a_duplicate_with_422` |
| Rombel kembar ditolak | `CrudPagesTest::test_a_duplicate_rombel_is_refused`, `ProsedurAkademikTest::test_a_duplicate_rombel_is_refused_on_the_paralel_field` |
| Kode mata pelajaran kembar ditolak | `ProsedurAkademikTest::test_a_duplicate_subject_code_is_refused` |
| NIP kembar ditolak tanpa menyisakan akun yatim | `AdminSectionTest::test_a_duplicate_nip_is_refused_and_leaves_no_orphan_account` |
| Nama guru kembar ditolak kecuali dikonfirmasi | `AdminSectionTest::test_a_duplicate_guru_name_is_refused_unless_explicitly_allowed` |
| NIS kembar di dalam satu berkas impor terdeteksi | `ImporPenggunaTest::test_a_duplicate_nis_inside_the_same_file_is_caught` |
| Roster pertemuan bersifat idempoten (mengganti, bukan menduplikasi) | `PresensiRosterTest::test_marking_twice_replaces_rather_than_duplicates` |

### Unique key

Indeks unik yang benar-benar dibuat migrasi dan diverifikasi lewat test di atas:

| Nama constraint | Kolom | Berkas migrasi |
|---|---|---|
| `kelas_rombel_unique` | `tahun_ajaran_kode, tingkat, jurusan_kode, paralel` | `2024_01_01_000001_create_kelas_table.php` |
| `jurnal_pertemuan_peran_unique` | `jadwal_id, tanggal, diisi_oleh_peran` | `2026_07_30_000004_add_unique_jurnal_per_pertemuan.php` |
| `jurnal_public_id_unique` | `public_id` | `2026_07_30_000006_add_public_id_to_jurnal.php` |
| `presensi_jurnal_siswa_unique` | `jurnal_id, siswa_nis` | `2026_07_23_080002_add_unique_presensi.php` |
| `presensi_harian_unik` | `kelas_id, tanggal, siswa_nis` | `2026_08_19_000001_create_presensi_harian_table.php` |
| `kelas_qr_token_unique` | `qr_token` | `2026_07_30_000001_add_qr_token_to_kelas.php` |
| `jadwal_kelas_slot_unique` | `kelas_id, hari, jam_ke_mulai` | `2026_08_25_000004_add_jadwal_unique_keys.php` |
| `jadwal_guru_slot_unique` | `guru_nip, hari, jam_ke_mulai` | `2026_08_25_000004_add_jadwal_unique_keys.php` |
| `jadwal_ruangan_slot_unique` | `ruangan_kode, hari, jam_ke_mulai` | `2026_08_25_000004_add_jadwal_unique_keys.php` |
| unik kolom | `users.username`, `users.email`, `users.nip`, `users.nis`, `siswa.nisn`, `mata_pelajaran.kode` | migrasi masing-masing |

### Foreign key

| Bukti | Berkas |
|---|---|
| Guru yang sudah punya riwayat mengajar tidak dapat dihapus (jadwal & jurnalnya akan ikut terhapus) | `AdminSectionTest::test_a_guru_with_history_cannot_be_deleted` |
| Menghapus akun tidak menghapus data orangnya | `AdminSectionTest::test_deleting_an_account_leaves_the_person_on_the_register` |
| Menghapus jurnal hanya menghapus roster miliknya (cascade `presensi.jurnal_id`), lalu presensi harian dihitung ulang | `AuthorizationTest::test_deleting_a_journal_removes_its_own_roster_only` |
| Peran akun tidak dapat dilepas dari data orangnya | `AdminSectionTest::test_an_accounts_role_cannot_be_changed_away_from_its_person` |

FK yang relevan: `jadwal.guru_nip → guru.nip`, `jadwal.kelas_id → kelas.id`,
`jurnal.jadwal_id → jadwal.id` (cascade), `presensi.jurnal_id → jurnal.id`
(cascade), `presensi.siswa_nis → siswa.nis`, `presensi_harian.kelas_id → kelas.id`,
`presensi_harian.siswa_nis → siswa.nis`, `users.nip → guru.nip`,
`users.nis → siswa.nis`, `kelas.wali_kelas_nip → guru.nip`,
`kelas.ketua_nis → siswa.nis`, `guru_mata_pelajaran.guru_nip → guru.nip`.

### Atomic transaction dan rollback

| Bukti | Berkas |
|---|---|
| NIP kembar membatalkan pembuatan guru **dan** akunnya (tidak ada akun yatim) | `AdminSectionTest::test_a_duplicate_nip_is_refused_and_leaves_no_orphan_account` |
| Guru dan akunnya dibuat bersama | `AdminSectionTest::test_creating_a_guru_creates_the_person_and_the_account_together` |
| Siswa dan akunnya dibuat bersama | `AdminSectionTest::test_creating_a_siswa_creates_the_person_and_the_account_together` |
| Satu NIP tak dikenal membatalkan seluruh penyimpanan mata pelajaran beserta pivot pengampunya | `ProsedurAkademikTest::test_an_unknown_teacher_rolls_the_whole_subject_back` (memeriksa `mata_pelajaran` kosong **dan** `guru_mata_pelajaran` = 0 baris) |
| Baris impor bermasalah dilaporkan dan tidak pernah ditulis | `ImporPenggunaTest::test_rows_with_problems_are_reported_and_never_written` |

### Schedule collision

| Bukti | Berkas |
|---|---|
| Slot bentrok ditolak dengan pesan pada field | `JadwalFormTest::test_a_clashing_slot_is_refused_with_a_field_error` |
| Guru tidak dapat dipesan pada dua kelas bersamaan | `JadwalFormTest::test_a_teacher_cannot_be_booked_into_two_classes_at_once`, `ProsedurAkademikTest::test_a_teacher_cannot_be_in_two_classes_at_once` |
| Rentang JP tumpang-tindih ditolak (kasus yang tidak terlihat oleh indeks unik) | `JadwalFormTest::test_an_overlapping_period_range_is_refused_too`, `ProsedurAkademikTest::test_an_overlapping_period_range_is_refused` |
| Kelas tidak dapat dijadwalkan dua kali pada jam yang sama | `ProsedurAkademikTest::test_a_class_cannot_be_booked_twice_in_one_period` |
| Guru yang tidak mengampu mapel ditolak | `ProsedurAkademikTest::test_a_teacher_cannot_be_scheduled_for_a_subject_they_do_not_hold` |

### Roster / validasi siswa terhadap kelas

| Bukti | Berkas |
|---|---|
| NIS siswa dari kelas lain ditolak validasi roster | `PresensiRosterTest::test_a_student_from_another_class_is_rejected` |
| Dua mata pelajaran pada hari yang sama memiliki roster terpisah | `PresensiRosterTest::test_two_subjects_on_one_day_keep_separate_rosters` |
| Penyimpanan roster menghasilkan satu baris per siswa dan menurunkan baris harian | `PresensiRosterTest::test_marking_stores_one_row_per_student_and_derives_the_day` |
| Pertemuan berjurnal ganda tetap menghasilkan satu baris harian per siswa | `JurnalGandaTest::test_a_doubled_meeting_still_derives_one_row_per_student` |
| Satu ketua kelas per kelas (mengangkat yang baru menurunkan yang lama) | `AdminSectionTest::test_promoting_a_ketua_kelas_demotes_the_previous_one` |

## Keterbatasan Testing

### 1. Perilaku yang diuji lewat fallback SQLite

Test suite berjalan pada SQLite in-memory (`phpunit.xml`). Aplikasi bercabang per
driver melalui `App\Support\DbDriver::mysql()`. Yang **benar-benar dieksekusi**
oleh PHPUnit adalah cabang PHP-nya, bukan objek MySQL-nya:

| Perilaku | Yang dieksekusi test | Objek MySQL yang setara |
|---|---|---|
| Penambahan kelas, mata pelajaran, jadwal | Transaksi PHP di `App\Support\PencatatanAkademik` | `sp_tambah_kelas`, `sp_tambah_mata_pelajaran`, `sp_tambah_jadwal` |
| Penambahan guru dan siswa | Transaksi PHP di `App\Support\PendaftaranPengguna` | `sp_tambah_guru`, `sp_tambah_siswa` |
| Penyimpanan presensi harian | `DB::transaction` di `App\Support\SimpanPresensiHarian` | `sp_simpan_presensi_harian` |
| Persentase kehadiran | Agregat query builder di `App\Support\Ringkasan` | `fn_persentase_kehadiran_siswa`, `fn_persentase_kehadiran_kelas` |
| Rekap presensi per kelas | Agregat query builder di `Api\Admin\LaporanController::rekapKelas` | View `v_rekap_presensi_kelas` |
| Pencarian teks jurnal (`?q=`) | `LIKE` di `Jurnal::scopeCariTeks` | Indeks `FULLTEXT ft_jurnal (materi, kegiatan)` |

**Karena itu, laporan ini tidak mengklaim bahwa stored procedure MySQL telah
teruji oleh PHPUnit.** Yang teruji adalah bahwa *aturan bisnis* yang sama
menghasilkan penolakan yang sama pada kedua jalur, karena kedua jalur memakai satu
penerjemah kode kesalahan (`App\Support\ProsedurTersimpan`).

### 2. Objek MySQL-only yang tidak dieksekusi SQLite sama sekali

| Objek | Akibat pada test |
|---|---|
| `v_jurnal_lengkap`, `v_rekap_presensi_kelas` | Tidak pernah dibaca; cabang SQLite dipakai |
| `fn_persentase_kehadiran_siswa`, `fn_persentase_kehadiran_kelas` | Tidak pernah dipanggil |
| `fn_nama_duplikat_guru`, `fn_nama_duplikat_siswa` | Tidak pernah dipanggil |
| `sp_simpan_presensi` | Tidak dipanggil aplikasi sama sekali (peninggalan; lihat `REPORT_MISMATCHES.md`) |
| `sp_simpan_presensi_harian`, `sp_tambah_*` (5 prosedur) | Tidak pernah dipanggil pada test |
| `trg_jurnal_after_update`, `trg_jurnal_after_delete` | Tabel `jurnal_audit` **selalu kosong** selama test; tidak ada test yang memverifikasi isi audit |
| `trg_guru/siswa/users_before_insert/update` (6 trigger) | Normalisasi nama dan aturan "akun guru menunjuk baris guru" tidak diuji pada tingkat database |
| Indeks `FULLTEXT ft_jurnal` | Tidak pernah dipakai |

Bukti keberadaan objek-objek ini diverifikasi langsung pada MySQL pengembangan
(lihat `BAB5_BACKEND_EVIDENCE.md`), **bukan** lewat PHPUnit.

### 3. Endpoint / modul tanpa automated test

Penelusuran seluruh berkas test menunjukkan tiga pola URL API yang benar-benar
dipanggil test: `/api/jurnal`, `/api/jurnal/{public_id}`, dan
`/api/statistik/kehadiran` (varian `/audit` tidak dipanggil). Endpoint berikut
**tidak memiliki automated test**:

| Endpoint tanpa test | Catatan |
|---|---|
| `POST /api/login`, `POST /api/logout`, `GET /api/me` | Login diuji hanya lewat formulir web (`LoginTest`) |
| `GET /api/dashboard` | Logika serupa diuji lewat dashboard web |
| `GET/POST/PUT/DELETE /api/kelas`, `/api/mata-pelajaran`, `/api/jadwal` | Aturan bisnisnya diuji lewat service dan formulir web |
| `GET /api/jurnal/{jurnal}/audit` | Bergantung pada trigger MySQL yang tidak aktif di SQLite |
| `GET /api/presensi`, `GET /api/presensi/{kelas}`, `POST /api/jurnal/{jurnal}/presensi` | Alur presensi diuji lewat controller web yang memakai service yang sama |
| `GET/POST/PUT/DELETE /api/users` | Aturan "hanya admin" diuji lewat halaman web akun |
| `GET /api/laporan/jurnal`, `/api/laporan/presensi`, `/api/laporan/rekap-kelas` | Laporan diuji lewat halaman web |
| Status `401` pada seluruh `/api/*` | Tidak ada test sama sekali |

Modul lain yang tidak memiliki automated test khusus: pengiriman e-mail,
scheduler/queue di lingkungan nyata (queue dijalankan `sync` saat test), dan
kinerja/beban.

### 4. Cakupan kode

`phpunit.xml` mendaftarkan `<source><include><directory>app</directory>` tetapi
tidak mengaktifkan laporan coverage, dan eksekusi ini dijalankan tanpa
`--coverage`. Karena itu **angka persentase code coverage tidak tersedia** dan
tidak dicantumkan di laporan ini.
