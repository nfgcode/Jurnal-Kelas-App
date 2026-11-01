<?php

namespace App\AcademicWorkflows;

use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class IntakeExecutor implements Executor
{
    public function execute(Operation $operation, Preview $preview): array
    {
        if ($operation->kind !== 'intake') {
            throw new DomainException('workflow_kind_invalid');
        }

        $targetYear = $operation->target_year;
        $target = TahunAjaran::whereKey($targetYear)->firstOrFail();

        $effectiveDate = $operation->source_metadata['effective_date']
            ?? ($target->mulai ? $target->mulai->toDateString() : null);

        if (! $effectiveDate) {
            throw new DomainException('workflow_execution_rejected');
        }

        $processed = 0;
        $skipped = 0;

        foreach ($preview->items as $item) {
            $nis = $item['student_ref'];
            $isExcluded = (bool) ($item['excluded'] ?? false);

            // Baris yang dikecualikan (excluded) dilewati dan tidak dibuatkan siswa/akun/membership
            if ($isExcluded) {
                $skipped++;
                continue;
            }

            $targetClassId = $item['target_class_id'] ?? null;
            if (! $targetClassId) {
                throw new DomainException('intake_target_invalid');
            }

            $targetClass = Kelas::whereKey($targetClassId)->firstOrFail();
            if ($targetClass->tahun_ajaran_kode !== $targetYear || $targetClass->tingkat !== 'X') {
                throw new DomainException('intake_target_invalid');
            }

            // Cari snapshot candidate identity untuk metadata tambahan (nisn, email, nama)
            $candidateMeta = collect($preview->snapshot['candidate_identity'] ?? [])
                ->firstWhere('student_ref', $nis);

            $nisn = $candidateMeta['nisn'] ?? null;
            $email = $candidateMeta['email'] ?? null;

            if (! $nisn || ! $email) {
                throw new DomainException('identity_invalid');
            }

            // Validasi ulang agar tidak mengambil alih data existing
            if (Siswa::where('nis', $nis)->exists() || Siswa::where('nisn', $nisn)->exists()) {
                throw new DomainException('identity_duplicate');
            }

            if (User::where('nis', $nis)->orWhere('email', strtolower($email))->exists()) {
                throw new DomainException('identity_duplicate');
            }

            // Ambil data nama & jenis kelamin dari input berkas atau fallback
            $nama = $item['nama'] ?? ('Siswa ' . $nis);
            $jk = in_array($item['jenis_kelamin'] ?? null, ['L', 'P'], true) ? $item['jenis_kelamin'] : null;

            // 1. Buat entitas Siswa baru
            Siswa::create([
                'nis' => $nis,
                'nisn' => $nisn,
                'nama' => $nama,
                'jenis_kelamin' => $jk,
                'kelas_id' => $targetClassId,
                'status' => 'aktif',
            ]);

            // 2. Buat membership awal di riwayat_kelas_siswa
            RiwayatKelasSiswa::create([
                'siswa_nis' => $nis,
                'kelas_id' => $targetClassId,
                'tahun_ajaran_kode' => $targetYear,
                'tingkat' => 'X',
                'status' => 'aktif',
                'aktif' => true,
                'tanggal_mulai' => $effectiveDate,
                'catatan' => "Penerimaan Siswa Baru Tahun Ajaran {$targetYear}",
            ]);

            // 3. Buat akun login siswa di tabel users
            // Mekanisme kredensial aman: Password acak kuat berkriptografi (32 hex bytes)
            // yang di-hash dengan Bcrypt. Siswa dapat login/aktivasi melalui mekanisme reset password sekolah.
            // Tidak ada plain password yang dibocorkan ke log, audit, atau database.
            $initialPassword = Str::random(32);
            // Pastikan username unik, karakter diizinkan, dan panjang aman (maksimal 255)
            $baseClean = strtolower(preg_replace('/[^A-Za-z0-9_.-]/', '', $nis));
            if ($baseClean === '') {
                $baseClean = 'student';
            }
            $baseClean = substr($baseClean, 0, 40);
            $username = 'siswa_' . $baseClean;

            $candidateUsername = $username;
            $suffixIndex = 1;
            while (User::where('username', $candidateUsername)->exists()) {
                $candidateUsername = $username . '_' . Str::lower(Str::random(4)) . ($suffixIndex > 1 ? $suffixIndex : '');
                $suffixIndex++;
            }
            $username = substr($candidateUsername, 0, 50);

            // Validasi regex username sebelum persistensi
            if (! preg_match('/\A[A-Za-z0-9._-]{3,255}\z/D', $username)) {
                $username = 'siswa_' . Str::lower(Str::random(12));
            }

            User::create([
                'username' => $username,
                'email' => strtolower($email),
                'password' => Hash::make($initialPassword),
                'role' => 'siswa',
                'status' => 'aktif',
                'nis' => $nis,
            ]);

            $processed++;
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => 0,
        ];
    }
}
