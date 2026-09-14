<?php

namespace Workdo\VideoProduction\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionSetting extends Model
{
    protected $table = 'video_production_settings';

    protected $fillable = [
        'project_id', 'created_by', 'monthly_reel_target', 'monthly_static_target',
        'minimum_shoots', 'maximum_shoots', 'included_hours_per_shoot',
        'required_lead_days', 'included_revisions', 'working_days',
        'workflow_effective_date',
        'report_email_enabled', 'report_email_recipients', 'report_email_cc',
        'report_email_subject', 'report_email_message',
    ];

    protected function casts(): array
    {
        return [
            'included_hours_per_shoot' => 'decimal:2',
            'working_days' => 'array',
            'workflow_effective_date' => 'date',
            'report_email_enabled' => 'boolean',
            'report_email_recipients' => 'array',
            'report_email_cc' => 'array',
        ];
    }

    public static function forCompany(int $companyId): self
    {
        return static::firstOrCreate(
            ['created_by' => $companyId, 'project_id' => null],
            ['working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']]
        );
    }

    public static function forProject(int $companyId, int $projectId): self
    {
        $defaults = static::query()->where('created_by', $companyId)->whereNull('project_id')->first();

        $values = $defaults ? $defaults->only([
            'monthly_reel_target', 'monthly_static_target', 'minimum_shoots', 'maximum_shoots',
            'included_hours_per_shoot', 'required_lead_days', 'included_revisions',
            'workflow_effective_date',
            'report_email_enabled', 'report_email_recipients', 'report_email_cc',
            'report_email_subject', 'report_email_message',
        ]) : [];
        $values['working_days'] = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];

        return static::firstOrCreate(
            ['created_by' => $companyId, 'project_id' => $projectId],
            $values
        );
    }
}
