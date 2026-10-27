<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZohoMigrationBatch extends Model
{
    public const ACCOUNTING_MODES = ['full_history', 'rebuild'];
    public const DUPLICATE_STRATEGIES = ['block', 'link', 'update'];

    protected $fillable = [
        'tenant_id',
        'created_by',
        'name',
        'zoho_organization_id',
        'status',
        'accounting_mode',
        'base_currency',
        'source_date_format',
        'inventory_cutover_at',
        'default_warehouse_id',
        'duplicate_strategy',
        'ai_enabled',
        'configuration_locked_at',
    ];

    protected function casts(): array
    {
        return [
            'inventory_cutover_at' => 'datetime',
            'configuration_locked_at' => 'datetime',
            'ai_enabled' => 'boolean',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(ZohoMigrationFile::class, 'batch_id');
    }
}
