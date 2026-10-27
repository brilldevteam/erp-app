<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoMigrationStagedRow extends Model
{
    protected $fillable = [
        'batch_id', 'file_id', 'tenant_id', 'sheet_name', 'source_row_number',
        'source_external_id', 'original_values', 'normalized_values', 'status',
        'validation_errors',
    ];

    protected function casts(): array
    {
        return [
            'original_values' => 'array',
            'normalized_values' => 'array',
            'validation_errors' => 'array',
        ];
    }
}
