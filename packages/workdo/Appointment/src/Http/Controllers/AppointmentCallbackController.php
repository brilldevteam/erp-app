<?php

namespace Workdo\Appointment\Http\Controllers;

use Workdo\Appointment\Models\AppointmentCallback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AppointmentCallbackController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-appointment-callbacks')){
            $callbacks = AppointmentCallback::query()
                ->with(['schedule', 'appointment', 'user'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-appointment-callbacks')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-appointment-callbacks')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('name'), function($q) {
                    $search = request('name');
                    $q->whereHas('schedule', function($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                              ->orWhere('email', 'like', '%' . $search . '%')
                              ->orWhere('unique_id', 'like', '%' . $search . '%');
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
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            $users = User::emp()
                ->where('created_by', creatorId())
                ->select('id', 'name')
                ->get();

            return Inertia::render('Appointment/AppointmentCallbacks/Index', [
                'callbacks' => $callbacks,
                'users' => $users,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function approve(AppointmentCallback $callback)
    {
        if(Auth::user()->can('manage-appointment-callbacks')){
            $callback->update([
                'status' => 'approved',
                'user_id' => $callback->schedule->user_id
            ]);

            // Send callback status update email
            if(company_setting('Appointment Callback Status Update', $callback->created_by) == 'on') {
                $emailData = [
                    'appointment_name' => $callback->appointment->appointment_name ?? 'Appointment',
                    'appointment_user_name' => $callback->schedule->name,
                    'appointment_user_email' => $callback->schedule->email,
                    'callback_date' => date('d-m-Y', strtotime($callback->date)),
                    'callback_time' => date('g:i A', strtotime($callback->start_time)) . ' - ' . date('g:i A', strtotime($callback->end_time)),
                    'callback_reason' => $callback->reason,
                    'callback_status' => 'Approved',
                ];
                
                \App\Models\EmailTemplate::sendEmailTemplate('Appointment Callback Status Update', [$callback->schedule->email], $emailData, $callback->created_by);
            }

            return redirect()->route('appointment.callbacks.index')->with('success', __('The callback has been approved successfully.'));
        }
        else{
            return redirect()->route('appointment.callbacks.index')->with('error', __('Permission denied'));
        }
    }

    public function reject(AppointmentCallback $callback)
    {
        if(Auth::user()->can('manage-appointment-callbacks')){
            $callback->update([
                'status' => 'reject'
            ]);

            // Send callback status update email
            if(company_setting('Appointment Callback Status Update', $callback->created_by) == 'on') {
                $emailData = [
                    'appointment_name' => $callback->appointment->appointment_name ?? 'Appointment',
                    'appointment_user_name' => $callback->schedule->name,
                    'appointment_user_email' => $callback->schedule->email,
                    'callback_date' => date('d-m-Y', strtotime($callback->date)),
                    'callback_time' => date('g:i A', strtotime($callback->start_time)) . ' - ' . date('g:i A', strtotime($callback->end_time)),
                    'callback_reason' => $callback->reason,
                    'callback_status' => 'Rejected',
                ];
                
                \App\Models\EmailTemplate::sendEmailTemplate('Appointment Callback Status Update', [$callback->schedule->email], $emailData, $callback->created_by);
            }

            return redirect()->route('appointment.callbacks.index')->with('success', __('The callback has been rejected successfully.'));
        }
        else{
            return redirect()->route('appointment.callbacks.index')->with('error', __('Permission denied'));
        }
    }

    public function complete(AppointmentCallback $callback)
    {
        if(Auth::user()->can('manage-appointment-callbacks')){
            $callback->update([
                'status' => 'complete'
            ]);

            // Send callback status update email
            if(company_setting('Appointment Callback Status Update', $callback->created_by) == 'on') {
                $emailData = [
                    'appointment_name' => $callback->appointment->appointment_name ?? 'Appointment',
                    'appointment_user_name' => $callback->schedule->name,
                    'appointment_user_email' => $callback->schedule->email,
                    'callback_date' => date('d-m-Y', strtotime($callback->date)),
                    'callback_time' => date('g:i A', strtotime($callback->start_time)) . ' - ' . date('g:i A', strtotime($callback->end_time)),
                    'callback_reason' => $callback->reason,
                    'callback_status' => 'Completed',
                ];
                
                \App\Models\EmailTemplate::sendEmailTemplate('Appointment Callback Status Update', [$callback->schedule->email], $emailData, $callback->created_by);
            }

            return redirect()->route('appointment.callbacks.index')->with('success', __('The callback has been completed successfully.'));
        }
        else{
            return redirect()->route('appointment.callbacks.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(AppointmentCallback $callback)
    {
        if(Auth::user()->can('delete-appointment-callbacks')){
            $callback->delete();
            return redirect()->route('appointment.callbacks.index')->with('success', __('The callback has been deleted.'));
        }
        else{
            return redirect()->route('appointment.callbacks.index')->with('error', __('Permission denied'));
        }
    }
}