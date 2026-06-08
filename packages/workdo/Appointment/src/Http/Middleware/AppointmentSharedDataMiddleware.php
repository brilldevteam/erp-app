<?php

namespace Workdo\Appointment\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Workdo\Appointment\Models\AppointmentSetting;
use Workdo\Appointment\Models\Appointment;
use App\Models\User;
use App\Classes\Module;

class AppointmentSharedDataMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (str_starts_with($request->route()?->getName() ?? '', 'appointment.public.')) {
            $userSlug = $request->route('userSlug');
            $userId = $this->getUserIdFromSlug($request);
            $user = User::find($userId);
            $locale = $user?->lang ?? config('app.locale');
            app()->setLocale($locale);

            $appointmentSettings = $this->getAppointmentSettings($userId);

            Inertia::share([
                'appointmentSettings' => $appointmentSettings,
                'userSlug' => $userSlug,
                'locale' => $locale,
                'companyAllSetting' => getCompanyAllSetting($userId),
                'auth' => [
                    'user' => ['activatedPackages' => ActivatedModule($userId ?? null)],
                ],
                'packages' => (new Module())->allModules(),
                'imageUrlPrefix' => getImageUrlPrefix(),
                'settings' => [
                    'logo_dark' => $appointmentSettings['logo_dark'],
                    'favicon' => $appointmentSettings['favicon'],
                    'titleText' => $appointmentSettings['title_text'],
                    'footerText' => $appointmentSettings['footer_text'],
                ]
            ]);
        }

        return $next($request);
    }

    private function getUserIdFromSlug(Request $request): int
    {
        $userSlug = $request->route('userSlug');
        if ($userSlug) {
            try {
                $user = User::where('slug', $userSlug)->first();
                if ($user) {
                    return $user->id;
                }
            } catch (\Exception $e) {
                \Log::error('Error finding user by slug: ' . $e->getMessage());
            }
        }

        abort(404, 'Appointment page not found');
    }

    private function getAppointmentSettings($userId)
    {
        $settings = AppointmentSetting::where('created_by', $userId)
            ->whereIn('key', ['logo_dark', 'favicon', 'title_text', 'footer_text', 'terms_conditions', 'privacy_policy'])
            ->pluck('value', 'key')
            ->toArray();

        return [
            'logo_dark' => $settings['logo_dark'] ?? 'packages/workdo/Appointment/src/Resources/images/logo.png',
            'favicon' => $settings['favicon'] ?? 'packages/workdo/Appointment/src/Resources/images/favicon.svg',
            'title_text' => $settings['title_text'] ?? 'MeetSpace',
            'footer_text' => $settings['footer_text'] ?? '',
            'terms_conditions' => isset($settings['terms_conditions']) ? json_decode($settings['terms_conditions'], true) : null,
            'privacy_policy' => isset($settings['privacy_policy']) ? json_decode($settings['privacy_policy'], true) : null,
        ];
    }
}
