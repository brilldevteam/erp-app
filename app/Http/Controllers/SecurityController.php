<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if (!Auth::user()->can('manage-profile') && !Auth::user()->can('change-password-profile')) {
            return back()->with('error', __('Permission denied'));
        }

        return Inertia::render('security/index', [
            'sessions' => $this->sessions($request),
            'loginHistories' => $this->loginHistories($request),
        ]);
    }

    public function destroySession(Request $request, string $sessionId): RedirectResponse
    {
        if (!Auth::user()->can('change-password-profile')) {
            return back()->with('error', __('Permission denied'));
        }

        if ($sessionId === $request->session()->getId()) {
            return back()->with('error', __('The current session cannot be removed here.'));
        }

        DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->where('user_id', Auth::id())
            ->delete();

        return back()->with('success', __('The session has been logged out successfully.'));
    }

    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        if (!Auth::user()->can('change-password-profile')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        if (!Hash::check($validated['current_password'], $request->user()->password)) {
            return back()->with('error', __('The current password is incorrect.'));
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', Auth::id())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', __('Other sessions have been logged out successfully.'));
    }

    private function sessions(Request $request): array
    {
        $currentSessionId = $request->session()->getId();

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', Auth::id())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'device' => parseBrowserData($session->user_agent ?? ''),
                'last_activity' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                'is_current' => $session->id === $currentSessionId,
            ])
            ->values()
            ->all();
    }

    private function loginHistories(Request $request): array
    {
        return LoginHistory::where('user_id', $request->user()->id)
            ->latest()
            ->limit(10)
            ->get(['id', 'ip', 'details', 'type', 'created_at'])
            ->map(fn (LoginHistory $history) => [
                'id' => $history->id,
                'ip' => $history->ip,
                'details' => $history->details ?: [],
                'type' => $history->type,
                'created_at' => $history->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
