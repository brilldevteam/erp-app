<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;
use Workdo\Hrm\Http\Controllers\EmployeeController;

class EmployeeAttendanceFilterTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_attendance_history_defaults_to_current_month(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');

        $filters = $this->normalize([]);

        $this->assertSame('month', $filters['filter']);
        $this->assertSame('2026-07-01', $filters['from']);
        $this->assertSame('2026-07-31', $filters['to']);
    }

    public function test_yesterday_and_exact_date_filters_are_normalized(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');

        $yesterday = $this->normalize(['attendance_filter' => 'yesterday']);
        $exactDate = $this->normalize(['attendance_filter' => 'date', 'attendance_date' => '2026-07-10']);

        $this->assertSame('2026-07-14', $yesterday['from']);
        $this->assertSame('2026-07-14', $yesterday['to']);
        $this->assertSame('2026-07-10', $exactDate['from']);
        $this->assertSame('2026-07-10', $exactDate['to']);
    }

    public function test_reversed_custom_range_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->normalize([
            'attendance_filter' => 'range',
            'attendance_from' => '2026-07-15',
            'attendance_to' => '2026-07-14',
        ]);
    }

    private function normalize(array $query): array
    {
        $request = Request::create('/hrm/employees/1', 'GET', $query);
        $this->app->instance('request', $request);

        $method = new ReflectionMethod(EmployeeController::class, 'normalizeAttendanceFilters');
        $method->setAccessible(true);

        return $method->invoke(new EmployeeController());
    }
}
