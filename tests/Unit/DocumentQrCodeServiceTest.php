<?php

namespace Tests\Unit;

use App\Services\Documents\DocumentQrCodeService;
use App\Services\Documents\DocumentShareService;
use Mockery;
use PHPUnit\Framework\TestCase;

class DocumentQrCodeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_generates_an_embeddable_svg_data_uri(): void
    {
        $service = new DocumentQrCodeService(Mockery::mock(DocumentShareService::class));
        $image = $service->image('https://example.com/d/secure-document-token');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $image);
        $svg = base64_decode(substr($image, strlen('data:image/svg+xml;base64,')), true);
        $this->assertIsString($svg);
        $this->assertStringContainsString('<svg', $svg);
    }
}
