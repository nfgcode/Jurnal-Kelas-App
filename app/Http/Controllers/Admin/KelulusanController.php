<?php

namespace App\Http\Controllers\Admin;

use App\AcademicWorkflows\GraduationSpreadsheet;
use App\AcademicWorkflows\Operation;
use App\AcademicWorkflows\WorkflowService;
use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KelulusanController extends Controller
{
    public function __construct(private WorkflowService $workflowService) {}

    public function index(Request $request)
    {
        $tahunList = TahunAjaran::orderByDesc('kode')->get();
        $tahunAktif = TahunAjaran::where('aktif', true)->first();

        $tahunAsal = $request->query('tahun_asal') ?: ($tahunAktif?->kode ?: $tahunList->first()?->kode);

        $tahunTujuanList = $tahunList->filter(function ($t) use ($tahunAsal) {
            return ! $t->aktif && $tahunAsal && strcmp($t->kode, $tahunAsal) > 0;
        })->sortBy('kode')->values();

        $tahunTujuan = $request->query('tahun_tujuan') ?: $tahunTujuanList->first()?->kode;

        $siswaCount = 0;
        if ($tahunAsal) {
            $siswaCount = Siswa::where('status', 'aktif')
                ->whereIn('kelas_id', Kelas::where('tahun_ajaran_kode', $tahunAsal)->where('tingkat', 'XII')->select('id'))
                ->count();
        }

        $operations = Operation::where('kind', 'graduation')
            ->with(['actor'])
            ->latest('id')
            ->paginate(10);

        $aiPrompt = GraduationSpreadsheet::aiPrompt($tahunAsal ?: '2026/2027');

        $coordinatorId = app(\App\AcademicWorkflows\Coordinator::class)->resolveValidCoordinatorId(
            $request->integer('coordinator_id') ?: null,
            'graduation',
            $tahunAsal,
            $tahunTujuan
        );

        return view('admin.kelulusan.index', [
            'tahunList' => $tahunList,
            'tahunAktif' => $tahunAktif,
            'tahunAsal' => $tahunAsal,
            'tahunTujuanList' => $tahunTujuanList,
            'tahunTujuan' => $tahunTujuan,
            'siswaCount' => $siswaCount,
            'operations' => $operations,
            'aiPrompt' => $aiPrompt,
            'coordinatorId' => $coordinatorId,
        ]);
    }

    public function template(Request $request): BinaryFileResponse
    {
        $sourceYear = $request->query('tahun_asal') ?: '2026/2027';

        return GraduationSpreadsheet::templateXlsx($sourceYear);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'berkas' => ['required', 'file', 'max:10240'],
            'tahun_asal' => ['required', 'string', 'exists:tahun_ajaran,kode'],
            'tahun_tujuan' => ['nullable', 'string', 'exists:tahun_ajaran,kode'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'official_reference' => ['required', 'string', 'max:80'],
            'coordinator_id' => ['nullable', 'integer', 'exists:academic_workflow_coordinators,id'],
        ]);

        $file = $request->file('berkas');
        $filePath = $file->getRealPath();

        try {
            $parsed = GraduationSpreadsheet::parse(
                $filePath,
                $data['tahun_asal'],
                $data['tahun_tujuan'] ?? null,
                $file->getClientOriginalName()
            );
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['berkas' => 'Gagal membaca berkas: ' . $e->getMessage()]);
        }

        $metadata = [
            'official_verified' => true,
            'official_reference' => $data['official_reference'],
            'source_content_hash' => $parsed['file_hash'],
            'source_version' => 'v1',
            'effective_date' => $data['effective_date'],
        ];

        // Format items for WorkflowService::prepare
        $items = [];
        foreach ($parsed['items'] as $item) {
            $items[] = [
                'student_ref' => $item['student_ref'],
                'decision' => $item['decision'] ?? 'unverified',
                'target_class_id' => $item['target_class_id'] ?? null,
            ];
        }

        try {
            $actor = $this->workflowService->authorize(Auth::user());

            // Prepare dan attach dibungkus dalam singleton AcademicOperation agar jika attach gagal,
            // draft operasi tidak tertinggal sebagai operasi yatim tanpa status/linking yang jelas.
            $op = \App\Support\AcademicOperation::run(function () use ($data, $items, $metadata, $actor) {
                // Validasi ketat coordinator_id jika disediakan sebelum membuat operation
                if (! empty($data['coordinator_id'])) {
                    $coord = DB::table('academic_workflow_coordinators')->where('id', $data['coordinator_id'])->lockForUpdate()->first();
                    if (! $coord) {
                        throw new DomainException('workflow_coordinator_missing');
                    }
                    if ($coord->source_year !== null && $coord->source_year !== $data['tahun_asal']) {
                        throw new DomainException('workflow_stage_attachment_invalid');
                    }
                    if ($coord->target_year !== null && ! empty($data['tahun_tujuan']) && $coord->target_year !== $data['tahun_tujuan']) {
                        throw new DomainException('workflow_stage_attachment_invalid');
                    }

                    $stage = DB::table('academic_workflow_stages')
                        ->where('coordinator_id', $data['coordinator_id'])
                        ->where('kind', 'graduation')
                        ->lockForUpdate()
                        ->first();

                    if (! $stage || $stage->operation_id !== null) {
                        throw new DomainException('workflow_stage_attachment_invalid');
                    }
                }

                $operation = $this->workflowService->prepare(
                    'graduation',
                    $data['tahun_asal'],
                    $data['tahun_tujuan'] ?? null,
                    $items,
                    $metadata,
                    $actor
                );

                if (! empty($data['coordinator_id'])) {
                    (new \App\AcademicWorkflows\Coordinator($this->workflowService))->attach(
                        (int) $data['coordinator_id'],
                        'graduation',
                        $operation->id,
                        $actor
                    );
                }

                return $operation;
            });

            $this->workflowService->validate($op->id, $actor);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['berkas' => 'Validasi gagal: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.kelulusan.review', $op->id)
            ->with('status', 'Berkas kelulusan berhasil diunggah. Silakan periksa pratinjau.');
    }

    public function review(Request $request, int $id)
    {
        $op = Operation::with(['items'])->findOrFail($id);

        if ($op->state === 'completed') {
            return redirect()->route('admin.kelulusan.show', $op->id);
        }

        $preview = $this->workflowService->preview($id, Auth::user());

        $q = trim((string) $request->query('q', ''));
        $filterStatus = $request->query('status', 'semua');

        // Map student names from snapshot
        $studentNames = collect($preview->snapshot['students'] ?? [])->pluck('nis', 'nis')->all();
        // Look up student names from DB for friendly display
        $dbStudents = Siswa::whereIn('nis', array_column($preview->items, 'student_ref'))
            ->pluck('nama', 'nis')
            ->all();

        $items = [];
        $countLulus = 0;
        $countTidakLulus = 0;
        $countUnverified = 0;
        $countMasalah = 0;

        foreach ($preview->items as $item) {
            $nis = $item['student_ref'];
            $nama = $dbStudents[$nis] ?? $nis;
            $decision = $item['decision'];
            $validation = $item['validation'];
            $errors = $item['errors'];

            $isMasalah = ($validation !== 'valid') || ! empty($errors);
            if ($isMasalah) {
                $countMasalah++;
            }
            if ($decision === 'lulus') {
                $countLulus++;
            } elseif ($decision === 'tidak_lulus') {
                $countTidakLulus++;
            } else {
                $countUnverified++;
            }

            // Filter search
            if ($q !== '') {
                if (! str_contains(strtolower($nis), strtolower($q)) && ! str_contains(strtolower($nama), strtolower($q))) {
                    continue;
                }
            }

            // Filter status
            if ($filterStatus === 'masalah' && ! $isMasalah) {
                continue;
            }
            if ($filterStatus === 'lulus' && $decision !== 'lulus') {
                continue;
            }
            if ($filterStatus === 'tidak_lulus' && $decision !== 'tidak_lulus') {
                continue;
            }
            if ($filterStatus === 'unverified' && $decision !== 'unverified') {
                continue;
            }

            $items[] = array_merge($item, [
                'nama' => $nama,
                'is_masalah' => $isMasalah,
            ]);
        }

        // Available classes in target year for Grade XII
        $targetClasses = [];
        if ($op->target_year) {
            $targetClasses = Kelas::where('tahun_ajaran_kode', $op->target_year)
                ->where('tingkat', 'XII')
                ->get();
        }

        // Group items for display:
        // 1. Masalah / Belum Terverifikasi
        // 2. Tidak Lulus
        // 3. Lulus
        $grouped = [
            'masalah' => array_filter($items, fn ($i) => $i['is_masalah']),
            'tidak_lulus' => array_filter($items, fn ($i) => ! $i['is_masalah'] && $i['decision'] === 'tidak_lulus'),
            'lulus' => array_filter($items, fn ($i) => ! $i['is_masalah'] && $i['decision'] === 'lulus'),
        ];

        return view('admin.kelulusan.review', [
            'op' => $op,
            'preview' => $preview,
            'items' => $items,
            'grouped' => $grouped,
            'targetClasses' => $targetClasses,
            'q' => $q,
            'filterStatus' => $filterStatus,
            'summary' => [
                'total' => count($preview->items),
                'lulus' => $countLulus,
                'tidak_lulus' => $countTidakLulus,
                'unverified' => $countUnverified,
                'masalah' => $countMasalah,
            ],
        ]);
    }

    public function updateItem(Request $request, int $id, int $itemId)
    {
        $op = Operation::with('items')->findOrFail($id);

        $data = $request->validate([
            'decision' => ['required', 'in:lulus,tidak_lulus,unverified'],
            'target_class_id' => ['nullable', 'exists:kelas,id'],
            'alasan' => ['nullable', 'string', 'max:255'],
        ]);

        $currentItems = $op->items->map(function ($item) use ($itemId, $data) {
            if ($item->id === $itemId) {
                return [
                    'student_ref' => $item->student_ref,
                    'decision' => $data['decision'],
                    'target_class_id' => $data['target_class_id'] ?? null,
                ];
            }

            return [
                'student_ref' => $item->student_ref,
                'decision' => $item->decision,
                'target_class_id' => $item->target_class_id,
            ];
        })->all();

        try {
            $this->workflowService->revise($id, $currentItems, $op->source_metadata, Auth::user());
            $this->workflowService->validate($id, Auth::user());
        } catch (DomainException $e) {
            return redirect()->route('admin.kelulusan.review', $id)->withErrors(['item' => 'Gagal memperbarui item: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.kelulusan.review', $id)->with('status', 'Keputusan siswa berhasil diperbarui.');
    }

    public function validateOp(int $id)
    {
        try {
            $this->workflowService->validate($id, Auth::user());
        } catch (DomainException $e) {
            return redirect()->route('admin.kelulusan.review', $id)->withErrors(['validate' => 'Validasi gagal: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.kelulusan.review', $id)->with('status', 'Validasi pratinjau berhasil diperbarui.');
    }

    public function confirmAndExecute(Request $request, int $id)
    {
        $op = Operation::findOrFail($id);

        if ($op->state === 'completed') {
            return redirect()->route('admin.kelulusan.show', $op->id);
        }

        try {
            // Re-validate to get latest snapshot
            $op = $this->workflowService->validate($id, Auth::user());

            if ($op->state !== 'ready') {
                return back()->withErrors([
                    'execute' => 'Kelulusan belum siap dieksekusi. Pastikan semua siswa telah terverifikasi dan tidak ada masalah yang tersisa.',
                ]);
            }

            $token = $this->workflowService->confirm(
                $id,
                $op->version,
                $op->snapshot_hash,
                Auth::user()
            );

            $this->workflowService->execute($id, $token, Auth::user());
        } catch (DomainException $e) {
            return back()->withErrors(['execute' => 'Eksekusi kelulusan gagal: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.kelulusan.show', $op->id)
            ->with('status', 'Kelulusan Kelas XII berhasil dieksekusi.');
    }

    public function show(int $id)
    {
        $op = Operation::with(['items', 'actor'])->findOrFail($id);

        if ($op->state !== 'completed') {
            return redirect()->route('admin.kelulusan.review', $op->id);
        }

        $lulusItems = $op->items()->where('decision', 'lulus')->get();
        $tidakLulusItems = $op->items()->where('decision', 'tidak_lulus')->with('targetClass')->get();

        $alumni = Alumni::where('tahun_lulus', $op->source_year)
            ->whereIn('siswa_nis', $lulusItems->pluck('student_ref'))
            ->with(['siswa', 'kelasTerakhir'])
            ->get();

        $audits = DB::table('academic_workflow_audits')
            ->where('operation_id', $op->id)
            ->orderBy('created_at')
            ->get();

        return view('admin.kelulusan.show', [
            'op' => $op,
            'lulusItems' => $lulusItems,
            'tidakLulusItems' => $tidakLulusItems,
            'alumni' => $alumni,
            'audits' => $audits,
        ]);
    }
}
