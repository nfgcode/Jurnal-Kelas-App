<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\AuditAdmin;
use App\Support\SiklusHidupSiswa;
use DomainException;
use Illuminate\Support\Facades\DB;

class AktivasiTahunAjaran
{
    /** Shared lock order: singleton, periods, classes, students. Caller owns transaction. */
    public static function kunci(): void
    {
        if (! DB::table('academic_operation_lock')->where('id', 1)->lockForUpdate()->first()) {
            throw new DomainException('Kunci operasi akademik tidak tersedia.');
        }
    }

    public function aktifkan(string $kode, ?User $admin = null): void
    {
        DB::transaction(function () use ($kode, $admin) {
            self::kunci();
            $periods = TahunAjaran::orderBy('kode')->lockForUpdate()->get();
            $target = $periods->firstWhere('kode', $kode);
            $source = $periods->firstWhere('aktif', true);
            if (! $target || $target->aktif || ! TahunAjaran::formatValid($kode)
                || ($source && (! TahunAjaran::formatValid($source->kode) || strcmp($kode, $source->kode) <= 0))) {
                throw new DomainException('Tahun asal/tujuan aktivasi tidak valid.');
            }
            if (! $target->mulai || ! $target->selesai || $target->mulai->gt($target->selesai)) {
                throw new DomainException('Tanggal tahun tujuan wajib lengkap dan berurutan.');
            }
            Kelas::orderBy('id')->lockForUpdate()->get();
            $students = Siswa::with('kelas.jurusan')->where('status', 'aktif')->orderBy('nis')->lockForUpdate()->get();
            // No implicit empty-school policy. At least one verified active placement is mandatory.
            if ($students->isEmpty()) {
                throw new DomainException('Aktivasi memerlukan siswa aktif dengan penempatan terverifikasi.');
            }
            foreach ($students as $student) {
                $class = $student->kelas;
                if (! $class || $class->tahun_ajaran_kode !== $kode
                    || ! in_array($class->tingkat, ['X', 'XI', 'XII'], true)
                    || ($class->jurusan && ! $class->jurusan->aktif)) {
                    throw new DomainException('Seluruh siswa aktif wajib ditempatkan di kelas tahun tujuan yang valid.');
                }
            }
            app(SiklusHidupSiswa::class)->validasiBatch($students, today()->toDateString());
            foreach ($students as $student) {
                $members = $student->riwayatKelas()->where('aktif', true)->lockForUpdate()->get();
                $member = $members->first();
                if ($members->count() !== 1 || ! $member
                    || (string) $member->kelas_id !== (string) $student->kelas_id
                    || $member->tahun_ajaran_kode !== $kode || $member->tingkat !== $student->kelas->tingkat
                    || ! in_array($member->status, ['aktif', 'tinggal_kelas'], true) || $member->tanggal_selesai !== null
                    || ! $member->tanggal_mulai
                    || $member->tanggal_mulai->lt($target->mulai) || $member->tanggal_mulai->gt($target->selesai)) {
                    throw new DomainException('Aktivasi memerlukan tepat satu riwayat aktif dengan kelas, tingkat, tahun, dan tanggal dalam periode tujuan yang terverifikasi.');
                }
            }
            TahunAjaran::where('aktif', true)->update(['aktif' => false]);
            TahunAjaran::where('kode', $kode)->update(['aktif' => true]);
            AuditAdmin::catatKritis('tahun_ajaran.aktifkan', 'tahun_ajaran', $kode,
                "Mengaktifkan tahun ajaran {$kode}", ['tahun_lama' => $source?->kode, 'tahun_baru' => $kode], user: $admin);
        });
    }
}
