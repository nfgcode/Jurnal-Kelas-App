@extends('layouts.app')

@section('title', 'Pratinjau Penerimaan Siswa Baru Tingkat X')

@section('content')
<div class="intake-review-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">Pratinjau Penerimaan Siswa Baru Tingkat X</h4>
            <div class="text-muted small">
                Operasi #{{ $op->id }} &bull; Tahun Ajaran Target: <strong>{{ $op->target_year }}</strong>
                &bull; Referensi: <strong>{{ $op->source_metadata['official_reference'] ?? '-' }}</strong>
                &bull; Tgl Efektif Masuk: <strong>{{ $op->source_metadata['effective_date'] ?? '-' }}</strong>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.intake.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-ikon nama="arrow-left" /> Kembali
            </a>
            <form method="POST" action="{{ route('admin.intake.validate', $op->id) }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm">
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

    {{-- Kartu Metrik Ringkasan --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase">Total Calon Siswa</div>
                    <div class="fs-4 fw-bold text-dark">{{ number_format($summary['total'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm border-0 border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="text-success small fw-bold text-uppercase">Kandidat Siap Didaftarkan</div>
                    <div class="fs-4 fw-bold text-success">{{ number_format($summary['siap'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm border-0 border-start {{ $summary['masalah'] > 0 ? 'border-danger' : 'border-secondary' }} border-4">
                <div class="card-body p-3">
                    <div class="{{ $summary['masalah'] > 0 ? 'text-danger' : 'text-muted' }} small fw-bold text-uppercase">Bermasalah / Duplikasi</div>
                    <div class="fs-4 fw-bold {{ $summary['masalah'] > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($summary['masalah'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body p-3">
                    <div class="text-warning small fw-bold text-uppercase">Dikecualikan (Excluded)</div>
                    <div class="fs-4 fw-bold text-warning">{{ number_format($summary['excluded'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Banner Status Kesiapan Gating --}}
    @if ($summary['masalah'] > 0 || $preview->validation !== 'valid')
        <div class="alert alert-warning mb-4 shadow-sm" role="alert">
            <div class="d-flex gap-2">
                <x-ikon nama="exclamation-octagon" class="fs-5 flex-shrink-0" />
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Finalisasi Ditahan (Ada {{ $summary['masalah'] }} Calon Siswa Memerlukan Perbaikan / Pengecualian)</h6>
                    <p class="small mb-0">
                        Penerimaan siswa baru hanya dapat dieksekusi jika seluruh baris yang bermasalah telah diperbaiki kelas tujuannya atau <strong>dikecualikan secara eksplisit</strong> dengan alasan administratif tertulis. Periksa daftar pada kelompok <strong>Masalah &amp; Duplikasi</strong> di bawah.
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-success mb-4 shadow-sm d-flex justify-content-between align-items-center" role="alert">
            <div class="d-flex gap-2">
                <x-ikon nama="check-circle" class="fs-5 flex-shrink-0" />
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Kandidat Siap Didaftarkan (Snapshot Valid)</h6>
                    <p class="small mb-0">Semua baris aktif telah tervalidasi unik dan terbebas dari duplikasi identitas. Anda dapat mengeksekusi pendaftaran sekarang.</p>
                </div>
            </div>
            <button type="button" class="btn btn-success fw-bold px-4" data-bs-toggle="modal" data-bs-target="#confirmExecutionModal">
                <x-ikon nama="check2-all" /> Finalisasi Penerimaan Siswa
            </button>
        </div>
    @endif

    {{-- Filter & Pencarian --}}
    <div class="card mb-3 shadow-sm border-0">
        <div class="card-body p-2">
            <form method="GET" action="{{ route('admin.intake.review', $op->id) }}" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent"><x-ikon nama="filter" /></span>
                        <input type="text" name="q" class="form-control" placeholder="Cari NIS, NISN, atau Email..." value="{{ $q }}">
                    </div>
                </div>
                <div class="col-md-5">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="semua" {{ $filterStatus === 'semua' ? 'selected' : '' }}>Semua Kategori Status</option>
                        <option value="masalah" {{ $filterStatus === 'masalah' ? 'selected' : '' }}>Ada Masalah / Duplikasi</option>
                        <option value="siap" {{ $filterStatus === 'siap' ? 'selected' : '' }}>Kandidat Siap Didaftarkan</option>
                        <option value="excluded" {{ $filterStatus === 'excluded' ? 'selected' : '' }}>Baris Dikecualikan</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Saring</button>
                    @if ($q !== '' || $filterStatus !== 'semua')
                        <a href="{{ route('admin.intake.review', $op->id) }}" class="btn btn-sm btn-link text-muted" title="Reset filter">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- KELOMPOK 1: MASALAH / DUPLIKASI --}}
    @if (! empty($grouped['masalah']))
        <div class="card mb-4 border-danger shadow-sm">
            <div class="card-header bg-danger text-white fw-bold d-flex justify-content-between align-items-center">
                <span>
                    <x-ikon nama="exclamation-octagon" /> Kelompok 1: Masalah &amp; Duplikasi Wajib Diselesaikan ({{ count($grouped['masalah']) }})
                </span>
                <span class="badge bg-white text-danger">Memerlukan Perbaikan / Pengecualian</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>NIS</th>
                                <th>NISN</th>
                                <th>Email Calon</th>
                                <th>Rombel Kelas X Tujuan</th>
                                <th>Keterangan Error / Konflik</th>
                                <th class="text-end">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grouped['masalah'] as $item)
                                <tr class="table-danger">
                                    <td class="font-monospace fw-bold">{{ $item['student_ref'] }}</td>
                                    <td class="font-monospace">{{ $item['nisn'] }}</td>
                                    <td>{{ $item['email'] }}</td>
                                    <td>
                                        @php
                                            $tCls = collect($availableClasses)->firstWhere('id', $item['target_class_id']);
                                        @endphp
                                        {{ $tCls ? $tCls->nama_kelas : '-' }}
                                    </td>
                                    <td>
                                        @foreach ($item['errors'] as $err)
                                            <span class="badge bg-danger">{{ $err }}</span>
                                        @endforeach
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                            <x-ikon nama="pencil" /> Perbaiki / Kecualikan
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- KELOMPOK 2: BARIS DIKECUALIKAN (EXCLUDED) --}}
    @if (! empty($grouped['excluded']))
        <div class="card mb-4 border-warning shadow-sm">
            <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center">
                <span>
                    <x-ikon nama="dash-circle" /> Kelompok 2: Baris Dikecualikan Secara Eksplisit ({{ count($grouped['excluded']) }})
                </span>
                <span class="badge bg-dark text-white">Dilewati Saat Eksekusi</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>NIS</th>
                                <th>NISN</th>
                                <th>Email</th>
                                <th>Rombel Tujuan</th>
                                <th>Alasan Pengecualian</th>
                                <th class="text-end">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grouped['excluded'] as $item)
                                <tr>
                                    <td class="font-monospace fw-bold text-muted">{{ $item['student_ref'] }}</td>
                                    <td class="font-monospace text-muted">{{ $item['nisn'] }}</td>
                                    <td class="text-muted">{{ $item['email'] }}</td>
                                    <td class="text-muted">
                                        @php
                                            $tCls = collect($availableClasses)->firstWhere('id', $item['target_class_id']);
                                        @endphp
                                        {{ $tCls ? $tCls->nama_kelas : '-' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">Dikecualikan Admin</span>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.intake.update-item', ['id' => $op->id, 'itemId' => $item['id']]) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="action" value="include">
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                <x-ikon nama="check-circle" /> Sertakan Kembali
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- KELOMPOK 3: KANDIDAT SIAP DIDAFTARKAN --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
            <span>
                <x-ikon nama="check-circle" /> Kelompok 3: Calon Siswa Siap Didaftarkan ({{ count($grouped['siap']) }})
            </span>
            <span class="badge bg-success">Siap Eksekusi</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>NIS</th>
                            <th>NISN</th>
                            <th>Email Login Calon</th>
                            <th>Rombel Kelas X Tujuan</th>
                            <th>Status Akses Awal</th>
                            <th class="text-end">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grouped['siap'] as $item)
                            <tr>
                                <td class="font-monospace fw-bold">{{ $item['student_ref'] }}</td>
                                <td class="font-monospace">{{ $item['nisn'] }}</td>
                                <td>{{ $item['email'] }}</td>
                                <td>
                                    @php
                                        $tCls = collect($availableClasses)->firstWhere('id', $item['target_class_id']);
                                    @endphp
                                    <strong>{{ $tCls ? $tCls->nama_kelas : '-' }}</strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary" title="Akun dibuat dengan password acak aman; aktivasi dilakukan melalui pengaturan admin sekolah">
                                        Perlu Pengaturan Admin
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                        <x-ikon nama="pencil" /> Ubah Kelas / Kecualikan
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Tidak ada data calon siswa siap diproses yang cocok dengan saringan filter saat ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modal Edit Item Calon Siswa --}}
@foreach ($items as $item)
    <div class="modal fade" id="editItemModal{{ $item['id'] }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.intake.update-item', ['id' => $op->id, 'itemId' => $item['id']]) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Penataan Calon Siswa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Data Identitas Calon Siswa</label>
                            <div class="fw-bold">NIS: {{ $item['student_ref'] }} &bull; NISN: {{ $item['nisn'] }}</div>
                            <div class="small text-muted">{{ $item['email'] }}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Pilih Tindakan Administratif <span class="text-danger">*</span></label>
                            <select name="action" class="form-select" id="actionSelect{{ $item['id'] }}" onchange="handleActionChange({{ $item['id'] }})" required>
                                <option value="update_class">Perbarui Rombel Kelas X Tujuan</option>
                                <option value="exclude">Kecualikan dari Penerimaan (Lewati Baris Ini)</option>
                            </select>
                        </div>

                        <div class="mb-3" id="classGroup{{ $item['id'] }}">
                            <label class="form-label fw-bold small">Rombel Kelas X Tujuan <span class="text-danger">*</span></label>
                            <select name="target_class_id" class="form-select">
                                <option value="">-- Pilih Kelas X Tujuan --</option>
                                @foreach ($availableClasses as $c)
                                    <option value="{{ $c->id }}" {{ (string)$c->id === (string)$item['target_class_id'] ? 'selected' : '' }}>
                                        {{ $c->nama_kelas }} ({{ $c->jurusan_kode }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3 d-none" id="exclusionGroup{{ $item['id'] }}">
                            <label class="form-label fw-bold small">Alasan Pengecualian Administratif <span class="text-danger">*</span></label>
                            <input type="text" name="exclusion_reason" class="form-control" id="exclusionInput{{ $item['id'] }}" placeholder="contoh: Duplikasi berkas / Data pendaftaran tidak valid">
                            <div class="form-text small">
                                Baris yang dikecualikan akan dilewati saat eksekusi dan tidak akan dibuatkan akun maupun siswa di database.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Penataan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

{{-- Modal Konfirmasi Eksekusi --}}
<div class="modal fade" id="confirmExecutionModal" tabindex="-1" aria-labelledby="confirmExecutionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.intake.execute', $op->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="confirmExecutionModalLabel">Konfirmasi Penerimaan Siswa Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Apakah Anda yakin ingin mengeksekusi pendaftaran resmi siswa baru tingkat X untuk tahun ajaran <strong>{{ $op->target_year }}</strong>?
                    </p>
                    <div class="bg-light p-3 rounded mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Siswa Baru Siap Didaftarkan:</span>
                            <strong class="text-success">{{ number_format($summary['siap'], 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Baris Dikecualikan (Dilewati):</span>
                            <strong class="text-warning">{{ number_format($summary['excluded'], 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-1 mt-1">
                            <span>Total Data:</span>
                            <strong class="text-dark">{{ number_format($summary['total'], 0, ',', '.') }}</strong>
                        </div>
                    </div>
                    <div class="alert alert-info small mb-0">
                        Proses ini atomik. Sistem akan membuat entitas siswa, membership kelas X awal, serta akun login portal belajar secara bersamaan dengan password acak aman.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">Ya, Eksekusi Pendaftaran</button>
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
