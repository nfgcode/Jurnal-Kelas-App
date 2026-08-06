@props(['jurnal'])

{{-- A journal changed on a day after its lesson — distinct from "Telat" (filled
     late). Visible to every role, so an after-the-fact edit is never silent. --}}
@if ($jurnal?->dieditSetelahHari())
    <x-chip tone="yellow" label="Diedit setelah hari-H" />
@endif
