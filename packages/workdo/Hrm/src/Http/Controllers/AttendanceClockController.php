<?php

namespace Workdo\Hrm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Workdo\Hrm\Models\Attendance;
use Workdo\Hrm\Models\AttendanceCorrectionRequest;
use Workdo\Hrm\Models\IpRestrict;
use Workdo\Hrm\Services\AttendanceClockService;

class AttendanceClockController extends Controller
{
    public function __construct(private AttendanceClockService $clock)
    {
    }

    public function clockIn(Request $request)
    {
        abort_unless(Auth::user()->can('use-staff-time-clock'), 403);
        abort_unless(Auth::user()->can('clock-in'), 403);
        $this->assertAllowedIp($request);
        $attendance = $this->clock->clockIn();
        return back()->with('success', __('Clocked in successfully.'))->with('clock_status', $this->clock->serialize($attendance));
    }

    public function pause(Request $request)
    {
        abort_unless(Auth::user()->can('use-staff-time-clock'), 403);
        abort_unless(Auth::user()->can('pause-attendance'), 403);
        $this->assertAllowedIp($request);
        $data = $request->validate([
            'reason' => 'required|in:break,personal,official_duty,other',
            'details' => 'nullable|string|max:1000',
        ]);
        $this->clock->pause($data['reason'], $data['details'] ?? null);
        return back()->with('success', __('Work timer paused.'));
    }

    public function resume(Request $request)
    {
        abort_unless(Auth::user()->can('use-staff-time-clock'), 403);
        abort_unless(Auth::user()->can('pause-attendance'), 403);
        $this->assertAllowedIp($request);
        $this->clock->resume();
        return back()->with('success', __('Work timer resumed.'));
    }

    public function clockOut(Request $request)
    {
        abort_unless(Auth::user()->can('use-staff-time-clock'), 403);
        abort_unless(Auth::user()->can('clock-out'), 403);
        $this->assertAllowedIp($request);
        $data = $request->validate(['work_update' => 'nullable|string|max:5000']);
        $this->clock->clockOut($data['work_update'] ?? null);
        return back()->with('success', __('Clocked out successfully.'));
    }

    public function updateWorkNote(Request $request)
    {
        abort_unless(Auth::user()->can('use-staff-time-clock'), 403);
        abort_unless(Auth::user()->can('update-own-work-update'), 403);
        $data = $request->validate(['work_update' => 'nullable|string|max:5000']);
        $this->clock->updateWorkNote($data['work_update'] ?? null);
        return back()->with('success', __('Daily work update saved.'));
    }

    public function status()
    {
        abort_unless(Auth::user()->can('use-staff-time-clock'), 403);
        abort_unless(Auth::user()->can('manage-own-attendances'), 403);
        return response()->json($this->clock->currentStatus());
    }

    public function requestCorrection(Request $request, Attendance $attendance)
    {
        abort_unless(Auth::user()->can('request-attendance-correction'), 403);
        $data = $request->validate([
            'requested_clock_in' => 'nullable|date',
            'requested_clock_out' => 'nullable|date',
            'reason' => 'required|string|max:2000',
        ]);
        $this->clock->requestCorrection($attendance, $data);
        return back()->with('success', __('Attendance correction requested.'));
    }

    public function reviewCorrection(Request $request, AttendanceCorrectionRequest $correction)
    {
        abort_unless(Auth::user()->can('review-attendance-corrections'), 403);
        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'decision_note' => 'required|string|max:2000',
        ]);
        $this->clock->reviewCorrection($correction, $data['decision'], $data['decision_note']);
        return back()->with('success', __('Attendance correction reviewed.'));
    }

    private function assertAllowedIp(Request $request): void
    {
        $setting = getCompanyAllSetting(creatorId());
        if (($setting['ip_restrict'] ?? 'off') === 'on') {
            abort_unless(IpRestrict::where('ip', $request->ip())->where('created_by', creatorId())->exists(), 403, __('This IP is not allowed to use the time clock.'));
        }
    }
}
