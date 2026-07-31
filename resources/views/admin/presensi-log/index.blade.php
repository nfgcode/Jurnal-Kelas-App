@extends('layouts.app')

@section('title', 'Log Presensi')

@section('content')
    <x-page-head
        title="Log Edit Presensi"
        :sub="'Jejak audit siapa menyimpan presensi tiap kelas · ' . number_format($log->total(), 0, ',', '.') . ' entri'" />

    <form class="filter-bar" method="GET">
        <x-query-hidden />

        <select class="select-hifi" name="kelas_id" style="width: 160px" onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected(($filters['kelas_id'] ?? null) == $kelas->id)>{{ $kelas->nama_kelas }}</option>
            @endforeach
        </select>

        <select class="select-hifi" name="diedit_oleh_id" style="width: 200px" onchange="this.form.submit()">
            <option value="">Semua Editor</option>
            @foreach ($editorList as $editor)
                <option value="{{ $editor->id }}" @selected(($filters['diedit_oleh_id'] ?? null) == $editor->id)>{{ $editor->name }}</option>
            @endforeach
        </select>

        <span class="filter-bar__note">
            Menampilkan {{ $log->count() }} dari {{ number_format($log->total(), 0, ',', '.') }}
        </span>
    </form>

    <x-card title="Riwayat Penyimpanan Presensi" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Waktu Edit</th>
                        <th>Editor</th>
                        <th>Peran</th>
                        <th>Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th>Tgl Pertemuan</th>
                        <th class="is-num">Jml Siswa</th>
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
                            <td class="is-nowrap">{{ $entri->jurnal?->jadwal?->kelas?->nama_kelas ?? '—' }}</td>
                            <td>{{ $entri->jurnal?->jadwal?->mataPelajaran?->nama ?? '—' }}</td>
                            <td class="is-muted is-nowrap">{{ $entri->jurnal?->tanggal?->format('d/m/Y') ?? '—' }}</td>
                            <td class="is-num">{{ $entri->jumlah_siswa }}</td>
                            <td class="is-num">
                                @if ($entri->jurnal)
                                    <a class="btn-hifi btn-hifi--ghost btn-hifi--sm" href="{{ route('presensi.show', $entri->jurnal->id) }}">Lihat</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Belum ada catatan edit presensi.</td></tr>
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
