<?php

namespace Workdo\EInvoice\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SalesInvoice;
use Workdo\Account\Models\Customer;

class EInvoiceController extends Controller
{
    public function download($id)
    {
        $invoice = SalesInvoice::with(['items.product', 'customerDetails'])->find($id);
        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        $customer = $invoice->customerDetails;
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $items = $invoice->items;
        $totalTaxPrice = $invoice->tax_amount;
        $totalTaxRate = 0;
        $productname = '';

        foreach ($items as $item) {
            $totalTaxRate += $item->tax_percentage;
            if ($item->product) {
                $productname = $item->product->name;
            }
        }

        if (empty($customer->electronic_address) || empty($customer->electronic_address_scheme)) {

            return response()->json(['error' => 'Please set the proper setting in Customer.'], 400);
        }

        $invoice_number = $invoice->invoice_number;
        
        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date->format('Y-m-d'),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'subtotal' => $invoice->subtotal,
                'tax_amount' => $invoice->tax_amount,
                'total_amount' => $invoice->total_amount,
                'items' => $invoice->items
            ],
            'customer' => $customer,
            'totalTaxPrice' => $totalTaxPrice,
            'totalTaxRate' => $totalTaxRate,
            'productname' => $productname,
            'invoiceNumber' => $invoice_number,
            'currency' => company_setting('defaultCurrency') ,
            'companySettings' => [
                'electronic_address_schema' => company_setting('electronic_address_schema'),
                'electronic_address' => company_setting('electronic_address'),
                'tax_number' => company_setting('tax_number'),
                'tax_type' => company_setting('tax_type'),
                'company_id_schema' => company_setting('company_id_schema'),
                'company_id' => company_setting('company_id'),
                'company_name' => company_setting('company_name')
            ]
        ]);
    }
}