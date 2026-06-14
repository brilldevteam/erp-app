<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModuleAssetController extends Controller
{
    public function favicon(string $module): BinaryFileResponse|Response
    {
        $packagesPath = realpath(base_path('packages/workdo'));
        $faviconPath = realpath(base_path("packages/workdo/{$module}/favicon.png"));

        if (
            $packagesPath === false
            || $faviconPath === false
            || !str_starts_with($faviconPath, $packagesPath . DIRECTORY_SEPARATOR)
            || !is_file($faviconPath)
        ) {
            abort(404);
        }

        return response()->file($faviconPath, [
            'Cache-Control' => 'public, max-age=86400',
            'Content-Type' => 'image/png',
        ]);
    }
}
