<?php
namespace App\AcademicWorkflows;

use App\Models\TahunAjaran;
use Illuminate\Support\Facades\DB;

/** SELECT-only evidence adapter. Locked reads are requested only by the executor boundary. */
final class Readiness
{
    /** Pure loaded-evidence check shared by preview and locked execution reads. */
    private static function historyOrdered(array $history, string $clock, ?string $end): bool
    {
        $active = array_values(array_filter($history,fn($row)=>(bool)$row['aktif']));
        $start = $active[0]['tanggal_mulai'] ?? null;
        if ($start && ($start > $clock || ($end && $start > $end))) return false;
        foreach ($history as $row) {
            if (!$row['tanggal_mulai'] || (!$row['aktif'] && (!$row['tanggal_selesai'] || $row['tanggal_mulai'] > $row['tanggal_selesai'] || ($start && $row['tanggal_selesai'] >= $start)))) return false;
        }
        return true;
    }
    private function rows(string $table, array $columns, bool $lock): array
    {
        $q = DB::table($table)->select($columns)->orderBy($columns[0]);
        if ($lock) $q->lockForUpdate();
        return $q->get()->map(fn ($row) => (array) $row)->all();
    }
    public function evaluate(Operation $op, bool $lock = false): Preview
    {
        $years = $this->rows('tahun_ajaran',['kode','aktif','mulai','selesai'],$lock);
        foreach ($years as &$year) foreach (['mulai','selesai'] as $field) if ($year[$field]) $year[$field]=substr($year[$field],0,10);
        unset($year);
        $classes = $this->rows('kelas',['id','tahun_ajaran_kode','tingkat','jurusan_kode','paralel'],$lock);
        $departments = $this->rows('jurusan',['kode','aktif'],$lock);
        $refs = $op->items()->orderBy('student_ref')->get();
        // Population is the complete eligible active source roster, never the submitted subset.
        $roster = [];
        if ($op->kind !== 'intake') {
            $loadRoster = DB::table('siswa')->join('kelas','kelas.id','=','siswa.kelas_id')
                ->where('siswa.status','aktif')->where('kelas.tahun_ajaran_kode',$op->source_year)
                ->whereIn('kelas.tingkat',$op->kind === 'graduation' ? ['XII'] : ['X','XI'])->orderBy('siswa.nis');
            if ($lock) $loadRoster->lockForUpdate();
            $roster = $loadRoster->pluck('siswa.nis')->all();
        }
        $submitted = $refs->pluck('student_ref')->all();
        foreach (array_diff($roster,$submitted) as $missing) $refs->push(new Item(['student_ref'=>$missing]));
        $refs = $refs->sortBy('student_ref')->values();
        $nis = $refs->pluck('student_ref')->all();
        $query = fn ($table, $columns) => DB::table($table)->select($columns)->whereIn($table === 'siswa' ? 'nis' : ($table === 'users' ? 'nis' : 'siswa_nis'), $nis)->orderBy($columns[0]);
        $load = function ($q) use ($lock) { if ($lock) $q->lockForUpdate(); return $q->get()->map(fn ($r)=>(array)$r)->all(); };
        $students = $load($query('siswa',['nis','nisn','kelas_id','status']));
        $history = $load($query('riwayat_kelas_siswa',['id','siswa_nis','kelas_id','tahun_ajaran_kode','tingkat','status','aktif','tanggal_mulai','tanggal_selesai']));
        foreach ($history as &$row) foreach (['tanggal_mulai','tanggal_selesai'] as $field) if ($row[$field]) $row[$field]=substr($row[$field],0,10);
        unset($row);
        $accounts = $op->kind === 'intake' ? $this->rows('users',['id','nis','email','role','status'],$lock) : $load($query('users',['id','nis','email','role','status']));
        if ($op->kind === 'intake') $students = $this->rows('siswa',['nis','nisn','kelas_id','status'],$lock);
        $alumni = $load($query('alumni',['id','siswa_nis']));
        $by = fn ($rows,$key,$value) => collect($rows)->first(fn($r)=>(string)$r[$key]===(string)$value);
        $source = $by($years,'kode',$op->source_year);
        $target = $by($years,'kode',$op->target_year);
        $periodValid = fn ($p) => $p && TahunAjaran::formatValid($p['kode']) && $p['mulai'] && $p['selesai'] && $p['mulai'] <= $p['selesai'];
        $clock = today()->toDateString();
        $effective = $op->source_metadata['effective_date'] ?? null;
        $dateValid = is_string($effective) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D',$effective) && checkdate((int)substr($effective,5,2),(int)substr($effective,8,2),(int)substr($effective,0,4));
        $out = [];
        foreach ($refs as $item) {
            $errors = []; $uncertain = false;
            $student = $by($students,'nis',$item->student_ref);
            $class = $by($classes,'id',$student['kelas_id'] ?? null);
            $dest = $by($classes,'id',$item->target_class_id);
            $decision = $item->decision ?? ($op->kind === 'promotion' ? 'naik' : 'unverified');
            if ($item->excluded) {
                if ($op->kind !== 'intake') $errors[] = 'exclusion_not_allowed';
                if (!$item->exclusion_reason_present) $errors[]='exclusion_reason_required';
            } elseif ($op->kind === 'intake') {
                if (!is_string($item->nisn) || !preg_match('/\A[0-9]{10}\z/D',$item->nisn) || !is_string($item->email) || !filter_var($item->email,FILTER_VALIDATE_EMAIL)) $errors[]='identity_invalid';
                $email = strtolower($item->email ?? '');
                if ($student || $by($accounts,'nis',$item->student_ref)
                    || ($item->nisn && $by($students,'nisn',$item->nisn))
                    || collect($accounts)->contains(fn($a)=>$email!=='' && strtolower($a['email'] ?? '')===$email)
                    || $refs->contains(fn($other)=>!$other->excluded && $other->student_ref!==$item->student_ref && (($item->nisn && $other->nisn===$item->nisn) || ($email!=='' && strtolower($other->email ?? '')===$email)))) $errors[]='identity_duplicate';
                if (!$dateValid || !$periodValid($target) || $effective<$target['mulai'] || $effective>$target['selesai']) $errors[]='intake_date_invalid';
                if (!$periodValid($target) || !$dest || $dest['tahun_ajaran_kode'] !== $op->target_year || $dest['tingkat'] !== 'X') $errors[]='intake_target_invalid';
            } else {
                if (!$student || $student['status'] !== 'aktif' || !$class || $class['tahun_ajaran_kode'] !== $op->source_year) $errors[]='source_student_invalid';
                $members = array_values(array_filter($history,fn($h)=>(string)$h['siswa_nis']===$item->student_ref && $h['aktif']));
                $member = $members[0] ?? null;
                if (!$periodValid($source) || count($members)!==1 || !$member || !$member['tanggal_mulai']) $uncertain=true;
                elseif (!$class || (string)$member['kelas_id']!==(string)$class['id'] || $member['tahun_ajaran_kode']!==$op->source_year || $member['tingkat']!==$class['tingkat'] || $member['tanggal_selesai'] || !in_array($member['status'],['aktif','tinggal_kelas'],true) || $member['tanggal_mulai']<$source['mulai'] || $member['tanggal_mulai']>$source['selesai']) $uncertain=true;
                $historyEnd = $op->kind === 'graduation' ? ($dateValid ? $effective : null) : ($source['selesai'] ?? null);
                if (!self::historyOrdered(array_values(array_filter($history,fn($h)=>(string)$h['siswa_nis']===$item->student_ref)),$clock,$historyEnd)) $uncertain=true;
                if ($by($alumni,'siswa_nis',$item->student_ref)) $errors[]='active_alumni_conflict';
                if ($op->kind === 'graduation') {
                    if (($class['tingkat'] ?? null)!=='XII') $errors[]='graduation_grade_invalid';
                    if (($op->source_metadata['official_verified'] ?? false)!==true || empty($op->source_metadata['official_reference']) || empty($op->source_metadata['source_version']) || !preg_match('/\A[a-f0-9]{64}\z/D',$op->source_metadata['source_content_hash'] ?? '') || $decision==='unverified') $uncertain=true;
                    if (!$dateValid || !$periodValid($source) || $effective<$source['mulai'] || $effective>$source['selesai'] || $clock<$source['mulai'] || $clock>$source['selesai'] || $effective>$clock) $errors[]='graduation_date_invalid';
                    if (!in_array($decision,['lulus','tidak_lulus','unverified'],true)) $errors[]='graduation_decision_invalid';
                    $grade = 'XII';
                    $needsTarget = $decision==='tidak_lulus';
                } else {
                    $grade = $decision==='tinggal_kelas' ? ($class['tingkat'] ?? null) : (['X'=>'XI','XI'=>'XII'][$class['tingkat'] ?? ''] ?? null);
                    if (!in_array($class['tingkat'] ?? null,['X','XI'],true) || !in_array($decision,['naik','tinggal_kelas'],true)) $errors[]='promotion_decision_invalid';
                    $needsTarget = true;
                }
                if ($needsTarget) {
                    if ($op->kind==='promotion' && (!$dateValid || !$periodValid($target) || $effective!==$target['mulai'])) $errors[]='promotion_date_invalid';
                    if (!$periodValid($target) || !$periodValid($source) || $op->target_year <= $op->source_year || $target['aktif'] || $target['mulai'] !== date('Y-m-d',strtotime($source['selesai'].' +1 day')) || today()->toDateString()>$target['mulai']) $errors[]='target_period_invalid';
                    $targetParalelMatch = ($dest && $op->kind === 'graduation')
                        ? (string)($dest['paralel'] ?? '') === (string)($class['paralel'] ?? null)
                        : true;
                    if (!$dest || $dest['tahun_ajaran_kode']!==$op->target_year || $dest['tingkat']!==$grade || $dest['jurusan_kode']!==($class['jurusan_kode'] ?? null) || !$targetParalelMatch) $errors[]='target_class_invalid';
                }
            }
            foreach ($item->excluded && $op->kind==='intake' ? [] : [$class,$dest] as $c) if ($c && $c['jurusan_kode'] && !($by($departments,'kode',$c['jurusan_kode'])['aktif'] ?? false)) $errors[]='department_inactive';
            // Unknown history remains unverified even when another prerequisite is invalid.
            $status = $uncertain ? 'unverified' : ($errors ? 'invalid' : 'valid');
            $out[]=['id'=>$item->id,'student_ref'=>$item->student_ref,'decision'=>$decision,'target_class_id'=>$item->target_class_id,'excluded'=>$item->excluded,'validation'=>$status,'errors'=>array_values(array_unique($errors))];
        }
        $statuses = array_column($out,'validation');
        $validation = !$out ? 'invalid' : (in_array('unverified',$statuses,true) ? 'unverified' : (in_array('invalid',$statuses,true) ? 'invalid':'valid'));
        if ($op->kind==='intake' && !$periodValid($target)) $validation='invalid';
        if ($op->kind!=='intake' && !$periodValid($source)) $validation='unverified';
        $snapshot=['clock_date'=>$clock,'effective_date'=>$effective,'population'=>['scope'=>$op->kind==='intake' ? 'submitted_candidates' : 'all_active_source_'.($op->kind==='graduation' ? 'XII' : 'X_XI'),'student_refs'=>$roster],'candidate_identity'=>$refs->map(fn($i)=>['student_ref'=>$i->student_ref,'nisn'=>$i->nisn,'email'=>$i->email,'exclusion_reason_present'=>(bool)$i->exclusion_reason_present])->all(),'kind'=>$op->kind,'source_year'=>$op->source_year,'target_year'=>$op->target_year,'source_metadata'=>$op->source_metadata,'years'=>$years,'classes'=>$classes,'departments'=>$departments,'students'=>$students,'history'=>$history,'accounts'=>$accounts,'alumni'=>$alumni,'items'=>$out,'validation'=>$validation];
        return new Preview($op->version,hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR)), $validation,$out,$snapshot);
    }
}
