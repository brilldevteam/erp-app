<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Next document number such as "SI-2026-11-001".
 *
 * Takes the highest number already used after the prefix, compared as a number, never as text: text order puts
 * "...-999" after "...-1000", and reading only the last 3 digits made every number after 999 repeat itself.
 * Deleted (soft-deleted) records count too, so a number is never handed out twice.
 */
class DocumentNumber
{
    /**
     * @param Builder     $scope  the records that share this sequence (usually one company's)
     * @param string      $column the number column
     * @param string      $prefix everything before the running number, e.g. "SI-2026-11-"
     * @param int         $pad    minimum digits; longer numbers are kept whole
     * @param Builder|null $unique when the column must be unique across more records than $scope (for example
     *                            across all companies), the query to check a candidate against
     */
    public static function next(Builder $scope, string $column, string $prefix, int $pad = 3, ?Builder $unique = null): string
    {
        $wrapped = $scope->getQuery()->getGrammar()->wrap($column);
        $start = strlen($prefix) + 1;
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';

        $last = (int) (clone $scope)->withoutGlobalScope(SoftDeletingScope::class)
            ->where($column, 'like', $like)
            ->selectRaw("MAX(CAST(SUBSTRING({$wrapped}, ?) AS UNSIGNED)) AS last_number", [$start])
            ->value('last_number');

        $check = $unique ? (clone $unique)->withoutGlobalScope(SoftDeletingScope::class) : null;
        for ($number = $last + 1, $tries = 0; $tries < 10000; $number++, $tries++) {
            $candidate = $prefix . str_pad((string) $number, $pad, '0', STR_PAD_LEFT);
            if (!$check || !(clone $check)->where($column, $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException("No free document number found for {$prefix}");
    }
}
