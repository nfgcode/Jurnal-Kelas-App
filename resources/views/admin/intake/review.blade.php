@extends('layouts.app')

@section('title', 'Pratinjau Penerimaan Siswa Baru Tingkat X')

@section('content')
<div class="intake-review-view space-y-5">
    <style>
        .intake-review-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .intake-review-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: #111827 !important;
            margin-bottom: 6px !important;
        }
        .intake-review-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .intake-review-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .intake-review-view .breadcrumb-nav a {
            color: #4b5563 !important;
        }
        .intake-review-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .intake-review-view .kpi-card__num {
            font-size: 24px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
        }
        .intake-review-view .kpi-card__label {
            font-size: 13px !important;
            font-weight: 500 !important;
            line-height: 1.3 !important;
            color: #4b5563 !important;
        }
        .intake-review-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .intake-review-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .intake-review-view .tbl td {
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
            <a href="{{ route('admin.intake.index') }}" class="text-decoration-none">Penerimaan Siswa Baru</a>
            <span class="text-muted">&rsaquo;</span>
            <span class="text-dark fw-semibold" aria-current="page">Tinjau Draf #{{ $op->id }}</span>
        </div>
    </nav>

    {{-- Page Header --}}
    <div class="page-head d-flex justify-content-between align-items-start">
        <div>
            <h2 class="page-head__title">Pratinjau Penerimaan Siswa Baru Tingkat X</h2>
            <p class="page-head__sub">
                Operasi #{{ $op->id }} &bull; Tahun Ajaran Sasaran:
                <span class="chip chip--solid-lembut fw-bold" style="font-size: 13px; color: var(--primary, #3b5d32);">{{ $op->target_year }}</span>
                &bull; Tanggal Efektif Masuk: <strong>{{ $op->source_metadata['effective_date'] ?? '-' }}</strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.intake.index') }}" class="btn-hifi btn-hifi--ghost">
                <x-ikon nama="arrow-left" /> Kembali
            </a>
            <form method="POST" action="{{ route('admin.intake.validate', $op->id) }}">
                @csrf
                <button type="submit" class="btn-hifi btn-hifi--ghost">
                    <x-ikon nama="arrow-repeat" /> Validasi Ulang
                </button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="card-hifi p-3 mb-3 border-l-4 border-primary bg-primary-soft d-flex align-items-center gap-2">
            <x-ikon nama="check-circle" class="text-primary" />
            <div class="text-dark fw-medium small">{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="card-hifi p-4 border-l-4 mb-4" style="border-left-color: #b91c1c; background: #fff5f5;">
            <div class="fw-bold text-danger mb-1">Perhatian Terdeteksi:</div>
            <ul class="mb-0 ps-3 text-danger small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 4 Stat Cards Ringkasan Verifikasi --}}
    <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px;" class="mb-4">
        {{-- Total Calon Siswa --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="people" />
            </div>
            <div>
                <div class="kpi-card__num text-dark">{{ number_format($summary['total'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Total Calon Siswa</div>
            </div>
        </div>

        {{-- Kandidat Siap Didaftarkan --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(22, 101, 52, 0.12); color: #166534; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="check-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #166534;">{{ number_format($summary['siap'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Siap Didaftarkan</div>
            </div>
        </div>

        {{-- Bermasalah / Duplikasi --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: {{ $summary['masalah'] > 0 ? 'rgba(185, 28, 28, 0.12)' : 'rgba(107, 114, 128, 0.12)' }}; color: {{ $summary['masalah'] > 0 ? '#b91c1c' : '#6b7280' }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="exclamation-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: {{ $summary['masalah'] > 0 ? '#b91c1c' : '#374151' }};">{{ number_format($summary['masalah'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Bermasalah / Duplikasi</div>
            </div>
        </div>

        {{-- Dikecualikan --}}
        <div class="card-hifi p-3 d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(180, 83, 9, 0.12); color: #b45309; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                <x-ikon nama="dash-circle" />
            </div>
            <div>
                <div class="kpi-card__num" style="color: #b45309;">{{ number_format($summary['excluded'], 0, ',', '.') }}</div>
                <div class="kpi-card__label">Dikecualikan</div>
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
                    <h4 class="fw-bold mb-1" style="font-size: 16.5px; color: #92400e;">Finalisasi Ditahan (Ada {{ $summary['masalah'] }} Calon Siswa Memerlukan Perbaikan / Pengecualian)</h4>
                    <p class="mb-0 text-muted" style="font-size: 13.5px; line-height: 1.5;">
                        Penerimaan siswa baru hanya dapat dieksekusi jika seluruh baris yang bermasalah telah diperbaiki rombel tujuannya atau <strong>dikecualikan secara eksplisit</strong>. Periksa daftar pada kelompok di bawah.
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
                    <h4 class="fw-bold text-dark mb-1" style="font-size: 16.5px;">Kandidat Siap Didaftarkan (Snapshot Valid)</h4>
                    <p class="text-muted small mb-0">Semua baris aktif telah tervalidasi unik dan terbebas dari duplikasi identitas. Anda dapat mengeksekusi pendaftaran sekarang.</p>
                </div>
            </div>
            <button type="button" class="btn-hifi" data-bs-toggle="modal" data-bs-target="#confirmExecutionModal" style="padding: 10px 24px;">
                <x-ikon nama="check2-all" /> Finalisasi Penerimaan Siswa
            </button>
        </div>
    @endif

    {{-- Filter & Pencarian --}}
    <div class="card-hifi p-3 mb-4">
        <form method="GET" action="{{ route('admin.intake.review', $op->id) }}" class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
            <div class="flex-grow-1">
                <input type="text" name="q" class="select-hifi w-100" placeholder="Cari NIS, NISN, atau Email..." value="{{ $q }}">
            </div>
            <div style="width: 260px;">
                <select name="status" class="select-hifi w-100" onchange="this.form.submit()">
                    <option value="semua" @selected($filterStatus === 'semua')>Semua Kategori ({{ $summary['total'] }})</option>
                    <option value="masalah" @selected($filterStatus === 'masalah')>Ada Masalah / Duplikasi ({{ $summary['masalah'] }})</option>
                    <option value="siap" @selected($filterStatus === 'siap')>Siap Didaftarkan ({{ $summary['siap'] }})</option>
                    <option value="excluded" @selected($filterStatus === 'excluded')>Dikecualikan ({{ $summary['excluded'] }})</option>
                </select>
            </div>
            <button type="submit" class="btn-hifi btn-hifi--ghost">Cari</button>
        </form>
    </div>

    {{-- KELOMPOK 1: MASALAH / DUPLIKASI --}}
    @if (! empty($grouped['masalah']))
        <x-card title="1. Masalah & Duplikasi Wajib Diselesaikan ({{ count($grouped['masalah']) }})" flush class="mb-4 border-l-4" style="border-left-color: #b91c1c;">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 140px;">NIS</th>
                            <th style="width: 140px;">NISN</th>
                            <th>Email Calon</th>
                            <th style="width: 160px;">Rombel Kelas X Tujuan</th>
                            <th>Keterangan Masalah</th>
                            <th class="is-num" style="width: 150px;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grouped['masalah'] as $item)
                            <tr>
                                <td class="is-strong font-monospace">{{ $item['student_ref'] }}</td>
                                <td class="font-monospace text-muted">{{ $item['nisn'] }}</td>
                                <td>{{ $item['email'] }}</td>
                                <td>
                                    @php
                                        $tCls = collect($availableClasses)->firstWhere('id', $item['target_class_id']);
                                    @endphp
                                    <span class="text-dark fw-semibold">{{ $tCls ? $tCls->nama_kelas : '-' }}</span>
                                </td>
                                <td>
                                    @foreach ($item['errors'] as $err)
                                        <span class="chip chip--neutral" style="color: #b91c1c; background: #fee2e2;">{{ $err }}</span>
                                    @endforeach
                                </td>
                                <td class="is-num tbl__aksi">
                                    <button type="button" class="btn-hifi btn-hifi--sm" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                        <x-ikon nama="pencil" /> Perbaiki / Lewati
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- KELOMPOK 2: BARIS DIKECUALIKAN (EXCLUDED) --}}
    @if (! empty($grouped['excluded']))
        <x-card title="2. Baris Dikecualikan (Dilewati Saat Eksekusi) ({{ count($grouped['excluded']) }})" flush class="mb-4 border-l-4" style="border-left-color: #b45309;">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 140px;">NIS</th>
                            <th style="width: 140px;">NISN</th>
                            <th>Email</th>
                            <th style="width: 160px;">Rombel Tujuan</th>
                            <th>Keterangan</th>
                            <th class="is-num" style="width: 160px;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grouped['excluded'] as $item)
                            <tr>
                                <td class="is-strong font-monospace text-muted">{{ $item['student_ref'] }}</td>
                                <td class="font-monospace text-muted">{{ $item['nisn'] }}</td>
                                <td class="text-muted">{{ $item['email'] }}</td>
                                <td class="text-muted">
                                    @php
                                        $tCls = collect($availableClasses)->firstWhere('id', $item['target_class_id']);
                                    @endphp
                                    {{ $tCls ? $tCls->nama_kelas : '-' }}
                                </td>
                                <td>
                                    <span class="chip chip--neutral" style="color: #b45309; background: #fef3c7;">Dikecualikan Admin</span>
                                </td>
                                <td class="is-num tbl__aksi">
                                    <form method="POST" action="{{ route('admin.intake.update-item', ['id' => $op->id, 'itemId' => $item['id']]) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="action" value="include">
                                        <button type="submit" class="btn-hifi btn-hifi--sm btn-hifi--ghost">
                                            <x-ikon nama="check-circle" /> Sertakan Kembali
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- KELOMPOK 3: KANDIDAT SIAP DIDAFTARKAN --}}
    <x-card title="3. Calon Siswa Siap Didaftarkan ({{ count($grouped['siap']) }})" flush class="mb-4 border-l-4 border-primary">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 140px;">NIS</th>
                        <th style="width: 140px;">NISN</th>
                        <th>Email Calon</th>
                        <th style="width: 160px;">Rombel Kelas X Sasaran</th>
                        <th>Status Akses Akun</th>
                        <th class="is-num" style="width: 150px;">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grouped['siap'] as $item)
                        <tr>
                            <td class="is-strong font-monospace">{{ $item['student_ref'] }}</td>
                            <td class="font-monospace text-muted">{{ $item['nisn'] }}</td>
                            <td>{{ $item['email'] }}</td>
                            <td>
                                @php
                                    $tCls = collect($availableClasses)->firstWhere('id', $item['target_class_id']);
                                @endphp
                                <strong class="text-dark">{{ $tCls ? $tCls->nama_kelas : '-' }}</strong>
                            </td>
                            <td>
                                <span class="chip chip--green" title="Akun dibuat dengan password acak aman">
                                    Siap Diterbitkan
                                </span>
                            </td>
                            <td class="is-num tbl__aksi">
                                <button type="button" class="btn-hifi btn-hifi--sm btn-hifi--ghost" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                    <x-ikon nama="pencil" /> Ubah / Lewati
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state text-center py-4 text-muted" style="font-size: 13.5px;">
                                Tidak ada data calon siswa siap diproses yang cocok dengan filter saat ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>

{{-- Modal Edit Item Calon Siswa --}}
@foreach ($items as $item)
    <div class="modal fade" id="editItemModal{{ $item['id'] }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content card-hifi p-4">
                <form method="POST" action="{{ route('admin.intake.update-item', ['id' => $op->id, 'itemId' => $item['id']]) }}">
                    @csrf
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="card-hifi__title mb-0" style="font-size: 17px !important;">Penataan Calon Siswa</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>

                    <div class="mb-3">
                        <div class="p-2 mb-3 rounded" style="background: rgba(0,0,0,0.03); font-size: 13px;">
                            <span class="text-muted">Data Calon:</span> <strong class="text-dark">NIS {{ $item['student_ref'] }} &bull; NISN {{ $item['nisn'] }}</strong>
                            <div class="text-muted small">{{ $item['email'] }}</div>
                        </div>

                        <div class="field mb-3">
                            <label class="field__label" for="actionSelect{{ $item['id'] }}">Tindakan Administratif</label>
                            <select name="action" class="select-hifi w-100" id="actionSelect{{ $item['id'] }}" onchange="handleActionChange({{ $item['id'] }})" required>
                                <option value="update_class">Perbarui Rombel Kelas X Tujuan</option>
                                <option value="exclude">Kecualikan dari Penerimaan (Lewati Baris Ini)</option>
                            </select>
                        </div>

                        <div class="field mb-3" id="classGroup{{ $item['id'] }}">
                            <label class="field__label">Pilih Rombel Kelas X Tujuan</label>
                            <select name="target_class_id" class="select-hifi w-100">
                                <option value="">-- Pilih Kelas X Tujuan --</option>
                                @foreach ($availableClasses as $c)
                                    <option value="{{ $c->id }}" @selected((string)$c->id === (string)$item['target_class_id'])>
                                        {{ $c->nama_kelas }} ({{ $c->jurusan_kode }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field mb-3 d-none" id="exclusionGroup{{ $item['id'] }}">
                            <label class="field__label">Alasan Pengecualian Administratif</label>
                            <input type="text" name="exclusion_reason" class="select-hifi w-100" id="exclusionInput{{ $item['id'] }}" placeholder="contoh: Duplikasi berkas / Mengundurkan diri">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn-hifi">Simpan Penataan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

{{-- Modal Konfirmasi Eksekusi Penerimaan --}}
<div class="modal fade" id="confirmExecutionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-hifi p-4">
            <form method="POST" action="{{ route('admin.intake.execute', $op->id) }}">
                @csrf
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-hifi__title mb-0" style="font-size: 18px !important;">Konfirmasi Penerimaan Siswa Baru</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="mb-3">
                    <p class="field__hint mb-3" style="font-size: 13.5px; color: #374151;">
                        Anda akan memfinalisasi penerimaan siswa baru tingkat X untuk Tahun Ajaran <strong>{{ $op->target_year }}</strong>:
                    </p>
                    <ul class="text-muted small ps-3 mb-3" style="line-height: 1.6;">
                        <li><strong>{{ $summary['siap'] }}</strong> siswa baru akan diterbitkan entitasnya dan ditempatkan ke kelas X tujuan.</li>
                        @if ($summary['excluded'] > 0)
                            <li><strong>{{ $summary['excluded'] }}</strong> calon siswa DIKECUALIKAN (dilewati dari pembuatan akun).</li>
                        @endif
                        <li>Proses ini dijalankan secara atomik. Akun portal siswa dibuat otomatis dengan password acak aman.</li>
                    </ul>
                </div>
                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <button type="button" class="btn-hifi btn-hifi--ghost" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-hifi">
                        <x-ikon nama="check-lg" /> Ya, Eksekusi Pendaftaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function handleActionChange(id) {
    var action = document.getElementById('actionSelect' + id).value;
    var classGroup = document.getElementById('classGroup' + id);
    var exclusionGroup = document.getElementById('exclusionGroup' + id);
    var exclusionInput = document.getElementById('exclusionInput' + id);

    if (action === 'exclude') {
        classGroup.classList.add('d-none');
        exclusionGroup.classList.remove('d-none');
        exclusionInput.setAttribute('required', 'required');
    } else {
        classGroup.classList.remove('d-none');
        exclusionGroup.classList.add('d-none');
        exclusionInput.removeAttribute('required');
    }
}
</script>
@endsection
