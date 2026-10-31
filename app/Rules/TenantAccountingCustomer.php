<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TenantAccountingCustomer implements ValidationRule
{
    public function __construct(private readonly int $tenantId)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_numeric($value) || !User::query()
            ->accountingCustomers($this->tenantId)
            ->whereKey((int) $value)
            ->exists()) {
            $fail(__('Selected customer does not exist.'));
        }
    }
}
