@props(['tanggal'])

{{-- Shown in place of the schedule dropdown when the user has no lesson on the
     chosen day. Without it the select is simply empty and the writer cannot tell
     whether the app is broken or the timetable just has nothing there. --}}
<div class="banner banner--info">
    <strong>Tidak ada jadwal Anda pada {{ $tanggal->translatedFormat('l, j F Y') }}.</strong>
    Pilih tanggal lain di atas. Bila jadwal Anda memang seharusnya ada di hari itu
    — misalnya baru berubah atau belum terdaftar — hubungi admin untuk memperbarui
    jadwal terlebih dahulu.
</div>
