<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoMigrationEvent extends Model
{
    protected $fillable = ['batch_id', 'tenant_id', 'user_id', 'stage_key', 'event_type', 'message', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
