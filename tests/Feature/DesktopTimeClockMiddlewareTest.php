<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureDesktopTimeClock;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DesktopTimeClockMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(EnsureDesktopTimeClock::class)
            ->any('/_tests/desktop-time-clock', fn () => response()->json(['allowed' => true]));
    }

    #[Test]
    public function desktop_requests_can_reach_time_clock_routes(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126.0')
            ->postJson('/_tests/desktop-time-clock')
            ->assertOk()
            ->assertJson(['allowed' => true]);
    }

    #[Test]
    public function mobile_and_tablet_requests_receive_a_desktop_only_error(): void
    {
        $agents = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 13; SM-T970) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
            'Dalvik/2.1.0 (Linux; U; Android 14; Pixel 8 Build/AP1A.240505.004)',
        ];

        foreach ($agents as $agent) {
            $this->withHeader('User-Agent', $agent)
                ->postJson('/_tests/desktop-time-clock')
                ->assertForbidden()
                ->assertJson([
                    'code' => 'desktop_only',
                    'message' => 'This feature is only accessible from a desktop or laptop computer.',
                ]);
        }
    }
}
