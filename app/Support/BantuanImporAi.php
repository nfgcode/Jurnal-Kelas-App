<?php

namespace App\Support;

use App\Models\Kelas;
use App\Models\MataPelajaran;

class BantuanImporAi
{
    public const PERINGATAN_PRIVASI = 'Jangan mengunggah data pribadi siswa ke layanan AI eksternal tanpa izin dan kebijakan pengelolaan data dari sekolah. Gunakan data anonim atau contoh apabila memungkinkan.';

    /**
     * Hasilkan teks prompt universal untuk merapikan data spreadsheet siswa.
     */
    public static function promptSiswa(?string $daftarKelasContoh = null): string
    {
        $kolom = ImporPengguna::kolom('siswa');
        $namaKolom = array_column($kolom, 'judul');
        $urutanKolomStr = implode("\t", $namaKolom);

        $daftarKelas = $daftarKelasContoh ?? Kelas::orderBy('nama_kelas')->pluck('nama_kelas')->take(10)->implode(', ');
        if (empty($daftarKelas)) {
            $daftarKelas = 'X RPL 1, X TKJ 1, XI RPL 1, XII TKJ 1 (sesuai data kelas terdaftar)';
        }

        $rincianKolom = [];
        foreach ($kolom as $kunci => $def) {
            $statusWajib = $def['wajib'] ? 'WAJIB' : 'OPSIONAL';
            $rincianKolom[] = "- Kolom {$def['judul']} [{$statusWajib}]: {$def['petunjuk']}";
        }
        $rincianKolomStr = implode("\n", $rincianKolom);

        $peringatan = self::PERINGATAN_PRIVASI;

        return <<<PROMPT
Anda adalah asisten pemformat dan validator spreadsheet data sekolah untuk aplikasi "Jurnal Kelas".
Tugas Anda adalah merapikan, memvalidasi, dan mengonversi data mentah siswa yang diberikan pengguna agar tepat sesuai dengan kontrak resmi template Excel Jurnal Kelas.

=== PERINGATAN PRIVASI & KEAMANAN DATA ===
{$peringatan}
Jangan pernah meminta atau mencatat password asli, token akses, atau data identitas rahasia.

=== KONTRAK STRUKTUR KOLOM IMPORT RESMI (SISWA) ===
Urutan kolom wajib tepat dan berurutan sebagai berikut:
{$urutanKolomStr}

Rincian aturan tiap kolom:
{$rincianKolomStr}

Aturan Penamaan Kelas:
- Kolom "Kelas" harus cocok persis dengan rombel kelas yang terdaftar di sekolah (contoh terdaftar saat ini: {$daftarKelas}).
- Jangan mengubah tingkat atau jurusan kelas tanpa instruksi tertulis.

=== ATURAN KETAT INTEGRITAS DATA (ANTI-HALUSINASI) ===
1. Gunakan HANYA data mentah yang diberikan oleh pengguna.
2. DILARANG KERAS mengarang/menciptakan nama siswa, NIS, kelas, jurusan, tanggal lahir, atau atribut lainnya.
3. Pertahankan angka nol di depan (leading zeros) pada NIS atau nomor identitas (misal "00123" tidak boleh menjadi "123").
4. Jangan pernah menggabungkan dua siswa yang berbeda hanya karena memiliki kemiripan nama.
5. Periksa duplikasi NIS atau email dalam data mentah yang diberikan.
6. Tandai data yang hilang, ambigu, atau tidak valid dengan status "PERLU KONFIRMASI" HANYA pada "Output B (Laporan Validasi)".
7. DILARANG KERAS memasukkan teks "PERLU KONFIRMASI" ke dalam tabel "Output A (Data Siap Impor)".
8. Baris yang memiliki masalah validasi atau belum lengkap HARUS DITAHAN di Output B dan TIDAK BOLEH dimasukkan ke Output A sampai dikonfirmasi pengguna.
9. Impor spreadsheet siswa ini BUKAN proses kelulusan. Kelulusan siswa Kelas XII dikelola terpisah pada modul Kenaikan & Kelulusan.

=== FORMAT KELUARAN YANG WAJIB DIHASILKAN ===

Hasilkan TEPAT DUA bagian keluaran:

### Output A: Data Siap Impor (Format TSV / Tabel)
- Baris pertama HARUS berupa header persis:
{$urutanKolomStr}
- Format data menggunakan TSV (Tab-Separated Values) agar langsung bisa disalin (copy-paste) ke Excel atau Google Sheets.
- Satu baris per siswa.
- HANYA sertakan baris siswa yang 100% valid dan memenuhi aturan di atas.
- Jangan menambahkan komentar atau teks lain di dalam tabel data ini.

### Output B: Laporan Validasi
Sajikan daftar rekaman yang memerlukan perhatian dalam format poin:
- Baris Asal / Referensi:
- Kolom Terdampak:
- Masalah yang Ditemukan:
- Koreksi / Klarifikasi yang Diperlukan:
- Status Kesiapan: (Siap Impor / Perlu Konfirmasi Pengguna)

Jika data mentah yang diberikan pengguna belum cukup, ajukan pertanyaan klarifikasi secara sopan dan spesifik tanpa menebak-nebak nilainya. Pratinjau aplikasi Jurnal Kelas tetap menjadi penentu validasi akhir.
PROMPT;
    }

    /**
     * Hasilkan teks prompt universal untuk merapikan data spreadsheet guru.
     */
    public static function promptGuru(?string $daftarMapelContoh = null, ?string $daftarKelasContoh = null): string
    {
        $kolom = ImporPengguna::kolom('guru');
        $namaKolom = array_column($kolom, 'judul');
        $urutanKolomStr = implode("\t", $namaKolom);

        $daftarKelas = $daftarKelasContoh ?? Kelas::orderBy('nama_kelas')->pluck('nama_kelas')->take(10)->implode(', ');
        if (empty($daftarKelas)) {
            $daftarKelas = 'X RPL 1, XI TKJ 1 (sesuai data kelas terdaftar)';
        }

        $daftarMapel = $daftarMapelContoh ?? MataPelajaran::orderBy('nama')->pluck('nama')->take(10)->implode(', ');
        if (empty($daftarMapel)) {
            $daftarMapel = 'Matematika, Bahasa Indonesia, Kejuruan (sesuai mapel terdaftar)';
        }

        $rincianKolom = [];
        foreach ($kolom as $kunci => $def) {
            $statusWajib = $def['wajib'] ? 'WAJIB' : 'OPSIONAL';
            $rincianKolom[] = "- Kolom {$def['judul']} [{$statusWajib}]: {$def['petunjuk']}";
        }
        $rincianKolomStr = implode("\n", $rincianKolom);

        $peringatan = self::PERINGATAN_PRIVASI;

        return <<<PROMPT
Anda adalah asisten pemformat dan validator spreadsheet data sekolah untuk aplikasi "Jurnal Kelas".
Tugas Anda adalah merapikan, memvalidasi, dan mengonversi data mentah guru/pendidik yang diberikan pengguna agar tepat sesuai dengan kontrak resmi template Excel Jurnal Kelas.

=== PERINGATAN PRIVASI & KEAMANAN DATA ===
{$peringatan}
Jangan pernah meminta atau mencatat password asli, token akses, atau data identitas rahasia.

=== KONTRAK STRUKTUR KOLOM IMPORT RESMI (GURU) ===
Urutan kolom wajib tepat dan berurutan sebagai berikut:
{$urutanKolomStr}

Rincian aturan tiap kolom:
{$rincianKolomStr}

Aturan Penugasan Mapel & Kelas:
- Kolom "Mata Pelajaran": gunakan nama atau kode mapel yang terdaftar di sekolah (contoh terdaftar: {$daftarMapel}). Pisahkan dengan koma bila lebih dari satu.
- Kolom "Wali Kelas": gunakan nama kelas perwalian yang terdaftar (contoh terdaftar: {$daftarKelas}). Kosongkan bila bukan wali kelas.
- Kolom "NIP": hanya boleh berisi angka dan harus unik.
- Kolom "Jenis Kelamin": gunakan 'L' untuk laki-laki dan 'P' untuk perempuan.

=== ATURAN KETAT INTEGRITAS DATA (ANTI-HALUSINASI) ===
1. Gunakan HANYA data mentah yang diberikan oleh pengguna.
2. DILARANG KERAS mengarang/menciptakan nama guru, NIP, mapel, nomor HP, atau alamat.
3. Pertahankan format NIP angka murni tanpa spasi atau tanda baca tambahan.
4. Periksa duplikasi NIP atau email dalam data mentah yang diberikan.
5. Tandai data yang hilang, ambigu, atau tidak valid dengan status "PERLU KONFIRMASI" HANYA pada "Output B (Laporan Validasi)".
6. DILARANG KERAS memasukkan teks "PERLU KONFIRMASI" ke dalam tabel "Output A (Data Siap Impor)".
7. Baris yang memiliki masalah validasi HARUS DITAHAN di Output B dan TIDAK BOLEH dimasukkan ke Output A sampai dikonfirmasi pengguna.

=== FORMAT KELUARAN YANG WAJIB DIHASILKAN ===

Hasilkan TEPAT DUA bagian keluaran:

### Output A: Data Siap Impor (Format TSV / Tabel)
- Baris pertama HARUS berupa header persis:
{$urutanKolomStr}
- Format data menggunakan TSV (Tab-Separated Values) agar langsung bisa disalin (copy-paste) ke Excel atau Google Sheets.
- Satu baris per guru.
- HANYA sertakan baris guru yang 100% valid dan memenuhi aturan di atas.
- Jangan menambahkan komentar atau teks lain di dalam tabel data ini.

### Output B: Laporan Validasi
Sajikan daftar rekaman yang memerlukan perhatian dalam format poin:
- Baris Asal / Referensi:
- Kolom Terdampak:
- Masalah yang Ditemukan:
- Koreksi / Klarifikasi yang Diperlukan:
- Status Kesiapan: (Siap Impor / Perlu Konfirmasi Pengguna)

Jika data mentah yang diberikan pengguna belum cukup, ajukan pertanyaan klarifikasi secara sopan dan spesifik tanpa menebak-nebak nilainya. Pratinjau aplikasi Jurnal Kelas tetap menjadi penentu validasi akhir.
PROMPT;
    }
}
