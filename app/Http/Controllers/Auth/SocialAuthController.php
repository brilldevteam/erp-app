<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\CompanyRegistrationService;
use App\Services\LoginHistoryService;
use App\Services\SocialAuthSettingsService;
use App\Services\SocialOAuthService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'microsoft'];
    private const INTENTS = ['login', 'signup'];

    public function redirect(
        Request $request,
        string $provider,
        string $intent,
        SocialOAuthService $oauth,
        SocialAuthSettingsService $settings
    ): RedirectResponse {
        if (!in_array($provider, self::PROVIDERS, true) || !in_array($intent, self::INTENTS, true)) {
            abort(404);
        }

        if ($intent === 'signup') {
            if (admin_setting('enableRegistration') !== 'on') {
                return redirect()->route('login')
                    ->with('error', __('Registration is currently disabled.'));
            }

            if (filled(admin_setting('termsConditionsUrl')) && !$request->boolean('terms_accepted')) {
                return redirect()->route('register')
                    ->with('error', __('You must accept the Terms and Conditions before signing up.'));
            }
        }

        try {
            $settings->providerConfig($provider);
            $request->session()->put('social_auth.provider', $provider);
            $request->session()->put('social_auth.intent', $intent);
            $request->session()->put('social_auth.terms_accepted', $request->boolean('terms_accepted'));
            $request->session()->put('social_auth.intended', url()->previous());

            return $oauth->redirect($request, $provider);
        } catch (ValidationException $exception) {
            return $this->failureRedirect($intent, $exception->validator->errors()->first());
        } catch (Throwable) {
            return $this->failureRedirect($intent, __('Unable to start social authentication. Please try again.'));
        }
    }

    public function callback(
        Request $request,
        string $provider,
        SocialOAuthService $oauth,
        CompanyRegistrationService $registration,
        LoginHistoryService $loginHistory
    ): RedirectResponse {
        if (!in_array($provider, self::PROVIDERS, true)) {
            abort(404);
        }

        $storedProvider = (string) $request->session()->pull('social_auth.provider', '');
        $intent = (string) $request->session()->pull('social_auth.intent', '');
        $termsAccepted = (bool) $request->session()->pull('social_auth.terms_accepted', false);

        if ($storedProvider !== $provider || !in_array($intent, self::INTENTS, true)) {
            return redirect()->route('login')
                ->with('error', __('The social authentication session has expired. Please try again.'));
        }

        if ($request->filled('error')) {
            return $this->failureRedirect($intent, __('Social authentication was cancelled or denied.'));
        }

        try {
            $providerUser = $oauth->user($request, $provider);
            $createdCompany = false;

            if (!$providerUser->emailTrusted || blank($providerUser->email) || blank($providerUser->id)) {
                return $this->failureRedirect(
                    $intent,
                    __('The provider did not return a trusted email address.')
                );
            }

            $account = SocialAccount::with('user')
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUser->id)
                ->first();

            if ($account) {
                $user = $account->user;
            } else {
                $user = User::whereRaw('LOWER(email) = ?', [strtolower($providerUser->email)])->first();

                if (!$user && $intent === 'login') {
                    return redirect()->route('login')->with(
                        'error',
                        __('No account was found for this email. Please sign up first.')
                    );
                }

                if (!$user) {
                    if (admin_setting('enableRegistration') !== 'on') {
                        return redirect()->route('login')
                            ->with('error', __('Registration is currently disabled.'));
                    }

                    if (filled(admin_setting('termsConditionsUrl')) && !$termsAccepted) {
                        return redirect()->route('register')->with(
                            'error',
                            __('You must accept the Terms and Conditions before signing up.')
                        );
                    }

                    $user = $registration->create(
                        $providerUser->name,
                        $providerUser->email,
                        null,
                        true,
                        false
                    );
                    $createdCompany = true;
                } elseif ($this->isDisabled($user)) {
                    return $this->failureRedirect(
                        $intent,
                        __('Your account has been disabled. Please contact the administrator.')
                    );
                } elseif (!$user->email_verified_at) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                $this->linkAccount($user, $provider, $providerUser);
            }

            if (!$user || $this->isDisabled($user)) {
                return $this->failureRedirect(
                    $intent,
                    __('Your account has been disabled. Please contact the administrator.')
                );
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            $loginHistory->record($request, $user);

            return redirect()->route($createdCompany ? 'plans.index' : 'dashboard');
        } catch (QueryException $exception) {
            report($exception);

            return $this->failureRedirect(
                $intent,
                __('This social account is already linked to another user.')
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->failureRedirect(
                $intent,
                __('Social authentication failed. Please try again.')
            );
        }
    }

    private function linkAccount(User $user, string $provider, object $providerUser): void
    {
        DB::transaction(function () use ($user, $provider, $providerUser) {
            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $providerUser->id,
                'provider_email' => strtolower($providerUser->email),
                'provider_avatar' => $providerUser->avatar,
            ]);
        });
    }

    private function failureRedirect(string $intent, string $message): RedirectResponse
    {
        return redirect()
            ->route($intent === 'signup' ? 'register' : 'login')
            ->with('error', $message);
    }

    private function isDisabled(User $user): bool
    {
        return (bool) $user->is_disable || !(bool) $user->is_enable_login;
    }
}
