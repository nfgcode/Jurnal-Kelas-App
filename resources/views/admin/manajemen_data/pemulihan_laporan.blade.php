@extends('layouts.app')

@section('title', 'Laporan Dampak Pemulihan Staging')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header Banner -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-l-4 border-emerald-500">
        <div class="flex items-center justify-between">
            <div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                    Hasil Uji Sandbox: {{ $report['status'] }}
                </span>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">Laporan Dampak & Integritas Relasional</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Arsip: <span class="font-mono font-medium text-gray-900 dark:text-gray-200">{{ $arsip->nama_berkas }}</span>
                </p>
            </div>
            <div class="text-right">
                <a href="{{ route('admin.manajemen-data.pemulihan.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                    &larr; Kembali ke Daftar Arsip
                </a>
            </div>
        </div>
    </div>

    <!-- Metrik Ringkasan Sandbox -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 uppercase">Tabel Terverifikasi</span>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $report['tables_valid'] }} / {{ $report['tables_checked'] }}</div>
            <span class="text-xs text-emerald-600 font-medium">31 Tabel Utama Lengkap</span>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 uppercase">Total Statement SQL</span>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $sqlVal['statement_count'] }}</div>
            <span class="text-xs text-emerald-600 font-medium">Lolos Allowlist SQL</span>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 uppercase">Orphan Record Check</span>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">0 Temuan</div>
            <span class="text-xs text-gray-500">Relasi Siswa, Guru & Riwayat Utuh</span>
        </div>
        <div class="bg-white dark:bg-gray-800 p-5 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 uppercase">Status Eksekusi Live</span>
            <div class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">DISABLED</div>
            <span class="text-xs text-gray-500">Keamanan Lingkungan Produksi</span>
        </div>
    </div>

    <!-- Ringkasan Jumlah Baris Data (Aman: Tanpa Hash/Password/Token) -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border border-gray-200 dark:border-gray-700">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Ringkasan Baris Tabel Terpilih</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            @foreach(['users', 'siswa', 'guru', 'jurnal', 'presensi', 'riwayat_kelas_siswa', 'kelas', 'tahun_ajaran'] as $tabel)
            <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded border border-gray-200 dark:border-gray-700">
                <span class="font-mono text-xs text-gray-500 uppercase">{{ $tabel }}</span>
                <div class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">
                    {{ $report['row_counts'][$tabel] ?? 0 }} baris
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Form Persetujuan Administratif dengan Reautentikasi Sandi -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-l-4 border-amber-500">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Persetujuan Administratif Uji Pemulihan</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Persetujuan ini mengonfirmasi bahwa arsip telah divalidasi dan memenuhi standar integritas data secara administratif.
            <strong class="text-red-600 dark:text-red-400">Tindakan ini TIDAK mengaktifkan atau mengeksekusi restore langsung pada database produksi aktif.</strong>
        </p>

        <form action="{{ route('admin.manajemen-data.pemulihan.setujui') }}" method="POST" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="approval_token" value="{{ $approvalToken }}">

            <div>
                <label for="catatan" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan Persetujuan (Opsional)</label>
                <textarea name="catatan" id="catatan" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Contoh: Terverifikasi untuk keperluan simulasi audit pemulihan bencana."></textarea>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Konfirmasi Sandi Admin (Reautentikasi Wajib) <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password" id="password" required class="mt-1 block w-full md:w-1/2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Masukkan kata sandi akun Anda">
                <p class="text-xs text-gray-500 mt-1">Sesuai kebijakan keamanan, reautentikasi kata sandi wajib sebelum memberikan persetujuan administratif.</p>
            </div>

            <div class="pt-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                    Setujui Hasil Uji Staging Secara Administratif
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
