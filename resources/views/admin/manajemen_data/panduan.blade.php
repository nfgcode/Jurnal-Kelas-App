@extends('layouts.app')

@section('title', 'Panduan Manajemen Data')

@section('content')
<div class="panduan-manajemen-data-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><x-ikon nama="question-circle" /> Panduan & Format Manajemen Data</h4>
            <div class="text-muted small">Aturan integritas impor multi-mode, keamanan kredensial, dan kebijakan pencadangan.</div>
        </div>
        <div>
            <a href="{{ route('admin.manajemen-data.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-ikon nama="arrow-left" /> Kembali ke Manajemen Data
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Panduan Tiga Mode Impor --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent fw-bold py-3">
                    <x-ikon nama="sliders" /> Tiga Mode Impor & Aturan Integritas
                </div>
                <div class="card-body p-4">
                    <h6 class="fw-bold text-success mb-1">1. Mode Tambah Baru (Default)</h6>
                    <p class="small text-muted mb-3">
                        Hanya mendaftarkan entitas baru yang belum ada di database. Jika nomor identitas (NIP/NIS) atau email sudah terdaftar, sistem akan menandainya sebagai <strong>Konflik</strong> dan tidak akan menimpa data yang sudah ada.
                    </p>

                    <h6 class="fw-bold text-primary mb-1">2. Mode Perbarui Data (Update Matched)</h6>
                    <p class="small text-muted mb-3">
                        Hanya memperbarui record yang cocok dengan identitas terpercaya (NIP guru atau NIS siswa). Baris dalam berkas yang identitasnya tidak ditemukan di database akan dilewati. Mode ini tidak membuat akun baru secara tidak sengaja.
                    </p>

                    <h6 class="fw-bold text-warning-emphasis mb-1">3. Mode Ganti Keseluruhan (Safe Synchronization)</h6>
                    <p class="small text-muted mb-0">
                        Menyelaraskan daftar pengguna aktif dengan berkas terbaru. Pengguna database yang tidak ada dalam berkas akan di-deaktivasi (status diubah menjadi <code>nonaktif</code>). <strong>Sistem tidak melakukan TRUNCATE, DELETE ALL, atau mematikan Foreign Key</strong> demi melindungi keutuhan riwayat kelas siswa, jurnal mengajar, dan rekapitulasi presensi. Mode ini hanya diizinkan untuk kategori Siswa dan Guru.
                    </p>
                </div>
            </div>

            {{-- Pemisahan Ekspor vs Cadangan Database --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent fw-bold py-3">
                    <x-ikon nama="shield-check" /> Perbedaan Ekspor Administratif vs Cadangan Database
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Aspek</th>
                                    <th>Ekspor Administratif (CSV / XLSX)</th>
                                    <th>Cadangan Database (.SQL.GZ / .SQL)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-bold">Tujuan Utama</td>
                                    <td>Analisis data, pembuatan laporan dinas, dan pengolahan spreadsheet staf.</td>
                                    <td>Pemulihan bencana (disaster recovery) dan migrasi server aplikasi.</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Kerahasiaan</td>
                                    <td>Hash password, token login, dan rahasia 2FA <strong>dihapus</strong> demi keamanan.</td>
                                    <td>Menyimpan seluruh snapshot tabel InnoDB secara utuh di penyimpanan privat.</td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Dapat Dipulihkan?</td>
                                    <td><span class="badge bg-danger">Bukan untuk Restore</span></td>
                                    <td><span class="badge bg-success">Arsip Pemulihan Resmi</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Kebijakan Retensi 12 Bulan --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent fw-bold py-3">
                    <x-ikon nama="clock-history" /> Kebijakan Retensi 12 Bulan & Proteksi Arsip
                </div>
                <div class="card-body p-4 small text-muted">
                    <p>
                        Scheduler otomatis akan membersihkan arsip cadangan yang telah berusia lebih dari <strong>12 bulan</strong> untuk mencegah kehabisan kapasitas disk server. Namun demikian, sistem memiliki dua lapisan proteksi mutlak:
                    </p>
                    <ul class="ps-3 mb-0">
                        <li><strong>Proteksi Pinned / Penting:</strong> Arsip yang diberi tanda pin (bintang/pin) oleh admin tidak akan pernah dihapus oleh pembersihan retensi otomatis.</li>
                        <li><strong>Proteksi Last-Valid:</strong> Cadangan valid terakhir yang dimiliki sistem dilindungi secara permanen dari penghapusan otomatis, memastikan sekolah selalu memiliki minimal satu titik pemulihan darurat.</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Bantuan Prompt AI dengan Peringatan Privasi --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 border-start border-danger border-4 mb-4">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-danger mb-2"><x-ikon nama="shield-exclamation" /> Peringatan Privasi Data Siswa & Staf</h6>
                    <p class="small text-muted mb-0">
                        <strong>Dilarang keras</strong> mengunggah file spreadsheet asli yang berisi NIK, nomor HP pribadi, alamat rumah, atau data sensitif lainnya ke layanan kecerdasan buatan (AI) publik atau pihak ketiga. Gunakan data dummy/samaran saat meminta bantuan AI untuk merapikan format kolom.
                    </p>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent fw-bold small text-uppercase">
                    <x-ikon nama="robot" /> Contoh Prompt AI Universal
                </div>
                <div class="card-body p-3">
                    <p class="small text-muted mb-2">Salin prompt di bawah untuk membantu merapikan data tanpa mengekspos data pribadi:</p>
                    <div class="p-2 bg-light rounded small font-monospace text-secondary" style="font-size: 0.78rem;">
                        "Tolong rapikan tabel daftar nama siswa berikut ke dalam format kolom: Nama Lengkap, Email, NIS, Kelas. Jangan sertakan nomor telepon atau data pribadi lainnya. Pastikan NIS berformat teks tanpa menghilangkan angka nol di depan."
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
