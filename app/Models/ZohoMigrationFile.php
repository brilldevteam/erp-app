<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZohoMigrationFile extends Model
{
    protected $fillable = [
        'batch_id', 'tenant_id', 'uploaded_by', 'original_filename', 'disk', 'path',
        'sha256', 'size_bytes', 'mime_type', 'status', 'dataset_type', 'profile_key',
        'detection_confidence', 'headers', 'row_count', 'failure_message',
    ];

    protected function casts(): array
    {
        return ['headers' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ZohoMigrationBatch::class, 'batch_id');
    }

    public function stagedRows(): HasMany
    {
        return $this->hasMany(ZohoMigrationStagedRow::class, 'file_id');
    }
}
