<?php

namespace Workdo\Appointment\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Workdo\Appointment\Models\Appointment;
use Workdo\Appointment\Models\AppointmentCallback;
use Workdo\Appointment\Models\Question;
use Workdo\Appointment\Models\Schedule;
use Workdo\Appointment\Events\CreateSchedule;
use Workdo\Appointment\Events\AppointmentCallback as AppointmentCallbackEvent;
use Workdo\Appointment\Models\AppointmentSetting;
use App\Models\User;
use Workdo\Appointment\Models\AppointmentHour;

class PublicController extends Controller
{
    private function getUserIdFromRequest(Request $request)
    {
        $userSlug = $request->route('userSlug');

        if ($userSlug) {
            $user = User::where('slug', $userSlug)->first();
            if ($user) {
                return $user->id;
            }
        }

        abort(404, __('Appointment page not found'));
    }

    public function book(Request $request, $userSlug, $encryptedId)
    {
        $appointment = Appointment::findByEncryptedId($encryptedId);

        if (!$appointment) {
            return Inertia::render('Appointment/Frontend/NotFound', [
                'title' => __('Appointment Not Found'),
                'message' => __('The appointment you are looking for could not be found or may have been removed.'),
                'showSearchButton' => true
            ]);
        }

        // Check if appointment is enabled
        if (!$appointment->enabled) {
            return Inertia::render('Appointment/Frontend/NotFound', [
                'title' => __('Appointment Not Available'),
                'message' => __('This appointment is currently not available for booking.'),
                'showSearchButton' => true
            ]);
        }

        $questionIds = $appointment->question_ids;
        if (is_string($questionIds)) {
            $questionIds = json_decode($questionIds, true) ?? [];
        }
        $questionIds = is_array($questionIds) ? $questionIds : [];

        $questions = Question::whereIn('id', $questionIds)
                           ->where('enabled', true)
                           ->get(['id', 'question_name', 'question_type', 'available_answers', 'required_answer']);

        $appointmentHours = AppointmentHour::where('created_by', $appointment->created_by)
            ->get(['day_name', 'start_time', 'end_time', 'day_off']);

        return Inertia::render('Appointment/Frontend/Book', [
            'appointment' => [
                'id' => $appointment->id,
                'appointment_name' => $appointment->appointment_name,
                'appointment_type' => $appointment->appointment_type,
                'duration' => $appointment->duration,
                'week_day' => $appointment->week_day,
                'phone_enable' => (int) $appointment->phone_enabled
            ],
            'questions' => $questions,
            'appointmentHours' => $appointmentHours,
            'userSlug' => $userSlug
        ]);
    }

    public function store(Request $request, $userSlug, $encryptedId)
    {
        $appointment = Appointment::findByEncryptedId($encryptedId);

        if (!$appointment) {
            abort(404, __('Appointment not found.'));
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'end_time' => 'required',
            'questions' => 'nullable|array'
        ]);

        // Transform questions to use question names as keys
        $questionsWithNames = [];
        if ($request->questions) {
            foreach ($request->questions as $questionId => $answer) {
                $question = Question::find($questionId);
                if ($question) {
                    $questionsWithNames[$question->question_name] = $answer;
                } else {
                    $questionsWithNames['Question ID: ' . $questionId] = $answer;
                }
            }
        }

        $schedule = Schedule::create([
            'unique_id' => 'SCH-' . strtoupper(uniqid()),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'appointment_id' => $appointment->id,
            'questions' => json_encode($questionsWithNames),
            'status' => 'pending',
            'creator_id' => $appointment->creator_id ?? null,
            'created_by' => $appointment->created_by ?? null
        ]);

        // Dispatch event for packages to handle their fields
        CreateSchedule::dispatch($request, $schedule);

        // Send appointment confirmation email
        $emailSent = false;
        if(company_setting('Appointment Booked', $appointment->created_by) == 'on') {
            $emailData = [
                'appointment_name' => $appointment->appointment_name,
                'appointment_user_name' => $request->name,
                'appointment_user_email' => $request->email,
                'appointment_date' => date('d-m-Y', strtotime($request->date)),
                'appointment_time' => date('g:i A', strtotime($request->start_time)) . ' - ' . date('g:i A', strtotime($request->end_time)),
                'appointment_number' => $schedule->unique_id,
            ];

            \App\Models\EmailTemplate::sendEmailTemplate('Appointment Booked', [$request->email], $emailData, $appointment->created_by);
            $emailSent = true;
        }

        $successMessage = $emailSent
            ? __('The appointment has been booked successfully! You will receive a confirmation email shortly.')
            : __('The appointment has been booked successfully!');

        return redirect()->route('appointment.public.success', [$userSlug, $schedule->unique_id])
            ->with('success', $successMessage);
    }

    public function success(Request $request, $userSlug, $uniqueId)
    {
        $userId = $this->getUserIdFromRequest($request);

        $schedule = Schedule::where('unique_id', $uniqueId)->where('created_by', $userId)->with('appointment')->first();

        if (!$schedule) {
            return Inertia::render('Appointment/Frontend/NotFound', [
                'title' => __('Appointment Not Found'),
                'message' => __('The appointment confirmation you are looking for could not be found.'),
                'showSearchButton' => true
            ]);
        }

        // Get company settings for date/time formatting
        $companySettings = [];
        if ($schedule->created_by) {
            $settings = getCompanyAllSetting($schedule->created_by);

            $companySettings = $settings;
        }

        // Format date and time based on settings
        $dateFormat = $companySettings['dateFormat'] ?? 'd-m-Y';
        $timeFormat = $companySettings['timeFormat'] ?? 'g:i A';

        $formattedDate = date($dateFormat, strtotime($schedule->date));
        $formattedStartTime = date($timeFormat, strtotime($schedule->start_time));
        $formattedEndTime = date($timeFormat, strtotime($schedule->end_time));

        return Inertia::render('Appointment/Frontend/Success', [
            'schedule' => array_merge($schedule->toArray(), [
                'formatted_date' => $formattedDate,
                'formatted_start_time' => $formattedStartTime,
                'formatted_end_time' => $formattedEndTime,
                'appointment' => array_merge($schedule->appointment->toArray(), [
                    'encrypted_id' => $schedule->appointment->getEncryptedId()
                ])
            ])
        ]);
    }

    public function search(Request $request, $userSlug)
    {
        return Inertia::render('Appointment/Frontend/Search', [
            'userSlug' => $userSlug
        ]);
    }

    public function details(Request $request, $userSlug, $uniqueId)
    {
        $userId = $this->getUserIdFromRequest($request);

        $schedule = Schedule::where('unique_id', $uniqueId)->where('created_by', $userId)->with('appointment')->first();

        if (!$schedule) {
            return Inertia::render('Appointment/Frontend/NotFound', [
                'title' => __('Appointment Not Found'),
                'message' => __('The appointment details you are looking for could not be found.'),
                'showSearchButton' => true
            ]);
        }

        // Get company settings for date/time formatting
        $companySettings = [];
        if ($schedule->created_by) {
            $settings = getCompanyAllSetting($schedule->created_by);
            $companySettings = $settings;
        }

        // Format date and time based on settings
        $dateFormat = $companySettings['dateFormat'] ?? 'd-m-Y';
        $timeFormat = $companySettings['timeFormat'] ?? 'g:i A';

        $formattedDate = date($dateFormat, strtotime($schedule->date));
        $formattedStartTime = date($timeFormat, strtotime($schedule->start_time));
        $formattedEndTime = date($timeFormat, strtotime($schedule->end_time));

        return Inertia::render('Appointment/Frontend/SearchResults', [
            'schedule' => array_merge($schedule->toArray(), [
                'formatted_date' => $formattedDate,
                'formatted_start_time' => $formattedStartTime,
                'formatted_end_time' => $formattedEndTime
            ]),
            'userSlug' => $userSlug
        ]);
    }

    public function searchAppointment(Request $request, $userSlug)
    {
        $userId = $this->getUserIdFromRequest($request);

        $request->validate([
            'appointment_number' => 'required|string',
            'email' => 'required|email'
        ], [
            'appointment_number.required' => __('Appointment number is required'),
            'email.required' => __('Email is required'),
            'email.email' => __('Please enter a valid email address')
        ]);

        // Check if the combination exists in Schedule table
        $schedule = Schedule::with('appointment')
            ->where('unique_id', $request->appointment_number)
            ->where('email', $request->email)
            ->where('created_by', $userId)
            ->first();

        if (!$schedule) {
            return back()->withErrors([
                'appointment_number' => __('Invalid appointment number and email combination. Please check your details and try again.')
            ]);
        }

        return redirect()->route('appointment.public.details', [$userSlug, $schedule->unique_id]);
    }

    public function callback(Request $request, $userSlug, $uniqueId)
    {
        $userId = $this->getUserIdFromRequest($request);
        $schedule = Schedule::where('unique_id', $uniqueId)->where('created_by', $userId)->first();

        if (!$schedule) {
            return Inertia::render('Appointment/Frontend/NotFound', [
                'title' => __('Appointment Not Found'),
                'message' => __('The appointment you are trying to request a callback for could not be found.'),
                'showSearchButton' => true
            ]);
        }

        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'end_time' => 'required',
            'reason' => 'required|string'
        ]);

        try {
            // Create appointment callback
            $callback = new AppointmentCallback();
            $callback->schedule_id = $schedule->id;
            $callback->unique_code = $schedule->unique_id;
            $callback->appointment_id = $schedule->appointment_id;
            $callback->reason = $request->reason;
            $callback->date = $request->date;
            $callback->start_time = $request->start_time;
            $callback->end_time = $request->end_time;
            $callback->status = 'pending';
            $callback->creator_id = $schedule->creator_id;
            $callback->created_by = $schedule->created_by;
            $callback->save();

            // Dispatch event for packages to handle their fields
            AppointmentCallbackEvent::dispatch($request, $schedule);

            // Send callback confirmation email
            if(company_setting('Appointment Callback', $schedule->created_by) == 'on') {
                $emailData = [
                    'appointment_name' => $schedule->appointment->appointment_name ?? 'Appointment',
                    'appointment_user_name' => $schedule->name,
                    'appointment_user_email' => $schedule->email,
                    'callback_date' => date('d-m-Y', strtotime($request->date)),
                    'callback_time' => date('g:i A', strtotime($request->start_time)) . ' - ' . date('g:i A', strtotime($request->end_time)),
                    'callback_reason' => $request->reason,
                ];

                EmailTemplate::sendEmailTemplate('Appointment Callback', [$schedule->email], $emailData, $schedule->created_by);
            }

            return redirect()->route('appointment.public.details', [$userSlug, $schedule->unique_id]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Failed to create callback appointment. Please try again.')]);
        }
    }

    public function cancel(Request $request, $userSlug, $uniqueId)
    {
        $userId = $this->getUserIdFromRequest($request);
        $schedule = Schedule::where('unique_id', $uniqueId)->where('created_by', $userId)->first();

        if (!$schedule) {
            return Inertia::render('Appointment/Frontend/NotFound', [
                'title' => __('Appointment Not Found'),
                'message' => __('The appointment you are trying to cancel could not be found.'),
                'showSearchButton' => true
            ]);
        }

        $request->validate([
            'reason' => 'required|string'
        ]);

        $schedule->update([
            'status' => 'cancel',
            'cancel_description' => $request->reason
        ]);

        return back();
    }

    public function faq(Request $request, $userSlug)
    {
        $userId = $this->getUserIdFromRequest($request);

        $faqSettings = AppointmentSetting::where('key', 'faq_settings')
            ->where('created_by', $userId)
            ->value('value');

        $faqData = null;
        if ($faqSettings) {
            $faqData = is_string($faqSettings) ? json_decode($faqSettings, true) : $faqSettings;
        }

        return Inertia::render('Appointment/Frontend/FAQ', [
            'faqSettings' => $faqData
        ]);
    }

    public function privacyPolicy(Request $request, $userSlug)
    {
        $userId = $this->getUserIdFromRequest($request);

        $privacySettings = AppointmentSetting::whereIn('key', ['privacy_policy', 'privacy_policy_enabled'])
            ->where('created_by', $userId)
            ->pluck('value', 'key')
            ->toArray();
        return Inertia::render('Appointment/Frontend/PrivacyPolicy', [
            'privacySettings' => [
                'content' => isset($privacySettings['privacy_policy']) ? (is_string($privacySettings['privacy_policy']) ? json_decode($privacySettings['privacy_policy'], true)['content'] ?? null : $privacySettings['privacy_policy']['content'] ?? null) : null,
                'enabled' => (bool) ($privacySettings['privacy_policy_enabled'] ?? true),
                'userSlug' => $userSlug
            ]
        ]);
    }

    public function termsConditions(Request $request, $userSlug)
    {
        $userId = $this->getUserIdFromRequest($request);

        $termsSettings = AppointmentSetting::whereIn('key', ['terms_conditions', 'terms_conditions_enabled'])
            ->where('created_by', $userId)
            ->pluck('value', 'key')
            ->toArray();
        return Inertia::render('Appointment/Frontend/TermsConditions', [
            'termsSettings' => [
                'content' => isset($termsSettings['terms_conditions']) ? (is_string($termsSettings['terms_conditions']) ? json_decode($termsSettings['terms_conditions'], true)['content'] ?? null : $termsSettings['terms_conditions']['content'] ?? null) : null,
                'enabled' => (bool) ($termsSettings['terms_conditions_enabled'] ?? true),
                'userSlug' => $userSlug
            ]
        ]);
    }

    public function getBookedSlots(Request $request, $userSlug, $encryptedId, $date)
    {
        $userId = $this->getUserIdFromRequest($request);
        $appointment = Appointment::findByEncryptedId($encryptedId);

        if (!$appointment || $appointment->created_by != $userId) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        $bookedSlots = Schedule::where('appointment_id', $appointment->id)
            ->where('date', $date)
            ->where('status', '!=', 'cancelled')
            ->get(['start_time', 'end_time'])
            ->map(function ($schedule) {
                // Ensure time format is HH:MM
                $startTime = date('H:i', strtotime($schedule->start_time));
                $endTime = date('H:i', strtotime($schedule->end_time));
                return $startTime . '-' . $endTime;
            })
            ->toArray();

        return response()->json([
            'bookedSlots' => $bookedSlots
        ]);
    }
}
