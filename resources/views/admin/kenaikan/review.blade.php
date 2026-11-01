@extends('layouts.app')

@section('title', 'Pratinjau Kenaikan Kelas X–XI')

@section('content')
<div class="kenaikan-review-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">Pratinjau Kenaikan Kelas X–XI</h4>
            <div class="text-muted small">
                Operasi #{{ $op->id }} &bull; Tahun Sumber: <strong>{{ $op->source_year }}</strong>
                &bull; Tahun Tujuan: <strong>{{ $op->target_year }}</strong>
                &bull; Tgl Efektif: <strong>{{ $op->source_metadata['effective_date'] ?? '-' }}</strong>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.kenaikan.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-ikon nama="arrow-left" /> Kembali
            </a>
            <form method="POST" action="{{ route('admin.kenaikan.validate', $op->id) }}">
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
                    <div class="text-muted small fw-bold text-uppercase">Total Roster X &amp; XI</div>
                    <div class="fs-4 fw-bold text-dark">{{ number_format($summary['total'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm border-0 border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="text-success small fw-bold text-uppercase">Siswa Naik Kelas</div>
                    <div class="fs-4 fw-bold text-success">{{ number_format($summary['naik'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body p-3">
                    <div class="text-warning small fw-bold text-uppercase">Tinggal Kelas</div>
                    <div class="fs-4 fw-bold text-warning">{{ number_format($summary['tinggal_kelas'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card shadow-sm border-0 border-start {{ $summary['masalah'] > 0 ? 'border-danger' : 'border-secondary' }} border-4">
                <div class="card-body p-3">
                    <div class="{{ $summary['masalah'] > 0 ? 'text-danger' : 'text-muted' }} small fw-bold text-uppercase">Konflik / Masalah</div>
                    <div class="fs-4 fw-bold {{ $summary['masalah'] > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($summary['masalah'], 0, ',', '.') }}</div>
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
                    <h6 class="alert-heading fw-bold mb-1">Finalisasi Ditahan (Ada {{ $summary['masalah'] }} Siswa Memerlukan Tindak Lanjut)</h6>
                    <p class="small mb-0">
                        Finalisasi kenaikan kelas hanya dapat diproses apabila seluruh siswa tingkat X dan XI telah memiliki keputusan yang sah, kelas tujuan yang valid, dan tidak ada konflik antara input manual dengan berkas spreadsheet. Selesaikan data pada kelompok <strong>Konflik &amp; Masalah</strong> di bawah.
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-success mb-4 shadow-sm d-flex justify-content-between align-items-center" role="alert">
            <div class="d-flex gap-2">
                <x-ikon nama="check-circle" class="fs-5 flex-shrink-0" />
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Seluruh Roster Siap Diproses</h6>
                    <p class="small mb-0">Semua keputusan telah valid dan terikat pada snapshot database saat ini. Anda dapat melakukan finalisasi sekarang.</p>
                </div>
            </div>
            <button type="button" class="btn btn-success fw-bold px-4" data-bs-toggle="modal" data-bs-target="#confirmExecutionModal">
                <x-ikon nama="check2-all" /> Finalisasi Kenaikan Kelas
            </button>
        </div>
    @endif

    {{-- Filter & Pencarian --}}
    <div class="card mb-3 shadow-sm border-0">
        <div class="card-body p-2">
            <form method="GET" action="{{ route('admin.kenaikan.review', $op->id) }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent"><x-ikon nama="filter" /></span>
                        <input type="text" name="q" class="form-control" placeholder="Cari NIS atau Nama Siswa..." value="{{ $q }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="semua" {{ $filterStatus === 'semua' ? 'selected' : '' }}>Semua Status Keputusan</option>
                        <option value="masalah" {{ $filterStatus === 'masalah' ? 'selected' : '' }}>Ada Masalah / Konflik</option>
                        <option value="naik" {{ $filterStatus === 'naik' ? 'selected' : '' }}>Naik Kelas</option>
                        <option value="tinggal_kelas" {{ $filterStatus === 'tinggal_kelas' ? 'selected' : '' }}>Tinggal Kelas</option>
                        <option value="unverified" {{ $filterStatus === 'unverified' ? 'selected' : '' }}>Belum Terverifikasi</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="tingkat" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="semua" {{ $filterTingkat === 'semua' ? 'selected' : '' }}>Semua Tingkat Asal</option>
                        <option value="X" {{ $filterTingkat === 'X' ? 'selected' : '' }}>Tingkat X</option>
                        <option value="XI" {{ $filterTingkat === 'XI' ? 'selected' : '' }}>Tingkat XI</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Saring</button>
                    @if ($q !== '' || $filterStatus !== 'semua' || $filterTingkat !== 'semua')
                        <a href="{{ route('admin.kenaikan.review', $op->id) }}" class="btn btn-sm btn-link text-muted" title="Reset filter">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- KELOMPOK 1: MASALAH & KONFLIK --}}
    @if (! empty($grouped['masalah']))
        <div class="card mb-4 border-danger shadow-sm">
            <div class="card-header bg-danger text-white fw-bold d-flex justify-content-between align-items-center">
                <span>
                    <x-ikon nama="exclamation-octagon" /> Kelompok 1: Konflik &amp; Masalah Wajib Diselesaikan ({{ count($grouped['masalah']) }})
                </span>
                <span class="badge bg-white text-danger">Prioritas Penanganan</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Kelas Asal</th>
                                <th>Keputusan</th>
                                <th>Kelas Tujuan</th>
                                <th>Keterangan Error</th>
                                <th class="text-end">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grouped['masalah'] as $item)
                                <tr class="table-danger">
                                    <td class="font-monospace fw-bold">{{ $item['student_ref'] }}</td>
                                    <td>{{ $item['nama'] }}</td>
                                    <td><span class="badge bg-secondary">{{ $item['kelas_asal'] }}</span></td>
                                    <td>
                                        <span class="badge bg-warning text-dark text-uppercase">{{ $item['decision'] }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $targetCls = collect($targetClasses)->firstWhere('id', $item['target_class_id']);
                                        @endphp
                                        {{ $targetCls ? $targetCls->nama_kelas : '-' }}
                                    </td>
                                    <td>
                                        @foreach ($item['errors'] as $err)
                                            <span class="badge bg-danger">{{ $err }}</span>
                                        @endforeach
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                            <x-ikon nama="pencil" /> Selesaikan
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

    {{-- KELOMPOK 2: SISWA TINGGAL KELAS --}}
    @if (! empty($grouped['tinggal_kelas']))
        <div class="card mb-4 border-warning shadow-sm">
            <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center">
                <span>
                    <x-ikon nama="dash-circle" /> Kelompok 2: Pengecualian Siswa Tinggal Kelas ({{ count($grouped['tinggal_kelas']) }})
                </span>
                <span class="badge bg-dark text-white">Mengulang Tingkat</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Kelas Asal</th>
                                <th>Tingkat Mengulang</th>
                                <th>Kelas Tujuan</th>
                                <th class="text-end">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grouped['tinggal_kelas'] as $item)
                                <tr>
                                    <td class="font-monospace fw-bold">{{ $item['student_ref'] }}</td>
                                    <td>{{ $item['nama'] }}</td>
                                    <td><span class="badge bg-secondary">{{ $item['kelas_asal'] }}</span></td>
                                    <td><span class="badge bg-warning text-dark">Tingkat {{ $item['tingkat_asal'] }}</span></td>
                                    <td>
                                        @php
                                            $targetCls = collect($targetClasses)->firstWhere('id', $item['target_class_id']);
                                        @endphp
                                        <strong>{{ $targetCls ? $targetCls->nama_kelas : '-' }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                            <x-ikon nama="pencil" /> Ubah
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

    {{-- KELOMPOK 3: SISWA NAIK KELAS --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-header bg-transparent fw-bold d-flex justify-content-between align-items-center">
            <span>
                <x-ikon nama="check-circle" /> Kelompok 3: Siswa Naik Kelas ({{ count($grouped['naik']) }})
            </span>
            <span class="badge bg-success">Promosi Tingkat</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas Asal</th>
                            <th>Tingkat Tujuan</th>
                            <th>Kelas Tujuan</th>
                            <th class="text-end">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grouped['naik'] as $item)
                            <tr>
                                <td class="font-monospace fw-bold">{{ $item['student_ref'] }}</td>
                                <td>{{ $item['nama'] }}</td>
                                <td><span class="badge bg-secondary">{{ $item['kelas_asal'] }}</span></td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        {{ $item['tingkat_asal'] === 'X' ? 'Tingkat XI' : 'Tingkat XII' }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $targetCls = collect($targetClasses)->firstWhere('id', $item['target_class_id']);
                                    @endphp
                                    <strong>{{ $targetCls ? $targetCls->nama_kelas : '-' }}</strong>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item['id'] }}">
                                        <x-ikon nama="pencil" /> Ubah
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Tidak ada data siswa naik kelas yang cocok dengan filter saat ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modal Edit Item Siswa --}}
@foreach ($items as $item)
    <div class="modal fade" id="editItemModal{{ $item['id'] }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.kenaikan.update-item', ['id' => $op->id, 'itemId' => $item['id']]) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Koreksi Keputusan Siswa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Data Siswa</label>
                            <div class="fw-bold">{{ $item['nama'] }} (NIS: {{ $item['student_ref'] }})</div>
                            <div class="small text-muted">Kelas Asal: {{ $item['kelas_asal'] }} (Tingkat {{ $item['tingkat_asal'] }})</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Keputusan Kenaikan <span class="text-danger">*</span></label>
                            <select name="decision" class="form-select" id="decisionSelect{{ $item['id'] }}" onchange="handleDecisionChange({{ $item['id'] }})" required>
                                <option value="naik" {{ $item['decision'] === 'naik' ? 'selected' : '' }}>NAIK KELAS</option>
                                <option value="tinggal_kelas" {{ $item['decision'] === 'tinggal_kelas' ? 'selected' : '' }}>TINGGAL KELAS (Mengulang)</option>
                                <option value="unverified" {{ $item['decision'] === 'unverified' ? 'selected' : '' }}>BELUM TERVERIFIKASI</option>
                            </select>
                        </div>

                        <div class="mb-3" id="targetClassGroup{{ $item['id'] }}">
                            <label class="form-label fw-bold small">Kelas Tujuan di Tahun {{ $op->target_year }} <span class="text-danger">*</span></label>
                            <select name="target_class_id" class="form-select" id="targetClassSelect{{ $item['id'] }}">
                                <option value="">-- Pilih Kelas Tujuan --</option>
                                @foreach ($targetClasses as $tClass)
                                    <option value="{{ $tClass->id }}" {{ (string)$tClass->id === (string)$item['target_class_id'] ? 'selected' : '' }}>
                                        {{ $tClass->nama_kelas }} (Tingkat {{ $tClass->tingkat }} - {{ $tClass->jurusan_kode }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text small">
                                Anda dapat memilih rombel paralel yang berbeda selama tingkat dan jurusan tetap sesuai.
                            </div>
                        </div>

                        <div class="mb-3" id="alasanGroup{{ $item['id'] }}">
                            <label class="form-label fw-bold small">Alasan Administratif <span class="text-danger" id="alasanRequired{{ $item['id'] }}">*</span></label>
                            <input type="text" name="alasan" class="form-control" id="alasanInput{{ $item['id'] }}" placeholder="Wajib diisi jika tinggal kelas (contoh: Kehadiran < 75%)" value="{{ old('alasan', $item['alasan'] ?? '') }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Keputusan</button>
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
            <form method="POST" action="{{ route('admin.kenaikan.execute', $op->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="confirmExecutionModalLabel">Konfirmasi Finalisasi Kenaikan Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Apakah Anda yakin ingin mengeksekusi kenaikan kelas untuk tahun ajaran <strong>{{ $op->source_year }}</strong> ke <strong>{{ $op->target_year }}</strong>?
                    </p>
                    <div class="bg-light p-3 rounded mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Siswa Naik Kelas:</span>
                            <strong class="text-success">{{ number_format($summary['naik'], 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Siswa Tinggal Kelas:</span>
                            <strong class="text-warning">{{ number_format($summary['tinggal_kelas'], 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-1 mt-1">
                            <span>Total Siswa Diproses:</span>
                            <strong class="text-dark">{{ number_format($summary['total'], 0, ',', '.') }}</strong>
                        </div>
                    </div>
                    <div class="alert alert-info small mb-0">
                        Eksekusi ini atomik dan akan memperbarui kelas siswa, menutup keanggotaan lama pada tanggal akhir tahun ajaran sumber, serta membuka keanggotaan baru pada tanggal mulai tahun ajaran tujuan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">Ya, Eksekusi Sekarang</button>
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
    var alasanReq = document.getElementById('alasanRequired' + id);

    if (select.value === 'tinggal_kelas') {
        alasanReq.style.display = 'inline';
        alasanInput.setAttribute('required', 'required');
    } else {
        alasanReq.style.display = 'none';
        alasanInput.removeAttribute('required');
    }
}
</script>
@endsection
