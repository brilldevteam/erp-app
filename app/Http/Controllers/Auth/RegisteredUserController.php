<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\CompanyRegistrationService;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response|RedirectResponse
    {
        // Check if registration is enabled
        $enableRegistration = admin_setting('enableRegistration');

        if ($enableRegistration !== 'on') {
            return redirect()->route('login');
        }

        return Inertia::render('auth/register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, CompanyRegistrationService $registration): RedirectResponse
    {
        // Check if registration is enabled
        $enableRegistration = admin_setting('enableRegistration');

        if ($enableRegistration !== 'on') {
            return redirect()->route('login');
        }

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];

        if (filled(admin_setting('termsConditionsUrl'))) {
            $rules['terms_accepted'] = ['accepted'];
        }

        $request->validate($rules);

        try {
            $enableEmailVerification = admin_setting('enableEmailVerification');

            $adminUser = User::where('type', 'superadmin')->first();
            $user = $registration->create(
                $request->name,
                $request->email,
                $request->password,
                $enableEmailVerification !== 'on',
                true
            );

            Auth::login($user);

            if ($enableEmailVerification === 'on') {
                // Apply dynamic mail configuration
                SetConfigEmail($adminUser->id);
                $user->sendEmailVerificationNotification();
                return redirect(route('verification.notice'))->with('status', 'verification-link-sent');
            }

            return redirect(route('dashboard', absolute: false));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Registration failed. Please try again.']);
        }
    }
}
