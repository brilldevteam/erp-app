<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoMigrationStage extends Model
{
    protected $fillable = ['batch_id', 'tenant_id', 'stage_key', 'position', 'status', 'accepted_count', 'rejected_count', 'warning_count', 'completed_at', 'completed_by', 'summary'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'summary' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ZohoMigrationBatch::class, 'batch_id');
    }
}
