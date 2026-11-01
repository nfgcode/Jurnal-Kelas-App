<?php
namespace Tests\Feature;

use App\AcademicWorkflows\WorkflowService;
use App\Models\{User,Siswa,RiwayatKelasSiswa,TahunAjaran};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicWorkflowReadinessTest extends TestCase
{
    use RefreshDatabase;
    private function roster(string $grade='XII'): array
    {
        $actor=User::factory()->create(['role'=>'admin','status'=>'aktif']); $this->actingAs($actor);
        $class=$this->buatKelas(['tingkat'=>$grade]);
        TahunAjaran::whereKey('2026/2027')->update(['mulai'=>'2026-07-01','selesai'=>'2027-06-30']);
        foreach (['A','B'] as $nis) {
            $s=Siswa::factory()->create(['nis'=>$nis,'kelas_id'=>$class->id,'status'=>'aktif']);
            RiwayatKelasSiswa::daftarkan($s,$class,'2026/2027','2026-07-01');
        }
        return [$actor,$class];
    }
    public function test_intake_identity_evidence_detects_global_and_batch_duplicates_but_excluded_rows_bypass(): void
    {
        [$actor,$class]=$this->roster('X'); $service=new WorkflowService;
        DB::table('siswa')->where('nis','B')->update(['nisn'=>'1234567890']);
        DB::table('users')->where('id',$actor->id)->update(['email'=>'existing@example.test']);
        $op=$service->prepare('intake',null,'2026/2027',[
            ['student_ref'=>'NEW1','nisn'=>'1234567890','email'=>'new@example.test','target_class_id'=>$class->id],
            ['student_ref'=>'NEW2','nisn'=>'9999999999','email'=>'existing@example.test','target_class_id'=>$class->id],
            ['student_ref'=>'NEW3','nisn'=>'9999999999','email'=>'NEW@example.test','target_class_id'=>$class->id],
        ],['effective_date'=>'2026-10-31'],$actor);
        DB::enableQueryLog(); DB::flushQueryLog();
        $preview=$service->preview($op->id,$actor);
        $queries=DB::getQueryLog(); DB::disableQueryLog();
        foreach ($queries as $query) $this->assertStringStartsWith('select',strtolower(ltrim($query['query'])));
        foreach ($preview->items as $item) $this->assertContains('identity_duplicate',$item['errors']);
        $this->assertSame('invalid',$preview->validation);
        $service->revise($op->id,[['student_ref'=>'NEW1','nisn'=>'bad','email'=>'bad','excluded'=>true,'exclusion_reason_present'=>true]],['effective_date'=>'2026-10-31'],$actor);
        $this->assertSame('valid',$service->preview($op->id,$actor)->validation);
        $service->revise($op->id,[['student_ref'=>'NEW1','excluded'=>true]],['effective_date'=>'2026-10-31'],$actor);
        $this->assertSame('invalid',$service->preview($op->id,$actor)->validation);
    }

    public function test_official_evidence_and_effective_dates_are_explicit_and_clock_bounded(): void
    {
        [$actor]=$this->roster(); $service=new WorkflowService;
        $items=[['student_ref'=>'A','decision'=>'lulus'],['student_ref'=>'B','decision'=>'lulus']];
        $metadata=['official_verified'=>true,'official_reference'=>'OFFICIAL','source_content_hash'=>str_repeat('a',64),'source_version'=>'v1','effective_date'=>'2026-10-31'];
        $this->travelTo(\Carbon\Carbon::parse('2026-10-31'));
        $op=$service->prepare('graduation','2026/2027',null,$items,$metadata,$actor);
        $this->assertSame('valid',$service->preview($op->id,$actor)->validation);
        $hash=$service->preview($op->id,$actor)->hash;
        $this->travelTo(\Carbon\Carbon::parse('2027-07-01'));
        $this->assertSame('invalid',$service->preview($op->id,$actor)->validation);
        $this->assertNotSame($hash,$service->preview($op->id,$actor)->hash);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-31'));
        unset($metadata['source_content_hash']);
        $service->revise($op->id,$items,$metadata,$actor);
        $this->assertSame('unverified',$service->preview($op->id,$actor)->validation);
        $metadata['source_content_hash']=str_repeat('a',64); $metadata['effective_date']='2027-07-01';
        $service->revise($op->id,$items,$metadata,$actor);
        $this->assertSame('invalid',$service->preview($op->id,$actor)->validation);
    }

    public function test_intake_requires_explicit_effective_date_inside_target_period(): void
    {
        [$actor,$class]=$this->roster('X'); $service=new WorkflowService;
        $items=[['student_ref'=>'NEW','nisn'=>'9988776655','email'=>'new@example.test','target_class_id'=>$class->id]];
        $op=$service->prepare('intake',null,'2026/2027',$items,[],$actor);
        $this->assertSame('invalid',$service->preview($op->id,$actor)->validation);
        foreach (['2026-06-30','2027-07-01','2026-02-30'] as $date) {
            $service->revise($op->id,$items,['effective_date'=>$date],$actor);
            $this->assertSame('invalid',$service->preview($op->id,$actor)->validation);
        }
        $service->revise($op->id,$items,['effective_date'=>'2026-07-01'],$actor);
        $this->assertSame('valid',$service->preview($op->id,$actor)->validation);
    }

    public function test_promotion_population_defaults_naik_but_every_source_student_needs_destination(): void
    {
        [$actor]=$this->roster('X'); $service=new WorkflowService;
        TahunAjaran::create(['kode'=>'2027/2028','aktif'=>false,'mulai'=>'2027-07-01','selesai'=>'2028-06-30']);
        $dest=$this->buatKelas(['tingkat'=>'XI','tahun_ajaran_kode'=>'2027/2028']);
        $items=[['student_ref'=>'A','target_class_id'=>$dest->id],['student_ref'=>'B','target_class_id'=>$dest->id]];
        $op=$service->prepare('promotion','2026/2027','2027/2028',$items,[],$actor);
        $this->assertSame('invalid',$service->preview($op->id,$actor)->validation);
        $metadata=['effective_date'=>'2027-07-01'];
        $service->revise($op->id,$items,$metadata,$actor);
        $this->assertSame('valid',$service->preview($op->id,$actor)->validation);
        $service->revise($op->id,[$items[0]],$metadata,$actor);
        $preview=$service->preview($op->id,$actor);
        $this->assertSame('invalid',$preview->validation);
        $this->assertCount(2,$preview->items);
        $this->assertSame('naik',$preview->items[1]['decision']);
        $this->assertContains('target_class_invalid',$preview->items[1]['errors']);
        $this->assertSame(['A','B'],$preview->snapshot['population']['student_refs']);
    }

    public function test_graduation_missing_source_student_is_unverified_and_source_roster_changes_snapshot(): void
    {
        [$actor,$class]=$this->roster(); $service=new WorkflowService;
        $op=$service->prepare('graduation','2026/2027',null,[['student_ref'=>'A','decision'=>'lulus']],['official_verified'=>true,'official_reference'=>'OFFICIAL','source_content_hash'=>str_repeat('b',64),'source_version'=>'v1','effective_date'=>'2026-10-31'],$actor);
        $first=$service->preview($op->id,$actor);
        $this->assertSame('unverified',$first->validation);
        $this->assertContains('B',array_column($first->items,'student_ref'));
        Siswa::factory()->create(['nis'=>'C','kelas_id'=>$class->id,'status'=>'aktif']);
        $this->assertNotSame($first->hash,$service->preview($op->id,$actor)->hash);
    }
}
