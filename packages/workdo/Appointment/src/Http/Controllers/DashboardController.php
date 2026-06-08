<?php

namespace Workdo\Appointment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Appointment\Models\Appointment;
use Workdo\Appointment\Models\Schedule;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if(Auth::user()->can('manage-appointment-dashboard')){
            $user = Auth::user();

            switch ($user->type) {
                case 'company':
                    return $this->companyDashboard($request);
                default:
                    return $this->userDashboard($request);
            }
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    private function companyDashboard(Request $request)
    {
            // Appointment stats
            $totalAppointments = Appointment::where('created_by', creatorId())->count();

            // Schedule stats
            $totalSchedules = Schedule::whereHas('appointment', function($q) {
                $q->where('created_by', creatorId());
            })->count();

            $approvedSchedules = Schedule::whereHas('appointment', function($q) {
                $q->where('created_by', creatorId());
            })->where('status', 'approved')->count();

            $rejectedSchedules = Schedule::whereHas('appointment', function($q) {
                $q->where('created_by', creatorId());
            })->where('status', 'reject')->count();

            $pendingSchedules = Schedule::whereHas('appointment', function($q) {
                $q->where('created_by', creatorId());
            })->where('status', 'pending')->count();

            $completeSchedules = Schedule::whereHas('appointment', function($q) {
                $q->where('created_by', creatorId());
            })->where('status', 'complete')->count();

            // Recent appointments
            $recentAppointments = Appointment::where('created_by', creatorId())
                ->get()
                ->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,
                        'encrypted_id' => $appointment->getEncryptedId(),
                        'appointment_name' => $appointment->appointment_name,
                        'appointment_type' => $appointment->appointment_type,
                        'week_day' => $appointment->week_day,
                        'created_at' => $appointment->created_at,
                    ];
                });

            // Recent schedules
            $recentSchedules = Schedule::whereHas('appointment', function($q) {
                $q->where('created_by', creatorId());
            })
            ->with(['appointment'])
            ->latest()
            ->take(6)
            ->get();

            // Calendar events
            $calendarEvents = Schedule::whereHas('appointment', function($q) {
                $q->where('created_by', creatorId());
            })
            ->with(['appointment'])
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'title' => $schedule->appointment->appointment_name ?? 'Appointment',
                    'startDate' => $schedule->date,
                    'endDate' => $schedule->date,
                    'time' => date('H:i', strtotime($schedule->start_time)),
                    'status' => $schedule->status,
                    'name' => $schedule->name,
                    'description' => $schedule->name . ' - ' . $schedule->status,
                    'type' => 'Appointment'
                ];
            });

            return Inertia::render('Appointment/Index', [
                'stats' => [
                    'total_appointments' => $totalAppointments,
                    'total_approved' => $approvedSchedules,
                    'total_rejected' => $rejectedSchedules,
                    'total_pending' => $pendingSchedules,
                ],
                'recent_appointments' => $recentAppointments,
                'recent_schedules' => $recentSchedules,
                'calendar_events' => $calendarEvents,
                'chart_data' => [
                    'total' => $totalSchedules,
                    'approved' => $approvedSchedules,
                    'rejected' => $rejectedSchedules,
                    'pending' => $pendingSchedules,
                    'complete' => $completeSchedules,
                ]
            ]);
        }

    private function userDashboard(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        // Get assigned schedules (where user is assigned) and created schedules
        $assignedSchedules = Schedule::where('created_by', creatorId())
            ->where(function($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('creator_id', $userId);
            })
            ->pluck('id');

        // Overview Statistics for staff
        $assignedSchedulesCount = $assignedSchedules->count();
        $pendingSchedules = Schedule::whereIn('id', $assignedSchedules)->where('status', 'pending')->count();
        $approvedSchedules = Schedule::whereIn('id', $assignedSchedules)->where('status', 'approved')->count();
        $completedSchedules = Schedule::whereIn('id', $assignedSchedules)->where('status', 'complete')->count();

        // Recent assigned schedules
        $recentSchedules = Schedule::whereIn('id', $assignedSchedules)
            ->with(['appointment'])
            ->latest()
            ->take(6)
            ->get();

        // Calendar events from assigned schedules
        $calendarEvents = Schedule::whereIn('id', $assignedSchedules)
            ->with(['appointment'])
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'title' => $schedule->appointment->appointment_name ?? 'Appointment',
                    'startDate' => $schedule->date,
                    'endDate' => $schedule->date,
                    'time' => date('H:i', strtotime($schedule->start_time)),
                    'status' => $schedule->status,
                    'name' => $schedule->name,
                    'description' => $schedule->name . ' - ' . $schedule->status,
                    'type' => 'Appointment'
                ];
            });

        return Inertia::render('Appointment/Dashboard/UserDashboard', [
            'stats' => [
                'assigned_schedules' => $assignedSchedulesCount,
                'pending_schedules' => $pendingSchedules,
                'approved_schedules' => $approvedSchedules,
                'completed_schedules' => $completedSchedules,
                'rejected_schedules' => 0,
            ],
            'recent_schedules' => $recentSchedules->map(function($schedule) {
                return [
                    'id' => $schedule->id,
                    'unique_id' => $schedule->unique_id,
                    'name' => $schedule->name,
                    'email' => $schedule->email,
                    'date' => $schedule->date,
                    'start_time' => $schedule->start_time,
                    'status' => $schedule->status,
                    'appointment_name' => $schedule->appointment->appointment_name ?? 'N/A',
                ];
            }),
            'calendar_events' => $calendarEvents,
            'performance_chart' => [
                ['name' => 'Completed', 'value' => $completedSchedules, 'color' => '#3b82f6'],
                ['name' => 'Approved', 'value' => $approvedSchedules, 'color' => '#10b981'],
                ['name' => 'Pending', 'value' => $pendingSchedules, 'color' => '#f59e0b'],
            ],
        ]);
    }
}