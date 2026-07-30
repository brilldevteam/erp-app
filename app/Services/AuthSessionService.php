<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthSessionService
{
    public const PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    public const LOGOUT_OTHER_DEVICES = 'LOGOUT_OTHER_DEVICES';
    public const PASSWORD_RESET = 'PASSWORD_RESET';
    public const SESSION_KEY = 'security_version';

    public function initializeWebSession(Request $request): void
    {
        if ($request->user()) {
            $request->session()->put(self::SESSION_KEY, $this->userSecurityVersion($request->user()));
        }
    }

    public function assignTokenVersion(PersonalAccessToken $token, User $user): void
    {
        if ($this->tokenHasSecurityVersionColumn()) {
            $token->forceFill([
                'security_version' => $this->userSecurityVersion($user),
            ])->save();
        }
    }

    public function revokeOtherDevices(User $user, Request $request, string $reason): User
    {
        $user->forceFill([
            'security_version' => $this->userSecurityVersion($user) + 1,
            'security_revoked_at' => now(),
            'security_revoked_reason' => $reason,
            'remember_token' => Str::random(60),
        ])->save();

        $user->refresh();

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $this->userSecurityVersion($user));
        }

        $this->deleteOtherDatabaseSessions($user, $request);
        $this->revokeOtherTokens($user, $request);

        return $user;
    }

    public function revokeAllDevices(User $user, string $reason): User
    {
        $user->forceFill([
            'security_version' => $this->userSecurityVersion($user) + 1,
            'security_revoked_at' => now(),
            'security_revoked_reason' => $reason,
            'remember_token' => Str::random(60),
        ])->save();

        $user->refresh();

        $this->deleteAllDatabaseSessions($user);
        $user->tokens()->delete();

        return $user;
    }

    public function isCurrentRequestValid(Request $request): bool
    {
        $user = $request->user();

        if (!$user) {
            return true;
        }

        $currentVersion = $this->userSecurityVersion($user);
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            if (!$this->tokenHasSecurityVersionColumn()) {
                return true;
            }

            return (int) ($token->security_version ?? 0) === $currentVersion;
        }

        if (!$request->hasSession()) {
            return true;
        }

        $sessionVersion = $request->session()->get(self::SESSION_KEY);

        if ($sessionVersion === null) {
            if ($user->security_revoked_at !== null) {
                return false;
            }

            $request->session()->put(self::SESSION_KEY, $currentVersion);

            return true;
        }

        return (int) $sessionVersion === $currentVersion;
    }

    public function revokedResponse(Request $request): JsonResponse|RedirectResponse
    {
        $reason = $request->user()?->security_revoked_reason ?: self::LOGOUT_OTHER_DEVICES;
        $message = __('Your session has ended because your password was changed or your account was logged out from another device.');

        if ($request->expectsJson() || $request->is('api/*') || $request->header('X-Inertia')) {
            return response()->json([
                'success' => false,
                'code' => 'SESSION_REVOKED',
                'reason' => $reason,
                'message' => $message,
            ], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login')->with('error', $message);
    }

    public function forgetCurrentAuthentication(Request $request): void
    {
        if ($request->user()?->currentAccessToken() instanceof PersonalAccessToken) {
            $request->user()->currentAccessToken()->delete();
            return;
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    private function deleteOtherDatabaseSessions(User $user, Request $request): void
    {
        if (config('session.driver') !== 'database' || !$request->hasSession()) {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    private function deleteAllDatabaseSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }

    private function revokeOtherTokens(User $user, Request $request): void
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken instanceof PersonalAccessToken) {
            $this->assignTokenVersion($currentToken, $user);

            $user->tokens()
                ->where('id', '!=', $currentToken->id)
                ->delete();

            return;
        }

        $user->tokens()->delete();
    }

    private function userSecurityVersion(User $user): int
    {
        return max(1, (int) ($user->security_version ?? 1));
    }

    private function tokenHasSecurityVersionColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = DB::getSchemaBuilder()->hasColumn('personal_access_tokens', 'security_version');
        }

        return $hasColumn;
    }
}
