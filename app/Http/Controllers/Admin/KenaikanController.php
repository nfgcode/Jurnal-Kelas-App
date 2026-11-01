<?php

namespace App\Http\Controllers\Admin;

use App\AcademicWorkflows\Operation;
use App\AcademicWorkflows\PromotionSpreadsheet;
use App\AcademicWorkflows\WorkflowService;
use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KenaikanController extends Controller
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

        $siswaCountX = 0;
        $siswaCountXI = 0;
        if ($tahunAsal) {
            $siswaCountX = Siswa::where('status', 'aktif')
                ->whereIn('kelas_id', Kelas::where('tahun_ajaran_kode', $tahunAsal)->where('tingkat', 'X')->select('id'))
                ->count();
            $siswaCountXI = Siswa::where('status', 'aktif')
                ->whereIn('kelas_id', Kelas::where('tahun_ajaran_kode', $tahunAsal)->where('tingkat', 'XI')->select('id'))
                ->count();
        }

        $operations = Operation::where('kind', 'promotion')
            ->with(['actor'])
            ->latest('id')
            ->paginate(10);

        $aiPrompt = PromotionSpreadsheet::aiPrompt($tahunAsal ?: '2026/2027');

        $coordinatorId = app(\App\AcademicWorkflows\Coordinator::class)->resolveValidCoordinatorId(
            $request->integer('coordinator_id') ?: null,
            'promotion',
            $tahunAsal,
            $tahunTujuan
        );

        return view('admin.kenaikan.index', [
            'tahunList' => $tahunList,
            'tahunAktif' => $tahunAktif,
            'tahunAsal' => $tahunAsal,
            'tahunTujuanList' => $tahunTujuanList,
            'tahunTujuan' => $tahunTujuan,
            'siswaCountX' => $siswaCountX,
            'siswaCountXI' => $siswaCountXI,
            'totalSiswa' => $siswaCountX + $siswaCountXI,
            'operations' => $operations,
            'aiPrompt' => $aiPrompt,
            'coordinatorId' => $coordinatorId,
        ]);
    }

    public function template(Request $request): BinaryFileResponse
    {
        $sourceYear = $request->query('tahun_asal') ?: '2026/2027';

        return PromotionSpreadsheet::templateXlsx($sourceYear);
    }

    public function inisiasi(Request $request)
    {
        $data = $request->validate([
            'tahun_asal' => ['required', 'string', 'exists:tahun_ajaran,kode'],
            'tahun_tujuan' => ['required', 'string', 'exists:tahun_ajaran,kode'],
            'metode' => ['required', 'in:default,upload'],
            'berkas' => ['nullable', 'file', 'max:10240'],
            'coordinator_id' => ['nullable', 'integer', 'exists:academic_workflow_coordinators,id'],
        ]);

        $source = TahunAjaran::whereKey($data['tahun_asal'])->firstOrFail();
        $target = TahunAjaran::whereKey($data['tahun_tujuan'])->firstOrFail();

        $effectiveDate = $target->mulai ? $target->mulai->toDateString() : null;
        if (! $effectiveDate) {
            return back()->withInput()->withErrors(['tahun_tujuan' => 'Tahun tujuan harus memiliki tanggal mulai yang sah.']);
        }

        $items = [];
        $fileHash = 'manual-default-roster';

        if ($data['metode'] === 'upload') {
            if (! $request->hasFile('berkas')) {
                return back()->withInput()->withErrors(['berkas' => 'Berkas spreadsheet wajib diunggah untuk metode ini.']);
            }

            $file = $request->file('berkas');
            try {
                $parsed = PromotionSpreadsheet::parse(
                    $file->getRealPath(),
                    $data['tahun_asal'],
                    $data['tahun_tujuan'],
                    $file->getClientOriginalName()
                );
            } catch (\Throwable $e) {
                return back()->withInput()->withErrors(['berkas' => 'Gagal membaca berkas: ' . $e->getMessage()]);
            }

            $fileHash = $parsed['file_hash'];
            foreach ($parsed['items'] as $item) {
                $items[] = [
                    'student_ref' => $item['student_ref'],
                    'decision' => $item['decision'] ?? 'unverified',
                    'target_class_id' => $item['target_class_id'] ?? null,
                ];
            }
        } else {
            $built = PromotionSpreadsheet::buildDefaultRoster($data['tahun_asal'], $data['tahun_tujuan']);
            foreach ($built['items'] as $item) {
                $items[] = [
                    'student_ref' => $item['student_ref'],
                    'decision' => $item['decision'],
                    'target_class_id' => $item['target_class_id'],
                ];
            }
        }

        $metadata = [
            'official_verified' => true,
            'official_reference' => 'Kenaikan-' . str_replace('/', '-', $data['tahun_asal']),
            'source_content_hash' => hash('sha256', $fileHash),
            'source_version' => 'v1',
            'effective_date' => $effectiveDate,
        ];

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
                    if ($coord->target_year !== null && $coord->target_year !== $data['tahun_tujuan']) {
                        throw new DomainException('workflow_stage_attachment_invalid');
                    }

                    $stage = DB::table('academic_workflow_stages')
                        ->where('coordinator_id', $data['coordinator_id'])
                        ->where('kind', 'promotion')
                        ->lockForUpdate()
                        ->first();

                    if (! $stage || $stage->operation_id !== null) {
                        throw new DomainException('workflow_stage_attachment_invalid');
                    }
                }

                $operation = $this->workflowService->prepare(
                    'promotion',
                    $data['tahun_asal'],
                    $data['tahun_tujuan'],
                    $items,
                    $metadata,
                    $actor
                );

                if (! empty($data['coordinator_id'])) {
                    (new \App\AcademicWorkflows\Coordinator($this->workflowService))->attach(
                        (int) $data['coordinator_id'],
                        'promotion',
                        $operation->id,
                        $actor
                    );
                }

                return $operation;
            });

            $this->workflowService->validate($op->id, $actor);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['inisiasi' => 'Validasi awal alur kenaikan kelas gagal: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.kenaikan.review', $op->id)
            ->with('status', 'Alur kenaikan kelas berhasil disiapkan. Silakan periksa pratinjau.');
    }

    public function review(Request $request, int $id)
    {
        $op = Operation::with(['items'])->findOrFail($id);

        if ($op->state === 'completed') {
            return redirect()->route('admin.kenaikan.show', $op->id);
        }

        $preview = $this->workflowService->preview($id, Auth::user());

        $q = trim((string) $request->query('q', ''));
        $filterStatus = $request->query('status', 'semua');
        $filterTingkat = $request->query('tingkat', 'semua');

        $dbStudents = Siswa::whereIn('nis', array_column($preview->items, 'student_ref'))
            ->with('kelas')
            ->get()
            ->keyBy('nis');

        $items = [];
        $countNaik = 0;
        $countTinggalKelas = 0;
        $countUnverified = 0;
        $countMasalah = 0;

        foreach ($preview->items as $item) {
            $nis = $item['student_ref'];
            $student = $dbStudents->get($nis);
            $nama = $student ? $student->nama : $nis;
            $tingkatAsal = $student?->kelas?->tingkat ?? '-';
            $decision = $item['decision'];
            $validation = $item['validation'];
            $errors = $item['errors'];

            $isMasalah = ($validation !== 'valid') || ! empty($errors);
            if ($isMasalah) {
                $countMasalah++;
            }
            if ($decision === 'naik') {
                $countNaik++;
            } elseif ($decision === 'tinggal_kelas') {
                $countTinggalKelas++;
            } else {
                $countUnverified++;
            }

            // Filter search
            if ($q !== '') {
                if (! str_contains(strtolower($nis), strtolower($q)) && ! str_contains(strtolower($nama), strtolower($q))) {
                    continue;
                }
            }

            // Filter tingkat
            if ($filterTingkat !== 'semua' && $tingkatAsal !== $filterTingkat) {
                continue;
            }

            // Filter status
            if ($filterStatus === 'masalah' && ! $isMasalah) {
                continue;
            }
            if ($filterStatus === 'naik' && $decision !== 'naik') {
                continue;
            }
            if ($filterStatus === 'tinggal_kelas' && $decision !== 'tinggal_kelas') {
                continue;
            }
            if ($filterStatus === 'unverified' && $decision !== 'unverified') {
                continue;
            }

            $items[] = array_merge($item, [
                'nama' => $nama,
                'tingkat_asal' => $tingkatAsal,
                'kelas_asal' => $student?->kelas?->nama_kelas ?? '-',
                'jurusan_kode' => $student?->kelas?->jurusan_kode ?? '-',
                'is_masalah' => $isMasalah,
            ]);
        }

        // Kelas tujuan yang tersedia pada tahun ajaran target
        $targetClasses = [];
        if ($op->target_year) {
            $targetClasses = Kelas::where('tahun_ajaran_kode', $op->target_year)
                ->orderBy('tingkat')
                ->orderBy('jurusan_kode')
                ->orderBy('paralel')
                ->get();
        }

        // Kelompokkan item:
        // 1. Masalah / Perlu Diselesaikan
        // 2. Tinggal Kelas
        // 3. Naik
        $grouped = [
            'masalah' => array_filter($items, fn ($i) => $i['is_masalah']),
            'tinggal_kelas' => array_filter($items, fn ($i) => ! $i['is_masalah'] && $i['decision'] === 'tinggal_kelas'),
            'naik' => array_filter($items, fn ($i) => ! $i['is_masalah'] && $i['decision'] === 'naik'),
        ];

        return view('admin.kenaikan.review', [
            'op' => $op,
            'preview' => $preview,
            'items' => $items,
            'grouped' => $grouped,
            'targetClasses' => $targetClasses,
            'q' => $q,
            'filterStatus' => $filterStatus,
            'filterTingkat' => $filterTingkat,
            'summary' => [
                'total' => count($preview->items),
                'naik' => $countNaik,
                'tinggal_kelas' => $countTinggalKelas,
                'unverified' => $countUnverified,
                'masalah' => $countMasalah,
            ],
        ]);
    }

    public function updateItem(Request $request, int $id, int $itemId)
    {
        $op = Operation::with('items')->findOrFail($id);

        $data = $request->validate([
            'decision' => ['required', 'in:naik,tinggal_kelas,unverified'],
            'target_class_id' => ['nullable', 'exists:kelas,id'],
            'alasan' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['decision'] === 'tinggal_kelas' && empty($data['alasan'])) {
            return redirect()->route('admin.kenaikan.review', $id)->withErrors(['item' => 'Alasan wajib diisi jika siswa tinggal kelas.']);
        }

        $items = $op->items;
        if (is_array($items)) {
            $items = collect($items);
        }
        $currentItems = $items->map(function ($item) use ($itemId, $data) {
            $itemArr = is_array($item) ? $item : $item->toArray();
            $id = $itemArr['id'] ?? null;
            if ($id === $itemId) {
                return [
                    'student_ref' => $itemArr['student_ref'],
                    'decision' => $data['decision'],
                    'target_class_id' => $data['target_class_id'] ?? null,
                ];
            }

            return [
                'student_ref' => $itemArr['student_ref'],
                'decision' => $itemArr['decision'],
                'target_class_id' => $itemArr['target_class_id'] ?? null,
            ];
        })->values()->all();

        try {
            $this->workflowService->revise($id, $currentItems, $op->source_metadata, Auth::user());
            $this->workflowService->validate($id, Auth::user());
        } catch (DomainException $e) {
            return redirect()->route('admin.kenaikan.review', $id)->withErrors(['item' => 'Gagal memperbarui item: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.kenaikan.review', $id)->with('status', 'Keputusan siswa berhasil diperbarui.');
    }

    public function validateOp(int $id)
    {
        try {
            $this->workflowService->validate($id, Auth::user());
        } catch (DomainException $e) {
            return redirect()->route('admin.kenaikan.review', $id)->withErrors(['validate' => 'Validasi gagal: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.kenaikan.review', $id)->with('status', 'Validasi pratinjau berhasil diperbarui.');
    }

    public function confirmAndExecute(Request $request, int $id)
    {
        $op = Operation::findOrFail($id);

        if ($op->state === 'completed') {
            return redirect()->route('admin.kenaikan.show', $op->id);
        }

        try {
            $actor = $this->workflowService->authorize(Auth::user());
            // Re-validate to get latest snapshot
            $op = $this->workflowService->validate($id, $actor);

            if ($op->state !== 'ready') {
                return back()->withErrors([
                    'execute' => 'Kenaikan kelas belum siap dieksekusi. Selesaikan seluruh konflik dan masalah yang tersisa.',
                ]);
            }

            $token = $this->workflowService->confirm(
                $id,
                $op->version,
                $op->snapshot_hash,
                $actor
            );

            $this->workflowService->execute($id, $token, $actor);
        } catch (DomainException $e) {
            return back()->withErrors(['execute' => 'Eksekusi kenaikan kelas gagal: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.kenaikan.show', $op->id)
            ->with('status', 'Kenaikan Kelas X–XI berhasil dieksekusi.');
    }

    public function show(int $id)
    {
        $op = Operation::with(['items', 'actor'])->findOrFail($id);

        if ($op->state !== 'completed') {
            return redirect()->route('admin.kenaikan.review', $op->id);
        }

        $naikItems = $op->items()->where('decision', 'naik')->with('targetClass')->get();
        $tinggalItems = $op->items()->where('decision', 'tinggal_kelas')->with('targetClass')->get();

        $students = Siswa::whereIn('nis', $op->items()->pluck('student_ref'))
            ->get()
            ->keyBy('nis');

        $audits = DB::table('academic_workflow_audits')
            ->where('operation_id', $op->id)
            ->orderBy('created_at')
            ->get();

        return view('admin.kenaikan.show', [
            'op' => $op,
            'naikItems' => $naikItems,
            'tinggalItems' => $tinggalItems,
            'students' => $students,
            'audits' => $audits,
        ]);
    }
}
