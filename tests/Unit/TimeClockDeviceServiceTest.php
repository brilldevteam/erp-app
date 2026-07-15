<?php

namespace Tests\Unit;

use App\Services\TimeClockDeviceService;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimeClockDeviceServiceTest extends TestCase
{
    #[Test]
    public function desktop_and_laptop_user_agents_are_allowed(): void
    {
        $service = app(TimeClockDeviceService::class);

        $this->assertTrue($service->allowsTimeClock($this->request(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36'
        )));
        $this->assertTrue($service->allowsTimeClock($this->request(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 Version/17.5 Safari/605.1.15'
        )));
    }

    #[Test]
    public function phones_and_tablets_are_blocked(): void
    {
        $service = app(TimeClockDeviceService::class);

        $agents = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 Chrome/126.0 Mobile Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 13; SM-T970) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
            'Dalvik/2.1.0 (Linux; U; Android 14; Pixel 8 Build/AP1A.240505.004)',
        ];

        foreach ($agents as $agent) {
            $this->assertFalse($service->allowsTimeClock($this->request($agent)));
        }
    }

    #[Test]
    public function browser_mobile_hint_and_client_cookie_are_blocked(): void
    {
        $service = app(TimeClockDeviceService::class);
        $hintedRequest = $this->request('Mozilla/5.0', ['HTTP_SEC_CH_UA_MOBILE' => '?1']);
        $cookieRequest = $this->request('Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5)');
        $cookieRequest->cookies->set('time_clock_device', 'mobile_or_tablet');

        $this->assertFalse($service->allowsTimeClock($hintedRequest));
        $this->assertFalse($service->allowsTimeClock($cookieRequest));
    }

    private function request(string $userAgent, array $server = []): Request
    {
        return Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => $userAgent,
            ...$server,
        ]);
    }
}
