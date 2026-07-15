<?php

namespace App\Services;

use Illuminate\Http\Request;

class TimeClockDeviceService
{
    public const DESKTOP_ONLY_MESSAGE = 'This feature is only accessible from a desktop or laptop computer.';

    public function access(Request $request): array
    {
        $device = $this->deviceType($request);

        return [
            'allowed' => $device === 'desktop',
            'device' => $device,
            'reason' => $device === 'desktop' ? null : 'desktop_only',
            'message' => $device === 'desktop' ? null : self::DESKTOP_ONLY_MESSAGE,
        ];
    }

    public function allowsTimeClock(Request $request): bool
    {
        return $this->deviceType($request) === 'desktop';
    }

    public function deviceType(Request $request): string
    {
        $clientClassification = $request->cookie('time_clock_device');
        if ($clientClassification === 'mobile_or_tablet') {
            return 'mobile_or_tablet';
        }

        $userAgent = strtolower((string) $request->userAgent());
        $mobileHint = strtolower((string) $request->header('Sec-CH-UA-Mobile'));

        if ($mobileHint === '?1') {
            return 'mobile';
        }

        if ($userAgent === '') {
            return $request->is('api/*') ? 'unknown' : 'desktop';
        }

        if (preg_match('/ipad|tablet|kindle|silk\/|playbook|nexus\s*(7|9|10)|sm-t\d+|gt-p\d+|lenovo\s+tab|tab\s/', $userAgent)) {
            return 'tablet';
        }

        if (str_contains($userAgent, 'android') && !str_contains($userAgent, 'mobile')) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone|iemobile|opera mini|opera mobi|blackberry|webos|okhttp|dalvik|cfnetwork|dart|flutter/', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
