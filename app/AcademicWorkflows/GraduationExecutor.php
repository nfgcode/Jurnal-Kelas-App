<?php

namespace App\AcademicWorkflows;

use App\Models\Alumni;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use DomainException;

final class GraduationExecutor implements Executor
{
    public function execute(Operation $operation, Preview $preview): array
    {
        if ($operation->kind !== 'graduation') {
            throw new DomainException('workflow_kind_invalid');
        }

        $sourceYear = $operation->source_year;
        $targetYear = $operation->target_year;
        $source = TahunAjaran::whereKey($sourceYear)->firstOrFail();
        $target = $targetYear ? TahunAjaran::whereKey($targetYear)->first() : null;

        $effectiveDate = $operation->source_metadata['effective_date'] ?? $source->selesai?->toDateString();
        $officialRef = $operation->source_metadata['official_reference'] ?? 'Kelulusan Resmi';
        $sourceEndDate = $source->selesai ? $source->selesai->toDateString() : $effectiveDate;
        $targetStartDate = $target && $target->mulai ? $target->mulai->toDateString() : null;

        $processed = 0;

        foreach ($preview->items as $item) {
            $nis = $item['student_ref'];
            $decision = $item['decision'];

            if ($decision === 'lulus') {
                $activeRiwayat = RiwayatKelasSiswa::where('siswa_nis', $nis)
                    ->where('aktif', true)
                    ->first();

                $lastClassId = $activeRiwayat?->kelas_id;

                RiwayatKelasSiswa::where('siswa_nis', $nis)
                    ->where('aktif', true)
                    ->update([
                        'aktif' => false,
                        'status' => 'lulus',
                        'tanggal_selesai' => $effectiveDate,
                        'catatan' => $officialRef,
                    ]);

                Siswa::where('nis', $nis)->update([
                    'status' => 'lulus',
                    'kelas_id' => null,
                ]);

                Kelas::where('ketua_nis', $nis)->update(['ketua_nis' => null]);

                Alumni::create([
                    'siswa_nis' => $nis,
                    'tahun_lulus' => $sourceYear,
                    'tanggal_lulus' => $effectiveDate,
                    'kelas_terakhir_id' => $lastClassId,
                    'keterangan' => $officialRef,
                ]);

                $processed++;
            } elseif ($decision === 'tidak_lulus') {
                $targetClassId = $item['target_class_id'];
                if (! $targetClassId || ! $target || ! $targetStartDate) {
                    throw new DomainException('target_class_invalid');
                }

                RiwayatKelasSiswa::where('siswa_nis', $nis)
                    ->where('aktif', true)
                    ->update([
                        'aktif' => false,
                        'status' => 'tinggal_kelas',
                        'tanggal_selesai' => $sourceEndDate,
                        'catatan' => $officialRef,
                    ]);

                RiwayatKelasSiswa::create([
                    'siswa_nis' => $nis,
                    'kelas_id' => $targetClassId,
                    'tahun_ajaran_kode' => $targetYear,
                    'tingkat' => 'XII',
                    'status' => 'tinggal_kelas',
                    'aktif' => true,
                    'tanggal_mulai' => $targetStartDate,
                    'catatan' => 'Tinggal kelas XII saat kelulusan',
                ]);

                Siswa::where('nis', $nis)->update([
                    'status' => 'aktif',
                    'kelas_id' => $targetClassId,
                ]);

                Kelas::where('ketua_nis', $nis)
                    ->where('id', '!=', $targetClassId)
                    ->update(['ketua_nis' => null]);

                $processed++;
            }
        }

        return [
            'processed' => $processed,
            'skipped' => 0,
            'failed' => 0,
        ];
    }
}
