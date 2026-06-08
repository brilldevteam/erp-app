<?php

namespace Workdo\Workflow\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowAction extends Model
{
    protected $fillable = ['workflow_id', 'type', 'config', 'message'];
    
    protected $casts = ['config' => 'array'];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
}