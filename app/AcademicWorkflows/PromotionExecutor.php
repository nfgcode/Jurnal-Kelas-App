<?php

namespace App\AcademicWorkflows;

use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use DomainException;

final class PromotionExecutor implements Executor
{
    public function execute(Operation $operation, Preview $preview): array
    {
        if ($operation->kind !== 'promotion') {
            throw new DomainException('workflow_kind_invalid');
        }

        $sourceYear = $operation->source_year;
        $targetYear = $operation->target_year;

        $source = TahunAjaran::whereKey($sourceYear)->firstOrFail();
        $target = TahunAjaran::whereKey($targetYear)->firstOrFail();

        $sourceEndDate = $source->selesai ? $source->selesai->toDateString() : null;
        $targetStartDate = $target->mulai ? $target->mulai->toDateString() : null;

        if (! $sourceEndDate || ! $targetStartDate) {
            throw new DomainException('workflow_execution_rejected');
        }

        $processed = 0;

        foreach ($preview->items as $item) {
            $nis = $item['student_ref'];
            $decision = $item['decision'];
            $targetClassId = $item['target_class_id'];

            if (! in_array($decision, ['naik', 'tinggal_kelas'], true)) {
                throw new DomainException('promotion_decision_invalid');
            }

            if (! $targetClassId) {
                throw new DomainException('target_class_invalid');
            }

            $targetClass = Kelas::whereKey($targetClassId)->firstOrFail();
            if ($targetClass->tahun_ajaran_kode !== $targetYear) {
                throw new DomainException('target_class_invalid');
            }

            // Ambil riwayat kelas aktif saat ini
            $activeRiwayat = RiwayatKelasSiswa::where('siswa_nis', $nis)
                ->where('aktif', true)
                ->first();

            $sourceClass = $activeRiwayat ? Kelas::find($activeRiwayat->kelas_id) : null;
            if ($sourceClass && $sourceClass->tingkat === 'XII') {
                // Siswa XII tidak boleh diproses dalam alur kenaikan kelas
                throw new DomainException('promotion_decision_invalid');
            }

            // 1. Tutup keanggotaan kelas lama pada tanggal selesai tahun sumber
            $historyStatus = $decision === 'tinggal_kelas' ? 'tinggal_kelas' : 'aktif';
            $catatanLama = $decision === 'naik'
                ? "Naik ke tingkat {$targetClass->tingkat} ({$targetYear})"
                : "Tinggal kelas di tingkat {$targetClass->tingkat} ({$targetYear})";

            RiwayatKelasSiswa::where('siswa_nis', $nis)
                ->where('aktif', true)
                ->update([
                    'aktif' => false,
                    'status' => $historyStatus,
                    'tanggal_selesai' => $sourceEndDate,
                    'catatan' => $catatanLama,
                ]);

            // 2. Buka keanggotaan kelas baru pada tanggal mulai tahun tujuan
            RiwayatKelasSiswa::create([
                'siswa_nis' => $nis,
                'kelas_id' => $targetClassId,
                'tahun_ajaran_kode' => $targetYear,
                'tingkat' => $targetClass->tingkat,
                'status' => $decision === 'naik' ? 'aktif' : 'tinggal_kelas',
                'aktif' => true,
                'tanggal_mulai' => $targetStartDate,
                'catatan' => $decision === 'naik'
                    ? "Kenaikan kelas dari tahun {$sourceYear}"
                    : "Mengulang tingkat {$targetClass->tingkat} pada tahun {$targetYear}",
            ]);

            // 3. Perbarui kelas_id siswa secara atomik (status tetap 'aktif')
            Siswa::where('nis', $nis)->update([
                'status' => 'aktif',
                'kelas_id' => $targetClassId,
            ]);

            // 4. Rekonsiliasi jabatan ketua kelas (jika siswa pindah kelas, lepaskan jabatan ketua di kelas lama)
            Kelas::where('ketua_nis', $nis)
                ->where('id', '!=', $targetClassId)
                ->update(['ketua_nis' => null]);

            $processed++;
        }

        return [
            'processed' => $processed,
            'skipped' => 0,
            'failed' => 0,
        ];
    }
}
