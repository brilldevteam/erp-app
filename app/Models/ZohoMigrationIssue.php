<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoMigrationIssue extends Model
{
    protected $fillable = ['batch_id', 'file_id', 'staged_row_id', 'tenant_id', 'stage_key', 'type', 'severity', 'field', 'source_value', 'message', 'suggestions', 'resolution_url', 'status'];

    protected function casts(): array
    {
        return ['suggestions' => 'array'];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ZohoMigrationFile::class, 'file_id');
    }

    public function stagedRow(): BelongsTo
    {
        return $this->belongsTo(ZohoMigrationStagedRow::class, 'staged_row_id');
    }
}
