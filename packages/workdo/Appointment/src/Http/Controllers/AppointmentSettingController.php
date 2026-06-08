<?php

namespace Workdo\Appointment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Appointment\Models\AppointmentSetting;
use Workdo\Appointment\Models\AppointmentHour;

class AppointmentSettingController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-appointment-settings')){
            $settings = AppointmentSetting::where('created_by', creatorId())
                ->whereIn('key', ['logo_dark', 'favicon', 'title_text', 'footer_text'])
                ->pluck('value', 'key')
                ->toArray();

            return Inertia::render('Appointment/SystemSetup/BrandSettings/Index', [
                'settings' => [
                    'logo_dark' => $settings['logo_dark'] ?? '',
                    'favicon' => $settings['favicon'] ?? '',
                    'titleText' => $settings['title_text'] ?? '',
                    'footerText' => $settings['footer_text'] ?? ''
                ]
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function update(Request $request)
    {
        if(Auth::user()->can('manage-appointment-settings')){
            $request->validate([
                'settings.logo_dark' => 'nullable|string',
                'settings.favicon' => 'nullable|string',
                'settings.titleText' => 'required|string|max:255',
                'settings.footerText' => 'required|string|max:500'
            ]);

            $settings = $request->input('settings', []);
            $settingsMap = [
                'logo_dark' => 'logo_dark',
                'favicon' => 'favicon',
                'titleText' => 'title_text',
                'footerText' => 'footer_text'
            ];

            foreach ($settings as $key => $value) {
                if (isset($settingsMap[$key])) {
                    AppointmentSetting::updateOrInsert(
                            ['key' => $settingsMap[$key], 'created_by' => creatorId()],
                            ['value' => $value ?: ($key === 'logo_dark' ? 'media/appointment_logo.svg' : ($key === 'favicon' ? 'media/appointment_favicon.svg' : $value)), 'updated_at' => now()]
                        );
                }
            }

            return back()->with('success', __('The appointment settings have been updated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function updateFaq(Request $request)
    {
        if(Auth::user()->can('manage-appointment-settings')){
            $request->validate([
                'settings.faq_title' => 'required|string|max:255',
                'settings.faq_description' => 'required|string|max:500',
                'settings.faq_questions' => 'required|array|min:1',
                'settings.faq_questions.*.title' => 'required|string|max:255',
                'settings.faq_questions.*.description' => 'required|string'
            ]);

            $faqData = $request->input('settings', []);

            AppointmentSetting::updateOrInsert(
                    ['key' => 'faq_settings', 'created_by' => creatorId()],
                    ['value' => json_encode($faqData), 'updated_at' => now()]
                );

            return back()->with('success', __('The FAQ settings have been updated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function faqSettings()
    {
        if(Auth::user()->can('manage-appointment-settings')){
            $faqSettings = AppointmentSetting::where('created_by', creatorId())
                ->where('key', 'faq_settings')
                ->value('value');

            $settings = $faqSettings ? (is_string($faqSettings) ? json_decode($faqSettings, true) : $faqSettings) : [
                'faq_title' => __('Frequently Asked Questions'),
                'faq_description' => __('Find answers to common questions about our appointment booking system.'),
                'faq_questions' => [
                    ['title' => __('How do I book an appointment?'), 'description' => __('You can book an appointment through our online booking system.')]
                ]
            ];

            return Inertia::render('Appointment/SystemSetup/FAQSettings/Index', [
                'settings' => $settings
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function privacySettings()
    {
        if(Auth::user()->can('manage-appointment-settings')){
            $privacySettings = AppointmentSetting::where('created_by', creatorId())
                ->where('key', 'privacy_policy')
                ->value('value');

            $settings = $privacySettings ? (is_string($privacySettings) ? json_decode($privacySettings, true) : $privacySettings) : [
                'content' => '<h2>Privacy Policy</h2><p>We protect your personal information in accordance with applicable privacy laws.</p>',
                'enabled' => true
            ];

            return Inertia::render('Appointment/SystemSetup/PrivacyPolicy/Index', [
                'settings' => $settings
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function updatePrivacy(Request $request)
    {
        if(Auth::user()->can('manage-appointment-settings')){
            $request->validate([
                'settings.content' => 'required|string',
                'settings.enabled' => 'required|boolean'
            ]);

            $privacyData = $request->input('settings', []);

            AppointmentSetting::updateOrInsert(
                    ['key' => 'privacy_policy', 'created_by' => creatorId()],
                    ['value' => json_encode($privacyData), 'updated_at' => now()]
                );

            return back()->with('success', __('The privacy policy settings have been updated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function termsSettings()
    {
        if(Auth::user()->can('manage-appointment-settings')){
            $termsSettings = AppointmentSetting::where('created_by', creatorId())
                ->where('key', 'terms_conditions')
                ->value('value');

            $settings = $termsSettings ? (is_string($termsSettings) ? json_decode($termsSettings, true) : $termsSettings) : [
                'content' => '<h2>Terms and Conditions</h2><p>By using our appointment booking system, you agree to these terms and conditions.</p>',
                'enabled' => true
            ];

            return Inertia::render('Appointment/SystemSetup/TermsConditions/Index', [
                'settings' => $settings
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function updateTerms(Request $request)
    {
        if(Auth::user()->can('manage-appointment-settings')){
            $request->validate([
                'settings.content' => 'required|string',
                'settings.enabled' => 'required|boolean'
            ]);

            $termsData = $request->input('settings', []);

            AppointmentSetting::updateOrInsert(
                    ['key' => 'terms_conditions', 'created_by' => creatorId()],
                    ['value' => json_encode($termsData), 'updated_at' => now()]
                );

            return back()->with('success', __('The terms & conditions settings have been updated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function appointmentHours()
    {
        if(Auth::user()->can('manage-appointment-settings')){
            $hours = AppointmentHour::where('created_by', creatorId())->get()->keyBy('day_name');

            return Inertia::render('Appointment/SystemSetup/AppointmentHours/Index', [
                'hours' => $hours,
                'weekdays' => AppointmentHour::$weekdays
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function storeAppointmentHours(Request $request)
    {
        if(Auth::user()->can('manage-appointment-settings')){
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
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function getAppointmentHours()
    {
        if(Auth::user()->can('manage-appointment-settings')){
            $hours = AppointmentHour::where('created_by', creatorId())->get();

            return response()->json($hours->keyBy('day_name'));
        } else {
            return response()->json(['error' => __('Permission denied')], 403);
        }
    }
}
