@php
    $template = in_array($document['template_key'], ['classic', 'modern', 'minimal', 'zoho'], true)
        ? $document['template_key']
        : 'classic';
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', $document['settings']['accent_color'] ?? '')
        ? $document['settings']['accent_color']
        : '#0f766e';
    $money = function ($value) use ($document) {
        $formatted = number_format((float) $value, 2);
        return ($document['currency']['position'] ?? 'pre') === 'post'
            ? $formatted . $document['currency']['symbol']
            : $document['currency']['symbol'] . $formatted;
    };
    $address = function ($value) {
        if (is_string($value)) {
            $value = json_decode($value, true) ?: [];
        }
        return array_filter([
            $value['name'] ?? null,
            $value['address_line_1'] ?? null,
            $value['address_line_2'] ?? null,
            trim(implode(', ', array_filter([$value['city'] ?? null, $value['state'] ?? null]))) ?: null,
            trim(implode(' ', array_filter([$value['zip_code'] ?? null, $value['country'] ?? null]))) ?: null,
        ]);
    };
    $billing = $address($document['customer']['billing_address'] ?? []);
    $shipping = $address($document['customer']['shipping_address'] ?? []);
    $logoValue = $template === 'modern'
        ? ($document['company']['logo'] ?? null)
        : ($document['company']['logo_dark'] ?? $document['company']['logo'] ?? null);
    $imageUrl = function ($value) {
        if (!$value) {
            return null;
        }

        return str_starts_with($value, 'http') || str_starts_with($value, 'data:')
            ? $value
            : getImageUrlPrefix() . urlencode($value);
    };
    $logo = $imageUrl($logoValue);
    $signature = $imageUrl($document['settings']['signature_image'] ?? null);
    $qrImage = $document['qr']['image'] ?? null;
    $qrUrl = $document['qr']['url'] ?? null;
    $paymentInstructions = trim((string) ($document['settings']['payment_instructions'] ?? ''));
    foreach (['Payment Method', 'Account Name', 'Account Number', 'Bank', 'Reference'] as $paymentLabel) {
        $paymentInstructions = preg_replace(
            '/\s+(?=' . preg_quote($paymentLabel, '/') . '\s*:)/i',
            "\n",
            $paymentInstructions,
        );
    }
    $paymentInstructions = ltrim((string) preg_replace(
        '/\s+(?=Thank you for your business\.?)/i',
        "\n",
        $paymentInstructions,
    ));
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['label'] }} #{{ $document['number'] }}</title>
    <style>
        @page { size: A4; margin: 13mm 12mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1f2937; background: #eef2f5; font: 12px/1.45 DejaVu Sans, Arial, sans-serif; }
        .toolbar { max-width: 210mm; margin: 14px auto; padding: 12px 16px; background: #fff; border-radius: 8px; display: flex; gap: 8px; align-items: center; }
        .toolbar form { display: flex; gap: 8px; align-items: center; margin: 0; }
        .toolbar input { width: 145px; padding: 8px 9px; border: 1px solid #d1d5db; border-radius: 5px; }
        .flash { max-width: 210mm; margin: 12px auto; padding: 10px 14px; border-radius: 6px; background: #dcfce7; color: #166534; }
        .flash.error { background: #fee2e2; color: #991b1b; }
        .button { display: inline-block; border: 0; border-radius: 6px; padding: 9px 14px; background: {{ $accent }}; color: #fff; text-decoration: none; cursor: pointer; font-weight: 700; }
        .button.secondary { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .document { position: relative; width: 186mm; min-height: 267mm; margin: 0 auto 24px; padding: 12mm; background: #fff; overflow: hidden; }
        .document > :not(.document-watermark):not(.zoho-watermark):not(.zoho-watermark-text) { position: relative; z-index: 2; }
        .document-watermark { position: absolute; z-index: 1; left: 28mm; top: 78mm; width: 130mm; height: 125mm; object-fit: contain; opacity: .035; transform: rotate(-35deg); }
        .header { display: table; width: 100%; margin-bottom: 25px; }
        .header > div { display: table-cell; vertical-align: top; }
        .brand { width: 57%; }
        .meta { width: 43%; text-align: right; }
        .logo { max-width: 150px; max-height: 72px; margin-bottom: 10px; }
        .company-name { margin: 0 0 6px; font-size: 22px; color: #111827; }
        .company-lines, .muted { color: #6b7280; }
        .title { margin: 0; font-size: 28px; letter-spacing: 1px; color: {{ $accent }}; }
        .number { margin: 4px 0 10px; font-size: 15px; font-weight: 700; }
        .status { display: inline-block; margin-bottom: 8px; padding: 3px 9px; color: {{ $accent }}; background: #eef8f6; border-radius: 12px; text-transform: uppercase; font-size: 9px; font-weight: 700; }
        .parties { display: table; width: 100%; margin: 18px 0 24px; }
        .party { display: table-cell; width: 50%; vertical-align: top; padding-right: 18px; }
        .party-title { margin-bottom: 7px; color: #6b7280; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .party-name { font-size: 13px; font-weight: 700; }
        table.items { width: 100%; border-collapse: collapse; margin: 12px 0 22px; table-layout: fixed; }
        .items thead { display: table-header-group; }
        .items tr { page-break-inside: avoid; }
        .items th { padding: 9px 7px; color: #fff; background: {{ $accent }}; text-align: right; font-size: 9px; text-transform: uppercase; }
        .items th:first-child, .items td:first-child { text-align: left; }
        .items td { padding: 10px 7px; border-bottom: 1px solid #e5e7eb; text-align: right; vertical-align: top; }
        .description { color: #6b7280; font-size: 9px; }
        .totals-wrap { display: table; width: 100%; page-break-inside: avoid; }
        .notes { display: table-cell; width: 54%; padding-right: 25px; vertical-align: top; }
        .totals { display: table-cell; width: 46%; vertical-align: top; }
        .total-row { display: table; width: 100%; padding: 4px 0; }
        .total-row span { display: table-cell; }
        .total-row span:last-child { text-align: right; }
        .grand-total { margin-top: 5px; padding: 10px; color: #fff; background: {{ $accent }}; font-size: 15px; font-weight: 700; }
        .balance { padding: 8px 10px; border: 1px solid {{ $accent }}; color: {{ $accent }}; font-weight: 700; }
        .section-title { margin: 12px 0 4px; font-size: 9px; color: #6b7280; letter-spacing: .8px; text-transform: uppercase; }
        .payment-instructions { white-space: pre-line; overflow-wrap: anywhere; word-break: normal; line-height: 1.35; }
        .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #d1d5db; text-align: center; color: #6b7280; font-size: 10px; }
        .document-verification { display: table; width: 100%; margin-top: 22px; page-break-inside: avoid; }
        .document-verification > div { display: table-cell; width: 50%; vertical-align: bottom; }
        .document-signature { text-align: left; }
        .document-signature img { display: block; max-width: 150px; max-height: 65px; margin-bottom: 5px; }
        .document-signature-label { display: inline-block; min-width: 150px; padding-top: 5px; border-top: 1px solid #6b7280; font-size: 9px; color: #6b7280; }
        .document-qr { text-align: right; }
        .document-qr img { width: 86px; height: 86px; }
        .document-qr-label { margin-top: 3px; font-size: 8px; color: #6b7280; }
        .modern .document { padding-top: 0; }
        .modern .header { padding: 12mm 10mm 9mm; margin-left: -12mm; width: calc(100% + 24mm); color: #fff; background: {{ $accent }}; }
        .modern .company-name, .modern .title, .modern .company-lines, .modern .muted { color: #fff; }
        .modern .status { color: {{ $accent }}; background: #fff; }
        .minimal .document { padding: 15mm; }
        .minimal .title { color: #111827; font-weight: 400; }
        .minimal .items th { color: #111827; background: #fff; border-top: 1px solid #111827; border-bottom: 1px solid #111827; }
        .minimal .grand-total { color: #111827; background: #f3f4f6; }
        .minimal .balance { color: #111827; border-color: #d1d5db; }
        .zoho .document { position: relative; width: 196mm; min-height: 277mm; padding: 10mm 7mm 15mm; color: #222; font-family: Arial, sans-serif; font-size: 9px; overflow: hidden; }
        .zoho-header { display: table; width: 100%; margin-bottom: 16mm; }
        .zoho-header > div { display: table-cell; vertical-align: top; }
        .zoho-brand { width: 55%; }
        .zoho-brand .logo { max-width: 155px; max-height: 63px; margin: 0; }
        .zoho-brand-name { margin-top: 3px; font-size: 18px; font-weight: 700; color: #111; }
        .zoho-heading { width: 45%; text-align: right; }
        .zoho-heading h1 { margin: 0; font-size: 30px; line-height: 1.05; font-weight: 400; color: #111; }
        .zoho-document-number { margin-top: 7px; font-size: 10px; font-weight: 700; }
        .zoho-info { display: table; width: 100%; margin-bottom: 10mm; }
        .zoho-info > div { display: table-cell; width: 50%; vertical-align: top; }
        .zoho-info.has-shipping > div { width: 32%; padding-right: 5mm; }
        .zoho-info.has-shipping > .zoho-info-right { width: 36%; padding-right: 0; }
        .zoho-info-right { text-align: right; }
        .zoho-label { margin-bottom: 3px; color: #333; }
        .zoho-customer { font-weight: 700; }
        .zoho-subject { margin-top: 6mm; }
        .zoho-date-row { display: table; width: 100%; }
        .zoho-date-row span { display: table-cell; }
        .zoho-date-row span:last-child { text-align: right; }
        .zoho-items { position: relative; z-index: 2; width: 100%; border-collapse: collapse; table-layout: fixed; }
        .zoho-items thead { display: table-header-group; }
        .zoho-items tr { page-break-inside: avoid; }
        .zoho-items th { padding: 7px 8px; color: #fff; background: #373936; font-weight: 400; text-align: right; }
        .zoho-items th:first-child, .zoho-items td:first-child { width: 6%; text-align: center; }
        .zoho-items th:nth-child(2), .zoho-items td:nth-child(2) { text-align: left; }
        .zoho-items td { padding: 9px 8px; text-align: right; vertical-align: top; }
        .zoho-item-name { margin-bottom: 7px; }
        .zoho-item-description { white-space: pre-line; line-height: 1.55; color: #333; }
        .zoho-summary-line { position: relative; z-index: 2; border-top: 1px solid #777; margin-top: 3mm; }
        .zoho-summary { position: relative; z-index: 2; width: 50%; margin-left: 50%; }
        .zoho-summary-row { display: table; width: 100%; padding: 6px 9px; }
        .zoho-summary-row span { display: table-cell; }
        .zoho-summary-row span:last-child { text-align: right; }
        .zoho-summary-row.total { padding: 9px; background: #f1f1f1; font-weight: 700; }
        .zoho-notes { position: relative; z-index: 2; margin-top: 13mm; max-width: 86%; line-height: 1.45; white-space: pre-line; }
        .zoho-notes-title { margin-bottom: 4px; font-size: 10px; }
        .zoho-watermark { position: absolute; z-index: 1; left: 26mm; top: 66mm; width: 130mm; height: 155mm; opacity: .035; transform: rotate(-43deg); object-fit: contain; }
        .zoho-watermark-text { position: absolute; z-index: 1; left: 26mm; top: 108mm; width: 145mm; color: #111; opacity: .035; transform: rotate(-43deg); font-size: 42px; font-weight: 700; text-align: center; }
        .zoho-footer { position: absolute; left: 7mm; right: 7mm; bottom: 5mm; text-align: center; color: #666; font-size: 8px; }
        .zoho-footer span + span:before { content: '  |  '; color: #888; }
        .zoho .document-verification { margin-top: 9mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .document { width: auto; min-height: auto; margin: 0; padding: 0; }
            .modern .header { margin-left: -12mm; width: calc(100% + 24mm); }
        }
    </style>
</head>
<body class="{{ $template }}">
    @if(!empty($shareToken))
        @if(session('success'))<div class="flash">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="flash error">{{ session('error') }}</div>@endif
        <div class="toolbar">
            <a class="button secondary" href="{{ route('documents.public.pdf', $shareToken) }}">{{ __('Download PDF') }}</a>
            @if($document['type'] === 'quotation' && $document['status'] === 'sent')
                <form method="post" action="{{ route('documents.public.accept', $shareToken) }}">
                    @csrf
                    <input name="name" required maxlength="255" value="{{ $document['customer']['name'] }}" placeholder="{{ __('Your name') }}">
                    <input name="comment" maxlength="2000" placeholder="{{ __('Optional comment') }}">
                    <button class="button" type="submit">{{ __('Accept') }}</button>
                </form>
                <form method="post" action="{{ route('documents.public.reject', $shareToken) }}">
                    @csrf
                    <input name="name" required maxlength="255" value="{{ $document['customer']['name'] }}" placeholder="{{ __('Your name') }}">
                    <input name="comment" maxlength="2000" placeholder="{{ __('Optional comment') }}">
                    <button class="button secondary" type="submit">{{ __('Reject') }}</button>
                </form>
            @elseif($document['type'] === 'invoice' && $document['totals']['balance'] > 0)
                @if(!empty($paymentProviders['stripe']))
                    <form method="post" action="{{ route('documents.public.pay', [$shareToken, 'stripe']) }}">@csrf<button class="button" type="submit">{{ __('Pay with Stripe') }}</button></form>
                @endif
                @if(!empty($paymentProviders['paypal']))
                    <form method="post" action="{{ route('documents.public.pay', [$shareToken, 'paypal']) }}">@csrf<button class="button" type="submit">{{ __('Pay with PayPal') }}</button></form>
                @endif
            @endif
        </div>
    @endif

    @if($template === 'zoho')
        @include('documents.templates.zoho')
    @else
    <main class="document">
        @if($logo)<img class="document-watermark" src="{{ $logo }}" alt="">@endif
        <header class="header">
            <div class="brand">
                @if($logo)<img class="logo" src="{{ $logo }}" alt="{{ $document['company']['name'] }}">@endif
                @if($document['company']['name'])<h1 class="company-name">{{ $document['company']['name'] }}</h1>@endif
                <div class="company-lines">
                    @foreach(array_filter([$document['company']['address'], trim(implode(', ', array_filter([$document['company']['city'], $document['company']['state'], $document['company']['postal_code']]))), $document['company']['country'], $document['company']['phone'], $document['company']['email']]) as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                    @if($document['company']['registration_number'])<div>{{ __('Registration') }}: {{ $document['company']['registration_number'] }}</div>@endif
                    @if($document['company']['tax_number'])<div>{{ __('Tax Number') }}: {{ $document['company']['tax_number'] }}</div>@endif
                </div>
            </div>
            <div class="meta">
                <div class="status">{{ $document['status'] }}</div>
                <h2 class="title">{{ $document['label'] }}</h2>
                <div class="number">#{{ $document['number'] }}</div>
                <div><span class="muted">{{ __('Date') }}:</span> {{ optional($document['date'])->format('Y-m-d') }}</div>
                <div><span class="muted">{{ __('Due Date') }}:</span> {{ optional($document['due_date'])->format('Y-m-d') }}</div>
                @if($document['reference'])<div><span class="muted">{{ __('Reference') }}:</span> {{ $document['reference'] }}</div>@endif
            </div>
        </header>

        <section class="parties">
            <div class="party">
                <div class="party-title">{{ $document['type'] === 'quotation' ? __('Quote To') : __('Bill To') }}</div>
                <div class="party-name">{{ $document['customer']['company_name'] ?: $document['customer']['name'] }}</div>
                @if($document['customer']['company_name'] && $document['customer']['name'])<div>{{ $document['customer']['name'] }}</div>@endif
                <div>{{ $document['customer']['email'] }}</div>
                @foreach($billing as $line)<div>{{ $line }}</div>@endforeach
                @if($document['customer']['tax_number'])<div>{{ __('Tax Number') }}: {{ $document['customer']['tax_number'] }}</div>@endif
            </div>
            @if($document['settings']['show_shipping'] && $shipping)
                <div class="party">
                    <div class="party-title">{{ __('Ship To') }}</div>
                    @foreach($shipping as $line)<div>{{ $line }}</div>@endforeach
                </div>
            @endif
        </section>

        <table class="items">
            <thead><tr>
                <th style="width:35%">{{ __('Item') }}</th>
                @if($document['settings']['show_quantity'])<th>{{ __('Qty') }}</th>@endif
                <th>{{ __('Rate') }}</th>
                @if($document['settings']['show_discount'])<th>{{ __('Discount') }}</th>@endif
                @if($document['settings']['show_tax'])<th>{{ __('Tax') }}</th>@endif
                <th>{{ __('Amount') }}</th>
            </tr></thead>
            <tbody>
            @foreach($document['items'] as $item)
                <tr>
                    <td>
                        <strong>{{ $item['name'] }}</strong>
                        @if($document['settings']['show_sku'] && $item['sku'])<div class="description">{{ __('SKU') }}: {{ $item['sku'] }}</div>@endif
                        @if($document['settings']['show_description'] && $item['description'])<div class="description">{{ $item['description'] }}</div>@endif
                    </td>
                    @if($document['settings']['show_quantity'])<td>{{ rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') }} {{ $item['unit'] }}</td>@endif
                    <td>{{ $money($item['unit_price']) }}</td>
                    @if($document['settings']['show_discount'])<td>{{ $item['discount_percentage'] }}%<br><span class="description">-{{ $money($item['discount_amount']) }}</span></td>@endif
                    @if($document['settings']['show_tax'])<td>@foreach($item['taxes'] as $tax)<div>{{ $tax['name'] }} {{ $tax['rate'] }}%</div>@endforeach<div class="description">{{ $money($item['tax_amount']) }}</div></td>@endif
                    <td><strong>{{ $money($item['total']) }}</strong></td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <section class="totals-wrap">
            <div class="notes">
                @if($document['notes'])<div class="section-title">{{ __('Notes') }}</div><div>{{ $document['notes'] }}</div>@endif
                @if($document['payment_terms'])<div class="section-title">{{ __('Terms & Conditions') }}</div><div>{{ $document['payment_terms'] }}</div>@endif
                @if($document['type'] === 'invoice' && $paymentInstructions)<div class="section-title">{{ __('Bank / Payment Instructions') }}</div><div class="payment-instructions">{{ $paymentInstructions }}</div>@endif
            </div>
            <div class="totals">
                <div class="total-row"><span>{{ __('Subtotal') }}</span><span>{{ $money($document['totals']['subtotal']) }}</span></div>
                @if($document['totals']['discount'] > 0)<div class="total-row"><span>{{ __('Discount') }}</span><span>-{{ $money($document['totals']['discount']) }}</span></div>@endif
                @if($document['totals']['tax'] > 0)<div class="total-row"><span>{{ __('Tax') }}</span><span>{{ $money($document['totals']['tax']) }}</span></div>@endif
                <div class="total-row grand-total"><span>{{ __('Total') }}</span><span>{{ $money($document['totals']['total']) }}</span></div>
                @if($document['type'] === 'invoice' && $document['totals']['paid'] > 0)<div class="total-row"><span>{{ __('Paid') }}</span><span>{{ $money($document['totals']['paid']) }}</span></div>@endif
                @if($document['type'] === 'invoice')<div class="total-row balance"><span>{{ __('Balance Due') }}</span><span>{{ $money($document['totals']['balance']) }}</span></div>@endif
            </div>
        </section>

        @include('documents.partials.verification')

        <footer class="footer">{{ $document['settings']['footer'] }}</footer>
    </main>
    @endif
</body>
</html>
