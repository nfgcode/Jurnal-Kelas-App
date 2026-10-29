@extends('layouts.app')

@section('title', 'Sistem & Log')

@push('styles')
<style>
    .cek-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; }
    .cek { border: 1px solid var(--n-200); border-radius: var(--radius-card); background: var(--n-100); padding: 12px 14px; }
    .cek__atas { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 4px; }
    .cek__nama { font-size: 12px; font-weight: 600; }
    .cek__nilai { font-size: 15px; font-weight: 700; letter-spacing: -.01em; margin-bottom: 4px; }
    .cek__detail { font-size: 10.5px; color: var(--n-600); line-height: 1.5; }

    .log-entri { border-top: 1px solid var(--n-200); padding: 9px 0; }
    .log-entri:first-child { border-top: 0; }
    .log-entri__atas { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .log-entri__waktu { font-size: 10.5px; color: var(--n-600); font-variant-numeric: tabular-nums; }
    .log-entri__pesan { font-size: 11.5px; color: var(--n-900); margin-top: 3px; word-break: break-word; }
    .log-entri pre, .audit-pre {
        margin: 8px 0 0; padding: 9px 11px; background: var(--n-200);
        border-radius: 8px; font-size: 10px; line-height: 1.5;
        max-height: 260px; overflow: auto; white-space: pre-wrap; word-break: break-word;
    }
    .log-entri summary, .audit-summary { font-size: 10.5px; color: var(--p-400); cursor: pointer; margin-top: 4px; }
</style>
@endpush

@section('content')
    @php
        $tone = ['ok' => 'green', 'warn' => 'yellow', 'gagal' => 'red', 'n/a' => 'neutral'];
        $label = ['ok' => 'OK', 'warn' => 'Perhatian', 'gagal' => 'Gagal', 'n/a' => 'N/A'];
        $adaGagal = collect($cek)->contains(fn ($c) => $c['status'] === 'gagal');
    @endphp

    <x-page-head
        title="Sistem & Log"
        :sub="$adaGagal ? 'Ada komponen yang bermasalah — periksa kartu berwarna merah' : 'Semua komponen utama berjalan normal'">
        <x-chip :tone="$adaGagal ? 'red' : 'green'" :label="$adaGagal ? 'Perlu tindakan' : 'Sehat'" />
    </x-page-head>

    {{-- 1. Status komponen --}}
    <x-card title="Status Komponen">
        <x-slot:actions>
            <span class="card-hifi__meta">diperiksa {{ now()->translatedFormat('d/m/Y H:i') }}</span>
        </x-slot:actions>

        <div class="cek-grid">
            @foreach ($cek as $item)
                <div class="cek">
                    <div class="cek__atas">
                        <span class="cek__nama">{{ $item['nama'] }}</span>
                        <x-chip :tone="$tone[$item['status']] ?? 'neutral'" :label="$label[$item['status']] ?? $item['status']" />
                    </div>
                    <div class="cek__nilai">{{ $item['nilai'] }}</div>
                    <div class="cek__detail">{{ $item['detail'] }}</div>
                </div>
            @endforeach
        </div>
    </x-card>

    {{-- 2. Pengingat Administrasi Data Tahunan --}}
    <x-card title="Pengingat Administrasi Data" class="mt-3">
        <x-slot:actions>
            <x-chip :tone="$pengingat['tone']" :label="$pengingat['label']" />
        </x-slot:actions>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h6 class="fw-bold mb-1">{{ $pengingat['judul'] }}</h6>
                <p class="text-muted small mb-0">{{ $pengingat['deskripsi'] }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap flex-shrink-0">
                @foreach ($pengingat['tautan'] as $t)
                    <a href="{{ $t['url'] }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm">
                        <x-ikon :nama="$t['ikon']" /> {{ $t['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </x-card>

    {{-- 3. Tab Navigasi Log: Log Sistem vs Aktivitas Admin --}}
    <ul class="nav nav-tabs my-4" id="sistemNavTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ $tabAktif === 'log-sistem' ? 'active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'log-sistem']) }}">
                <x-ikon nama="activity" /> Log Sistem &amp; Error
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ $tabAktif === 'aktivitas-admin' ? 'active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['tab' => 'aktivitas-admin']) }}">
                <x-ikon nama="shield-lock" /> Aktivitas Admin ({{ number_format($auditLogs->total(), 0, ',', '.') }})
            </a>
        </li>
    </ul>

    @if ($tabAktif === 'aktivitas-admin')
        {{-- ========================================== --}}
        {{-- TAB AKTIVITAS ADMIN (AUDIT LOG TERSTRUKTUR) --}}
        {{-- ========================================== --}}
        <x-card title="Log Aktivitas & Audit Administratif" flush>
            <x-slot:actions>
                <span class="card-hifi__meta">
                    Total {{ number_format($auditLogs->total(), 0, ',', '.') }} rekaman audit
                </span>
            </x-slot:actions>

            {{-- Filter Bar Audit --}}
            <div class="card-hifi__body border-bottom" style="padding-bottom: 14px">
                <form class="filter-bar" method="GET">
                    <input type="hidden" name="tab" value="aktivitas-admin">

                    <label class="filter-bar__search">
                        <x-ikon nama="search" />
                        <input class="input-hifi" type="search" name="audit_q" value="{{ $filter['audit_q'] ?? '' }}"
                               placeholder="Cari deskripsi, entitas...">
                    </label>

                    @if ($pelaksanaAuditTersedia->isNotEmpty())
                        <select class="select-hifi" name="audit_user" style="width: 160px" onchange="this.form.submit()">
                            <option value="">Semua Pelaksana</option>
                            @foreach ($pelaksanaAuditTersedia as $p)
                                <option value="{{ $p }}" @selected(($filter['audit_user'] ?? null) === $p)>
                                    {{ $p }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <select class="select-hifi" name="audit_aksi" style="width: 170px" onchange="this.form.submit()">
                        <option value="">Semua Aksi</option>
                        @foreach ($aksiAuditTersedia as $aksi)
                            <option value="{{ $aksi }}" @selected(($filter['audit_aksi'] ?? null) === $aksi)>
                                {{ $aksi }}
                            </option>
                        @endforeach
                    </select>

                    <select class="select-hifi" name="audit_status" style="width: 140px" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="berhasil" @selected(($filter['audit_status'] ?? null) === 'berhasil')>Berhasil</option>
                        <option value="gagal" @selected(($filter['audit_status'] ?? null) === 'gagal')>Gagal</option>
                        <option value="diproses" @selected(($filter['audit_status'] ?? null) === 'diproses')>Diproses</option>
                    </select>

                    <input class="input-hifi" type="date" name="audit_dari" value="{{ $filter['audit_dari'] ?? '' }}"
                           title="Dari Tanggal" style="width: 135px" onchange="this.form.submit()">
                    <span class="text-muted small">s/d</span>
                    <input class="input-hifi" type="date" name="audit_sampai" value="{{ $filter['audit_sampai'] ?? '' }}"
                           title="Sampai Tanggal" style="width: 135px" onchange="this.form.submit()">

                    @if ($filter['audit_q'] ?? $filter['audit_user'] ?? $filter['audit_aksi'] ?? $filter['audit_status'] ?? $filter['audit_dari'] ?? $filter['audit_sampai'] ?? false)
                        <a href="{{ route('admin.sistem.index', ['tab' => 'aktivitas-admin']) }}" class="btn-hifi btn-hifi--ghost btn-hifi--sm">
                            Reset
                        </a>
                    @endif
                </form>
            </div>

            {{-- Tabel Audit --}}
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pelaksana</th>
                            <th>Aksi</th>
                            <th>Entitas</th>
                            <th class="is-num">Status</th>
                            <th>Deskripsi Operasi</th>
                            <th>Rincian Metadata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($auditLogs as $logItem)
                            @php
                                $statusTone = match ($logItem->status) {
                                    'berhasil' => 'green',
                                    'gagal' => 'red',
                                    'diproses' => 'yellow',
                                    default => 'neutral',
                                };
                                $sanitized = $logItem->metadata_sanitasi;
                            @endphp
                            <tr>
                                <td class="is-muted is-nowrap small">
                                    {{ $logItem->created_at ? $logItem->created_at->format('d/m/Y H:i:s') : '—' }}
                                </td>
                                <td>
                                    <span class="is-strong">{{ $logItem->nama_user ?? 'Sistem' }}</span>
                                    <span class="is-muted small d-block">{{ ucfirst($logItem->role_user ?? 'admin') }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-monospace" style="font-size: 10.5px">
                                        {{ $logItem->aksi }}
                                    </span>
                                </td>
                                <td class="is-muted small">
                                    <span class="text-dark fw-semibold">{{ $logItem->tipe_entitas }}</span>
                                    @if ($logItem->entitas_id)
                                        <br><span class="font-monospace text-secondary" style="font-size: 10px">{{ Str::limit($logItem->entitas_id, 24) }}</span>
                                    @endif
                                </td>
                                <td class="is-num">
                                    <x-chip :tone="$statusTone" :label="ucfirst($logItem->status)" />
                                </td>
                                <td class="small" style="max-width: 280px; word-break: break-word">
                                    {{ $logItem->deskripsi }}
                                </td>
                                <td class="small">
                                    @if (! empty($sanitized))
                                        <details class="audit-details">
                                            <summary class="audit-summary">Metadata ({{ count($sanitized) }})</summary>
                                            <pre class="audit-pre">{{ json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </details>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="empty-state">Belum ada catatan aktivitas admin yang sesuai dengan filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($auditLogs->hasPages())
                <x-slot:foot>
                    <span>
                        Menampilkan {{ $auditLogs->firstItem() ?? 0 }}–{{ $auditLogs->lastItem() ?? 0 }}
                        dari {{ number_format($auditLogs->total(), 0, ',', '.') }} aktivitas
                    </span>
                    <x-pager :paginator="$auditLogs" />
                </x-slot:foot>
            @endif
        </x-card>
    @else
        {{-- ========================================== --}}
        {{-- TAB LOG SISTEM & ERROR (DEFAULT)           --}}
        {{-- ========================================== --}}

        {{-- Laporan dari pengguna --}}
        <x-card title="Laporan Error dari Pengguna" flush>
        <x-slot:actions>
            <span class="card-hifi__meta">
                {{ $jumlahBaru }} baru · {{ number_format($laporan->total(), 0, ',', '.') }} total
            </span>
        </x-slot:actions>

        <div class="card-hifi__body" style="padding-bottom: 0">
            <form class="filter-bar" method="GET">
                @if ($filter['level'] ?? null)
                    <input type="hidden" name="level" value="{{ $filter['level'] }}">
                @endif
                <select class="select-hifi" name="status" style="width: 170px" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    @foreach (\App\Models\LaporanError::STATUS as $s)
                        <option value="{{ $s }}" @selected(($filter['status'] ?? null) === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <span class="filter-bar__note">Laporan dikirim guru/siswa dari halaman error</span>
            </form>
        </div>

        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pelapor</th>
                        <th>Ref</th>
                        <th>Cerita Pengguna</th>
                        <th>Detail Teknis</th>
                        <th class="is-num">Kejadian</th>
                        <th class="is-num">Status</th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($laporan as $item)
                        @php $chip = $item->statusChip(); @endphp
                        <tr>
                            <td class="is-muted is-nowrap">{{ $item->created_at->format('d/m H:i') }}</td>
                            <td>
                                <span class="is-strong">{{ $item->pelapor?->nama ?? '—' }}</span>
                                <span class="is-muted">{{ $item->pelapor ? '· '.ucfirst($item->pelapor->role) : '' }}</span>
                            </td>
                            <td class="is-muted" style="font-family: ui-monospace, monospace; font-size: 10.5px">{{ $item->ref }}</td>
                            <td class="is-muted">{{ $item->pesan ? Str::limit($item->pesan, 70) : '—' }}</td>
                            <td class="is-muted" style="font-size: 10.5px">
                                {{ Str::limit($item->exception_pesan ?? '—', 60) }}
                                @if ($item->exception_file)
                                    <br><span style="opacity: .75">
                                        {{ Str::afterLast($item->exception_file, '/') }}:{{ $item->exception_line }}
                                    </span>
                                @endif
                                @if ($item->url)
                                    <br><span style="opacity: .75">{{ Str::limit(Str::after($item->url, config('app.url')), 44) }}</span>
                                @endif
                            </td>
                            <td class="is-num">{{ $item->jumlah }}×</td>
                            <td class="is-num"><x-chip :tone="$chip['tone']" :label="$chip['label']" /></td>
                            <td class="is-num">
                                <form method="POST" action="{{ route('admin.sistem.laporan.status', $item) }}"
                                      class="d-inline-flex gap-1">
                                    @csrf
                                    <select class="select-hifi select-hifi--sm" name="status"
                                            style="width: 108px" onchange="this.form.submit()">
                                        @foreach (\App\Models\LaporanError::STATUS as $s)
                                            <option value="{{ $s }}" @selected($item->status === $s)>{{ ucfirst($s) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Belum ada laporan dari pengguna.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $laporan->firstItem() ?? 0 }}–{{ $laporan->lastItem() ?? 0 }}
                dari {{ number_format($laporan->total(), 0, ',', '.') }} laporan
            </span>
            <x-pager :paginator="$laporan" />
        </x-slot:foot>
    </x-card>

    {{-- 3. Pengumuman --}}
    <div class="grid-row grid-row--2">
        <x-card title="Tayangkan Pengumuman">
            <p class="field__hint mb-2">
                Pesan ini muncul sebagai banner di halaman guru dan siswa — untuk memberi tahu
                pemeliharaan atau gangguan tanpa mematikan aplikasi.
            </p>

            <form method="POST" action="{{ route('admin.sistem.pengumuman.simpan') }}" class="form-grid">
                @csrf
                <x-field label="Pesan" name="pesan" required>
                    <textarea class="input-hifi" name="pesan" maxlength="500" required
                              placeholder="Contoh: Server sekolah dimatikan pukul 17.00 untuk pemeliharaan."></textarea>
                </x-field>

                <div class="form-grid form-grid--2">
                    <x-field label="Jenis" name="tipe" required>
                        <select class="select-hifi" name="tipe" required>
                            @foreach (['info' => 'Info', 'peringatan' => 'Peringatan', 'maintenance' => 'Pemeliharaan'] as $v => $l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Berakhir (opsional)" name="selesai"
                             hint="Kosongkan bila ingin dimatikan manual.">
                        <input class="input-hifi" type="datetime-local" name="selesai">
                    </x-field>
                </div>

                <div class="d-flex justify-content-end">
                    <button class="btn-hifi" type="submit">Tayangkan</button>
                </div>
            </form>
        </x-card>

        <x-card title="Riwayat Pengumuman" flush>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr><th>Pesan</th><th>Jenis</th><th>Berakhir</th><th class="is-num">Status</th><th class="is-num">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($pengumuman as $item)
                            <tr>
                                <td>{{ Str::limit($item->pesan, 52) }}</td>
                                <td class="is-muted">{{ ucfirst($item->tipe) }}</td>
                                <td class="is-muted is-nowrap">{{ $item->selesai?->format('d/m H:i') ?? '—' }}</td>
                                <td class="is-num">
                                    <x-chip :tone="$item->aktif ? 'green' : 'neutral'"
                                            :label="$item->aktif ? 'Tayang' : 'Mati'" />
                                </td>
                                <td class="is-num">
                                    <form method="POST" action="{{ route('admin.sistem.pengumuman.alih', $item) }}">
                                        @csrf
                                        <button class="btn-hifi btn-hifi--ghost btn-hifi--sm" type="submit">
                                            {{ $item->aktif ? 'Hentikan' : 'Tayangkan' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">Belum ada pengumuman.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- 4. Log error --}}
    <x-card title="Log Aplikasi Terbaru" flush>
        <x-slot:actions>
            <span class="card-hifi__meta">
                {{ number_format($log['ukuran'] / 1024, 0, ',', '.') }} KB
                {{ $log['terpotong'] ? '· menampilkan bagian akhir' : '' }}
            </span>
        </x-slot:actions>

        <div class="card-hifi__body">
            <form class="filter-bar" method="GET">
                @if ($filter['status'] ?? null)
                    <input type="hidden" name="status" value="{{ $filter['status'] }}">
                @endif
                <select class="select-hifi" name="level" style="width: 160px" onchange="this.form.submit()">
                    <option value="">Semua Level</option>
                    @foreach ($levelTersedia as $lv)
                        <option value="{{ $lv }}" @selected(($filter['level'] ?? null) === $lv)>{{ $lv }}</option>
                    @endforeach
                </select>

                <span class="filter-bar__note">{{ count($log['entri']) }} entri terbaru</span>
            </form>

            @forelse ($log['entri'] as $entri)
                <div class="log-entri">
                    <div class="log-entri__atas">
                        <x-chip :tone="\App\Support\PembacaLog::tone($entri['level'])" :label="$entri['level']" />
                        <span class="log-entri__waktu">{{ $entri['waktu'] }} · {{ $entri['lingkungan'] }}</span>
                    </div>
                    <div class="log-entri__pesan">{{ $entri['pesan'] }}</div>
                    <details>
                        <summary>Lihat detail</summary>
                        <pre>{{ $entri['lengkap'] }}</pre>
                    </details>
                </div>
            @empty
                <p class="empty-state">Tidak ada entri log{{ ($filter['level'] ?? null) ? ' untuk level tersebut' : '' }}.</p>
            @endforelse
        </div>

        <x-slot:foot>
            <span>Hanya sebagian akhir berkas log yang dibaca agar halaman tetap ringan.</span>
            <form method="POST" action="{{ route('admin.sistem.log.bersihkan') }}"
                  data-konfirmasi="Bersihkan seluruh isi berkas log? Tindakan ini tidak bisa dibatalkan.">
                @csrf
                <button class="btn-hifi btn-hifi--ghost btn-hifi--sm" type="submit">Bersihkan Log</button>
            </form>
        </x-slot:foot>
    </x-card>
    @endif
@endsection
