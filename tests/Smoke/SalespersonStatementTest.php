<?php

namespace Tests\Smoke;

use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\AuthSessionService;
use App\Services\SalesInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Workdo\Quotation\Models\QuotationSalesperson;
use Workdo\Quotation\Models\SalesQuotation;

/**
 * Runs against the local copy of live data and rolls everything back.
 */
class SalespersonStatementTest extends TestCase
{
    use DatabaseTransactions;

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([AuthSessionService::SESSION_KEY => max(1, (int) ($user->security_version ?? 1))]);
    }

    private function quotationWithSalesperson(): SalesQuotation
    {
        $quotation = SalesQuotation::whereNotNull('salesperson_id')->whereHas('items')->latest('id')->first();
        if (!$quotation) {
            $this->markTestSkipped('No quotation with a salesperson in this database.');
        }

        return $quotation;
    }

    public function test_statement_lists_every_quotation_line_and_invoices_inherit_the_quotation_salesperson(): void
    {
        $quotation = $this->quotationWithSalesperson();
        $company = User::findOrFail($quotation->created_by);
        $salesperson = QuotationSalesperson::findOrFail($quotation->salesperson_id);

        $response = $this->as($company)->get(route('quotation-salespersons.show', $salesperson))->assertOk();
        $statement = collect($response->viewData('page')['props']['statement']);

        $expectedQuotationLines = SalesQuotation::where('created_by', $company->id)->where('salesperson_id', $salesperson->id)->withCount('items')->get()->sum('items_count');
        $this->assertSame($expectedQuotationLines, $statement->where('document_type', 'quotation')->count());
        $this->assertTrue($statement->where('document_type', 'quotation')->every(fn ($line) => $line['item_name'] !== '' && array_key_exists('typed', $line)));

        $invoiceLinesBefore = $statement->where('document_type', 'invoice')->count();
        $this->actingAs($company);
        $invoice = app(SalesInvoiceService::class)->create([
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'customer_id' => $quotation->customer_id,
            'salesperson_id' => $salesperson->id,
            'type' => 'service',
            'currency_code' => $quotation->currency_code ?: 'QAR',
            'exchange_rate' => 1,
            'items' => [[
                'product_id' => null,
                'item_name' => 'Statement test typed line',
                'quantity' => 2,
                'unit_price' => 150,
                'description' => 'Typed on the invoice',
                'discount_type' => 'percentage',
                'discount_value' => 0,
                'taxes' => [],
            ]],
        ], $company->id, $company->id);
        $this->assertSame($salesperson->id, (int) $invoice->fresh()->salesperson_id);

        $statement = collect($this->as($company)->get(route('quotation-salespersons.show', $salesperson))->assertOk()->viewData('page')['props']['statement']);
        $typedLine = $statement->firstWhere('document_id', $invoice->id);
        $this->assertSame($invoiceLinesBefore + 1, $statement->where('document_type', 'invoice')->count());
        $this->assertSame('Statement test typed line', $typedLine['item_name']);
        $this->assertTrue($typedLine['typed']);
        $this->assertEquals(2, $typedLine['quantity']);
        $this->assertEquals(300, $typedLine['total_amount']);
    }

    public function test_invoice_forms_offer_salespersons_and_save_the_choice(): void
    {
        $quotation = $this->quotationWithSalesperson();
        $company = User::findOrFail($quotation->created_by);

        $create = $this->as($company)->get(route('sales-invoices.create'))->assertOk();
        $this->assertNotEmpty($create->viewData('page')['props']['salespersons']);

        $draft = SalesInvoice::where('created_by', $company->id)->where('status', 'draft')->with('items')->latest('id')->first();
        if (!$draft) {
            $this->markTestSkipped('No draft invoice to edit in this database.');
        }
        $edit = $this->as($company)->get(route('sales-invoices.edit', $draft))->assertOk();
        $this->assertArrayHasKey('salespersons', $edit->viewData('page')['props']);

        $converted = $this->as($company)->get(route('quotations.convert-to-invoice', $quotation));
        if ($converted->status() === 200) {
            $this->assertSame((string) $quotation->salesperson_id, $converted->viewData('page')['props']['initialInvoice']['salesperson_id']);
        }
    }

    public function test_salesperson_must_belong_to_the_company(): void
    {
        $quotation = $this->quotationWithSalesperson();
        $company = User::findOrFail($quotation->created_by);
        $foreign = QuotationSalesperson::where('created_by', '!=', $company->id)->first()
            ?? QuotationSalesperson::create(['name' => 'Other company person '.uniqid(), 'is_active' => true, 'created_by' => 1]);

        $this->as($company)->post(route('sales-invoices.store'), [
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'customer_id' => $quotation->customer_id,
            'salesperson_id' => $foreign->id,
            'type' => 'service',
            'currency_code' => 'QAR',
            'exchange_rate' => 1,
            'items' => [['item_name' => 'x', 'quantity' => 1, 'unit_price' => 1]],
        ])->assertSessionHasErrors('salesperson_id');
    }
}
