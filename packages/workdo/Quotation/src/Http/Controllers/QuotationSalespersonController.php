<?php

namespace Workdo\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Workdo\Quotation\Models\QuotationSalesperson;

class QuotationSalespersonController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('create-quotations'), 403);
        $data = $this->validated($request);
        $salesperson = QuotationSalesperson::create([...$data, 'is_active' => true, 'created_by' => creatorId()]);

        return response()->json($salesperson, 201);
    }

    public function update(Request $request, QuotationSalesperson $salesperson): JsonResponse
    {
        $this->authorizeCompany($request, $salesperson);
        $salesperson->update($this->validated($request, $salesperson));

        return response()->json($salesperson);
    }

    public function toggle(Request $request, QuotationSalesperson $salesperson): JsonResponse
    {
        $this->authorizeCompany($request, $salesperson);
        $salesperson->update(['is_active' => ! $salesperson->is_active]);

        return response()->json($salesperson);
    }

    public function destroy(Request $request, QuotationSalesperson $salesperson): JsonResponse
    {
        $this->authorizeCompany($request, $salesperson);
        $salesperson->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request, ?QuotationSalesperson $salesperson = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('quotation_salespersons')->where('created_by', creatorId())->ignore($salesperson?->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }

    private function authorizeCompany(Request $request, QuotationSalesperson $salesperson): void
    {
        abort_unless($request->user()->can('edit-quotations') && $salesperson->created_by === creatorId(), 403);
    }
}
