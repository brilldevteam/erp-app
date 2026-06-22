@if(($document['settings']['show_signature'] && $signature) || ($document['settings']['show_qr'] && $qrImage))
    <section class="document-verification">
        <div class="document-signature">
            @if($document['settings']['show_signature'] && $signature)
                <img src="{{ $signature }}" alt="{{ __('Authorized Signature') }}">
                <div class="document-signature-label">{{ __('Authorized Signature') }}</div>
            @endif
        </div>
        <div class="document-qr">
            @if($document['settings']['show_qr'] && $qrImage)
                <img src="{{ $qrImage }}" alt="{{ __('Document QR code') }}">
                <div class="document-qr-label">{{ __('Scan to view this document securely') }}</div>
                @if($qrUrl)<div class="document-qr-label">{{ parse_url($qrUrl, PHP_URL_HOST) }}</div>@endif
            @endif
        </div>
    </section>
@endif
