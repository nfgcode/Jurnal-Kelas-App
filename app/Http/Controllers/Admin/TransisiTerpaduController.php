<?php

namespace App\Http\Controllers\Admin;

use App\AcademicWorkflows\Coordinator;
use App\AcademicWorkflows\WorkflowService;
use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransisiTerpaduController extends Controller
{
    public function __construct(private WorkflowService $workflowService) {}

    private function coordinator(): Coordinator
    {
        return new Coordinator($this->workflowService);
    }

    public function index(Request $request)
    {
        $tahunList = TahunAjaran::orderByDesc('kode')->get();
        $tahunAktif = TahunAjaran::where('aktif', true)->first();

        $tahunAsal = $request->query('tahun_asal') ?: ($tahunAktif?->kode ?: $tahunList->first()?->kode);
        $tahunTujuanList = $tahunList->filter(function ($t) use ($tahunAsal) {
            return ! $t->aktif && $tahunAsal && strcmp($t->kode, $tahunAsal) > 0;
        })->sortBy('kode')->values();

        $tahunTujuan = $request->query('tahun_tujuan') ?: $tahunTujuanList->first()?->kode;

        // Cari sesi koordinator yang tersimpan
        $sessions = DB::table('academic_workflow_coordinators as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.actor_id')
            ->select('c.*', 'u.nama as actor_name')
            ->orderByDesc('c.id')
            ->paginate(10);

        return view('admin.transisi_terpadu.index', [
            'tahunList' => $tahunList,
            'tahunAktif' => $tahunAktif,
            'tahunAsal' => $tahunAsal,
            'tahunTujuanList' => $tahunTujuanList,
            'tahunTujuan' => $tahunTujuan,
            'sessions' => $sessions,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tahun_asal' => ['required', 'string', 'exists:tahun_ajaran,kode'],
            'tahun_tujuan' => ['required', 'string', 'exists:tahun_ajaran,kode'],
            'stages' => ['required', 'array', 'min:2', 'max:3'],
            'stages.*' => ['required', 'in:graduation,promotion,intake'],
        ]);

        $source = TahunAjaran::whereKey($data['tahun_asal'])->firstOrFail();
        $target = TahunAjaran::whereKey($data['tahun_tujuan'])->firstOrFail();

        // Validasi kalender akademik aktual
        if ($target->aktif) {
            return back()->withInput()->withErrors(['tahun_tujuan' => 'Tahun tujuan harus tahun ajaran nonaktif.']);
        }
        if (! $source->selesai || ! $target->mulai || $target->mulai->lte($source->selesai)) {
            return back()->withInput()->withErrors(['tahun_tujuan' => 'Tanggal mulai tahun tujuan harus setelah tanggal selesai tahun sumber.']);
        }

        // Cek apakah sudah ada sesi aktif untuk pasangan periode ini
        $activeSession = DB::table('academic_workflow_coordinators as c')
            ->join('academic_workflow_stages as s', 's.coordinator_id', '=', 'c.id')
            ->leftJoin('academic_workflow_operations as o', 'o.id', '=', 's.operation_id')
            ->where('c.source_year', $data['tahun_asal'])
            ->where('c.target_year', $data['tahun_tujuan'])
            ->where(function ($q) {
                $q->whereNull('s.operation_id')
                    ->orWhere('o.state', '!=', 'completed');
            })
            ->value('c.id');

        if ($activeSession) {
            return redirect()->route('admin.transisi-terpadu.show', $activeSession)
                ->with('status', 'Sesi transisi terpadu aktif untuk periode ini sudah ada. Anda dialihkan ke sesi tersebut.');
        }

        try {
            $actor = $this->workflowService->authorize(Auth::user());
            $coordinatorId = $this->coordinator()->create(
                $data['stages'],
                $actor,
                $data['tahun_asal'],
                $data['tahun_tujuan']
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['setup' => 'Gagal membuat sesi terpadu: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.transisi-terpadu.show', $coordinatorId)
            ->with('status', 'Sesi transisi terpadu berhasil dibuat. Silakan ikuti tahapan yang telah diatur.');
    }

    public function show(int $id)
    {
        $actor = $this->workflowService->authorize(Auth::user());
        $session = DB::table('academic_workflow_coordinators as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.actor_id')
            ->where('c.id', $id)
            ->select('c.*', 'u.nama as actor_name')
            ->first();

        if (! $session) {
            abort(404, 'Sesi transisi terpadu tidak ditemukan.');
        }

        $stages = $this->coordinator()->status($id, $actor);

        // Evaluasi progres keseluruhan
        $totalStages = count($stages);
        $completedStages = count(array_filter($stages, fn ($s) => $s['state'] === 'completed'));
        $isCompleted = ($totalStages > 0 && $completedStages === $totalStages);

        // Tentukan tahap aktif saat ini (tahap pertama yang belum completed)
        $currentActiveIndex = -1;
        foreach ($stages as $idx => $st) {
            if ($st['state'] !== 'completed') {
                $currentActiveIndex = $idx;
                break;
            }
        }

        $audits = DB::table('admin_audit_log')
            ->where('tipe_entitas', 'academic_workflow_coordinator')
            ->where('entitas_id', (string) $id)
            ->orderBy('created_at')
            ->get();

        return view('admin.transisi_terpadu.show', [
            'session' => $session,
            'stages' => $stages,
            'totalStages' => $totalStages,
            'completedStages' => $completedStages,
            'isCompleted' => $isCompleted,
            'currentActiveIndex' => $currentActiveIndex,
            'audits' => $audits,
        ]);
    }

    public function attach(Request $request, int $id)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:graduation,promotion,intake'],
            'operation_id' => ['required', 'integer', 'exists:academic_workflow_operations,id'],
        ]);

        try {
            $actor = $this->workflowService->authorize(Auth::user());
            $this->coordinator()->attach($id, $data['kind'], $data['operation_id'], $actor);
        } catch (DomainException $e) {
            return back()->withErrors(['attach' => 'Gagal menghubungkan operasi ke sesi: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.transisi-terpadu.show', $id)
            ->with('status', 'Operasi alur kerja berhasil dihubungkan ke tahap sesi terpadu.');
    }
}
