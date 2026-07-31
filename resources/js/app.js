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
        if (['preset', 'mulai', 'selesai', 'kelas_id', 'guru_id'].includes(key)) {
            url.searchParams.set(key, value);
        }
    });
    url.searchParams.set('tipe', trigger.dataset.detailTipe);
    if (trigger.dataset.detailTanggal) url.searchParams.set('tanggal', trigger.dataset.detailTanggal);
    if (trigger.dataset.detailGuru) url.searchParams.set('guru_id', trigger.dataset.detailGuru);
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
