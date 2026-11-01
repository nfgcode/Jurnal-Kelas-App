<?php
namespace App\AcademicWorkflows;
use Illuminate\Database\Eloquent\Model;
final class Operation extends Model
{
    protected $table = 'academic_workflow_operations';
    protected $guarded = ['id'];
    protected function casts(): array { return ['source_metadata'=>'array','snapshot'=>'array','result'=>'array','version'=>'integer','confirmation_expires_at'=>'datetime']; }
    public function save(array $options = [])
    {
        return \App\Support\AcademicOperation::run(function () use ($options) {
            if ($this->exists && static::whereKey($this->getKey())->lockForUpdate()->value('state') === 'completed') throw new \DomainException('workflow_immutable');
            return parent::save($options);
        });
    }
    public function delete()
    {
        throw new \DomainException('workflow_immutable');
    }
    public function items() { return $this->hasMany(Item::class, 'operation_id'); }
    public function actor() { return $this->belongsTo(\App\Models\User::class, 'actor_id'); }
}
