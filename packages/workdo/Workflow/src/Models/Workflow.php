<?php

namespace Workdo\Workflow\Models;

use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    protected $fillable = ['name', 'module', 'submodule', 'is_active', 'created_by', 'creator_id'];

    public function conditions()
    {
        return $this->hasMany(WorkflowCondition::class);
    }

    public function actions()
    {
        return $this->hasMany(WorkflowAction::class);
    }

    public function scopeForModule($query, $module)
    {
        return $query->where('module', $module);
    }
}
