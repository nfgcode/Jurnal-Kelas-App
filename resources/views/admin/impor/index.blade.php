@extends('layouts.app')

@section('title', 'Impor Data')

@push('styles')
<style>
    .panduan-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 12px;
    }
    .panduan-item {
        background: var(--n-100);
        border: 1px solid var(--n-200);
        border-radius: var(--radius-card);
        padding: 12px 14px;
    }
    .panduan-item__header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }
    .panduan-item__no {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: var(--p-500, #2563eb);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        flex-shrink: 0;
    }
    .panduan-item__judul {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--n-900);
    }
    .panduan-item__isi {
        font-size: 11px;
        color: var(--n-600);
        line-height: 1.5;
        margin: 0;
    }
    .prompt-box {
        background: var(--n-100);
        border: 1px solid var(--n-300);
        border-radius: var(--radius-card);
        padding: 14px;
    }
    .prompt-textarea {
        width: 100%;
        height: 200px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 11px;
        line-height: 1.5;
        background: #fff;
        border: 1px solid var(--n-300);
        border-radius: 6px;
        padding: 10px;
        color: var(--n-900);
        resize: vertical;
    }
    .banner-privasi {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        color: #92400e;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 11.5px;
        line-height: 1.5;
    }
</style>
@endpush

@section('content')
    {{-- A plain & here, not &amp;: the component echoes the attribute through
         {{ }}, which escapes it once on the way out. --}}
    <x-page-head
        title="Impor Data Siswa & Guru"
        sub="Unduh template Excel, siapkan data dengan bantuan AI, lalu unggah untuk membuat banyak akun sekaligus">
        <a class="btn-hifi btn-hifi--ghost" href="{{ route('admin.akun.index') }}">← Daftar Akun</a>
    </x-page-head>

    @if ($jumlahKelas === 0)
        <p class="banner banner--bahaya mb-2">
            Belum ada kelas terdaftar. Impor siswa membutuhkan nama kelas yang sudah ada —
            <a class="auth__link" href="{{ route('kelas.create') }}">tambahkan kelas dulu</a>.
        </p>
    @endif

    {{-- 1. Panduan Persiapan Data Excel --}}
    <x-card title="Panduan Persiapan Data Excel" class="mb-4">
        <x-slot:actions>
            <span class="card-hifi__meta">Alur 6 Langkah &amp; Tata Kelola Tahunan</span>
        </x-slot:actions>

        <div class="panduan-grid mb-3">
            <div class="panduan-item">
                <div class="panduan-item__header">
                    <span class="panduan-item__no">1</span>
                    <span class="panduan-item__judul">Unduh Template Resmi</span>
                </div>
                <p class="panduan-item__isi">
                    Gunakan berkas template Excel resmi di bawah. Jangan mengubah nama atau urutan kolom header baris pertama.
                </p>
            </div>

            <div class="panduan-item">
                <div class="panduan-item__header">
                    <span class="panduan-item__no">2</span>
                    <span class="panduan-item__judul">Susun Baris Data</span>
                </div>
                <p class="panduan-item__isi">
                    Hapus 2 baris contoh sebelum mengunggah. Setiap baris mewakili 1 orang akun pengguna.
                </p>
            </div>

            <div class="panduan-item">
                <div class="panduan-item__header">
                    <span class="panduan-item__no">3</span>
                    <span class="panduan-item__judul">Verifikasi Identitas &amp; Rombel</span>
                </div>
                <p class="panduan-item__isi">
                    Pastikan NIS/NIP bersifat unik, pertahankan angka 0 di depan, serta nama kelas persis seperti yang terdaftar.
                </p>
            </div>

            <div class="panduan-item">
                <div class="panduan-item__header">
                    <span class="panduan-item__no">4</span>
                    <span class="panduan-item__judul">Unggah Berkas</span>
                </div>
                <p class="panduan-item__isi">
                    Pilih berkas (.xlsx/.csv) dan tentukan password bawaan bila kolom password dikosongkan.
                </p>
            </div>

            <div class="panduan-item">
                <div class="panduan-item__header">
                    <span class="panduan-item__no">5</span>
                    <span class="panduan-item__judul">Tinjau Pratinjau Sistem</span>
                </div>
                <p class="panduan-item__isi">
                    Sistem akan memvalidasi tiap baris data tanpa menyentuh database terlebih dahulu.
                </p>
            </div>

            <div class="panduan-item">
                <div class="panduan-item__header">
                    <span class="panduan-item__no">6</span>
                    <span class="panduan-item__judul">Konfirmasi Simpan</span>
                </div>
                <p class="panduan-item__isi">
                    Penyimpanan dilakukan secara aman dan transaksional setelah semua kendala validasi diselesaikan.
                </p>
            </div>
        </div>

        <div class="border-top pt-3 mt-2">
            <h6 class="fw-bold mb-2 text-dark" style="font-size: 12px">Catatan Siklus Administrasi Data Tahunan:</h6>
            <ul class="small text-muted mb-2 ps-3" style="font-size: 11px; line-height: 1.6">
                <li><strong>Calon Siswa Baru (Kelas X):</strong> Siapkan berkas dari hasil PPDB dan pastikan rombel kelas X tujuan telah dibuat di Data Kelas sebelum impor.</li>
                <li><strong>Pemeriksaan Siswa Berjalan (Kelas XI &amp; XII):</strong> Tinjau status keaktifan dan kelengkapan penugasan rombel kelas berjalan.</li>
                <li><strong>Identifikasi Calon Lulusan (Kelas XII):</strong> Pastikan identitas siswa kelas XII lengkap dan mutakhir menjelang transisi akhir tahun ajaran.</li>
                <li class="text-danger fw-semibold">Penting: Mengunggah data siswa Kelas XII tidak memproses kelulusan secara otomatis. Kelulusan siswa dan pencatatan alumni dikelola terpisah melalui modul Kenaikan &amp; Kelulusan.</li>
            </ul>
        </div>
    </x-card>

    {{-- 2. Bantuan Format Excel dengan AI (Universal Model-Agnostic Assistant) --}}
    <x-card title="Bantuan Format Excel dengan AI" class="mb-4">
        <x-slot:actions>
            <x-chip tone="neutral" label="Model-Agnostik" />
        </x-slot:actions>

        <div class="banner-privasi mb-3">
            <strong>Peringatan Perlindungan Data Siswa:</strong> {{ $peringatanPrivasi }}
        </div>

        <p class="field__hint mb-3">
            Gunakan prompt universal di bawah ini untuk merapikan data spreadsheet yang berantakan menggunakan AI pilihan Anda
            (ChatGPT, Claude, Gemini, DeepSeek, dsb.). AI akan memvalidasi kelengkapan data dan menghasilkan tabel TSV yang siap disalin (copy-paste) ke Excel.
        </p>

        <div class="accordion" id="accordionAi">
            {{-- Bagian Siswa --}}
            <div class="prompt-box mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div>
                        <strong class="text-dark" style="font-size: 12.5px">Prompt AI untuk Data Siswa</strong>
                        <span class="text-muted small d-block">Mencakup 7 kolom resmi template siswa dan aturan integritas identitas</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="statusSalinSiswa" style="display: none;"></span>
                        <button type="button" class="btn-hifi btn-hifi--sm" id="btnSalinSiswa"
                                onclick="salinPrompt('promptSiswaText', 'btnSalinSiswa', 'statusSalinSiswa')">
                            <x-ikon nama="clipboard" /> Salin Prompt AI Siswa
                        </button>
                    </div>
                </div>

                <details class="mt-2">
                    <summary class="small text-primary cursor-pointer fw-semibold">Tampilkan / Baca Teks Prompt Lengkap Siswa</summary>
                    <textarea id="promptSiswaText" class="prompt-textarea mt-2" readonly>{{ $promptSiswa }}</textarea>
                </details>
            </div>

            {{-- Bagian Guru --}}
            <div class="prompt-box">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div>
                        <strong class="text-dark" style="font-size: 12.5px">Prompt AI untuk Data Guru</strong>
                        <span class="text-muted small d-block">Mencakup 10 kolom resmi template guru, mapel terdaftar, dan format NIP</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="statusSalinGuru" style="display: none;"></span>
                        <button type="button" class="btn-hifi btn-hifi--sm" id="btnSalinGuru"
                                onclick="salinPrompt('promptGuruText', 'btnSalinGuru', 'statusSalinGuru')">
                            <x-ikon nama="clipboard" /> Salin Prompt AI Guru
                        </button>
                    </div>
                </div>

                <details class="mt-2">
                    <summary class="small text-primary cursor-pointer fw-semibold">Tampilkan / Baca Teks Prompt Lengkap Guru</summary>
                    <textarea id="promptGuruText" class="prompt-textarea mt-2" readonly>{{ $promptGuru }}</textarea>
                </details>
            </div>
        </div>
    </x-card>

    <div class="grid-row grid-row--2">
        @foreach (['siswa' => $kolomSiswa, 'guru' => $kolomGuru] as $jenis => $kolom)
            <x-card :title="'Impor ' . ucfirst($jenis)" :meta="count($kolom) . ' kolom'">
                <p class="field__hint">
                    Template berisi baris judul, dua baris contoh, dan lembar <strong>Petunjuk</strong>
                    berisi penjelasan tiap kolom serta daftar kelas yang terdaftar.
                    Hapus baris contoh sebelum mengunggah.
                </p>

                <a class="btn-hifi btn-hifi--ghost mt-2"
                   href="{{ route('admin.impor.template', ['jenis' => $jenis]) }}">
                    <x-ikon nama="download" /> Unduh Template {{ ucfirst($jenis) }}
                </a>

                <div class="tbl-wrap mt-3">
                    <table class="tbl">
                        <thead>
                            <tr><th>Kolom</th><th>Wajib</th><th>Penjelasan</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($kolom as $def)
                                <tr>
                                    <td class="is-strong is-nowrap">{{ $def['judul'] }}</td>
                                    <td>
                                        <x-chip :tone="$def['wajib'] ? 'red' : 'neutral'"
                                                :label="$def['wajib'] ? 'Wajib' : 'Opsional'" />
                                    </td>
                                    <td class="is-muted">{{ $def['petunjuk'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach
    </div>

    <x-card title="Unggah Berkas Terisi" meta="Ditinjau dulu sebelum disimpan" id="unggah">
        <form method="POST" action="{{ route('admin.impor.pratinjau') }}" enctype="multipart/form-data">
            @csrf

            <div class="grid-row grid-row--2">
                <x-field label="Jenis Data" name="jenis" required>
                    <select class="select-hifi" name="jenis" id="jenis" required>
                        {{-- ?jenis=guru from Data Guru's "Tambah Beberapa Guru". --}}
                        <option value="siswa" @selected(old('jenis', request('jenis')) === 'siswa')>Siswa</option>
                        <option value="guru" @selected(old('jenis', request('jenis')) === 'guru')>Guru</option>
                    </select>
                </x-field>

                <x-field label="Berkas Excel (.xlsx atau .csv)" name="berkas" required>
                    <input class="input-hifi" type="file" name="berkas" id="berkas"
                           accept=".xlsx,.csv" required>
                </x-field>
            </div>

            <div class="grid-row grid-row--2">
                <x-field label="Password Bawaan" name="password_bawaan"
                         hint="Dipakai untuk baris yang kolom Password-nya kosong. Minimal 8 karakter. Mintalah pengguna menggantinya setelah login pertama.">
                    <input class="input-hifi" type="text" name="password_bawaan" id="password_bawaan"
                           value="{{ old('password_bawaan') }}" minlength="8" autocomplete="off">
                </x-field>

                <x-field label="Data yang Sudah Ada" name="perbarui">
                    <label class="d-flex align-items-center gap-2" style="font-size: 12.5px">
                        <input type="checkbox" name="perbarui" value="1" @checked(old('perbarui'))>
                        Perbarui data yang sudah ada (dicocokkan berdasarkan email)
                    </label>
                    <span class="field__hint">
                        Tanpa ini, baris dengan email yang sudah terdaftar akan ditolak, bukan ditimpa.
                    </span>
                </x-field>
            </div>

            <button class="btn-hifi mt-2" type="submit">
                <x-ikon nama="file-earmark-arrow-up" /> Periksa Berkas
            </button>
        </form>
    </x-card>
@endsection

@push('scripts')
<script>
function salinPrompt(textareaId, btnId, statusId) {
    const textarea = document.getElementById(textareaId);
    const btn = document.getElementById(btnId);
    const status = document.getElementById(statusId);
    if (!textarea) return;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textarea.value).then(() => {
            tampilkanSukses(btn, status);
        }).catch(() => {
            salinFallback(textarea, btn, status);
        });
    } else {
        salinFallback(textarea, btn, status);
    }
}

function salinFallback(textarea, btn, status) {
    try {
        textarea.select();
        textarea.setSelectionRange(0, 99999);
        const sukses = document.execCommand('copy');
        if (sukses) {
            tampilkanSukses(btn, status);
        } else {
            tampilkanGagal(btn, status);
        }
    } catch (err) {
        tampilkanGagal(btn, status);
    }
}

function tampilkanSukses(btn, status) {
    if (status) {
        status.textContent = '✓ Prompt AI berhasil disalin!';
        status.className = 'text-success small fw-semibold';
        status.style.display = 'inline-block';
        setTimeout(() => { status.style.display = 'none'; }, 3500);
    }
    if (btn) {
        const textAwal = btn.innerHTML;
        btn.innerHTML = '<span style="color: #10b981">✓ Tersalin!</span>';
        setTimeout(() => { btn.innerHTML = textAwal; }, 2500);
    }
}

function tampilkanGagal(btn, status) {
    if (status) {
        status.textContent = 'Pilih dan salin teks manual dari kotak teks.';
        status.className = 'text-danger small fw-semibold';
        status.style.display = 'inline-block';
    }
}
</script>
@endpush
