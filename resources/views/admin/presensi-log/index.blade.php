@extends('layouts.app')

@section('title', 'Log Presensi')

@section('content')
    <x-page-head
        title="Log Pengisian Presensi"
        :sub="'Jejak audit siapa mengisi presensi harian tiap kelas · ' . number_format($log->total(), 0, ',', '.') . ' entri'" />

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <select class="select-hifi" name="kelas_id" style="width: 160px" data-searchable onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected(($filters['kelas_id'] ?? null) == $kelas->id)>{{ $kelas->nama_kelas }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="diedit_oleh_id" style="width: 200px" data-searchable onchange="this.form.submit()">
            <option value="">Semua Pengisi</option>
            @foreach ($editorList as $editor)
                <option value="{{ $editor->id }}" @selected(($filters['diedit_oleh_id'] ?? null) == $editor->id)>{{ $editor->name }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="koreksi" style="width: 170px" onchange="this.form.submit()">
            <option value="">Semua Jenis</option>
            <option value="1" @selected(($filters['koreksi'] ?? null) === '1')>Hanya Koreksi</option>
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $log->count() }} dari {{ number_format($log->total(), 0, ',', '.') }}
        </span>
    </form>

    <x-card title="Riwayat Pengisian Presensi Harian" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Waktu Simpan</th>
                        <th>Pengisi</th>
                        <th>Peran</th>
                        <th>Kelas</th>
                        <th>Tanggal Presensi</th>
                        <th class="is-num">Jml Siswa</th>
                        <th class="is-num">Jenis</th>
                        <th class="is-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($log as $entri)
                        @php
                            $editor = $entri->dieditOleh;
                            $peranTone = match ($editor?->role) {
                                'guru' => 'green',
                                'siswa' => 'yellow',
                                default => 'neutral',
                            };
                            $peranLabel = $editor
                                ? ($editor->isKetuaKelas() ? 'Ketua Kelas' : ucfirst($editor->role))
                                : '—';
                        @endphp
                        <tr>
                            <td class="is-muted is-nowrap">{{ $entri->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="is-strong">
                                @if ($editor)
                                    <a class="text-reset" href="{{ route('admin.users.show', $editor) }}">{{ $editor->name }}</a>
                                @else
                                    <span class="is-muted">(dihapus)</span>
                                @endif
                            </td>
                            <td><x-chip :tone="$peranTone" :label="$peranLabel" /></td>
                            <td class="is-nowrap">{{ $entri->kelas?->nama_kelas ?? '—' }}</td>
                            <td class="is-muted is-nowrap">{{ $entri->tanggal?->format('d/m/Y') ?? '—' }}</td>
                            <td class="is-num">{{ $entri->jumlah_siswa }}</td>
                            <td class="is-num">
                                <x-chip :tone="$entri->koreksi ? 'yellow' : 'green'"
                                        :label="$entri->koreksi ? 'Koreksi' : 'Awal'" />
                            </td>
                            <td class="is-num">
                                @if ($entri->kelas)
                                    <a class="btn-hifi btn-hifi--ghost btn-hifi--sm"
                                       href="{{ route('presensi-harian.show', [$entri->kelas_id, 'tanggal' => $entri->tanggal?->toDateString()]) }}">Lihat</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Belum ada catatan pengisian presensi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:foot>
            <span>
                Menampilkan {{ $log->firstItem() ?? 0 }}–{{ $log->lastItem() ?? 0 }}
                dari {{ number_format($log->total(), 0, ',', '.') }} entri
            </span>
            <x-pager :paginator="$log" />
        </x-slot:foot>
    </x-card>
@endsection
