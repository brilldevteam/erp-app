<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        if(Auth::user()->can('change-password-profile') && Auth::user()->id === $request->user()->id){
            if (Session::has('impersonator_id')) {
                return back()->with('error', __('Leave Login As User to change password.'));
            }

            $validated = $request->validate([
                'current_password' => ['required', 'current_password'],
                'password' => [
                    'required',
                    Password::min(8)->mixedCase()->numbers()->symbols(),
                    'confirmed',
                ],
                'logout_other_devices' => ['nullable', 'boolean'],
            ]);

            $request->user()->update([
                'password' => Hash::make($validated['password']),
                'password_changed_at' => now(),
            ]);

            if ($request->boolean('logout_other_devices')) {
                $request->session()->regenerate();
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $request->user()->id)
                    ->where('id', '!=', $request->session()->getId())
                    ->delete();
            }

            try {
                SetConfigEmail(creatorId());
                Mail::raw(__('Your account password was changed successfully.'), function ($message) use ($request): void {
                    $message->to($request->user()->email)
                        ->subject(__('Password Changed'));
                });
            } catch (\Throwable) {
            }

            return back()->with('success', __('Password updated successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}
