@php
    /*
     * Carries the cross-cutting controls through a filter form.
     *
     * A GET form submits only its own inputs, so without this every other choice
     * in the URL is silently thrown away: sort by subject, pick "Minggu Lalu",
     * set 50 rows — then type a search term, and all three snap back to their
     * defaults with no hint why.
     *
     * The list is fixed rather than "everything except this form's fields": these
     * six belong to the pager, the sort headers and the period filter, and none of
     * them is ever a filter-bar input, so there is no way to emit a duplicate of a
     * field the form already owns.
     *
     * `page` is deliberately absent: a new filter means the reader wants the first
     * page of the new result, not page 7 of the old one.
     */
    $sticky = ['sort', 'dir', 'per', 'preset', 'mulai', 'selesai'];
@endphp

@foreach ($sticky as $nama)
    @if (filled(request()->query($nama)) && is_scalar(request()->query($nama)))
        <input type="hidden" name="{{ $nama }}" value="{{ request()->query($nama) }}">
    @endif
@endforeach
