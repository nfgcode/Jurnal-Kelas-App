// Fonts and icons ship with the build rather than coming from a CDN: this app
// runs on a school LAN that may have no internet at all, where CDN requests
// simply hang and every icon renders as an empty box.
// Latin subset only. The full package also ships Cyrillic, Greek and Vietnamese
// cuts — 58 font files for an Indonesian school app that will never render a
// single one of those glyphs.
import '@fontsource/inter/latin-400.css';
import '@fontsource/inter/latin-500.css';
import '@fontsource/inter/latin-600.css';
import '@fontsource/inter/latin-700.css';
// Icons come from resources/sass/_ikon.scss (a subset, see the note there),
// pulled in by app.scss rather than imported here.

// Bootstrap JS — only the two components with behaviour here. Importing the
// whole package also pulled in carousel, collapse, offcanvas, scrollspy, tab,
// toast, tooltip and popover, none of which this app uses. Each component file
// registers its own data-api listeners, so data-bs-toggle="dropdown" still works
// without any wiring of ours.
import Dropdown from 'bootstrap/js/dist/dropdown';
import Modal from 'bootstrap/js/dist/modal';

// The dashboard drill-down opens the modal by hand; the dropdown runs off its
// data attributes, but is exposed for symmetry.
window.bootstrap = { Dropdown, Modal };

// The mobile sidebar toggle lives inline in layouts/app.blade.php, where it can
// also drive the scrim. A second handler here only fought it for the same
// button, so it was removed.

// Dashboard drill-down. Any element carrying data-detail-tipe opens a modal
// listing the meetings behind that figure; the modal (#detailModal) and its
// endpoint live on the admin dashboard, so this stays inert everywhere else.
const escapeHtml = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const chip = (data) =>
    data ? `<span class="chip chip--${escapeHtml(data.tone)}">${escapeHtml(data.label)}</span>` : '';

const renderTabel = (baris) => {
    const rows = baris
        .map(
            (r) => `<tr>
                <td class="is-muted">${escapeHtml(r.tanggal)}</td>
                <td class="is-strong">${escapeHtml(r.kelas ?? '—')}</td>
                <td>${escapeHtml(r.mapel ?? '—')}</td>
                <td class="is-muted">${r.guruUrl
                    ? `<a class="text-reset" href="${escapeHtml(r.guruUrl)}">${escapeHtml(r.guru ?? '—')}</a>`
                    : escapeHtml(r.guru ?? '—')}</td>
                <td>${escapeHtml(r.materi ?? '—')}</td>
                <td>${chip(r.guruChip)}</td>
                <td class="is-num">${escapeHtml(r.hadir)}/${escapeHtml(r.total)}</td>
                <td class="is-num">${chip(r.statusChip)}</td>
            </tr>`
        )
        .join('');

    return `<div class="tbl-wrap"><table class="tbl">
        <thead><tr>
            <th>Tanggal</th><th>Kelas</th><th>Mapel</th><th>Guru</th>
            <th>Materi</th><th>Kehadiran Guru</th><th class="is-num">Hadir</th><th class="is-num">Status</th>
        </tr></thead>
        <tbody>${rows}</tbody>
    </table></div>`;
};

const renderPresensi = (baris) => {
    const rows = baris
        .map(
            (r) => `<tr>
                <td class="is-muted">${escapeHtml(r.tanggal)}</td>
                <td class="is-strong">${escapeHtml(r.siswa ?? '—')}</td>
                <td>${escapeHtml(r.kelas ?? '—')}</td>
                <td>${escapeHtml(r.mapel ?? '—')}</td>
                <td>${chip(r.statusChip)}</td>
                <td class="is-muted">${escapeHtml(r.keterangan ?? '—')}</td>
            </tr>`
        )
        .join('');

    return `<div class="tbl-wrap"><table class="tbl">
        <thead><tr>
            <th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Mapel</th><th>Status</th><th>Keterangan</th>
        </tr></thead>
        <tbody>${rows}</tbody>
    </table></div>`;
};

const renderBelum = (baris) => {
    const rows = baris
        .map(
            (r) => `<tr>
                <td class="is-muted">${escapeHtml(r.tanggal)}</td>
                <td class="is-strong">${escapeHtml(r.kelas ?? '—')}</td>
                <td>${escapeHtml(r.mapel ?? '—')}</td>
                <td class="is-muted">${escapeHtml(r.guru ?? '—')}</td>
            </tr>`
        )
        .join('');

    return `<div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Tanggal</th><th>Kelas</th><th>Mapel</th><th>Guru</th></tr></thead>
        <tbody>${rows}</tbody>
    </table></div>`;
};

const renderKelengkapan = (baris) => {
    const rows = baris
        .map((r) => {
            const persen = Number(r.persen) || 0;
            return `<tr>
                <td class="is-strong">${escapeHtml(r.kelas ?? '—')}</td>
                <td>
                    <span class="meter-cell">
                        <span class="meter" style="width: 120px"><span class="meter__fill" style="width: ${persen}%"></span></span>
                        <span class="is-strong">${persen}%</span>
                    </span>
                </td>
            </tr>`;
        })
        .join('');

    return `<div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Kelas</th><th>Kelengkapan Jurnal</th></tr></thead>
        <tbody>${rows}</tbody>
    </table></div>`;
};

const renderers = {
    presensi: renderPresensi,
    belum: renderBelum,
    kelengkapan: renderKelengkapan,
};

document.addEventListener('click', async (event) => {
    const trigger = event.target.closest('[data-detail-tipe]');
    const modalEl = document.getElementById('detailModal');

    if (!trigger || !modalEl) {
        return;
    }

    event.preventDefault();

    const url = new URL(modalEl.dataset.url, window.location.origin);
    // Carry the active period and any report filter so the detail matches the
    // figures on screen. Explicit data-* attributes below still take priority.
    new URLSearchParams(window.location.search).forEach((value, key) => {
        if (['preset', 'mulai', 'selesai', 'kelas_id', 'guru_nip', 'tingkat', 'jurusan'].includes(key)) {
            url.searchParams.set(key, value);
        }
    });
    url.searchParams.set('tipe', trigger.dataset.detailTipe);
    if (trigger.dataset.detailTanggal) url.searchParams.set('tanggal', trigger.dataset.detailTanggal);
    if (trigger.dataset.detailGuru) url.searchParams.set('guru_nip', trigger.dataset.detailGuru);
    if (trigger.dataset.detailKelas) url.searchParams.set('kelas_id', trigger.dataset.detailKelas);
    if (trigger.dataset.detailStatus) url.searchParams.set('status', trigger.dataset.detailStatus);

    const judul = document.getElementById('detailModalJudul');
    const meta = document.getElementById('detailModalMeta');
    const body = document.getElementById('detailModalBody');

    body.innerHTML = '<p class="empty-state">Memuat…</p>';
    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();

    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            throw new Error('Gagal memuat');
        }

        const data = await response.json();

        judul.textContent = data.judul ?? 'Detail';
        meta.textContent = data.meta ?? '';
        body.innerHTML = data.kosong
            ? '<p class="empty-state">Tidak ada data pada periode ini.</p>'
            : (renderers[data.tampilan] ?? renderTabel)(data.baris);
    } catch (error) {
        meta.textContent = '';
        body.innerHTML = '<p class="empty-state">Tidak dapat memuat detail. Coba lagi.</p>';
    }
});

// Keyboard parity: Enter/Space on a focused drill-down trigger acts as a click.
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }

    const trigger = event.target.closest('[data-detail-tipe]');

    if (trigger) {
        event.preventDefault();
        trigger.click();
    }
});

// Searchable dropdowns. Progressive enhancement: a <select data-searchable>
// keeps working as a plain select if this never runs, but when it does the
// native control is replaced with a button + filterable panel. Selecting an
// option writes back to the real <select> and dispatches its change event, so
// the existing onchange="this.form.submit()" behaviour is preserved.
(() => {
    const STYLE_ID = 'select-search-style';
    if (!document.getElementById(STYLE_ID)) {
        const style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = `
            .ss{position:relative;display:inline-block}
            .ss__panel{position:absolute;z-index:50;top:calc(100% + 4px);left:0;min-width:100%;
                background:var(--surface,#fff);border:1px solid var(--n-200,#d8dee4);border-radius:10px;
                box-shadow:0 8px 24px rgba(0,0,0,.12);padding:6px;display:none}
            .ss--open .ss__panel{display:block}
            .ss__search{width:100%;margin-bottom:6px}
            .ss__list{max-height:240px;overflow-y:auto;list-style:none;margin:0;padding:0}
            .ss__opt{padding:7px 10px;border-radius:7px;cursor:pointer;font-size:13px;white-space:nowrap}
            .ss__opt:hover,.ss__opt.is-active{background:var(--n-100,#eef1f4)}
            .ss__opt[hidden]{display:none}
            .ss__empty{padding:8px 10px;color:var(--n-500,#8a94a0);font-size:12px}
        `;
        document.head.appendChild(style);
    }

    const enhance = (select) => {
        if (select.dataset.ssReady) return;
        select.dataset.ssReady = '1';

        const options = Array.from(select.options);
        const wrap = document.createElement('div');
        wrap.className = 'ss';
        wrap.style.width = select.style.width || '';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = select.className + ' ss__button';
        button.style.width = '100%';
        button.style.textAlign = 'left';

        const labelFor = (value) => options.find((o) => o.value === value)?.textContent.trim() || '';
        const syncLabel = () => { button.textContent = labelFor(select.value) || options[0]?.textContent || ''; };
        syncLabel();

        const panel = document.createElement('div');
        panel.className = 'ss__panel';

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'input-hifi ss__search';
        search.placeholder = 'Cari…';

        const list = document.createElement('ul');
        list.className = 'ss__list';
        const empty = document.createElement('li');
        empty.className = 'ss__empty';
        empty.textContent = 'Tidak ditemukan';
        empty.hidden = true;

        options.forEach((opt) => {
            const li = document.createElement('li');
            li.className = 'ss__opt';
            li.textContent = opt.textContent.trim() || '—';
            li.dataset.value = opt.value;
            li.addEventListener('click', () => {
                select.value = opt.value;
                syncLabel();
                close();
                // Fire change so any onchange (e.g. form submit) still runs.
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            list.appendChild(li);
        });
        list.appendChild(empty);

        const filter = () => {
            const q = search.value.toLowerCase().trim();
            let shown = 0;
            list.querySelectorAll('.ss__opt').forEach((li) => {
                const match = li.textContent.toLowerCase().includes(q);
                li.hidden = !match;
                if (match) shown++;
            });
            empty.hidden = shown > 0;
        };
        search.addEventListener('input', filter);

        const open = () => { wrap.classList.add('ss--open'); search.value = ''; filter(); search.focus(); };
        const close = () => wrap.classList.remove('ss--open');
        button.addEventListener('click', () => wrap.classList.contains('ss--open') ? close() : open());
        document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) close(); });
        search.addEventListener('keydown', (e) => { if (e.key === 'Escape') { close(); button.focus(); } });

        select.hidden = true;
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(button);
        wrap.appendChild(panel);
        panel.appendChild(search);
        panel.appendChild(list);
    };

    document.querySelectorAll('select[data-searchable]').forEach(enhance);
})();

// Dashboard date picker (components/pilih-tanggal): the native date input lies
// invisibly over the date label. A click opens the platform calendar where the
// browser supports it, and picking a day reloads the dashboard on that date.
document.addEventListener('click', (e) => {
    const input = e.target.closest('.pilih-tanggal__input');
    if (input && typeof input.showPicker === 'function') {
        try { input.showPicker(); } catch { /* not user-activated / unsupported */ }
    }
});

document.addEventListener('change', (e) => {
    if (e.target.matches('[data-kirim-otomatis]') && e.target.value) {
        e.target.form.requestSubmit();
    }
});

// Journal forms (jurnal/isi, jurnal/mengisi): a real local draft. Typing is
// saved to this device as it happens, "Simpan Draf" saves on demand, and an
// unsent draft found on the next visit is offered back rather than silently
// poured over what the server rendered. Submitting clears it — a validation
// error re-renders the form from old() anyway.
(() => {
    const simpan = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch { return false; } };
    const baca = (k) => { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch { return null; } };
    const hapus = (k) => { try { localStorage.removeItem(k); } catch { /* storage unavailable */ } };
    const jam = (t) => new Date(t).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

    // Free text and the attendance choice; never the token, method spoof, the
    // meeting picker (it reloads the page) or the attestation box.
    const bidang = (form) => [...form.elements].filter((el) =>
        el.name && !el.name.startsWith('_') && !['tanggal', 'jadwal_id'].includes(el.name)
        && (el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && ['text', 'radio'].includes(el.type)) && !el.readOnly));

    const nilai = (form) => {
        const out = {};
        bidang(form).forEach((el) => {
            if (el.type === 'radio') { if (el.checked) out[el.name] = el.value; } else { out[el.name] = el.value; }
        });
        return out;
    };

    const terapkan = (form, isi) => {
        bidang(form).forEach((el) => {
            if (!(el.name in isi)) return;
            if (el.type === 'radio') el.checked = el.value === isi[el.name]; else el.value = isi[el.name];
        });
        form.dispatchEvent(new Event('input', { bubbles: true }));
    };

    document.querySelectorAll('form[data-draf]').forEach((form) => {
        const kunci = 'draf:' + form.dataset.draf;
        const status = document.querySelector(`[data-draf-status="${form.id}"]`);
        const tulisStatus = (teks) => { if (status) status.textContent = teks; };
        let jeda;

        const simpanSekarang = (manual) => {
            const ok = simpan(kunci, { isi: nilai(form), waktu: Date.now() });
            tulisStatus(ok
                ? (manual ? 'Draf tersimpan' : 'Draf tersimpan otomatis') + ' · ' + jam(Date.now())
                : 'Draf tidak dapat disimpan di perangkat ini');
        };

        const lama = baca(kunci);
        const sama = lama && JSON.stringify(lama.isi) === JSON.stringify(nilai(form));
        if (lama && !sama && !('drafLewati' in form.dataset)) {
            const banner = document.createElement('div');
            banner.className = 'draf-banner';
            banner.setAttribute('role', 'status');
            banner.innerHTML = `<span>Ada draf yang belum terkirim, disimpan pukul ${jam(lama.waktu)}.</span>`
                + '<span class="draf-banner__aksi"><button type="button" class="btn-hifi btn-hifi--sm" data-pulihkan>Pulihkan</button>'
                + '<button type="button" class="btn-hifi btn-hifi--sm btn-hifi--ghost" data-buang>Buang</button></span>';
            form.prepend(banner);
            banner.querySelector('[data-pulihkan]').addEventListener('click', () => {
                terapkan(form, lama.isi);
                banner.remove();
                tulisStatus('Draf dipulihkan · ' + jam(lama.waktu));
            });
            banner.querySelector('[data-buang]').addEventListener('click', () => {
                hapus(kunci);
                banner.remove();
                tulisStatus('Draf dibuang');
            });
        } else if (sama) {
            hapus(kunci);
        }

        form.addEventListener('input', (e) => {
            if (!e.isTrusted) return;
            clearTimeout(jeda);
            jeda = setTimeout(() => simpanSekarang(false), 800);
        });
        form.addEventListener('change', (e) => {
            if (!e.isTrusted) return;
            clearTimeout(jeda);
            jeda = setTimeout(() => simpanSekarang(false), 300);
        });
        document.querySelectorAll(`[data-simpan-draf="${form.id}"]`).forEach((btn) =>
            btn.addEventListener('click', () => { clearTimeout(jeda); simpanSekarang(true); }));
        form.addEventListener('submit', () => { clearTimeout(jeda); hapus(kunci); });
    });

    // "Sebelum Menyimpan": tick each item as the form actually satisfies it.
    document.querySelectorAll('[data-checklist-for]').forEach((daftar) => {
        const form = document.getElementById(daftar.dataset.checklistFor);
        if (!form) return;

        const periksa = () => {
            const pilihan = form.querySelector('input[name="kehadiran_guru"]:checked')?.value;
            const materi = (form.elements.materi?.value || '').trim();
            const ket = (form.elements.kehadiran_guru_keterangan?.value || '').trim();
            const hasil = {
                kehadiran: Boolean(pilihan),
                materi: materi.length >= 3,
                keterangan: !pilihan || pilihan === 'hadir' || ket.length > 0,
            };
            daftar.querySelectorAll('[data-cek]').forEach((item) => {
                const ok = hasil[item.dataset.cek];
                item.classList.toggle('checklist__item--done', ok);
                item.classList.toggle('checklist__item--todo', !ok);
            });
        };

        form.addEventListener('input', periksa);
        form.addEventListener('change', periksa);
        periksa();
    });
})();

// Destructive actions ask first. The question rides in data-konfirmasi, an
// ordinary HTML-escaped attribute, instead of an inline onclick="confirm('{{ }}')":
// there the browser decodes &#039; back to a quote before running the script, so
// a name such as  x'); alert(document.cookie);//  broke out of the JS string.
document.addEventListener('click', (e) => {
    const pemicu = e.target.closest('button[data-konfirmasi], a[data-konfirmasi]');
    if (pemicu && !window.confirm(pemicu.dataset.konfirmasi)) {
        e.preventDefault();
        e.stopImmediatePropagation();
    }
});

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.matches('form[data-konfirmasi]') && !window.confirm(form.dataset.konfirmasi)) {
        e.preventDefault();
    }
});
