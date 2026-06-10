<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\LoginHistoryService;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        $enableRegistration = admin_setting('enableRegistration');

        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'enableRegistration' => $enableRegistration === 'on',
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, LoginHistoryService $loginHistory): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Log login history
        $loginHistory->record($request, Auth::user());

        if (Auth::check() && Auth::user()->hasRole('superadmin')) {
            try {
                $output = Artisan::call('migrate:status');
                $result = Artisan::output();

                // Check if there are pending migrations
                if (strpos($result, 'Pending') !== false) {
                    // Redirect to updater if not already on updater route
                    return redirect()->route('updater.index');
                }
            } catch (\Exception $e) {
                // Ignore errors in checking migrations
            }
        }

        return redirect()->route('dashboard');

        // old code
        // return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

}
