<?php
namespace App\AcademicWorkflows;
use Illuminate\Database\Eloquent\Model;
final class Item extends Model
{
    protected $table = 'academic_workflow_items';
    protected $fillable = ['operation_id','student_ref','decision','target_class_id','excluded','validation','errors'];
    public function save(array $options = [])
    {
        return \App\Support\AcademicOperation::run(function () use ($options) {
            $op=$this->lockedOwner();
            if (in_array($op->state,['completed','executing'],true)) throw new \DomainException('workflow_immutable');
            if (in_array($op->state,['ready','confirmed'],true) && $this->isDirty(['student_ref','decision','target_class_id','excluded','nisn','email','exclusion_reason_present'])) {
                $op->forceFill(['state'=>'draft','version'=>$op->version+1,'snapshot'=>null,'snapshot_hash'=>null,'validation'=>'unverified','confirmation_id'=>null,'confirmed_by'=>null,'confirmed_version'=>null,'confirmed_hash'=>null,'confirmed_at'=>null,'confirmation_expires_at'=>null])->save();
            }
            return parent::save($options);
        });
    }
    public function delete()
    {
        return \App\Support\AcademicOperation::run(function () {
            $op=$this->lockedOwner();
            if (in_array($op->state,['completed','executing'],true)) throw new \DomainException('workflow_immutable');
            return parent::delete();
        });
    }
    private function lockedOwner(): Operation
    {
        // Called only after the singleton lock. Never trust dirty attributes or
        // syncOriginal(): resolve the persisted FK again inside this transaction.
        $owner=$this->operation_id;
        if ($this->exists) {
            $persisted=self::whereKey($this->getRawOriginal($this->getKeyName()))->lockForUpdate()->firstOrFail();
            if ((string)$this->getKey() !== (string)$persisted->getKey() || (string)$owner !== (string)$persisted->operation_id) throw new \DomainException('workflow_immutable');
            $owner=$persisted->operation_id;
        }
        return Operation::whereKey($owner)->lockForUpdate()->firstOrFail();
    }
    protected function casts(): array { return ['excluded'=>'boolean','errors'=>'array']; }
    public function targetClass()
    {
        return $this->belongsTo(\App\Models\Kelas::class, 'target_class_id');
    }
}
