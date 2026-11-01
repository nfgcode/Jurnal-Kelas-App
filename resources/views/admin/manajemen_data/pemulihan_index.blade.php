@extends('layouts.app')

@section('title', 'Uji Pemulihan Staging Terisolasi')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header Stepper & Status Banner -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-l-4 border-indigo-500">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Uji Pemulihan Database (Staging Terisolasi)</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Validasi integritas arsip cadangan, verifikasi relasi pada sandbox terisolasi, dan tinjau laporan dampak.
                </p>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300">
                    Production Restore: DISABLED
                </span>
                <p class="text-xs text-gray-400 mt-1">Lingkungan Aktif Dilindungi Penuh</p>
            </div>
        </div>

        <!-- Stepper Indicator -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 text-center">
            <div class="p-2.5 rounded bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800">
                <span class="block text-xs font-bold text-indigo-600 dark:text-indigo-400">Tahap 1</span>
                <span class="text-xs text-gray-700 dark:text-gray-300">Pilih Arsip</span>
            </div>
            <div class="p-2.5 rounded bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
                <span class="block text-xs font-bold text-gray-500">Tahap 2</span>
                <span class="text-xs text-gray-700 dark:text-gray-300">Validasi Checksum</span>
            </div>
            <div class="p-2.5 rounded bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
                <span class="block text-xs font-bold text-gray-500">Tahap 3</span>
                <span class="text-xs text-gray-700 dark:text-gray-300">Restore Sandbox</span>
            </div>
            <div class="p-2.5 rounded bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
                <span class="block text-xs font-bold text-gray-500">Tahap 4</span>
                <span class="text-xs text-gray-700 dark:text-gray-300">Laporan Dampak</span>
            </div>
            <div class="p-2.5 rounded bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
                <span class="block text-xs font-bold text-gray-500">Tahap 5</span>
                <span class="text-xs text-gray-700 dark:text-gray-300">Persetujuan Admin</span>
            </div>
        </div>
    </div>

    <!-- Alert Runner Status -->
    @if(! $isRunnerAvailable)
    <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200">
        <div class="flex items-center space-x-3">
            <svg class="h-6 w-6 text-amber-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                <span class="font-bold">Runner Staging Terisolasi: NOT AVAILABLE.</span>
                <p class="text-xs mt-0.5">Sistem menolak uji coba pemulihan demi menjaga isolasi fail-closed dari database produksi.</p>
            </div>
        </div>
    </div>
    @endif

    @if(session('status'))
    <div class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm">
        {{ session('status') }}
    </div>
    @endif

    @if($errors->any())
    <div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 text-sm">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Daftar Arsip yang Memenuhi Syarat -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">Pilih Arsip Cadangan untuk Uji Staging</h2>
            <span class="text-xs text-gray-500">Format yang didukung: SQL.GZ & SQL</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left">Nama Berkas</th>
                        <th class="px-6 py-3 text-left">Format & Ukuran</th>
                        <th class="px-6 py-3 text-left">Checksum SHA-256</th>
                        <th class="px-6 py-3 text-left">Status Verifikasi</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    @forelse($arsipList as $arsip)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-white">
                            {{ $arsip->nama_berkas }}
                            <div class="text-xs text-gray-400">{{ $arsip->created_at }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-600 dark:text-gray-300">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 uppercase">
                                {{ $arsip->format }}
                            </span>
                            <span class="ml-2 text-xs text-gray-400">{{ number_format($arsip->ukuran_byte / 1024, 1) }} KB</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ substr($arsip->checksum_sha256, 0, 16) }}...
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($arsip->status_validasi === 'terverifikasi')
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                                checksum_verified
                            </span>
                            @else
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                {{ $arsip->status_validasi }}
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <form action="{{ route('admin.manajemen-data.pemulihan.uji-staging') }}" method="POST">
                                @csrf
                                <input type="hidden" name="arsip_id" value="{{ $arsip->id }}">
                                <button type="submit" @if(! $isRunnerAvailable) disabled @endif
                                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Mulai Uji Staging &rarr;
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                            Belum ada arsip database SQL/SQL.GZ yang terdaftar untuk dipulihkan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($arsipList->hasPages())
        <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $arsipList->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
