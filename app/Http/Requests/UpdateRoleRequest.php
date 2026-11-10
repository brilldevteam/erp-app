<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where('created_by', creatorId())
                    ->ignore($this->role->id),
                $this->reservedRoleName(),
            ],
            'label' => 'required|string|max:255',
            'permissions' => 'array',
            // A company may only hand out permissions it holds itself.
            'permissions.*' => ['string', 'exists:permissions,name', $this->grantablePermission()],
        ];
    }

    private function grantablePermission(): \Closure
    {
        $held = auth()->user()?->type === 'superadmin' ? null : auth()->user()?->getAllPermissions()->pluck('name')->all();

        return function (string $attribute, mixed $value, \Closure $fail) use ($held) {
            if ($held !== null && !in_array($value, $held, true)) {
                $fail(__('You cannot grant a permission you do not have.'));
            }
        };
    }

    /** Platform role names belong to the super admin's support team. */
    private function reservedRoleName(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (auth()->user()?->type !== 'superadmin' && in_array(strtolower(trim((string) $value)), ['superadmin', 'support-agent', 'support-manager'], true)) {
                $fail(__('This role name is reserved.'));
            }
        };
    }
}
