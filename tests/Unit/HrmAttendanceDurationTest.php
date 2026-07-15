<?php

namespace Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Workdo\Hrm\Services\AttendanceClockService;

class HrmAttendanceDurationTest extends TestCase
{
    #[Test]
    public function it_subtracts_multiple_unpaid_pauses(): void
    {
        $summary = app(AttendanceClockService::class)->calculateDurationSummary(
            Carbon::parse('2026-07-14 08:00:00'),
            Carbon::parse('2026-07-14 17:00:00'),
            [
                ['started_at' => '2026-07-14 10:00:00', 'ended_at' => '2026-07-14 10:15:00', 'counts_as_work' => false],
                ['started_at' => '2026-07-14 13:00:00', 'ended_at' => '2026-07-14 13:45:00', 'counts_as_work' => false],
            ],
        );

        $this->assertSame(32400, $summary['elapsed_seconds']);
        $this->assertSame(3600, $summary['unpaid_pause_seconds']);
        $this->assertSame(28800, $summary['worked_seconds']);
    }

    #[Test]
    public function official_duty_remains_paid_working_time(): void
    {
        $summary = app(AttendanceClockService::class)->calculateDurationSummary(
            '2026-07-14 08:00:00', '2026-07-14 17:00:00',
            [['started_at' => '2026-07-14 14:00:00', 'ended_at' => '2026-07-14 15:30:00', 'counts_as_work' => true]],
        );

        $this->assertSame(5400, $summary['paid_outside_seconds']);
        $this->assertSame(32400, $summary['worked_seconds']);
    }

    #[Test]
    public function an_overnight_session_keeps_the_full_elapsed_time(): void
    {
        $summary = app(AttendanceClockService::class)->calculateDurationSummary(
            '2026-07-14 20:00:00', '2026-07-15 08:00:00', []
        );

        $this->assertSame(43200, $summary['elapsed_seconds']);
        $this->assertSame(43200, $summary['worked_seconds']);
    }
}
