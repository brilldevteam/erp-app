<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Workdo\Taskly\Models\ProjectContract;

class ValidProjectContractForVendor implements ValidationRule
{
    public function __construct(private readonly mixed $vendorUserId)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!Schema::hasTable('project_contracts')) {
            $fail(__('The selected project contract is not available.'));
            return;
        }

        $contract = ProjectContract::query()
            ->where('created_by', creatorId())
            ->whereHas('project', fn ($query) => $query->accessibleTo(Auth::user()))
            ->with('vendor:id,user_id')
            ->find($value);

        if (!$contract || !$contract->vendor || (int) $contract->vendor->user_id !== (int) $this->vendorUserId) {
            $fail(__('The selected project contract does not belong to the selected vendor.'));
        }
    }
}
