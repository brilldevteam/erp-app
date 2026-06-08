<?php

namespace Workdo\Appointment\Http\Controllers;

use Workdo\Appointment\Models\Appointment;
use Workdo\Appointment\Models\AppointmentHour;
use Workdo\Appointment\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Appointment\Http\Requests\StoreAppointmentRequest;
use Workdo\Appointment\Http\Requests\UpdateAppointmentRequest;
use Workdo\Appointment\Events\CreateAppointment;
use Workdo\Appointment\Events\UpdateAppointment;
use Workdo\Appointment\Events\DestroyAppointment;

class AppointmentController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-appointments')){
            $appointments = Appointment::query()

                ->where(function($q) {
                    if(Auth::user()->can('manage-any-appointments')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-appointments')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('appointment_name'), function($q) {
                    $q->where(function($query) {
                    $query->where('appointment_name', 'like', '%' . request('appointment_name') . '%');
                    });
                })
                ->when(request('appointment_type') !== null && request('appointment_type') !== '', fn($q) => $q->where('appointment_type', request('appointment_type')))

                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            // Add encrypted_id to each appointment
            $appointments->getCollection()->transform(function ($appointment) {
                $appointment->encrypted_id = $appointment->getEncryptedId();
                return $appointment;
            });

            return Inertia::render('Appointment/Appointments/Index', [
                'appointments' => $appointments,

            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreAppointmentRequest $request)
    {
        if(Auth::user()->can('create-appointments')){
            $validated = $request->validated();

            $validated['phone_enabled'] = $request->boolean('phone_enabled', false);
            $validated['enabled'] = $request->boolean('enabled', true);
            $validated['week_day'] = json_encode($validated['week_day']);
            $validated['question_ids'] = json_encode($validated['question_ids'] ?? []);

            $appointment = new Appointment();
            $appointment->appointment_name = $validated['appointment_name'];
            $appointment->appointment_type = $validated['appointment_type'];
            $appointment->week_day = $validated['week_day'];
            $appointment->duration = $validated['duration'];
            $appointment->phone_enabled = $validated['phone_enabled'];
            $appointment->question_ids = $validated['question_ids'];
            $appointment->enabled = $validated['enabled'];

            $appointment->creator_id = Auth::id();
            $appointment->created_by = creatorId();
            $appointment->save();

            // Dispatch event for packages to handle their fields
            CreateAppointment::dispatch($request, $appointment);

            return redirect()->route('appointment.appointments.index')->with('success', __('The appointment has been created successfully.'));
        }
        else{
            return redirect()->route('appointment.appointments.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        if(Auth::user()->can('edit-appointments')){
            $validated = $request->validated();

            $validated['phone_enabled'] = $request->boolean('phone_enabled', false);
            $validated['enabled'] = $request->boolean('enabled', true);
            $validated['week_day'] = json_encode($validated['week_day']);
            $validated['question_ids'] = json_encode($validated['question_ids'] ?? []);

            $appointment->appointment_name = $validated['appointment_name'];
            $appointment->appointment_type = $validated['appointment_type'];
            $appointment->week_day = $validated['week_day'];
            $appointment->duration = $validated['duration'];
            $appointment->phone_enabled = $validated['phone_enabled'];
            $appointment->question_ids = $validated['question_ids'];
            $appointment->enabled = $validated['enabled'];

            $appointment->save();

            // Dispatch event for packages to handle their fields
            UpdateAppointment::dispatch($request, $appointment);

            return redirect()->back()->with('success', __('The appointment details are updated successfully.'));
        }
        else{
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(Appointment $appointment)
    {
        if(Auth::user()->can('delete-appointments')){
            DestroyAppointment::dispatch($appointment);

            $appointment->delete();

            return redirect()->route('appointment.appointments.index')->with('success', __('The appointment has been deleted.'));
        }
        else{
            return redirect()->route('appointment.appointments.index')->with('error', __('Permission denied'));
        }
    }

    public function storeHours(Request $request)
    {
        if(Auth::user()->can('create-appointment-hours')){
            $request->validate([
                'data' => 'required|array'
            ]);

            $data = $request->input('data');

            foreach($data as $dayName => $dayData) {
                AppointmentHour::updateOrCreate(
                    [
                        'day_name' => $dayName,
                        'created_by' => creatorId()
                    ],
                    [
                        'start_time' => isset($dayData['add_day_off']) && $dayData['add_day_off'] ? null : ($dayData['start_time'] ?: '09:00'),
                        'end_time' => isset($dayData['add_day_off']) && $dayData['add_day_off'] ? null : ($dayData['end_time'] ?: '18:00'),
                        'day_off' => isset($dayData['add_day_off']) ? $dayData['add_day_off'] : false,
                        'creator_id' => Auth::id()
                    ]
                );
            }

            return redirect()->back()->with('success', __('The appointment hours have been saved successfully.'));
        }
        else{
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function getHours()
    {
        if(Auth::user()->can('manage-appointment-hours')){
            $hours = AppointmentHour::where('created_by', creatorId())->get();

            return response()->json($hours->keyBy('day_name'));
        }
        else{
            return response()->json(['error' => __('Permission denied')], 403);
        }
    }

    public function calendar(Request $request)
    {
        if(Auth::user()->can('view-appointments-calendar')){
            $schedules = Schedule::with(['appointment', 'user'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-schedules')) {
                        $q->whereHas('appointment', function($query) {
                            $query->where('created_by', creatorId());
                        });
                    } elseif(Auth::user()->can('manage-own-schedules')) {
                        $q->where('created_by', creatorId())
                          ->where(function($query) {
                              $query->where('user_id', Auth::id())
                                    ->orWhere('creator_id', Auth::id());
                          });
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->get()
                ->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'title' => ($schedule->appointment->appointment_name ?? 'Appointment'),
                        'date' => date('Y-m-d', strtotime($schedule->date)),
                        'startDate' => date('Y-m-d', strtotime($schedule->date)),
                        'endDate' => date('Y-m-d', strtotime($schedule->date)),
                        'time' => date('H:i', strtotime($schedule->start_time)),
                        'description' => $schedule->name,
                        'type' => $schedule->status,
                        'attendees' => [$schedule->name, $schedule->user->name ?? 'Unassigned'],
                        'status' => $schedule->status,
                        'schedule_data' => [
                            'unique_id' => $schedule->unique_id,
                            'name' => $schedule->name,
                            'email' => $schedule->email,
                            'phone' => $schedule->phone,
                            'start_time' => date('H:i', strtotime($schedule->start_time)),
                            'end_time' => date('H:i', strtotime($schedule->end_time)),
                            'appointment_name' => $schedule->appointment->appointment_name ?? 'N/A',
                            'appointment_id' => $schedule->appointment_id,
                            'status' => $schedule->status
                        ]
                    ];
                });

            // Get schedule counts per appointment
            $appointmentScheduleCounts = Schedule::selectRaw('appointment_id, COUNT(*) as count')
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-schedules')) {
                        $q->whereHas('appointment', function($query) {
                            $query->where('created_by', creatorId());
                        });
                    } elseif(Auth::user()->can('manage-own-schedules')) {
                        $q->where('created_by', creatorId())
                          ->where(function($query) {
                              $query->where('user_id', Auth::id())
                                    ->orWhere('creator_id', Auth::id());
                          });
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->groupBy('appointment_id')
                ->pluck('count', 'appointment_id')
                ->toArray();

            return Inertia::render('Appointment/Appointments/Calendar', [
                'events' => $schedules,
                'appointmentScheduleCounts' => $appointmentScheduleCounts
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}