<?php

namespace App\Services\BulkImport;

use App\Services\BulkImport\Definitions\CustomerDefinition;
use App\Services\BulkImport\Definitions\ProductServiceDefinition;
use InvalidArgumentException;

class BulkImportRegistry
{
    public function all(): array
    {
        return [
            'customers' => new CustomerDefinition(),
            'product-service-items' => new ProductServiceDefinition(),
        ];
    }

    public function get(string $entity): EntityDefinition
    {
        return $this->all()[$entity] ?? throw new InvalidArgumentException('Unsupported import entity.');
    }
}
