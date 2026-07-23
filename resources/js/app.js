// Bootstrap JS
import * as bootstrap from 'bootstrap';

// Make bootstrap available globally
window.bootstrap = bootstrap;

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
                <td class="is-muted">${escapeHtml(r.guru ?? '—')}</td>
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
