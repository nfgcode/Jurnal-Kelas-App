@props([
    // Wording differs by role, so the label is passed in rather than branched on.
    'label',
    'jadwalList',
    'jadwalTerisi',
    'tanggalAktif',
    'jadwal' => null,
    'jurnal' => null,
    'kelas' => null,
    // A guru chooses across the classes they teach, so the option needs to name
    // the class; a ketua kelas only ever sees their own, where it would be noise.
    'denganKelas' => false,
])

{{--
    Which meeting a journal is about: the date, the periods it covers, and the
    schedule slot itself.

    Extracted because jurnal/isi (guru) and jurnal/mengisi (ketua kelas) render
    this identically apart from wording, and every change to it — reloading on a
    new date, marking slots already written up, the empty-day panel, hiding the
    save button — had to be made twice, in step, or the two forms would drift.
--}}

<div class="form-grid form-grid--3">
    <x-field label="Tanggal" name="tanggal" required>
        {{-- Reloading on change is what makes the schedule list below match the
             chosen day. Editing stays on the journal's own date, so only the
             create form reloads. --}}
        <input class="input-hifi" type="date" name="tanggal"
               value="{{ old('tanggal', $tanggalAktif->toDateString()) }}" required
               @unless ($jurnal)
                   onchange="window.location = '{{ route('jurnal.create') }}?tanggal=' + this.value"
               @endunless>
    </x-field>

    <x-field label="Jam Ke (Mulai)">
        <input class="input-hifi" type="text" value="JP {{ $jadwal?->jam_ke_mulai ?? '—' }}" readonly>
    </x-field>

    <x-field label="Jam Ke (Selesai)">
        <input class="input-hifi" type="text" value="JP {{ $jadwal?->jam_ke_selesai ?? '—' }}" readonly>
    </x-field>
</div>

<div class="form-grid form-grid--2">
    <x-field :label="$label" name="jadwal_id" required>
        @if ($jadwalList->isEmpty())
            <x-jadwal-kosong :tanggal="$tanggalAktif" />
        @else
            <select class="select-hifi" name="jadwal_id" data-searchable required
                    onchange="window.location = '{{ route('jurnal.create') }}?tanggal={{ $tanggalAktif->toDateString() }}&jadwal_id=' + this.value">
                @foreach ($jadwalList as $pilihanJadwal)
                    <option value="{{ $pilihanJadwal->id }}" @selected($jadwal?->id === $pilihanJadwal->id)>
                        @if ($denganKelas){{ $pilihanJadwal->kelas?->nama_kelas }} · @endif{{ $pilihanJadwal->mataPelajaran?->nama }}
                        · JP {{ $pilihanJadwal->jpLabel() }}@if (in_array($pilihanJadwal->id, $jadwalTerisi, true)) — sudah diisi @endif
                    </option>
                @endforeach
            </select>
        @endif
    </x-field>

    <x-field label="Ruang">
        <input class="input-hifi" type="text" value="{{ $jadwal?->ruang ?? $kelas?->ruang ?? '—' }}" readonly>
    </x-field>
</div>
