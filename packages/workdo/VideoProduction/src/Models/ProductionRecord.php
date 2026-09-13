<?php

namespace Workdo\VideoProduction\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;

class ProductionRecord extends Model
{
    protected $table = 'video_production_records';

    protected $fillable = ['type', 'record_key', 'recorded_at', 'status', 'data', 'creator_id', 'created_by'];

    protected static function booted(): void
    {
        static::deleted(function (ProductionRecord $record) {
            $proofImagePath = data_get($record->data, 'proof_image_path');
            if ($proofImagePath) {
                Storage::disk('public')->delete($proofImagePath);
            }
            collect(data_get($record->data, 'supporting_files', []))
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

    public static function nextKey(int $companyId, string $type): string
    {
        $labels = [
            'shoot' => 'SHOOT',
            'deliverable' => 'DEL',
        ];
        $companyName = company_setting('company_name', $companyId)
            ?: User::query()->find($companyId)?->name
            ?: 'Production';
        $companyCode = substr(preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii($companyName))), 0, 3);
        $prefix = ($companyCode ?: 'PRO').'-'.now()->format('Y').'-'.($labels[$type] ?? strtoupper($type)).'-';
        $sequence = static::query()
            ->where('created_by', $companyId)
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
