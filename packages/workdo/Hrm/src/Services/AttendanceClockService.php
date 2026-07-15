<?php

namespace Workdo\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Workdo\Hrm\Models\Attendance;
use Workdo\Hrm\Models\AttendanceActionLog;
use Workdo\Hrm\Models\AttendanceCorrectionRequest;
use Workdo\Hrm\Models\AttendanceInterval;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\Holiday;
use Workdo\Hrm\Models\LeaveApplication;
use Workdo\Hrm\Models\Shift;
use App\Models\User;

class AttendanceClockService
{
    public const UNPAID_REASONS = ['break', 'personal', 'other'];
    public const ALL_REASONS = ['break', 'personal', 'official_duty', 'other'];

    public function clockIn(): Attendance
    {
        return DB::transaction(function () {
            $employee = Employee::where('user_id', Auth::id())
                ->where('created_by', creatorId())
                ->lockForUpdate()
                ->first();

            if (!$employee) {
                throw ValidationException::withMessages(['attendance' => __('Please convert staff to employee first.')]);
            }

            if (!$employee->shift) {
                throw ValidationException::withMessages(['attendance' => __('Please assign a shift before clocking in.')]);
            }

            $open = Attendance::where('employee_id', Auth::id())
                ->where('created_by', creatorId())
                ->whereIn('work_status', ['working', 'paused'])
                ->lockForUpdate()
                ->first();

            if ($open) {
                throw ValidationException::withMessages(['attendance' => __('You already have an open attendance session.')]);
            }

            $today = now()->toDateString();
            $existing = Attendance::where('employee_id', Auth::id())
                ->where('created_by', creatorId())
                ->whereDate('date', $today)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages(['attendance' => __('You have already recorded attendance for today.')]);
            }

            $this->assertClockingDayIsAllowed($today);

            $attendance = Attendance::create([
                'employee_id' => Auth::id(),
                'shift_id' => $employee->shift,
                'date' => $today,
                'clock_in' => now(),
                'work_status' => 'working',
                'status' => 'absent',
                'creator_id' => Auth::id(),
                'created_by' => creatorId(),
            ]);

            $this->log($attendance, 'clock_in', ['at' => $attendance->clock_in]);
            return $this->recalculate($attendance);
        });
    }

    public function pause(string $reason, ?string $details): Attendance
    {
        if (!in_array($reason, self::ALL_REASONS, true)) {
            throw ValidationException::withMessages(['reason' => __('Invalid pause reason.')]);
        }
        if ($reason === 'other' && blank($details)) {
            throw ValidationException::withMessages(['details' => __('An explanation is required for Other.')]);
        }

        return DB::transaction(function () use ($reason, $details) {
            $attendance = $this->openAttendanceForUpdate();
            if ($attendance->work_status !== 'working') {
                throw ValidationException::withMessages(['attendance' => __('The attendance session is already paused.')]);
            }

            AttendanceInterval::create([
                'attendance_id' => $attendance->id,
                'reason' => $reason,
                'details' => $details,
                'counts_as_work' => $reason === 'official_duty',
                'started_at' => now(),
                'created_by_user' => Auth::id(),
            ]);
            $attendance->update(['work_status' => 'paused']);
            $this->log($attendance, 'pause', ['reason' => $reason, 'details' => $details, 'at' => now()]);
            return $this->recalculate($attendance);
        });
    }

    public function resume(): Attendance
    {
        return DB::transaction(function () {
            $attendance = $this->openAttendanceForUpdate();
            if ($attendance->work_status !== 'paused') {
                throw ValidationException::withMessages(['attendance' => __('The attendance session is not paused.')]);
            }

            $interval = AttendanceInterval::where('attendance_id', $attendance->id)
                ->whereNull('ended_at')->lockForUpdate()->latest('started_at')->first();
            if (!$interval) {
                throw ValidationException::withMessages(['attendance' => __('The open pause interval could not be found.')]);
            }

            $interval->update(['ended_at' => now()]);
            $attendance->update(['work_status' => 'working']);
            $this->log($attendance, 'resume', ['at' => now()]);
            return $this->recalculate($attendance);
        });
    }

    public function clockOut(?string $workUpdate = null): Attendance
    {
        return DB::transaction(function () use ($workUpdate) {
            $attendance = $this->openAttendanceForUpdate();
            $now = now();

            AttendanceInterval::where('attendance_id', $attendance->id)
                ->whereNull('ended_at')->lockForUpdate()->update(['ended_at' => $now, 'updated_at' => $now]);

            $attendance->update([
                'clock_out' => $now,
                'work_status' => 'completed',
                'work_update' => $workUpdate ?? $attendance->work_update,
            ]);
            $this->log($attendance, 'clock_out', ['at' => $now]);
            $attendance = $this->recalculate($attendance);
            if ($attendance->is_abnormally_long) {
                $this->notifyHr(__('Abnormally long attendance session'), __(':employee clocked out after :hours hours.', [
                    'employee' => $attendance->user?->name ?? __('A staff member'),
                    'hours' => round($attendance->elapsed_seconds / 3600, 2),
                ]));
            }
            return $attendance;
        });
    }

    public function updateWorkNote(?string $workUpdate): Attendance
    {
        return DB::transaction(function () use ($workUpdate) {
            $attendance = $this->openAttendanceForUpdate();
            $attendance->update(['work_update' => $workUpdate]);
            $this->log($attendance, 'work_update', ['length' => mb_strlen((string) $workUpdate)]);
            return $this->recalculate($attendance);
        });
    }

    public function requestCorrection(Attendance $attendance, array $data): AttendanceCorrectionRequest
    {
        if ($attendance->created_by !== creatorId() || $attendance->employee_id !== Auth::id()) {
            abort(403);
        }
        if ($attendance->work_status !== 'completed') {
            throw ValidationException::withMessages(['attendance' => __('Only completed attendance can be corrected.')]);
        }
        if ($attendance->correctionRequests()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['attendance' => __('A correction request is already pending.')]);
        }

        return DB::transaction(function () use ($attendance, $data) {
            $request = AttendanceCorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'requester_id' => Auth::id(),
                'original_clock_in' => $attendance->clock_in,
                'original_clock_out' => $attendance->clock_out,
                'requested_clock_in' => $data['requested_clock_in'] ?? null,
                'requested_clock_out' => $data['requested_clock_out'] ?? null,
                'reason' => $data['reason'],
                'created_by' => creatorId(),
            ]);
            $this->log($attendance, 'correction_requested', [
                'request_id' => $request->id,
                'original_clock_in' => $attendance->clock_in,
                'original_clock_out' => $attendance->clock_out,
                'requested_clock_in' => $request->requested_clock_in,
                'requested_clock_out' => $request->requested_clock_out,
            ]);
            $this->notifyHr(__('Attendance correction requested'), __(':employee requested a correction for :date.', [
                'employee' => Auth::user()->name,
                'date' => $attendance->date->format('Y-m-d'),
            ]));
            return $request;
        });
    }

    public function reviewCorrection(AttendanceCorrectionRequest $request, string $decision, string $note): AttendanceCorrectionRequest
    {
        if ($request->created_by !== creatorId() || $request->status !== 'pending') {
            abort(404);
        }

        return DB::transaction(function () use ($request, $decision, $note) {
            $request = AttendanceCorrectionRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') {
                throw ValidationException::withMessages(['request' => __('This request has already been reviewed.')]);
            }

            $attendance = Attendance::whereKey($request->attendance_id)
                ->where('created_by', creatorId())->lockForUpdate()->firstOrFail();

            $original = ['clock_in' => $attendance->clock_in, 'clock_out' => $attendance->clock_out];
            if ($decision === 'approved') {
                $clockIn = $request->requested_clock_in ?: $attendance->clock_in;
                $clockOut = $request->requested_clock_out ?: $attendance->clock_out;
                if (!$clockOut || Carbon::parse($clockOut)->lte(Carbon::parse($clockIn))) {
                    throw ValidationException::withMessages(['request' => __('Clock out must be after clock in.')]);
                }
                $outsideInterval = $attendance->intervals()->where(function ($query) use ($clockIn, $clockOut) {
                    $query->where('started_at', '<', $clockIn)
                        ->orWhere('ended_at', '>', $clockOut);
                })->exists();
                if ($outsideInterval) {
                    throw ValidationException::withMessages(['request' => __('Requested times cannot exclude recorded pause intervals.')]);
                }
                $attendance->update(['clock_in' => $clockIn, 'clock_out' => $clockOut]);
                $this->recalculate($attendance);
            }

            $request->update([
                'status' => $decision,
                'reviewed_by' => Auth::id(),
                'decision_note' => $note,
                'reviewed_at' => now(),
            ]);
            $this->log($attendance, 'correction_'.$decision, [
                'request_id' => $request->id, 'note' => $note, 'original' => $original,
                'result' => ['clock_in' => $attendance->fresh()->clock_in, 'clock_out' => $attendance->fresh()->clock_out],
            ]);
            $this->notifyUser($request->requester, __('Attendance correction :decision', ['decision' => $decision]), __('Your attendance correction for :date was :decision. HR note: :note', [
                'date' => $attendance->date->format('Y-m-d'), 'decision' => $decision, 'note' => $note,
            ]));
            return $request->fresh();
        });
    }

    public function currentStatus(): array
    {
        $attendance = Attendance::with(['intervals', 'shift'])
            ->where('employee_id', Auth::id())
            ->where('created_by', creatorId())
            ->whereIn('work_status', ['working', 'paused'])
            ->latest('clock_in')->first();

        if (!$attendance) {
            $attendance = Attendance::with(['intervals', 'shift'])
                ->where('employee_id', Auth::id())->where('created_by', creatorId())
                ->whereDate('date', now()->toDateString())->latest()->first();
        }

        if (!$attendance) {
            return ['work_status' => 'not_started', 'server_time' => now()->toIso8601String()];
        }

        if ($attendance->work_status !== 'completed') {
            $attendance = $this->recalculate($attendance);
        }

        return $this->serialize($attendance);
    }

    public function recalculate(Attendance $attendance): Attendance
    {
        $attendance->loadMissing(['intervals', 'shift']);
        $end = $attendance->clock_out ? Carbon::parse($attendance->clock_out) : now();
        $start = Carbon::parse($attendance->clock_in);
        $summary = $this->calculateDurationSummary($start, $end, $attendance->intervals);
        $elapsed = $summary['elapsed_seconds'];
        $unpaid = $summary['unpaid_pause_seconds'];
        $paidOutside = $summary['paid_outside_seconds'];
        $worked = $summary['worked_seconds'];
        $workedHours = round($worked / 3600, 2);
        $breakHours = round($unpaid / 3600, 2);
        $standardHours = $this->standardHours($attendance->shift);
        $overtimeHours = max(0, round($workedHours - $standardHours, 2));
        $employee = Employee::where('user_id', $attendance->employee_id)
            ->where('created_by', $attendance->created_by)->first();

        $status = 'absent';
        if ($workedHours >= $standardHours) {
            $status = 'present';
        } elseif ($workedHours >= ($standardHours / 2)) {
            $status = 'half day';
        }

        $attendance->forceFill([
            'elapsed_seconds' => $elapsed,
            'unpaid_pause_seconds' => $unpaid,
            'paid_outside_seconds' => $paidOutside,
            'worked_seconds' => $worked,
            'total_hour' => $workedHours,
            'break_hour' => $breakHours,
            'overtime_hours' => $overtimeHours,
            'overtime_amount' => round($overtimeHours * (float) ($employee?->rate_per_hour ?? 0), 2),
            'status' => $status,
            'is_abnormally_long' => $elapsed > 16 * 3600,
        ])->save();

        return $attendance->fresh(['intervals', 'shift', 'user']);
    }

    public function calculateDurationSummary(Carbon|string $clockIn, Carbon|string $clockOut, iterable $intervals): array
    {
        $start = Carbon::parse($clockIn);
        $end = Carbon::parse($clockOut);
        $elapsed = max(0, (int) $start->diffInSeconds($end));
        $unpaid = 0;
        $paidOutside = 0;

        foreach ($intervals as $interval) {
            $startedAt = data_get($interval, 'started_at');
            $endedAt = data_get($interval, 'ended_at') ?: $end;
            $intervalStart = Carbon::parse($startedAt)->max($start);
            $intervalEnd = Carbon::parse($endedAt)->min($end);
            $seconds = $intervalEnd->gt($intervalStart) ? (int) $intervalStart->diffInSeconds($intervalEnd) : 0;
            if ((bool) data_get($interval, 'counts_as_work')) {
                $paidOutside += $seconds;
            } else {
                $unpaid += $seconds;
            }
        }

        $unpaid = min($unpaid, $elapsed);
        return [
            'elapsed_seconds' => $elapsed,
            'unpaid_pause_seconds' => $unpaid,
            'paid_outside_seconds' => min($paidOutside, $elapsed),
            'worked_seconds' => max(0, $elapsed - $unpaid),
        ];
    }

    public function serialize(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'work_status' => $attendance->work_status,
            'clock_in_time' => optional($attendance->clock_in)->toIso8601String(),
            'clock_out_time' => optional($attendance->clock_out)->toIso8601String(),
            'elapsed_seconds' => (int) $attendance->elapsed_seconds,
            'unpaid_pause_seconds' => (int) $attendance->unpaid_pause_seconds,
            'paid_outside_seconds' => (int) $attendance->paid_outside_seconds,
            'worked_seconds' => (int) $attendance->worked_seconds,
            'work_update' => $attendance->work_update,
            'is_abnormally_long' => (bool) $attendance->is_abnormally_long,
            'server_time' => now()->toIso8601String(),
            'timeline' => $attendance->intervals->map(fn ($interval) => [
                'id' => $interval->id,
                'reason' => $interval->reason,
                'details' => $interval->details,
                'counts_as_work' => $interval->counts_as_work,
                'started_at' => $interval->started_at?->toIso8601String(),
                'ended_at' => $interval->ended_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function openAttendanceForUpdate(): Attendance
    {
        $attendance = Attendance::where('employee_id', Auth::id())
            ->where('created_by', creatorId())
            ->whereIn('work_status', ['working', 'paused'])
            ->lockForUpdate()->latest('clock_in')->first();
        if (!$attendance) {
            throw ValidationException::withMessages(['attendance' => __('No open attendance session was found.')]);
        }
        return $attendance;
    }

    private function assertClockingDayIsAllowed(string $date): void
    {
        $day = Carbon::parse($date);
        $workingDays = json_decode(getCompanyAllSetting(creatorId())['working_days'] ?? '[]', true) ?: [];
        if (!in_array($day->dayOfWeek, $workingDays)) {
            throw ValidationException::withMessages(['attendance' => __('Attendance cannot be created for non-working days.')]);
        }
        if (Holiday::where('created_by', creatorId())->where('start_date', '<=', $date)->where('end_date', '>=', $date)->exists()) {
            throw ValidationException::withMessages(['attendance' => __('Attendance cannot be created on holidays.')]);
        }
        if (LeaveApplication::where('created_by', creatorId())->where('employee_id', Auth::id())
            ->where('status', 'approved')->where('start_date', '<=', $date)->where('end_date', '>=', $date)->exists()) {
            throw ValidationException::withMessages(['attendance' => __('You are on approved leave today.')]);
        }
    }

    private function standardHours(?Shift $shift): float
    {
        if (!$shift) {
            return 8;
        }
        $start = Carbon::parse($shift->start_time);
        $end = Carbon::parse($shift->end_time);
        if ($end->lte($start)) {
            $end->addDay();
        }
        $minutes = $start->diffInMinutes($end);
        if ($shift->break_start_time && $shift->break_end_time) {
            $breakStart = Carbon::parse($shift->break_start_time);
            $breakEnd = Carbon::parse($shift->break_end_time);
            if ($breakEnd->lte($breakStart)) {
                $breakEnd->addDay();
            }
            $minutes -= $breakStart->diffInMinutes($breakEnd);
        }
        return max(1, round($minutes / 60, 2));
    }

    private function log(Attendance $attendance, string $action, array $metadata = []): void
    {
        AttendanceActionLog::create([
            'attendance_id' => $attendance->id,
            'actor_id' => Auth::id(),
            'action' => $action,
            'metadata' => $metadata,
            'created_by' => creatorId(),
            'created_at' => now(),
        ]);
    }

    private function notifyHr(string $subject, string $message): void
    {
        $recipients = User::where(function ($query) {
            $query->whereKey(creatorId())->orWhere('created_by', creatorId());
        })->get()->filter(fn (User $user) => $user->id === creatorId() || $user->can('review-attendance-corrections'));

        foreach ($recipients as $recipient) {
            $this->notifyUser($recipient, $subject, $message);
        }
    }

    private function notifyUser(?User $user, string $subject, string $message): void
    {
        if (!$user?->email) {
            return;
        }
        try {
            Mail::raw($message, fn ($mail) => $mail->to($user->email)->subject($subject));
        } catch (\Throwable $exception) {
            Log::warning('Attendance notification could not be sent.', ['user_id' => $user->id, 'error' => $exception->getMessage()]);
        }
    }
}
