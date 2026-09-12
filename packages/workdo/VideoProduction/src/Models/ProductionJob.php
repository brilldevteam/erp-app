<?php

namespace Workdo\VideoProduction\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductionJob extends Model
{
    protected $table = 'video_production_jobs';

    public const STATUSES = [
        'draft', 'planning', 'shoot_scheduled', 'shoot_confirmed',
        'shoot_completed', 'editing', 'internal_review', 'client_review',
        'revision', 'approved', 'delivered', 'archived', 'cancelled',
    ];

    protected $fillable = [
        'name', 'reference', 'description', 'status',
        'start_date', 'end_date', 'creator_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function scopeForCompany(Builder $query, ?int $companyId = null): Builder
    {
        return $query->where('created_by', $companyId ?? creatorId());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function records()
    {
        return $this->hasMany(ProductionRecord::class, 'production_job_id');
    }

    public static function nextReference(?int $companyId = null): string
    {
        $companyId ??= creatorId();
        $companyName = company_setting('company_name', $companyId)
            ?: User::query()->find($companyId)?->name
            ?: 'Job';
        $companyCode = substr(preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii($companyName))), 0, 3);
        $prefix = ($companyCode ?: 'JOB').'-'.now()->format('Y').'-';
        $lastReference = static::query()
            ->where('created_by', $companyId)
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');
        $sequence = $lastReference ? ((int) substr($lastReference, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
