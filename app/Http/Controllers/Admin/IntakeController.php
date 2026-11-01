<?php

namespace App\Http\Controllers\Admin;

use App\AcademicWorkflows\IntakeSpreadsheet;
use App\AcademicWorkflows\Operation;
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

class IntakeController extends Controller
{
    public function __construct(private WorkflowService $workflowService) {}

    public function index(Request $request)
    {
        $tahunList = TahunAjaran::orderByDesc('kode')->get();
        $tahunAktif = TahunAjaran::where('aktif', true)->first();

        // Default ke tahun ajaran berikutnya atau tahun pertama dalam daftar
        $tahunTujuan = $request->query('tahun_tujuan') ?: (
            $tahunList->first(fn ($t) => ! $t->aktif && $tahunAktif && strcmp($t->kode, $tahunAktif->kode) > 0)?->kode
            ?: ($tahunAktif?->kode ?: $tahunList->first()?->kode)
        );

        // Muat rombel kelas X yang sudah siap di tahun tujuan
        $rombelXList = collect();
        if ($tahunTujuan) {
            $rombelXList = Kelas::where('tahun_ajaran_kode', $tahunTujuan)
                ->where('tingkat', 'X')
                ->orderBy('jurusan_kode')
                ->orderBy('paralel')
                ->get();
        }

        $operations = Operation::where('kind', 'intake')
            ->with(['actor'])
            ->latest('id')
            ->paginate(10);

        $aiPrompt = IntakeSpreadsheet::aiPrompt($tahunTujuan ?: '2027/2028');

        $coordinatorId = app(\App\AcademicWorkflows\Coordinator::class)->resolveValidCoordinatorId(
            $request->integer('coordinator_id') ?: null,
            'intake',
            $tahunAktif?->kode,
            $tahunTujuan
        );

        return view('admin.intake.index', [
            'tahunList' => $tahunList,
            'tahunAktif' => $tahunAktif,
            'tahunTujuan' => $tahunTujuan,
            'rombelXList' => $rombelXList,
            'operations' => $operations,
            'aiPrompt' => $aiPrompt,
            'coordinatorId' => $coordinatorId,
        ]);
    }

    public function template(Request $request): BinaryFileResponse
    {
        $targetYear = $request->query('tahun_tujuan') ?: '2027/2028';

        return IntakeSpreadsheet::templateXlsx($targetYear);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'tahun_tujuan' => ['required', 'string', 'exists:tahun_ajaran,kode'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'official_reference' => ['required', 'string', 'max:80'],
            'berkas' => ['required', 'file', 'max:10240'],
            'coordinator_id' => ['nullable', 'integer', 'exists:academic_workflow_coordinators,id'],
        ]);

        $file = $request->file('berkas');

        try {
            $parsed = IntakeSpreadsheet::parse(
                $file->getRealPath(),
                $data['tahun_tujuan'],
                $file->getClientOriginalName()
            );
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['berkas' => 'Gagal membaca berkas: ' . $e->getMessage()]);
        }

        $items = [];
        foreach ($parsed['items'] as $item) {
            $items[] = [
                'student_ref' => $item['student_ref'],
                'nisn' => $item['nisn'],
                'email' => $item['email'],
                'target_class_id' => $item['target_class_id'] ?? null,
                'excluded' => (bool) ($item['excluded'] ?? false),
                'exclusion_reason_present' => (bool) ($item['exclusion_reason_present'] ?? false),
            ];
        }

        $metadata = [
            'official_verified' => true,
            'official_reference' => $data['official_reference'],
            'source_content_hash' => $parsed['file_hash'],
            'source_version' => 'v1',
            'effective_date' => $data['effective_date'],
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
                    if ($coord->target_year !== null && $coord->target_year !== $data['tahun_tujuan']) {
                        throw new DomainException('workflow_stage_attachment_invalid');
                    }

                    $stage = DB::table('academic_workflow_stages')
                        ->where('coordinator_id', $data['coordinator_id'])
                        ->where('kind', 'intake')
                        ->lockForUpdate()
                        ->first();

                    if (! $stage || $stage->operation_id !== null) {
                        throw new DomainException('workflow_stage_attachment_invalid');
                    }
                }

                $operation = $this->workflowService->prepare(
                    'intake',
                    null,
                    $data['tahun_tujuan'],
                    $items,
                    $metadata,
                    $actor
                );

                if (! empty($data['coordinator_id'])) {
                    (new \App\AcademicWorkflows\Coordinator($this->workflowService))->attach(
                        (int) $data['coordinator_id'],
                        'intake',
                        $operation->id,
                        $actor
                    );
                }

                return $operation;
            });

            $this->workflowService->validate($op->id, $actor);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['upload' => 'Validasi awal alur penerimaan gagal: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.intake.review', $op->id)
            ->with('status', 'Berkas calon siswa baru berhasil diunggah. Silakan periksa hasil pratinjau.');
    }

    public function review(Request $request, int $id)
    {
        $op = Operation::with(['items'])->findOrFail($id);

        if ($op->state === 'completed') {
            return redirect()->route('admin.intake.show', $op->id);
        }

        $preview = $this->workflowService->preview($id, Auth::user());

        $q = trim((string) $request->query('q', ''));
        $filterStatus = $request->query('status', 'semua');

        // Snapshot candidate meta map
        $candidateMap = collect($preview->snapshot['candidate_identity'] ?? [])
            ->keyBy('student_ref');

        // Muat kelas tingkat X yang sah pada tahun tujuan
        $availableClasses = Kelas::where('tahun_ajaran_kode', $op->target_year)
            ->where('tingkat', 'X')
            ->orderBy('jurusan_kode')
            ->orderBy('paralel')
            ->get();

        $items = [];
        $countSiap = 0;
        $countMasalah = 0;
        $countExcluded = 0;

        foreach ($preview->items as $item) {
            $nis = $item['student_ref'];
            $meta = $candidateMap->get($nis);
            $validation = $item['validation'];
            $errors = $item['errors'] ?? [];
            $isExcluded = (bool) ($item['excluded'] ?? false);

            $isMasalah = (! $isExcluded) && (($validation !== 'valid') || ! empty($errors));

            if ($isExcluded) {
                $countExcluded++;
            } elseif ($isMasalah) {
                $countMasalah++;
            } else {
                $countSiap++;
            }

            // Filter search
            if ($q !== '') {
                $matchNis = str_contains(strtolower($nis), strtolower($q));
                $matchNisn = str_contains(strtolower($meta['nisn'] ?? ''), strtolower($q));
                $matchEmail = str_contains(strtolower($meta['email'] ?? ''), strtolower($q));
                if (! $matchNis && ! $matchNisn && ! $matchEmail) {
                    continue;
                }
            }

            // Filter status
            if ($filterStatus === 'masalah' && ! $isMasalah) {
                continue;
            }
            if ($filterStatus === 'siap' && ($isMasalah || $isExcluded)) {
                continue;
            }
            if ($filterStatus === 'excluded' && ! $isExcluded) {
                continue;
            }

            $items[] = array_merge($item, [
                'nisn' => $meta['nisn'] ?? '-',
                'email' => $meta['email'] ?? '-',
                'is_masalah' => $isMasalah,
                'is_excluded' => $isExcluded,
            ]);
        }

        // Kelompokkan item untuk review:
        // 1. Masalah / Duplikasi (Perlu tindakan/pengecualian)
        // 2. Baris Dikecualikan (Excluded)
        // 3. Kandidat Siap Diproses (Valid)
        $grouped = [
            'masalah' => array_filter($items, fn ($i) => $i['is_masalah']),
            'excluded' => array_filter($items, fn ($i) => $i['is_excluded']),
            'siap' => array_filter($items, fn ($i) => ! $i['is_masalah'] && ! $i['is_excluded']),
        ];

        return view('admin.intake.review', [
            'op' => $op,
            'preview' => $preview,
            'items' => $items,
            'grouped' => $grouped,
            'availableClasses' => $availableClasses,
            'q' => $q,
            'filterStatus' => $filterStatus,
            'summary' => [
                'total' => count($preview->items),
                'siap' => $countSiap,
                'masalah' => $countMasalah,
                'excluded' => $countExcluded,
            ],
        ]);
    }

    public function updateItem(Request $request, int $id, int $itemId)
    {
        $op = Operation::with('items')->findOrFail($id);

        $data = $request->validate([
            'target_class_id' => ['nullable', 'exists:kelas,id'],
            'action' => ['required', 'in:update_class,exclude,include'],
            'exclusion_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['action'] === 'exclude' && empty(trim((string) ($data['exclusion_reason'] ?? '')))) {
            return redirect()->route('admin.intake.review', $id)
                ->withErrors(['item' => 'Alasan administratif wajib diisi jika baris dikecualikan.']);
        }

        $currentItems = $op->items->map(function ($item) use ($itemId, $data) {
            $itemArr = is_array($item) ? $item : $item->toArray();
            $idVal = $itemArr['id'] ?? null;

            if ($idVal === $itemId) {
                if ($data['action'] === 'exclude') {
                    return [
                        'student_ref' => $itemArr['student_ref'],
                        'nisn' => $itemArr['nisn'],
                        'email' => $itemArr['email'],
                        'target_class_id' => $itemArr['target_class_id'] ?? null,
                        'excluded' => true,
                        'exclusion_reason_present' => true,
                    ];
                } elseif ($data['action'] === 'include') {
                    return [
                        'student_ref' => $itemArr['student_ref'],
                        'nisn' => $itemArr['nisn'],
                        'email' => $itemArr['email'],
                        'target_class_id' => $itemArr['target_class_id'] ?? null,
                        'excluded' => false,
                        'exclusion_reason_present' => false,
                    ];
                } elseif ($data['action'] === 'update_class') {
                    return [
                        'student_ref' => $itemArr['student_ref'],
                        'nisn' => $itemArr['nisn'],
                        'email' => $itemArr['email'],
                        'target_class_id' => $data['target_class_id'] ? (int) $data['target_class_id'] : null,
                        'excluded' => (bool) $itemArr['excluded'],
                        'exclusion_reason_present' => (bool) $itemArr['exclusion_reason_present'],
                    ];
                }
            }

            return [
                'student_ref' => $itemArr['student_ref'],
                'nisn' => $itemArr['nisn'],
                'email' => $itemArr['email'],
                'target_class_id' => $itemArr['target_class_id'] ?? null,
                'excluded' => (bool) $itemArr['excluded'],
                'exclusion_reason_present' => (bool) $itemArr['exclusion_reason_present'],
            ];
        })->values()->all();

        try {
            $actor = $this->workflowService->authorize(Auth::user());
            $this->workflowService->revise($id, $currentItems, $op->source_metadata, $actor);
            $this->workflowService->validate($id, $actor);
        } catch (DomainException $e) {
            return redirect()->route('admin.intake.review', $id)
                ->withErrors(['item' => 'Gagal memperbarui item: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.intake.review', $id)
            ->with('status', 'Data kandidat siswa berhasil diperbarui.');
    }

    public function validateOp(int $id)
    {
        try {
            $actor = $this->workflowService->authorize(Auth::user());
            $this->workflowService->validate($id, $actor);
        } catch (DomainException $e) {
            return redirect()->route('admin.intake.review', $id)
                ->withErrors(['validate' => 'Validasi gagal: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.intake.review', $id)
            ->with('status', 'Validasi pratinjau berhasil diperbarui.');
    }

    public function confirmAndExecute(Request $request, int $id)
    {
        $op = Operation::findOrFail($id);

        if ($op->state === 'completed') {
            return redirect()->route('admin.intake.show', $op->id);
        }

        try {
            $actor = $this->workflowService->authorize(Auth::user());
            $op = $this->workflowService->validate($id, $actor);

            if ($op->state !== 'ready') {
                return back()->withErrors([
                    'execute' => 'Penerimaan siswa belum siap dieksekusi. Selesaikan atau kecualikan seluruh baris yang bermasalah.',
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
            return back()->withErrors(['execute' => 'Eksekusi penerimaan siswa gagal: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.intake.show', $op->id)
            ->with('status', 'Penerimaan Siswa Baru Tingkat X berhasil dieksekusi.');
    }

    public function show(int $id)
    {
        $op = Operation::with(['items', 'actor'])->findOrFail($id);

        if ($op->state !== 'completed') {
            return redirect()->route('admin.intake.review', $op->id);
        }

        $processedItems = $op->items()->where('excluded', false)->with('targetClass')->get();
        $excludedItems = $op->items()->where('excluded', true)->get();

        $students = Siswa::whereIn('nis', $processedItems->pluck('student_ref'))
            ->get()
            ->keyBy('nis');

        $audits = DB::table('academic_workflow_audits')
            ->where('operation_id', $op->id)
            ->orderBy('created_at')
            ->get();

        return view('admin.intake.show', [
            'op' => $op,
            'processedItems' => $processedItems,
            'excludedItems' => $excludedItems,
            'students' => $students,
            'audits' => $audits,
        ]);
    }
}
