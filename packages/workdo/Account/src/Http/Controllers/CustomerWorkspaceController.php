<?php

namespace Workdo\Account\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplates\DocumentTemplateService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Workdo\Account\Models\Customer;
use Workdo\Account\Services\CustomerWorkspaceService;

class CustomerWorkspaceController extends Controller
{
    public function show(Request $request, Customer $customer, CustomerWorkspaceService $service)
    {
        $this->authorizeCustomer($customer);

        return Inertia::render('Account/Customers/Workspace', [
            'directory' => $service->directory($request, creatorId()),
            'workspace' => $service->workspace($customer, $request),
            'filters' => $request->only(['search', 'view', 'sort', 'direction', 'currency', 'portal', 'activity_from', 'type', 'status', 'start_date', 'end_date']),
        ]);
    }

    public function statementCsv(Request $request, Customer $customer, CustomerWorkspaceService $service)
    {
        $this->authorizeCustomer($customer);
        abort_unless(auth()->user()->can('view-customer-detail-report'), 403);
        $statement = $service->statementExport($customer, $request);

        return response()->streamDownload(function () use ($statement) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Type', 'Number', 'Debit', 'Credit', 'Running Balance']);
            foreach ($statement['data'] as $row) {
                fputcsv($output, [$row['date'], $row['type'], $row['number'], $row['debit'], $row['credit'], $row['running_balance']]);
            }
            fclose($output);
        }, 'customer-statement-' . $customer->customer_code . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function statementPdf(Request $request, Customer $customer, CustomerWorkspaceService $workspace, DocumentTemplateService $templates)
    {
        $this->authorizeCustomer($customer);
        abort_unless(auth()->user()->can('print-customer-detail-report') || auth()->user()->can('view-customer-detail-report'), 403);

        $statement = $workspace->statementExport($customer, $request);
        $template = $templates->resolveForDocument(DocumentTemplate::TYPE_STATEMENT, creatorId(), null);

        return Inertia::render('Account/Customers/StatementPrint', [
            'customer' => $customer,
            'documentTemplate' => $template,
            'templateDocument' => $templates->customerStatementDocument(
                $customer->toArray(),
                $statement,
                $template,
                creatorId(),
                $request->string('start_date')->value() ?: null,
                $request->string('end_date')->value() ?: null,
            ),
        ]);
    }

    private function authorizeCustomer(Customer $customer): void
    {
        abort_unless(auth()->user()->can('manage-customers') || auth()->user()->can('view-customers'), 403);
        abort_unless((int) $customer->created_by === (int) creatorId(), 404);
        if (!auth()->user()->can('manage-any-customers')) {
            abort_unless(auth()->user()->can('manage-own-customers') && (int) $customer->creator_id === (int) auth()->id(), 403);
        }
    }
}
