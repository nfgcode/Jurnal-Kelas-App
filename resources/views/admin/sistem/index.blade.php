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
    .log-entri pre {
        margin: 8px 0 0; padding: 9px 11px; background: var(--n-200);
        border-radius: 8px; font-size: 10px; line-height: 1.5;
        max-height: 260px; overflow: auto; white-space: pre-wrap; word-break: break-word;
    }
    .log-entri summary { font-size: 10.5px; color: var(--p-400); cursor: pointer; margin-top: 4px; }
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

    {{-- 2. Laporan dari pengguna --}}
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
                                <span class="is-strong">{{ $item->pelapor?->name ?? '—' }}</span>
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
                  onsubmit="return confirm('Bersihkan seluruh isi berkas log? Tindakan ini tidak bisa dibatalkan.')">
                @csrf
                <button class="btn-hifi btn-hifi--ghost btn-hifi--sm" type="submit">Bersihkan Log</button>
            </form>
        </x-slot:foot>
    </x-card>
@endsection
