<?php

namespace Workdo\VideoProduction\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionSetting extends Model
{
    protected $table = 'video_production_settings';

    protected $fillable = [
        'created_by', 'monthly_reel_target', 'monthly_static_target',
        'minimum_shoots', 'maximum_shoots', 'included_hours_per_shoot',
        'required_lead_days', 'included_revisions', 'working_days',
        'workflow_effective_date',
    ];

    protected function casts(): array
    {
        return [
            'included_hours_per_shoot' => 'decimal:2',
            'working_days' => 'array',
            'workflow_effective_date' => 'date',
        ];
    }

    public static function forCompany(int $companyId): self
    {
        return static::firstOrCreate(
            ['created_by' => $companyId],
            ['working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']]
        );
    }
}
