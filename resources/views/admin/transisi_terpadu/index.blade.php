@extends('layouts.app')

@section('title', 'Transisi Akademik Terpadu')

@section('content')
<div class="transisi-bento-view space-y-5">
    <style>
        .transisi-bento-view .page-head {
            margin-top: 8px !important;
            margin-bottom: 24px !important;
        }
        .transisi-bento-view .page-head__title {
            font-size: 22px !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            color: var(--text, #131313) !important;
            margin-bottom: 6px !important;
        }
        .transisi-bento-view .page-head__sub {
            font-size: 14px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
            margin: 0 !important;
        }
        .transisi-bento-view .card-hifi__title {
            font-size: 18.5px !important;
            font-weight: 700 !important;
            line-height: 1.3 !important;
            color: #111827 !important;
        }
        .transisi-bento-view .card-hifi__meta {
            font-size: 14px !important;
            font-weight: 500 !important;
            line-height: 1.5 !important;
            color: #4b5563 !important;
        }
        .transisi-bento-view .field__label {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            color: #1f2937 !important;
        }
        .transisi-bento-view .field__hint {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.4 !important;
            color: #4b5563 !important;
        }
        .transisi-bento-view .stage-card {
            border-radius: var(--radius-card, 12px);
            background: #fafbfa;
            border: 2px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            user-select: none;
        }
        .transisi-bento-view .stage-card:hover {
            border-color: var(--primary, #3b5d32);
            background: #ffffff;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        }
        .transisi-bento-view .stage-card.is-selected {
            border-color: var(--primary, #3b5d32);
            background: #fdfefe;
            box-shadow: 0 4px 16px rgba(59, 93, 50, 0.1);
        }
        .transisi-bento-view .stage-card__title {
            font-size: 17px !important;
            font-weight: 700 !important;
            line-height: 1.3 !important;
            color: #111827 !important;
        }
        .transisi-bento-view .stage-card__desc {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #374151 !important;
        }
        .transisi-bento-view .stage-card__check {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            color: #111827 !important;
        }
        .transisi-bento-view .kpi-card__num {
            font-size: 24px !important;
            font-weight: 700 !important;
            line-height: 1.2 !important;
            color: var(--text, #131313) !important;
        }
        .transisi-bento-view .kpi-card__label {
            font-size: 13px !important;
            font-weight: 500 !important;
            line-height: 1.3 !important;
            color: var(--muted, #626262) !important;
        }
        .transisi-bento-view .btn-hifi {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .transisi-bento-view .chip {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
        }
        .transisi-bento-view .breadcrumb-nav {
            font-size: 13px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        .transisi-bento-view .breadcrumb-nav a {
            color: var(--muted, #626262) !important;
        }
        .transisi-bento-view .breadcrumb-nav a:hover {
            color: var(--primary, #3b5d32) !important;
        }
        .transisi-bento-view .tbl th {
            font-size: 12.5px !important;
            font-weight: 600 !important;
            line-height: 1.4 !important;
            padding: 10px 14px !important;
        }
        .transisi-bento-view .tbl td {
            font-size: 13.5px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            padding: 10px 14px !important;
        }
        .transisi-bento-view .tbl td.is-strong {
            font-weight: 600 !important;
        }
        .transisi-bento-view .select-hifi {
            font-size: 14px !important;
            line-height: 1.5 !important;
        }
    </style>

    {{-- Breadcrumb Navigasi Horizontal --}}
    <nav class="breadcrumb-nav mb-3" aria-label="Breadcrumb">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
            <span class="text-muted">&rsaquo;</span>
            <a href="{{ route('admin.tahun-ajaran.index') }}" class="text-decoration-none">Tahun Ajaran</a>
            <span class="text-muted">&rsaquo;</span>
            <span class="text-dark fw-semibold" aria-current="page">Transisi Terpadu</span>
        </div>
    </nav>

    {{-- Page Header --}}
    <x-page-head
        title="Transisi Terpadu"
        sub="Pusat koordinasi perpindahan tahun ajaran sekolah secara bertahap dan teratur." />

    {{-- Status Flash & Error Messages --}}
    @if (session('status'))
        <div class="card-hifi p-3 border-l-4 border-primary bg-primary-soft d-flex align-items-center gap-2 mb-4">
            <x-ikon nama="check-circle" />
            <span class="fw-semibold text-primary" style="font-size: 14px;">{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="card-hifi p-4 border-l-4 border-danger mb-4" role="alert">
            <div class="d-flex align-items-center gap-2 text-danger fw-bold mb-2" style="font-size: 14px;">
                <x-ikon nama="exclamation-triangle" />
                <span>Terjadi kesalahan pada formulir:</span>
            </div>
            <ul class="mb-0 ps-3 text-danger" style="font-size: 13.5px; line-height: 1.5;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.transisi-terpadu.store') }}" id="formTransisiTerpadu">
        @csrf

        {{-- Bento Baris 1: Pemilihan Periode + 4 KPI Cards --}}
        <div style="display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 16px; align-items: stretch;" class="mb-4">
            {{-- Kartu Utama Pemilihan Periode --}}
            <div class="card-hifi d-flex flex-column justify-content-between p-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <x-ikon nama="calendar3" />
                        <h3 class="card-hifi__title mb-0">Setup Sesi Transisi Terpadu Baru</h3>
                    </div>
                    <p class="field__hint mb-3">
                        Tentukan periode tahun ajaran asal dan sasaran untuk memulai koordinasi transisi baru.
                    </p>

                    <div class="form-grid form-grid--2 mb-2">
                        <div class="field">
                            <label class="field__label" for="tahun_asal">
                                Tahun Ajaran Asal<span class="field__req"> *</span>
                            </label>
                            <select id="tahun_asal" name="tahun_asal" class="select-hifi" onchange="window.location.href = '{{ route('admin.transisi-terpadu.index') }}?tahun_asal=' + encodeURIComponent(this.value)" required>
                                @foreach ($tahunList as $t)
                                    <option value="{{ $t->kode }}" @selected($t->kode === $tahunAsal)>
                                        {{ $t->kode }} {{ $t->aktif ? '(Aktif Saat Ini)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="field__hint">Periode yang datanya akan dipromosikan atau diluluskan.</span>
                        </div>

                        <div class="field">
                            <label class="field__label" for="tahun_tujuan">
                                Tahun Ajaran Tujuan<span class="field__req"> *</span>
                            </label>
                            <select id="tahun_tujuan" name="tahun_tujuan" class="select-hifi" required>
                                @if ($tahunTujuanList->isEmpty())
                                    <option value="" disabled selected>Belum ada tahun tujuan nonaktif mendatang</option>
                                @else
                                    @foreach ($tahunTujuanList as $t)
                                        <option value="{{ $t->kode }}" @selected($t->kode === $tahunTujuan)>
                                            {{ $t->kode }} (Mendatang)
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <span class="field__hint">Wajib tahun nonaktif mendatang (belum aktif).</span>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-top d-flex align-items-center gap-2 text-muted" style="font-size: 13px; line-height: 1.4;">
                    <x-ikon nama="shield-check" />
                    <span>Perpindahan data siswa dilakukan secara aman per rombel target.</span>
                </div>
            </div>

            {{-- 4 KPI Cards dalam Grid 2x2 --}}
            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; align-content: stretch;">
                {{-- KPI 1: Total Siswa --}}
                <div class="card-hifi p-3 d-flex align-items-center gap-3">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                        <x-ikon nama="people" />
                    </div>
                    <div>
                        <div class="kpi-card__num">{{ number_format($kpiSiswa['total'] ?? 0, 0, ',', '.') }}</div>
                        <div class="kpi-card__label">Total Siswa</div>
                    </div>
                </div>

                {{-- KPI 2: Siswa Kelas X --}}
                <div class="card-hifi p-3 d-flex align-items-center gap-3">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                        <x-ikon nama="person-check" />
                    </div>
                    <div>
                        <div class="kpi-card__num">{{ number_format($kpiSiswa['X'] ?? 0, 0, ',', '.') }}</div>
                        <div class="kpi-card__label">Siswa Kelas X</div>
                    </div>
                </div>

                {{-- KPI 3: Siswa Kelas XI --}}
                <div class="card-hifi p-3 d-flex align-items-center gap-3">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                        <x-ikon nama="person-check" />
                    </div>
                    <div>
                        <div class="kpi-card__num">{{ number_format($kpiSiswa['XI'] ?? 0, 0, ',', '.') }}</div>
                        <div class="kpi-card__label">Siswa Kelas XI</div>
                    </div>
                </div>

                {{-- KPI 4: Calon Lulusan (Kelas XII) --}}
                <div class="card-hifi p-3 d-flex align-items-center gap-3">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(59, 93, 50, 0.12); color: var(--primary, #3b5d32); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px;">
                        <x-ikon nama="mortarboard" />
                    </div>
                    <div>
                        <div class="kpi-card__num">{{ number_format($kpiSiswa['XII'] ?? 0, 0, ',', '.') }}</div>
                        <div class="kpi-card__label">Calon Lulusan (XII)</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Container Besar Pemilihan Tahapan Koordinasi --}}
        <div class="card-hifi mb-4">
            {{-- Header Container --}}
            <div class="card-hifi__head py-3 px-4">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <x-ikon nama="diagram-3" />
                    <h3 class="card-hifi__title mb-0" style="font-size: 18px !important;">Pilih Tahapan yang Ingin Dijalankan</h3>
                </div>
                <span class="card-hifi__meta" style="font-size: 13.5px !important; color: var(--text-2, #474747) !important;">
                    Pilih 1 tahapan saja (alur mandiri) atau centang 2–3 tahapan sekaligus untuk dijalankan berurutan secara otomatis.
                </span>
            </div>

            {{-- Body Container: Tiga Kartu Tahapan Sejajar --}}
            <div class="card-hifi__body p-4 pt-3">
                <div class="grid-row grid-row--3" style="gap: 16px;">
                    {{-- Tahap 1: Kelulusan --}}
                    <label class="stage-card is-selected p-4 d-flex flex-column justify-content-between m-0" for="stageGrad" id="cardGrad">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="chip chip--neutral fw-bold" style="font-size: 12px;">Tahap 1</span>
                                <div class="text-primary" style="font-size: 22px;"><x-ikon nama="mortarboard" /></div>
                            </div>
                            <h4 class="stage-card__title mb-2">Kelulusan Siswa Kelas XII</h4>
                            <p class="stage-card__desc mb-3">
                                Penetapan status kelulusan siswa tingkat akhir, penomoran dokumen resmi, serta pemindahan otomatis akun ke arsip alumni.
                            </p>
                        </div>

                        <div class="pt-3 border-top">
                            <div class="d-flex align-items-center gap-2">
                                <input type="checkbox" name="stages[]" value="graduation" id="stageGrad" checked style="width: 18px; height: 18px; accent-color: var(--primary, #3b5d32);" onchange="updateStageCard(this, 'cardGrad')">
                                <span class="fw-semibold text-dark" style="font-size: 14px;">Jalankan Kelulusan XII</span>
                            </div>
                        </div>
                    </label>

                    {{-- Tahap 2: Kenaikan --}}
                    <label class="stage-card is-selected p-4 d-flex flex-column justify-content-between m-0" for="stageProm" id="cardProm">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="chip chip--neutral fw-bold" style="font-size: 12px;">Tahap 2</span>
                                <div class="text-primary" style="font-size: 22px;"><x-ikon nama="arrow-up-circle" /></div>
                            </div>
                            <h4 class="stage-card__title mb-2">Kenaikan Kelas X &amp; XI</h4>
                            <p class="stage-card__desc mb-3">
                                Kenaikan tingkat siswa dari kelas X ke XI dan XI ke XII, alokasi rombel baru, serta penanganan siswa tinggal kelas.
                            </p>
                        </div>

                        <div class="pt-3 border-top">
                            <div class="d-flex align-items-center gap-2">
                                <input type="checkbox" name="stages[]" value="promotion" id="stageProm" checked style="width: 18px; height: 18px; accent-color: var(--primary, #3b5d32);" onchange="updateStageCard(this, 'cardProm')">
                                <span class="fw-semibold text-dark" style="font-size: 14px;">Jalankan Kenaikan Kelas</span>
                            </div>
                        </div>
                    </label>

                    {{-- Tahap 3: Penerimaan --}}
                    <label class="stage-card is-selected p-4 d-flex flex-column justify-content-between m-0" for="stageIntake" id="cardIntake">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="chip chip--neutral fw-bold" style="font-size: 12px;">Tahap 3</span>
                                <div class="text-primary" style="font-size: 22px;"><x-ikon nama="person-plus" /></div>
                            </div>
                            <h4 class="stage-card__title mb-2">Penerimaan Siswa Baru</h4>
                            <p class="stage-card__desc mb-3">
                                Pendaftaran peserta didik baru masuk kelas X, pembagian rombel awal, validasi identitas, dan aktivasi akun siswa.
                            </p>
                        </div>

                        <div class="pt-3 border-top">
                            <div class="d-flex align-items-center gap-2">
                                <input type="checkbox" name="stages[]" value="intake" id="stageIntake" checked style="width: 18px; height: 18px; accent-color: var(--primary, #3b5d32);" onchange="updateStageCard(this, 'cardIntake')">
                                <span class="fw-semibold text-dark" style="font-size: 14px;">Jalankan Siswa Baru</span>
                            </div>
                        </div>
                    </label>
                </div>

                {{-- Action Bar Bawah --}}
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 pt-3 mt-4 border-top">
                    <div class="d-flex align-items-center gap-2 text-muted" style="font-size: 13.5px;">
                        <x-ikon nama="info-circle" />
                        <span id="labelRangkumanTahap">
                            3 tahapan terpilih: sistem akan memandu secara otomatis dari awal sampai akhir.
                        </span>
                    </div>
                    <button type="submit" form="formTransisiTerpadu" id="btnSubmitTransisi" class="btn-hifi" style="padding: 10px 22px; font-size: 14.5px;" {{ $tahunTujuanList->isEmpty() ? 'disabled' : '' }}>
                        <x-ikon nama="play-circle" /> Lanjutkan ke Alur Transisi
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
        function updateStageCard(checkbox, cardId) {
            const card = document.getElementById(cardId);
            if (checkbox.checked) {
                card.classList.add('is-selected');
            } else {
                card.classList.remove('is-selected');
            }
            updateSummaryLabel();
        }

        function updateSummaryLabel() {
            const checkedBoxes = document.querySelectorAll('input[name="stages[]"]:checked');
            const label = document.getElementById('labelRangkumanTahap');
            const btn = document.getElementById('btnSubmitTransisi');

            if (checkedBoxes.length === 0) {
                label.innerHTML = '<span class="text-danger fw-semibold">Silakan pilih minimal 1 tahapan untuk melanjutkan.</span>';
                if (btn) btn.disabled = true;
            } else if (checkedBoxes.length === 1) {
                label.innerHTML = '<span class="text-primary fw-semibold">1 tahapan terpilih:</span> Anda akan langsung diarahkan ke form modul yang dipilih.';
                if (btn) btn.disabled = false;
            } else {
                label.innerHTML = `<span class="text-primary fw-semibold">${checkedBoxes.length} tahapan terpilih:</span> sistem akan mengoordinasikan alur secara otomatis berurutan.`;
                if (btn) btn.disabled = false;
            }
        }
    </script>

    {{-- Daftar Sesi Transisi Terpadu --}}
    <x-card title="Daftar Sesi Transisi Terpadu" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 100px;">ID Sesi</th>
                        <th>Periode Transisi (Sumber &rarr; Sasaran)</th>
                        <th>Pembuat Sesi</th>
                        <th>Waktu Pembuatan</th>
                        <th class="is-num" style="width: 200px;">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $ses)
                        <tr>
                            <td class="is-strong">#{{ $ses->id }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="chip chip--neutral">{{ $ses->source_year ?? '—' }}</span>
                                    <x-ikon nama="arrow-right" />
                                    <span class="chip chip--solid-lembut">{{ $ses->target_year ?? '—' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar avatar--xs">{{ strtoupper(substr($ses->actor_name ?? 'A', 0, 1)) }}</span>
                                    <span class="fw-semibold">{{ $ses->actor_name ?? ('User #' . $ses->actor_id) }}</span>
                                </div>
                            </td>
                            <td class="is-muted is-nowrap">
                                {{ \Illuminate\Support\Carbon::parse($ses->created_at)->format('d/m/Y H:i') }}
                            </td>
                            <td class="is-num tbl__aksi">
                                <a href="{{ route('admin.transisi-terpadu.show', $ses->id) }}" class="btn-hifi btn-hifi--sm btn-hifi--ghost">
                                    <x-ikon nama="speedometer2" /> Buka Dashboard Sesi
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state py-5 text-center text-muted">
                                Belum ada sesi transisi terpadu yang dibuat. Silakan gunakan formulir di atas untuk memulai sesi baru.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sessions->hasPages())
            <x-slot:foot>
                <div class="d-flex justify-content-between align-items-center w-100">
                    <span class="small text-muted">Menampilkan {{ $sessions->count() }} sesi</span>
                    <div>{{ $sessions->links() }}</div>
                </div>
            </x-slot:foot>
        @endif
    </x-card>
</div>
@endsection
