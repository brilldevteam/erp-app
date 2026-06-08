<?php

namespace Workdo\Appointment\Http\Controllers;

use Workdo\Appointment\Models\Schedule;
use Workdo\Appointment\Models\Appointment;
use Workdo\Appointment\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Appointment\Http\Requests\ApproveScheduleRequest;
use Workdo\Appointment\Events\AppointmentStatus;

class ScheduleController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-schedules')){
            $schedules = Schedule::query()
                ->with(['appointment', 'user'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-schedules')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-schedules')) {
                        $q->where('created_by', creatorId())
                          ->where(function($query) {
                              $query->where('creator_id', Auth::id())
                                    ->orWhere('user_id', Auth::id());
                          });
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('name'), function($q) {
                    $search = request('name');
                    $q->where(function($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                              ->orWhere('email', 'like', '%' . $search . '%')
                              ->orWhere('unique_id', 'like', '%' . $search . '%')
                              ->orWhereHas('appointment', function($q) use ($search) {
                                  $q->where('appointment_name', 'like', '%' . $search . '%');
                              });
                    });
                })
                ->when(request('status'), function($q) {
                    $q->where('status', request('status'));
                })
                ->when(request('date_from'), function($q) {
                    $q->whereDate('date', '>=', request('date_from'));
                })
                ->when(request('date_to'), function($q) {
                    $q->whereDate('date', '<=', request('date_to'));
                })
                ->when(request('appointment_id'), function($q) {
                    $q->where('appointment_id', request('appointment_id'));
                })
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            $users = User::emp()
                ->where('created_by', creatorId())
                ->select('id', 'name')
                ->get();

            // Transform schedules to include question names
            $transformedSchedules = $schedules->through(function ($schedule) {
                if ($schedule->questions) {
                    $questionsData = json_decode($schedule->questions, true) ?? [];
                    $questionNames = [];

                    foreach ($questionsData as $questionId => $answer) {
                        $question = Question::find($questionId);
                        if ($question) {
                            $questionNames[$question->question_name] = $answer;
                        } else {
                            $questionNames['Question ID: ' . $questionId] = $answer;
                        }
                    }

                    $schedule->questions_with_names = $questionNames;
                }
                return $schedule;
            });

            return Inertia::render('Appointment/Schedules/Index', [
                'schedules' => $transformedSchedules,
                'users' => $users,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function approve(ApproveScheduleRequest $request, Schedule $schedule)
    {
        if(Auth::user()->can('schedule-actions')){
            $validated = $request->validated();

            $schedule->update([
                'status' => 'approved',
                'user_id' => $validated['user_id']
            ]);

            // Send appointment status update email
            if(company_setting('Appointment Status Update', $schedule->created_by) == 'on') {
                $emailData = [
                    'appointment_name' => $schedule->appointment->appointment_name ?? 'Appointment',
                    'appointment_user_name' => $schedule->name,
                    'appointment_user_email' => $schedule->email,
                    'appointment_date' => date('d-m-Y', strtotime($schedule->date)),
                    'appointment_time' => date('g:i A', strtotime($schedule->start_time)) . ' - ' . date('g:i A', strtotime($schedule->end_time)),
                    'appointment_number' => $schedule->unique_id,
                    'appointment_status' => 'Approved',
                ];

                \App\Models\EmailTemplate::sendEmailTemplate('Appointment Status Update', [$schedule->email], $emailData, $schedule->created_by);
            }

            AppointmentStatus::dispatch($request, $schedule);
            return redirect()->route('appointment.schedules.index')->with('success', __('The schedule has been approved successfully.'));
        }
        else{
            return redirect()->route('appointment.schedules.index')->with('error', __('Permission denied'));
        }
    }

    public function reject(Request $request, Schedule $schedule)
    {
        if(Auth::user()->can('schedule-actions')){
            $schedule->update([
                'status' => 'reject'
            ]);

            // Send appointment status update email
            if(company_setting('Appointment Status Update', $schedule->created_by) == 'on') {
                $emailData = [
                    'appointment_name' => $schedule->appointment->appointment_name ?? 'Appointment',
                    'appointment_user_name' => $schedule->name,
                    'appointment_user_email' => $schedule->email,
                    'appointment_date' => date('d-m-Y', strtotime($schedule->date)),
                    'appointment_time' => date('g:i A', strtotime($schedule->start_time)) . ' - ' . date('g:i A', strtotime($schedule->end_time)),
                    'appointment_number' => $schedule->unique_id,
                    'appointment_status' => 'Rejected',
                ];

                \App\Models\EmailTemplate::sendEmailTemplate('Appointment Status Update', [$schedule->email], $emailData, $schedule->created_by);
            }

            AppointmentStatus::dispatch($request, $schedule);
            return redirect()->route('appointment.schedules.index')->with('success', __('The schedule has been rejected successfully.'));
        }
        else{
            return redirect()->route('appointment.schedules.index')->with('error', __('Permission denied'));
        }
    }

    public function complete(Request $request, Schedule $schedule)
    {
        if(Auth::user()->can('schedule-actions')){
            $schedule->update([
                'status' => 'complete'
            ]);

            // Send appointment status update email
            if(company_setting('Appointment Status Update', $schedule->created_by) == 'on') {
                $emailData = [
                    'appointment_name' => $schedule->appointment->appointment_name ?? 'Appointment',
                    'appointment_user_name' => $schedule->name,
                    'appointment_user_email' => $schedule->email,
                    'appointment_date' => date('d-m-Y', strtotime($schedule->date)),
                    'appointment_time' => date('g:i A', strtotime($schedule->start_time)) . ' - ' . date('g:i A', strtotime($schedule->end_time)),
                    'appointment_number' => $schedule->unique_id,
                    'appointment_status' => 'Completed',
                ];

                \App\Models\EmailTemplate::sendEmailTemplate('Appointment Status Update', [$schedule->email], $emailData, $schedule->created_by);
            }
            AppointmentStatus::dispatch($request, $schedule);

            return redirect()->route('appointment.schedules.index')->with('success', __('The schedule has been completed successfully.'));
        }
        else{
            return redirect()->route('appointment.schedules.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(Request $request, Schedule $schedule)
    {
        if(Auth::user()->can('delete-schedules')){
            $schedule->delete();

            // Check if request came from calendar page
            $redirectRoute = $request->header('referer') && str_contains($request->header('referer'), '/calendar')
                ? 'appointment.appointments.calendar'
                : 'appointment.schedules.index';

            return redirect()->route($redirectRoute)->with('success', __('The schedule has been deleted.'));
        }
        else{
            $redirectRoute = $request->header('referer') && str_contains($request->header('referer'), '/calendar')
                ? 'appointment.appointments.calendar'
                : 'appointment.schedules.index';

            return redirect()->route($redirectRoute)->with('error', __('Permission denied'));
        }
    }
}