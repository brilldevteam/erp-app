<?php

namespace Workdo\VideoProduction\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;

class ProductionRecord extends Model
{
    protected $table = 'video_production_records';

    protected $fillable = ['project_id', 'type', 'record_key', 'recorded_at', 'status', 'data', 'creator_id', 'created_by'];

    protected static function booted(): void
    {
        static::deleted(function (ProductionRecord $record) {
            $proofImagePath = data_get($record->data, 'proof_image_path');
            if ($proofImagePath) {
                Storage::disk('public')->delete($proofImagePath);
            }
            collect(data_get($record->data, 'supporting_files', []))
                ->merge(data_get($record->data, 'revision_1_files', []))
                ->merge(data_get($record->data, 'revision_2_files', []))
                ->merge(data_get($record->data, 'revision_3_files', []))
                ->pluck('path')
                ->filter()
                ->each(fn (string $path) => Storage::disk('public')->delete($path));
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(\Workdo\Taskly\Models\Project::class, 'project_id');
    }

    public static function nextKey(int $companyId, string $type, ?int $projectId = null, ?string $projectName = null): string
    {
        $labels = [
            'shoot' => 'SHOOT',
            'deliverable' => 'DEL',
        ];
        $scopeName = $projectName ?: company_setting('company_name', $companyId)
            ?: User::query()->find($companyId)?->name
            ?: 'Production';
        $scopeCode = substr(preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii($scopeName))), 0, 3);
        $prefix = ($scopeCode ?: 'PRO').'-'.now()->format('Y').'-'.($labels[$type] ?? strtoupper($type)).'-';
        $query = static::query()
            ->where('created_by', $companyId)
            ->where('type', $type)
            ->where('record_key', 'like', $prefix.'%');
        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }
        $sequence = $query
            ->pluck('record_key')
            ->map(function (string $key) use ($prefix) {
                $suffix = substr($key, strlen($prefix));

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() + 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
