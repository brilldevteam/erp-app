<?php

namespace App\Services\BulkImport\Concerns;

trait NormalizesImportValues
{
    protected function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    protected function nullableText(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value === '' ? null : $value;
    }

    protected function boolean(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower($this->text($value)), ['1', 'true', 'yes', 'active', 'enabled'], true);
    }

    protected function split(mixed $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;|]/', (string) $value))));
    }

    protected function address(array $row, string $prefix): array
    {
        return [
            'name' => $this->text($row["{$prefix}_name"] ?? ''),
            'address_line_1' => $this->text($row["{$prefix}_address_line_1"] ?? ''),
            'address_line_2' => $this->nullableText($row["{$prefix}_address_line_2"] ?? null),
            'city' => $this->text($row["{$prefix}_city"] ?? ''),
            'state' => $this->text($row["{$prefix}_state"] ?? ''),
            'country' => $this->text($row["{$prefix}_country"] ?? ''),
            'zip_code' => $this->text($row["{$prefix}_zip_code"] ?? ''),
        ];
    }
}
