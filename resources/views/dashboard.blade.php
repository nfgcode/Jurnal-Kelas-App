@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
{{-- Welcome --}}
<div class="mb-4">
    <h5 class="fw-semibold text-dark">Selamat Datang, {{ Auth::user()->name ?? 'User' }}! 👋</h5>
    <p class="text-muted mb-0">Berikut adalah ringkasan data Jurnal Kelas hari ini.</p>
</div>

{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    {{-- Total Siswa --}}
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 50px; height: 50px; background: rgba(13,110,253,0.1);">
                    <i class="bi bi-people fs-4 text-primary"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0">{{ $totalSiswa ?? 0 }}</h3>
                    <span class="text-muted small">Total Siswa</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Total Guru --}}
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 50px; height: 50px; background: rgba(25,135,84,0.1);">
                    <i class="bi bi-person-badge fs-4 text-success"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0">{{ $totalGuru ?? 0 }}</h3>
                    <span class="text-muted small">Total Guru</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Total Kelas --}}
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 50px; height: 50px; background: rgba(111,66,193,0.1);">
                    <i class="bi bi-building fs-4" style="color: #6f42c1;"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0">{{ $totalKelas ?? 0 }}</h3>
                    <span class="text-muted small">Total Kelas</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Jurnal Hari Ini --}}
    <div class="col-sm-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 50px; height: 50px; background: rgba(253,126,20,0.1);">
                    <i class="bi bi-journal-text fs-4" style="color: #fd7e14;"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0">{{ $jurnalHariIni ?? 0 }}</h3>
                    <span class="text-muted small">Jurnal Hari Ini</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Jurnal Terbaru --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="fw-semibold mb-0">
            <i class="bi bi-journal-text me-2 text-primary"></i>Jurnal Terbaru
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Tanggal</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Replace with dynamic data --}}
                    @forelse ($jurnalTerbaru ?? [] as $jurnal)
                        <tr>
                            <td class="ps-3">{{ $jurnal->tanggal }}</td>
                            <td>{{ $jurnal->jadwal->kelas->nama ?? '-' }}</td>
                            <td>{{ $jurnal->jadwal->mataPelajaran->nama ?? '-' }}</td>
                            <td>{{ $jurnal->jadwal->guru->name ?? '-' }}</td>
                        </tr>
                    @empty
                        {{-- Sample static data - Replace with dynamic data --}}
                        <tr>
                            <td class="ps-3">{{ date('d/m/Y') }}</td>
                            <td>XII RPL 1</td>
                            <td>Pemrograman Web</td>
                            <td>Budi Santoso</td>
                        </tr>
                        <tr>
                            <td class="ps-3">{{ date('d/m/Y') }}</td>
                            <td>XI TKJ 2</td>
                            <td>Administrasi Server</td>
                            <td>Siti Rahayu</td>
                        </tr>
                        <tr>
                            <td class="ps-3">{{ date('d/m/Y', strtotime('-1 day')) }}</td>
                            <td>X MM 1</td>
                            <td>Desain Grafis</td>
                            <td>Ahmad Hidayat</td>
                        </tr>
                        <tr>
                            <td class="ps-3">{{ date('d/m/Y', strtotime('-1 day')) }}</td>
                            <td>XII RPL 2</td>
                            <td>Basis Data</td>
                            <td>Dewi Lestari</td>
                        </tr>
                        <tr>
                            <td class="ps-3">{{ date('d/m/Y', strtotime('-2 days')) }}</td>
                            <td>XI RPL 1</td>
                            <td>Pemrograman Mobile</td>
                            <td>Eko Prasetyo</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
