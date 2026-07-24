@props(['kelasWali', 'kelas'])

{{-- Only a teacher who chairs more than one class needs to choose between them. --}}
@if ($kelasWali->count() > 1)
    <form method="GET">
        <select class="select-hifi" name="kelas_id" style="width: 190px" onchange="this.form.submit()">
            @foreach ($kelasWali as $pilihan)
                <option value="{{ $pilihan->id }}" @selected($pilihan->id === $kelas->id)>Kelas {{ $pilihan->nama_kelas }}</option>
            @endforeach
        </select>
    </form>
@endif
