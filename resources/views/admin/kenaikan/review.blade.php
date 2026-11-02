@extends('layouts.app')

@section('title', 'Pratinjau Kenaikan Kelas X–XI')

@section('content')
<div class="kenaikan-review-view space-y-5">
    <style>
        .kenaikan-review-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .kenaikan-review-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .kenaikan-review-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .kenaikan-review-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .kenaikan-review-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .kenaikan-review-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .kenaikan-review-view .kpi-card__num {
            font-size: 24px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
        }
        .kenaikan-review-view .kpi-card__label {
            font-size: 13px !important;
            font-weight: 500 !important;
            line-height: 1.3 !important;
            color: #4b5563 !important;
        }
        .kenaikan-review-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .kenaikan-review-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .kenaikan-review-view .tbl td {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            padding: 10px 14px !important;
        }
    </style>

    {{-- Breadcrumb Navigasi --}}
    <nav class="breadcrumb-nav mb-3" aria-label="Breadcrumb">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.tahun-ajaran.index') }}" class="text-decoration-none">Tahun Ajaran</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.kenaikan.index') }}" class="text-decoration-none">Kenaikan Kelas X–XI</a>
            <span class="text-muted">&rsaquo;</span>
            <span class="text-dark fw-semibold" aria-current="page">Tinjau Draf #{{ $op->id }}</span>
        </div>
    </nav>

    {{-- Page Header --}}
    <div class="page-head d-flex justify-content-between align-items-start">
        <div>
            <h2 class="page-head__title">Pratinjau Kenaikan Kelas X–XI</h2>
            <p class="page-head__sub">
                Operasi #{{ $op->id }} &bull; Periode:
                <span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: var(--primary, #3b5d32);">{{ $op->source_year }}</span>
                &rarr;
                <span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: #1e3a8a; background: #e0e7ff;">{{ $op->target_year }}</span>
                &bull; Tanggal Efektif: <strong>{{ $op->source_metadata['effective_date'] ?? '-' }}</strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.kenaikan.index') }}" class="btn-hifi btn-hifi--ghost">
                <x-ikon nama="arrow-left" /> Kembali
            </a>
            <form method="POST" action="{{ route('admin.kenaikan.validate', $op->id) }}">
                @csrf
                <button type="submit" class="btn-hifi btn-hifi--ghost">
                    <x-ikon nama="arrow-repeat" /> Validasi Ulang
                </button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-3" role="alert">
            <x-ikon nama="check-circle" />
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-3" role="alert">
            <div class="fw-bold mb-1">Perhatian:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 4 Stat Cards Ringkasan Verifikasi --}}
    <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px;" class="mb-4">
        {{-- Total Roster --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="people" />
            </div>
            <div>
                <div class="kpi-card__num text-dark">{{ number_format($summary['total'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Total Roster X &amp; XI</div>
            </div>
        </div>

        {{-- Siswa Naik Kelas --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(22, 101, 52, 0.12); color: #166534; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="arrow-up-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #166534;">{{ number_format($summary['naik'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Siswa Naik Kelas</div>
            </div>
        </div>

        {{-- Tinggal Kelas --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(180, 83, 9, 0.12); color: #b45309; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="dash-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #b45309;">{{ number_format($summary['tinggal_kelas'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Tinggal Kelas</div>
            </div>
        </div>

        {{-- Konflik / Masalah --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: {{ $summary['masalah'] > 0 ? 'rgba(185, 28, 28, 0.12)' : 'rgba(107, 114, 128, 0.12)' }}; color: {{ $summary['masalah'] > 0 ? '#b91c1c' : '#6b7280' }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="exclamation-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: {{ $summary['masalah'] > 0 ? '#b91c1c' : '#374151' }};">{{ number_format($summary['masalah'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Perlu Tindak Lanjut</div>
            </div>
        </div>
    </div>

    {{-- Banner Status Kesiapan Gating --}}
    @if ($summary['masalah'] > 0 || $preview->validation !== 'valid')
        <div class="card-hifi p-4 border-l-4 mb-4" style="border-left-color: #b45309; background: #fffbeb;">
            <div class="d-flex align-items-start gap-3">
                <div style="font-size: 24px; color: #b45309;">
                    <x-ikon nama="exclamation-triangle" />
                </div>
                <div>
                    <h4 class="fw-bold mb-1" style="font-size: 16.5px; color: #92400e;">Finalisasi Ditahan (Ada {{ $summary['masalah'] }} Siswa Memerlukan Tindak Lanjut)</h4>
                    <p class="mb-0 text-muted" style="font-size: 13.5px; line-height: 1.5;">
                        Finalisasi kenaikan kelas hanya dapat diproses apabila seluruh siswa tingkat X dan XI telah memiliki keputusan yang sah, kelas tujuan yang valid, dan tidak ada konflik data. Selesaikan data pada kelompok di bawah.
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="card-hifi p-4 border-l-4 border-primary bg-primary-soft d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <div style="font-size: 26px; color: var(--primary, #3b5d32);">
                    <x-ikon nama="check-circle" />
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 16.5px;">Seluruh Roster Siswa Siap Diproses</h4>
                    <p class="text-muted small mb-0">Semua keputusan telah valid. Anda dapat melakukan finalisasi kenaikan kelas sekarang.</p>
                </div>
            </div>
            <button type="button" class="btn-hifi" data-bs-toggle="modal" data-bs-target="#confirmExecutionModal" style="padding: 10px 24px;">
                <x-ikon nama="check2-all" /> Finalisasi Kenaikan Kelas
            </button>
        </div>
    @endif

    {{-- Filter & Pencarian --}}
    <div class="card-hifi p-3 mb-4">
        <form method="GET" action="{{ route('admin.kenaikan.review', $op->id) }}" class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
            <div class="flex-grow-1">
                <input type="text" name="q" class="select-hifi w-100" placeholder="Cari NIS atau Nama Siswa..." value="{{ $q }}">
            </div>
            <div style="width: 220px;">
                <select name="status" class="select-hifi w-100" onchange="this.form.submit()">
                    <option value="semua" @selected($filterStatus === 'semua')>Semua Status ({{ $summary['total'] }})</option>
                    <option value="masalah" @selected($filterStatus === 'masalah')>Ada Masalah / Konflik ({{ $summary['masalah'] }})</option>
                    <option value="naik" @selected($filterStatus === 'naik')>Naik Kelas ({{ $summary['naik'] }})</option>
                    <option value="tinggal_kelas" @selected($filterStatus === 'tinggal_kelas')>Tinggal Kelas ({{ $summary['tinggal_kelas'] }})</option>
                </select>
            </div>
            <div style="width: 180px;">
                <select name="tingkat" class="select-hifi w-100" onchange="this.form.submit()">
                    <option value="semua" @selected($filterTingkat === 'semua')>Semua Tingkat</option>
                    <option value="X" @selected($filterTingkat === 'X')>Tingkat X</option>
                    <option value="XI" @selected($filterTingkat === 'XI')>Tingkat XI</option>
                </select>
            </div>
            <button type="submit" class="btn-hifi btn-hifi--ghost">Cari</button>
        </form>
    </div>

    {{-- KELOMPOK 1: MASALAH & KONFLIK --}}
    @if (! empty($grouped['masalah']))
        <x-card title="1. Siswa Bermasalah & Konflik ({{ count($grouped['masalah']) }})" flush class="mb-4 border-l-4" style="border-left-color: #b91c1c;">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 140px;">NIS</th>
                            <th>Nama Siswa</th>
                            <th style="width: 120px;">Kelas Asal</th>
                            <th style="width: 130px;">Keputusan</th>
                            <th style="width: 140px;">Kelas Tujuan</th>
                            <th>Keterangan Masalah</th>
                            <th class="is-num" style="width: 140px;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grouped['masalah'] as $item)
                            <tr>
                                <td class="is-strong font-monospace">{{ $item['student_ref'] }}</td>
                                <td><strong>{{ $item['nama'] }}</strong></td>
                                <td><span class="chip chip--neutral">{{ $item['kelas_asal'] }}</span></td>
                                <td>
                                    <span class="chip chip--neutral" style="color: #b45309; background: #fef3c7;">{{ strtoupper($item['decision']) }}</span>
                                </td>
                                <td>
                                    @php
                                        $targetCls = collect($targetClasses)->firstWhere('id', $item['target_class_id']);
                                    @endphp
                                    <span class="text-dark fw-semibold">{{ $targetCls ? $targetCls->nama_kelas : '-' }}</span>
                                </td>
                                <td>
                                    @foreach ($item['errors'] as $err)
                                        <span class="chip chip--neutral" style="color: #b91c1c; background: #fee2e2;">{{ $err }}</span>
                                    @endforeach
                                </td>
                                <td class="is-num tbl__aksi">
                                    <button type="button" class="btn-hifi btn-hifi--sm" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                        <x-ikon nama="pencil" /> Perbaiki
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- KELOMPOK 2: SISWA TINGGAL KELAS --}}
    @if (! empty($grouped['tinggal_kelas']))
        <x-card title="2. Siswa Tinggal Kelas ({{ count($grouped['tinggal_kelas']) }})" flush class="mb-4 border-l-4" style="border-left-color: #b45309;">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 140px;">NIS</th>
                            <th>Nama Siswa</th>
                            <th style="width: 120px;">Kelas Asal</th>
                            <th style="width: 140px;">Tingkat Mengulang</th>
                            <th>Kelas Tujuan</th>
                            <th class="is-num" style="width: 140px;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grouped['tinggal_kelas'] as $item)
                            <tr>
                                <td class="is-strong font-monospace">{{ $item['student_ref'] }}</td>
                                <td><strong>{{ $item['nama'] }}</strong></td>
                                <td><span class="chip chip--neutral">{{ $item['kelas_asal'] }}</span></td>
                                <td><span class="chip chip--neutral" style="color: #b45309; background: #fef3c7;">Tingkat {{ $item['tingkat_asal'] }}</span></td>
                                <td>
                                    @php
                                        $targetCls = collect($targetClasses)->firstWhere('id', $item['target_class_id']);
                                    @endphp
                                    <strong>{{ $targetCls ? $targetCls->nama_kelas : '-' }}</strong>
                                </td>
                                <td class="is-num tbl__aksi">
                                    <button type="button" class="btn-hifi btn-hifi--sm btn-hifi--ghost" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                        <x-ikon nama="pencil" /> Ubah
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- KELOMPOK 3: SISWA NAIK KELAS --}}
    <x-card title="3. Siswa Naik Kelas ({{ count($grouped['naik']) }})" flush class="mb-4 border-l-4 border-primary">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 140px;">NIS</th>
                        <th>Nama Siswa</th>
                        <th style="width: 120px;">Kelas Asal</th>
                        <th style="width: 140px;">Tingkat Tujuan</th>
                        <th>Kelas Tujuan</th>
                        <th class="is-num" style="width: 140px;">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grouped['naik'] as $item)
                        <tr>
                            <td class="is-strong font-monospace">{{ $item['student_ref'] }}</td>
                            <td><strong>{{ $item['nama'] }}</strong></td>
                            <td><span class="chip chip--neutral">{{ $item['kelas_asal'] }}</span></td>
                            <td>
                                <span class="chip chip--green">
                                    {{ $item['tingkat_asal'] === 'X' ? 'Tingkat XI' : 'Tingkat XII' }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $targetCls = collect($targetClasses)->firstWhere('id', $item['target_class_id']);
                                @endphp
                                <strong class="text-dark">{{ $targetCls ? $targetCls->nama_kelas : '-' }}</strong>
                            </td>
                            <td class="is-num tbl__aksi">
                                <button type="button" class="btn-hifi btn-hifi--sm btn-hifi--ghost" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                    <x-ikon nama="pencil" /> Ubah
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state text-center py-4 text-muted" style="font-size: 13.5px;">
                                Tidak ada data siswa naik kelas yang cocok dengan filter saat ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>

{{-- Modal Edit Item Siswa --}}
@foreach ($items as $item)
    <div class="modal fade" id="editItemModal{{ $item['id'] }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content card-hifi p-4">
                <form method="POST" action="{{ route('admin.kenaikan.update-item', ['id' => $op->id, 'itemId' => $item['id']]) }}">
                    @csrf
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="card-hifi__title mb-0" style="font-size: 17px !important;">Tinjau Keputusan: {{ $item['nama'] }} ({{ $item['student_ref'] }})</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>

                    <div class="mb-3">
                        <div class="p-2 mb-3 rounded" style="background: rgba(0,0,0,0.03); font-size: 13px;">
                            <span class="text-muted">Kelas Asal:</span> <strong class="text-dark">{{ $item['kelas_asal'] }} (Tingkat {{ $item['tingkat_asal'] }})</strong>
                        </div>

                        <div class="field mb-3">
                            <label class="field__label" for="decisionSelect{{ $item['id'] }}">Keputusan Kenaikan</label>
                            <select name="decision" class="select-hifi w-100" id="decisionSelect{{ $item['id'] }}" onchange="handleDecisionChange({{ $item['id'] }})" required>
                                <option value="naik" @selected($item['decision'] === 'naik')>NAIK KELAS</option>
                                <option value="tinggal_kelas" @selected($item['decision'] === 'tinggal_kelas')>TINGGAL KELAS (Mengulang)</option>
                                <option value="unverified" @selected($item['decision'] === 'unverified')>BELUM TERVERIFIKASI</option>
                            </select>
                        </div>

                        <div class="field mb-3" id="targetClassGroup{{ $item['id'] }}">
                            <label class="field__label">Kelas Tujuan di Tahun {{ $op->target_year }}</label>
                            <select name="target_class_id" class="select-hifi w-100" id="targetClassSelect{{ $item['id'] }}">
                                <option value="">-- Pilih Kelas Tujuan --</option>
                                @foreach ($targetClasses as $tClass)
                                    <option value="{{ $tClass->id }}" @selected((string)$tClass->id === (string)$item['target_class_id'])>
                                        {{ $tClass->nama_kelas }} (Tingkat {{ $tClass->tingkat }} - {{ $tClass->jurusan_kode }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field mb-3" id="alasanGroup{{ $item['id'] }}">
                            <label class="field__label">Alasan / Catatan Penyesuaian</label>
                            <input type="text" name="alasan" class="select-hifi w-100" id="alasanInput{{ $item['id'] }}" placeholder="Wajib diisi jika tinggal kelas..." value="{{ old('alasan', $item['alasan'] ?? '') }}">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn-hifi">Simpan Keputusan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

{{-- Modal Konfirmasi Eksekusi Kenaikan --}}
<div class="modal fade" id="confirmExecutionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-hifi p-4">
            <form method="POST" action="{{ route('admin.kenaikan.execute', $op->id) }}">
                @csrf
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-hifi__title mb-0" style="font-size: 18px !important;">Konfirmasi Finalisasi Kenaikan Kelas</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="mb-3">
                    <p class="field__hint mb-3" style="font-size: 13.5px; color: #374151;">
                        Anda akan memfinalisasi pemindahan kelas untuk <strong>{{ $summary['total'] }}</strong> siswa tingkat X dan XI dari tahun <strong>{{ $op->source_year }}</strong> ke <strong>{{ $op->target_year }}</strong>:
                    </p>
                    <ul class="text-muted small ps-3 mb-3" style="line-height: 1.6;">
                        <li><strong>{{ $summary['naik'] }}</strong> siswa akan dipromosikan ke tingkat rombel berikutnya.</li>
                        @if ($summary['tinggal_kelas'] > 0)
                            <li><strong>{{ $summary['tinggal_kelas'] }}</strong> siswa TINGGAL KELAS akan ditempatkan pada rombel pengulangan.</li>
                        @endif
                        <li>Riwayat kelas sebelumnya akan ditutup rapi dan snapshot cadangan dibuat otomatis.</li>
                    </ul>
                </div>
                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-hifi">
                        <x-ikon nama="check-lg" /> Ya, Eksekusi Kenaikan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function handleDecisionChange(id) {
    var select = document.getElementById('decisionSelect' + id);
    var targetGroup = document.getElementById('targetClassGroup' + id);
    var targetSelect = document.getElementById('targetClassSelect' + id);
    var alasanGroup = document.getElementById('alasanGroup' + id);
    var alasanInput = document.getElementById('alasanInput' + id);

    if (select.value === 'tinggal_kelas') {
        alasanInput.setAttribute('required', 'required');
    } else {
        alasanInput.removeAttribute('required');
    }
}
</script>
@endsection
