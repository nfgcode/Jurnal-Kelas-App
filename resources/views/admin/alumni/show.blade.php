@extends('layouts.app')

@section('title', 'Detail Alumni · ' . $siswa->nama)

@section('content')
    <x-page-head
        :title="$siswa->nama"
        :sub="'NIS: ' . $siswa->nis . ' · Alumni Angkatan ' . $alumni->tahun_lulus">
        <div class="d-flex gap-2 align-items-center">
            <x-chip :tone="$evaluasi['tone_siklus_hidup']" :label="$evaluasi['label_siklus_hidup']" />
            <a href="{{ route('admin.alumni.index') }}" class="btn-hifi btn-hifi--ghost">&larr; Kembali</a>
        </div>
    </x-page-head>

    {{-- Alert Kelayakan Penghapusan & Keamanan Data --}}
    @if ($evaluasi['memiliki_dependensi'])
        <div class="alert alert-danger mb-4">
            <h6 class="fw-bold mb-2">
                <x-ikon nama="shield-lock" /> DIBLOKIR DARI PENGHAPUSAN PERMANEN
            </h6>
            <p class="mb-2 small">
                Data alumni ini memiliki rekam jejak pendidikan historis yang dilindungi. Penghapusan fisik dilarang keras demi menjaga integritas rekapitulasi kehadiran, laporan pengajaran guru, dan arsip kelembagaan sekolah.
            </p>
            <ul class="mb-0 ps-3 small">
                @foreach ($evaluasi['alasan_pemblokiran'] as $alasan)
                    <li>{{ $alasan }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="alert alert-info mb-4">
            <h6 class="fw-bold mb-1">Status Kelayakan Retensi:</h6>
            <p class="mb-0 small">{{ $evaluasi['status_kelayakan'] }}</p>
        </div>
    @endif

    <div class="grid-row grid-row--2 mb-4 align-items-start">
        {{-- Card 1: Data Identitas & Kelulusan --}}
        <x-card title="Informasi Alumni &amp; Akun">
            <div class="detail-list">
                <div class="detail-row">
                    <span class="detail-label">Nomor Induk Siswa (NIS)</span>
                    <span class="detail-val fw-semibold">{{ $siswa->nis }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">NISN</span>
                    <span class="detail-val">{{ $siswa->nisn ?? '—' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Nama Lengkap</span>
                    <span class="detail-val fw-semibold">{{ $siswa->nama }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Jenis Kelamin</span>
                    <span class="detail-val">{{ $siswa->jenis_kelamin === 'L' ? 'Laki-laki (L)' : ($siswa->jenis_kelamin === 'P' ? 'Perempuan (P)' : '—') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tahun Kelulusan</span>
                    <span class="detail-val fw-semibold">{{ $alumni->tahun_lulus }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tanggal Kelulusan</span>
                    <span class="detail-val">{{ $alumni->tanggal_lulus ? $alumni->tanggal_lulus->format('d/m/Y') : '—' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Rombel Kelas Terakhir</span>
                    <span class="detail-val">{{ $alumni->kelasTerakhir?->nama_kelas ?? '—' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Keterangan Kelulusan</span>
                    <span class="detail-val text-muted">{{ $alumni->keterangan ?? 'Lulus reguler' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Akun Login Siswa</span>
                    <span class="detail-val d-flex align-items-center gap-2">
                        @if ($siswa->akun)
                            <x-chip :tone="$siswa->akun->status === 'aktif' ? 'green' : 'neutral'" :label="ucfirst($siswa->akun->status)" />
                            <form method="POST" action="{{ route('admin.alumni.akun', $alumni) }}" class="d-inline"
                                  onsubmit="return confirm('Ubah status akun login alumni menjadi {{ $siswa->akun->status === 'aktif' ? 'nonaktif' : 'aktif' }}?');">
                                @csrf
                                <button type="submit" class="btn-hifi btn-hifi--ghost btn-hifi--sm">
                                    {{ $siswa->akun->status === 'aktif' ? 'Deaktivasi Akun' : 'Aktifkan Akun' }}
                                </button>
                            </form>
                        @else
                            <span class="text-muted small">Tidak ada akun</span>
                        @endif
                    </span>
                </div>
            </div>
        </x-card>

        {{-- Card 2: Evaluasi Siklus Hidup & Retensi --}}
        <x-card title="Evaluasi Kebijakan Retensi">
            <div class="detail-list">
                <div class="detail-row">
                    <span class="detail-label">Tanggal Referensi Inaktivitas</span>
                    <span class="detail-val fw-semibold">{{ $evaluasi['tanggal_referensi'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Durasi Inaktivitas</span>
                    <span class="detail-val">{{ $evaluasi['usia_inaktif_teks'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Kategori Ambang Retensi</span>
                    <span class="detail-val">
                        @if ($evaluasi['ambang_retensi'] === 'kurang_dari_1_tahun')
                            <span class="badge bg-secondary">&lt; 1 Tahun (Aman / Retensi Aktif)</span>
                        @elseif ($evaluasi['ambang_retensi'] === '1_sampai_2_tahun')
                            <span class="badge bg-warning text-dark">1–2 Tahun (Masa Tinjauan Deaktivasi)</span>
                        @else
                            <span class="badge bg-danger">&gt; 2 Tahun (Ambang Tinjauan Hapus)</span>
                        @endif
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status Siklus Hidup</span>
                    <span class="detail-val">
                        <x-chip :tone="$evaluasi['tone_siklus_hidup']" :label="$evaluasi['label_siklus_hidup']" />
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Izin Penghapusan Permanen</span>
                    <span class="detail-val text-danger fw-bold">
                        DITOLAK (Fitur penghapusan fisik tidak diaktifkan)
                    </span>
                </div>
            </div>

            <div class="mt-3 p-3 bg-light rounded border">
                <span class="field__label d-block mb-1">Prinsip Perlindungan Data:</span>
                <p class="small text-muted mb-0">
                    Sesuai kebijakan institusi pendidikan, bertambahnya usia data di atas 2 tahun <strong>tidak memberikan otorisasi</strong> untuk melakukan penghapusan otomatis. Data pendidikan yang memiliki catatan kehadiran atau jurnal dilindungi secara permanen.
                </p>
            </div>
        </x-card>
    </div>

    {{-- Card 3: Rincian Inspeksi Dependensi Relasional --}}
    <x-card title="Inspeksi Dependensi Data Historis" class="mb-4" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Jenis Dependensi</th>
                        <th>Jumlah Catatan</th>
                        <th>Status Integritas</th>
                        <th>Dampak Jika Dihapus Fisik</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold">Presensi Harian Siswa (Rombel)</td>
                        <td class="is-strong">{{ number_format($evaluasi['ringkasan_dependensi']['presensi_harian']) }} baris</td>
                        <td>
                            @if ($evaluasi['ringkasan_dependensi']['presensi_harian'] > 0)
                                <x-chip tone="red" label="Terkunci RESTRICT" />
                            @else
                                <x-chip tone="neutral" label="Kosong" />
                            @endif
                        </td>
                        <td class="is-muted small">Merusak keabsahan rekapitulasi kehadiran kelas harian sekolah.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Presensi Per Pertemuan (Legacy)</td>
                        <td class="is-strong">{{ number_format($evaluasi['ringkasan_dependensi']['presensi_legacy']) }} baris</td>
                        <td>
                            @if ($evaluasi['ringkasan_dependensi']['presensi_legacy'] > 0)
                                <x-chip tone="red" label="Terkunci RESTRICT" />
                            @else
                                <x-chip tone="neutral" label="Kosong" />
                            @endif
                        </td>
                        <td class="is-muted small">Menghilangkan riwayat absensi mata pelajaran di masa lampau.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Jurnal Kelas Diisi (Ketua Kelas)</td>
                        <td class="is-strong">{{ number_format($evaluasi['ringkasan_dependensi']['jurnal_diisi']) }} pertemuan</td>
                        <td>
                            @if ($evaluasi['ringkasan_dependensi']['jurnal_diisi'] > 0)
                                <x-chip tone="red" label="Terkunci RESTRICT" />
                            @else
                                <x-chip tone="neutral" label="Kosong" />
                            @endif
                        </td>
                        <td class="is-muted small">Mengakibatkan catatan materi &amp; jurnal mengajar guru kehilangan pengisi.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Keanggotaan Kelas Historis</td>
                        <td class="is-strong">{{ number_format($evaluasi['ringkasan_dependensi']['riwayat_kelas']) }} periode</td>
                        <td>
                            @if ($evaluasi['ringkasan_dependensi']['riwayat_kelas'] > 0)
                                <x-chip tone="red" label="Terkunci RESTRICT" />
                            @else
                                <x-chip tone="neutral" label="Kosong" />
                            @endif
                        </td>
                        <td class="is-muted small">Menghapus garis waktu mutasi dan kenaikan kelas siswa.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Data Kelulusan Alumni</td>
                        <td class="is-strong">{{ $evaluasi['ringkasan_dependensi']['data_alumni'] ? '1 rekaman' : '0' }}</td>
                        <td>
                            @if ($evaluasi['ringkasan_dependensi']['data_alumni'])
                                <x-chip tone="red" label="Terkunci RESTRICT" />
                            @else
                                <x-chip tone="neutral" label="Kosong" />
                            @endif
                        </td>
                        <td class="is-muted small">Menghilangkan arsip nomor kelulusan dan tanggal lulus sekolah.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Card 4: Riwayat Keanggotaan Kelas Historis --}}
    <x-card title="Garis Waktu Keanggotaan Kelas (Riwayat Akademik)" class="mb-4" flush>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <th>Tingkat</th>
                        <th>Rombel Kelas</th>
                        <th>Status</th>
                        <th>Keanggotaan</th>
                        <th>Periode</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riwayatKelas as $rk)
                        <tr>
                            <td class="is-strong">{{ $rk->tahun_ajaran_kode }}</td>
                            <td><span class="badge bg-secondary">{{ $rk->tingkat ?? '—' }}</span></td>
                            <td class="fw-semibold">{{ $rk->kelas?->nama_kelas ?? '—' }}</td>
                            <td>
                                <x-chip :tone="$rk->status === 'lulus' ? 'khaki' : ($rk->status === 'aktif' ? 'green' : 'neutral')" :label="ucfirst($rk->status)" />
                            </td>
                            <td>
                                <x-chip :tone="$rk->aktif ? 'green' : 'neutral'" :label="$rk->aktif ? 'Aktif' : 'Selesai'" />
                            </td>
                            <td class="is-muted is-nowrap small">
                                {{ $rk->tanggal_mulai ? $rk->tanggal_mulai->format('d/m/Y') : 'Awal tahun' }} s/d {{ $rk->tanggal_selesai ? $rk->tanggal_selesai->format('d/m/Y') : 'Selesai' }}
                            </td>
                            <td class="is-muted small">{{ $rk->catatan ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">Belum ada catatan riwayat kelas historis untuk siswa ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Card 5 & 6: Tinjauan Administratif & Riwayat --}}
    <div class="grid-row grid-row--2 mb-4 align-items-start">
        <x-card title="Formulir Tinjauan Administratif">
            <p class="field__hint mb-3">
                Catat hasil evaluasi administratif berkala mengenai status arsip data siswa ini. Seluruh pencatatan tinjauan akan direkam ke dalam audit log sistem.
            </p>

            <form method="POST" action="{{ route('admin.alumni.tinjauan', $alumni) }}" class="form-grid">
                @csrf
                <x-field label="Status Tinjauan" name="status_tinjauan" required>
                    <select class="select-hifi" name="status_tinjauan" required>
                        <option value="ditinjau">Ditinjau (Data valid &amp; dipertahankan sebagai arsip)</option>
                        <option value="ditunda">Ditunda (Menunggu verifikasi lanjutan pihak sekolah)</option>
                        <option value="disetujui_arsip">Disetujui Arsip Permanen (Data dibekukan secara aman)</option>
                        <option value="perlu_tinjauan">Perlu Tinjauan Ulang</option>
                    </select>
                </x-field>

                <x-field label="Catatan / Justifikasi Administratif" name="catatan">
                    <textarea class="input-hifi" name="catatan" rows="3" placeholder="Contoh: Seluruh rekaman presensi telah diverifikasi utuh dan dipertahankan dalam arsip kelulusan."></textarea>
                </x-field>

                <div class="d-flex justify-content-end mt-2">
                    <button class="btn-hifi" type="submit">Simpan Hasil Tinjauan</button>
                </div>
            </form>
        </x-card>

        <x-card title="Riwayat Keputusan Tinjauan" flush>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Status Tinjauan</th>
                            <th>Peninjau</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riwayatTinjauan as $rt)
                            <tr>
                                <td class="is-muted is-nowrap small">{{ $rt->ditinjau_pada ? $rt->ditinjau_pada->format('d/m/Y H:i') : $rt->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <x-chip tone="green" :label="ucfirst(str_replace('_', ' ', $rt->status_tinjauan))" />
                                </td>
                                <td class="is-muted small">{{ $rt->ditinjauOleh?->nama ?? $rt->ditinjauOleh?->username ?? 'Admin' }}</td>
                                <td class="is-muted small">{{ $rt->catatan ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state">Belum ada catatan tinjauan administratif sebelumnya.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
