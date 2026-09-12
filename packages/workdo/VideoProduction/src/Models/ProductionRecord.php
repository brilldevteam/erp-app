<?php

namespace Workdo\VideoProduction\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductionRecord extends Model
{
    protected $table = 'video_production_records';

    protected $fillable = ['production_job_id', 'type', 'record_key', 'recorded_at', 'status', 'data', 'creator_id', 'created_by'];

    protected static function booted(): void
    {
        static::deleted(function (ProductionRecord $record) {
            $proofImagePath = data_get($record->data, 'proof_image_path');
            if ($proofImagePath) {
                Storage::disk('public')->delete($proofImagePath);
            }
        });
    }

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'data' => 'array'];
    }

    public function scopeForCompany(Builder $query, ?int $companyId = null): Builder
    {
        return $query->where('created_by', $companyId ?? creatorId());
    }

    public function job()
    {
        return $this->belongsTo(ProductionJob::class, 'production_job_id');
    }

    public static function nextKey(ProductionJob $job, string $type): string
    {
        $labels = [
            'shoot' => 'SHOOT',
            'deliverable' => 'DEL',
            'revision' => 'REV',
            'time' => 'TIME',
            'evidence' => 'EVD',
        ];
        $prefix = $job->reference.'-'.($labels[$type] ?? strtoupper($type)).'-';
        $sequence = static::query()
            ->where('created_by', $job->created_by)
            ->where('production_job_id', $job->id)
            ->where('type', $type)
            ->where('record_key', 'like', $prefix.'%')
            ->pluck('record_key')
            ->map(function (string $key) use ($prefix) {
                $suffix = substr($key, strlen($prefix));

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() + 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
