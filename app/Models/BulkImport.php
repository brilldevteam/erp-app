<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BulkImport extends Model
{
    protected $fillable = [
        'entity_type',
        'status',
        'strategy',
        'original_filename',
        'file_path',
        'preview_path',
        'error_path',
        'tenant_id',
        'creator_id',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'duplicate_rows',
        'new_rows',
        'processed_rows',
        'imported_rows',
        'updated_rows',
        'skipped_rows',
        'failure_message',
        'validated_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
