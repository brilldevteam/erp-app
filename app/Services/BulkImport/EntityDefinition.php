<?php

namespace App\Services\BulkImport;

interface EntityDefinition
{
    public function key(): string;
    public function permission(): string;
    public function createPermission(): string;
    public function headers(): array;
    public function example(): array;
    public function instructions(): array;
    public function identity(array $row): string;
    public function validate(array $row, int $tenantId): array;
    public function duplicate(array $row, int $tenantId): bool;
    public function import(array $row, string $strategy, int $tenantId, int $actorId): string;
}
