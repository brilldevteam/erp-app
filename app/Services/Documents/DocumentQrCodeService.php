<?php

namespace App\Services\Documents;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Model;

class DocumentQrCodeService
{
    public function __construct(private readonly DocumentShareService $shares)
    {
    }

    public function for(string $type, Model $document, int $tenantId): array
    {
        $share = $this->shares->permanent($type, $document, $tenantId);

        return [
            'url' => $share['url'],
            'image' => $this->image($share['url']),
        ];
    }

    public function image(string $url): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 5,
        ]);
        $svg = (new QRCode($options))->render($url);

        return str_starts_with($svg, 'data:image/svg+xml;base64,')
            ? $svg
            : 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function refresh(array $qr): ?array
    {
        $url = (string) ($qr['url'] ?? '');
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        $baseUrl = rtrim((string) config('documents.public_url', config('app.url')), '/');
        $publicUrl = $baseUrl . $path . ($query ? '?' . $query : '');

        return [
            'url' => $publicUrl,
            'image' => $this->image($publicUrl),
        ];
    }
}
