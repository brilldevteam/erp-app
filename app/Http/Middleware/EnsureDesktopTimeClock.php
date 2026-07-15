<?php

namespace App\Http\Middleware;

use App\Services\TimeClockDeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDesktopTimeClock
{
    public function __construct(private TimeClockDeviceService $devices)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->devices->allowsTimeClock($request)) {
            return $next($request);
        }

        return response()->json([
            'code' => 'desktop_only',
            'message' => TimeClockDeviceService::DESKTOP_ONLY_MESSAGE,
        ], 403);
    }
}
