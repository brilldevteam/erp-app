<main class="document zoho-document">
    @if($logo)
        <img class="zoho-watermark" src="{{ $logo }}" alt="">
    @endif

    <header class="zoho-header">
        <div class="zoho-brand">
            @if($logo)
                <img class="logo" src="{{ $logo }}" alt="{{ $document['company']['name'] }}">
            @elseif($document['company']['name'])
                <div class="zoho-brand-name">{{ $document['company']['name'] }}</div>
            @endif
        </div>
        <div class="zoho-heading">
            <h1>{{ $document['type'] === 'quotation' ? __('Quotation') : __('Invoice') }}</h1>
            <div class="zoho-document-number"># {{ $document['number'] }}</div>
        </div>
    </header>

    <section class="zoho-info {{ $document['settings']['show_shipping'] && $shipping ? 'has-shipping' : '' }}">
        <div>
            <div class="zoho-label">{{ __('Bill To') }}</div>
            <div class="zoho-customer">{{ $document['customer']['company_name'] ?: $document['customer']['name'] }}</div>
            @if($document['customer']['company_name'] && $document['customer']['name'] && $document['customer']['company_name'] !== $document['customer']['name'])<div>{{ $document['customer']['name'] }}</div>@endif
            @foreach($billing as $line)<div>{{ $line }}</div>@endforeach
            <div class="zoho-subject"><span class="zoho-label">{{ __('Subject') }} :</span></div>
            <div>{{ $document['items'][0]['name'] ?? $document['label'] }}</div>
        </div>
        @if($document['settings']['show_shipping'] && $shipping)
            <div>
                <div class="zoho-label">{{ __('Ship To') }}</div>
                @foreach($shipping as $line)<div>{{ $line }}</div>@endforeach
            </div>
        @endif
        <div class="zoho-info-right">
            <div class="zoho-date-row">
                <span>{{ $document['type'] === 'quotation' ? __('Estimate Date') : __('Invoice Date') }} :</span>
                <span>{{ optional($document['date'])->format('d M Y') }}</span>
            </div>
            @if($document['type'] === 'invoice' && $document['due_date'])
                <div class="zoho-date-row"><span>{{ __('Due Date') }} :</span><span>{{ optional($document['due_date'])->format('d M Y') }}</span></div>
            @endif
        </div>
    </section>

    <table class="zoho-items">
        <thead><tr>
            <th>#</th>
            <th>{{ __('Item & Description') }}</th>
            @if($document['settings']['show_quantity'])<th>{{ __('Qty') }}</th>@endif
            <th>{{ __('Rate') }}</th>
            @if($document['settings']['show_discount'])<th>{{ __('Discount') }}</th>@endif
            @if($document['settings']['show_tax'])<th>{{ __('Tax') }}</th>@endif
            <th>{{ __('Amount') }}</th>
        </tr></thead>
        <tbody>
        @foreach($document['items'] as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    <div class="zoho-item-name">{{ $item['name'] }}</div>
                    @if($document['settings']['show_sku'] && $item['sku'])<div>{{ __('SKU') }}: {{ $item['sku'] }}</div>@endif
                    @if($document['settings']['show_description'] && $item['description'])<div class="zoho-item-description">{{ $item['description'] }}</div>@endif
                </td>
                @if($document['settings']['show_quantity'])<td>{{ rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') }} {{ $item['unit'] }}</td>@endif
                <td>{{ $money($item['unit_price']) }}</td>
                @if($document['settings']['show_discount'])<td>{{ $item['discount_percentage'] }}%<br>-{{ $money($item['discount_amount']) }}</td>@endif
                @if($document['settings']['show_tax'])<td>@foreach($item['taxes'] as $tax)<div>{{ $tax['name'] }} {{ $tax['rate'] }}%</div>@endforeach<div>{{ $money($item['tax_amount']) }}</div></td>@endif
                <td>{{ $money($item['total']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="zoho-summary-line"></div>
    <section class="zoho-summary">
        <div class="zoho-summary-row"><span>{{ __('Sub Total') }}</span><span>{{ $money($document['totals']['subtotal']) }}</span></div>
        @if($document['totals']['discount'] > 0)<div class="zoho-summary-row"><span>{{ __('Discount') }}</span><span>-{{ $money($document['totals']['discount']) }}</span></div>@endif
        @if($document['totals']['tax'] > 0)<div class="zoho-summary-row"><span>{{ __('Tax') }}</span><span>{{ $money($document['totals']['tax']) }}</span></div>@endif
        <div class="zoho-summary-row total"><span>{{ __('Total') }}</span><span>{{ $money($document['totals']['total']) }}</span></div>
        @if($document['type'] === 'invoice' && $document['totals']['paid'] > 0)<div class="zoho-summary-row"><span>{{ __('Paid') }}</span><span>{{ $money($document['totals']['paid']) }}</span></div>@endif
        @if($document['type'] === 'invoice' && $document['totals']['balance'] != $document['totals']['total'])<div class="zoho-summary-row total"><span>{{ __('Balance Due') }}</span><span>{{ $money($document['totals']['balance']) }}</span></div>@endif
    </section>

    @if($document['notes'] || $document['payment_terms'] || ($document['type'] === 'invoice' && $paymentInstructions))
        <section class="zoho-notes">
            @if($document['notes'])
                <div class="zoho-notes-title">{{ __('Notes') }}</div>
                <div>{{ $document['notes'] }}</div>
            @endif
            @if($document['payment_terms'])
                <div class="zoho-notes-title">{{ __('Terms & Conditions') }}</div>
                <div>{{ $document['payment_terms'] }}</div>
            @endif
            @if($document['type'] === 'invoice' && $paymentInstructions)
                <div class="zoho-notes-title">{{ __('Bank / Payment Instructions') }}</div>
                <div class="payment-instructions">{{ $paymentInstructions }}</div>
            @endif
        </section>
    @endif

    @include('documents.partials.verification')

    <footer class="zoho-footer">
        @foreach(array_filter([
            $document['settings']['footer'],
            $document['company']['address'],
            $document['company']['phone'],
            $document['company']['email'],
            $document['company']['website'] ?? null,
            $document['company']['registration_number'] ? __('CR NO') . ': ' . $document['company']['registration_number'] : null,
        ]) as $detail)<span>{{ $detail }}</span>@endforeach
    </footer>
</main>
