<?php

namespace App\Services\Documents;

use Illuminate\Database\Eloquent\Model;

class DocumentNumberService
{
    public function next(string $type, string $modelClass, string $column, ?int $tenantId = null): string
    {
        $tenantId ??= creatorId();
        $prefixKey = $type === 'quotation' ? 'quotation_number_prefix' : 'invoice_number_prefix';
        $nextKey = $type === 'quotation' ? 'quotation_next_number' : 'invoice_next_number';
        $prefix = company_setting($prefixKey, $tenantId) ?: ($type === 'quotation' ? 'QT' : 'SI');
        $padding = max(2, min(8, (int) (company_setting('document_number_padding', $tenantId) ?: 3)));
        $reset = company_setting('document_number_reset', $tenantId) ?: 'monthly';
        $period = match ($reset) {
            'never' => '',
            'yearly' => date('Y'),
            default => date('Y-m'),
        };
        $stem = $prefix.($period ? '-'.$period : '').'-';

        /** @var Model $modelClass */
        $last = $modelClass::where('created_by', $tenantId)
            ->where($column, 'like', $stem.'%')
            ->orderByRaw("LENGTH({$column}) DESC")
            ->orderBy($column, 'desc')
            ->value($column);
        $lastNumber = $last ? (int) substr($last, strlen($stem)) : 0;
        $configuredNext = max(1, (int) (company_setting($nextKey, $tenantId) ?: 1));
        $next = max($configuredNext, $lastNumber + 1);

        setSetting($nextKey, (string) ($next + 1), $tenantId, false);

        return $stem.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }
}
