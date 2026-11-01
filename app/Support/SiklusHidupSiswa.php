<?php

namespace App\Support;

use App\Models\{Kelas, RiwayatKelasSiswa, Siswa};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Boundary for individual corrections and general imports; bulk transitions remain explicit batches. */
class SiklusHidupSiswa
{
    /** Must be called inside the batch transaction, after locking students. */
    public function validasiBatch(iterable $students, string $tanggal): void
    {
        foreach ($students as $student) {
            if ($student->alumni()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['status' => 'Siswa aktif memiliki alumni. Verifikasi data; transisi tidak boleh menimpa atau mengabaikan alumni.']);
            }
            foreach ($student->riwayatKelas()->where('aktif', true)->lockForUpdate()->get() as $member) {
                if ((string) $member->kelas_id !== (string) $student->kelas_id
                    || ($member->tanggal_mulai && $member->tanggal_mulai->toDateString() > $tanggal)) {
                    throw ValidationException::withMessages(['kelas_id' => 'Riwayat kelas tidak konsisten atau tanggal penutupan sebelum tanggal mulai.']);
                }
            }
        }
    }

    /** Dated evidence requires complete period bounds containing the effective date. */
    private function validasiTanggalKelas(Kelas $kelas, string $tanggal): void
    {
        $period = $kelas->tahunAjaran;
        if (! $period || ! $period->mulai || ! $period->selesai
            || $period->mulai->gt($period->selesai)
            || $period->mulai->toDateString() > $tanggal || $period->selesai->toDateString() < $tanggal) {
            throw ValidationException::withMessages(['kelas_id' => 'Keanggotaan bertanggal memerlukan periode lengkap yang mencakup tanggal penempatan; verifikasi tanggal tahun ajaran terlebih dahulu.']);
        }
    }

    public function daftarkan(Siswa $siswa): void
    {
        DB::transaction(function () use ($siswa) {
            \App\Services\AktivasiTahunAjaran::kunci();
            $locked = Siswa::whereKey($siswa->nis)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'aktif' && $locked->kelas_id) {
                $kelas = $locked->kelas;
                $today = today()->toDateString();
                $this->validasiTanggalKelas($kelas, $today);
                RiwayatKelasSiswa::daftarkan($locked, $kelas, $kelas->tahun_ajaran_kode, $today);
            }
        });
    }

    public function validasiProfilImpor(Siswa $siswa, array $data): void
    {
        $status = $data['status'] ?? '';
        if ((string) ($data['_kelas_id'] ?? null) !== (string) $siswa->kelas_id
            || ($status !== '' && ($status === 'nonaktif' ? 'nonaktif' : 'aktif') !== $siswa->status)) {
            throw ValidationException::withMessages(['kelas_id' => 'Impor umum hanya memperbarui profil. Koreksi kelas/status melalui data siswa dengan alasan.']);
        }
    }

    public function profilImpor(Siswa $siswa, array $data): Siswa
    {
        $this->validasiProfilImpor($siswa, $data);
        return $this->perbarui($siswa, ['nama' => $data['nama']]);
    }

    public function perbarui(Siswa $siswa, array $data): Siswa
    {
        return DB::transaction(function () use ($siswa, $data) {
            \App\Services\AktivasiTahunAjaran::kunci();
            $locked = Siswa::whereKey($siswa->nis)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['kelas_id', 'status']);
            $after = array_replace($before, array_intersect_key($data, $before));
            $changed = (string) $after['kelas_id'] !== (string) $before['kelas_id'] || $after['status'] !== $before['status'];
            if ($changed) {
                if (trim((string) ($data['alasan'] ?? '')) === '') {
                    throw ValidationException::withMessages(['alasan' => 'Alasan wajib diisi untuk koreksi kelas atau status siswa.']);
                }
                $today = today()->toDateString();
                $members = RiwayatKelasSiswa::where('siswa_nis', $locked->nis)->where('aktif', true)->lockForUpdate()->get();
                foreach ($members as $member) {
                    if (($member->tanggal_mulai && $member->tanggal_mulai->toDateString() > $today)
                        || (string) $member->kelas_id !== (string) $before['kelas_id']) {
                        throw ValidationException::withMessages(['kelas_id' => 'Riwayat kelas tidak konsisten atau tanggal mulai masih di masa depan; verifikasi sebelum koreksi.']);
                    }
                }
                $alumni = $locked->alumni()->lockForUpdate()->first();
                if ($before['status'] === 'lulus' || $alumni) {
                    throw ValidationException::withMessages(['status' => 'Koreksi kelulusan tidak aman: data alumni/riwayat harus diverifikasi; pembatalan otomatis tidak tersedia.']);
                }
                if ($after['status'] === 'lulus') {
                    $last = Kelas::find($before['kelas_id']);
                    $period = $last?->tahunAjaran;
                    if (! $last || ! $period || ! $period->mulai || ! $period->selesai || ($period->mulai && $period->mulai->toDateString() > $today) || ($period->selesai && $period->selesai->toDateString() < $today)) {
                        throw ValidationException::withMessages(['status' => 'Kelulusan memerlukan kelas terakhir dan periode tanggal berjalan yang terverifikasi.']);
                    }
                    \App\Models\Alumni::create(['siswa_nis' => $locked->nis, 'tahun_lulus' => $period->kode, 'tanggal_lulus' => $today, 'kelas_terakhir_id' => $last->id, 'keterangan' => $data['alasan']]);
                    $data['kelas_id'] = $after['kelas_id'] = null;
                }
                foreach ($members as $member) {
                    $member->update(['aktif' => false, 'status' => $after['status'] === 'lulus' ? 'lulus' : 'pindah', 'tanggal_selesai' => $today,
                        'catatan' => trim(($member->catatan ? $member->catatan."\n" : '').$data['alasan'])]);
                }
                if ($after['status'] === 'aktif' && $after['kelas_id']) {
                    $kelas = Kelas::findOrFail($after['kelas_id']);
                    $this->validasiTanggalKelas($kelas, $today);
                    RiwayatKelasSiswa::daftarkan($locked, $kelas, $kelas->tahun_ajaran_kode, $today, $data['alasan']);
                }
            }
            $locked->update(collect($data)->only(['nisn', 'nama', 'jenis_kelamin', 'kelas_id', 'no_hp', 'alamat', 'status'])->all());
            if ($changed) {
                if ($before['status'] !== $locked->status) {
                    $locked->akun()->update(['status' => $locked->status === 'aktif' ? 'aktif' : 'nonaktif']);
                }
                Kelas::where('ketua_nis', $locked->nis)->when($locked->status === 'aktif' && $locked->kelas_id, fn ($q) => $q->where('id', '!=', $locked->kelas_id))->update(['ketua_nis' => null]);
                AuditAdmin::catatKritis('siswa.lifecycle', 'siswa', $locked->nis, 'Koreksi kelas/status siswa.', [
                    'sebelum' => $before, 'sesudah' => $locked->only(['kelas_id', 'status']), 'memiliki_alasan' => true,
                ]);
            }
            $siswa->setRawAttributes($locked->getAttributes(), true)->unsetRelations();
            return $siswa;
        });
    }
}
