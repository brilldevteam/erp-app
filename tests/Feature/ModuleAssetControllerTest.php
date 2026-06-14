<?php

namespace Tests\Feature;

use Tests\TestCase;

class ModuleAssetControllerTest extends TestCase
{
    public function test_it_serves_a_module_favicon_from_the_package_source(): void
    {
        $response = $this->get('/module-assets/DoubleEntry/favicon');

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
        $response->assertHeader('cache-control', 'max-age=86400, public');
    }

    public function test_it_returns_not_found_for_an_unknown_module(): void
    {
        $this->get('/module-assets/UnknownModule/favicon')->assertNotFound();
    }

    public function test_it_rejects_invalid_module_paths(): void
    {
        $this->get('/module-assets/../favicon')->assertNotFound();
    }
}
