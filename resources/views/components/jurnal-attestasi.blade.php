@props(['aktif' => false])

{{-- Shown only when editing a system-generated (auto-filled) journal: turning an
     automatic "absent" record into "present" is honesty-sensitive, so the editor
     must tick an explicit truthfulness statement before the change is accepted. --}}
@if ($aktif)
    <div style="border:1.5px solid var(--red-100);background:#fdecee;border-radius:var(--radius-card);padding:12px 14px;display:grid;gap:8px">
        <p style="margin:0;font-size:12.5px;color:var(--n-1000)">
            <x-ikon nama="exclamation-triangle" />
            Jurnal ini <strong>dibuat otomatis oleh sistem</strong> karena pertemuannya belum diisi
            (guru tercatat <strong>tidak hadir &middot; tanpa tugas</strong>). Ubah hanya bila memang
            tidak sesuai keadaan sebenarnya.
        </p>
        <label class="checkbox-row" style="font-size:12px">
            <input type="checkbox" name="pernyataan" value="1" @checked(old('pernyataan'))>
            Saya menyatakan mengubah jurnal ini dengan <strong>jujur</strong> sesuai keadaan sebenarnya,
            bukan untuk merekayasa kehadiran.
        </label>
        @error('pernyataan')<span class="field__error d-block">{{ $message }}</span>@enderror
    </div>
@endif
