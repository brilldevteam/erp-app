<?php

namespace App\Http\Controllers;

use App\Models\HelpdeskPopupNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpdeskPopupNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = HelpdeskPopupNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('viewed_at')
            ->whereNull('dismissed_at')
            ->whereHas('ticket', fn ($query) => $query->visibleTo($request->user()))
            ->oldest()
            ->limit(20)
            ->get(['id', 'ticket_id', 'event', 'message', 'created_at']);

        return response()->json(['notifications' => $notifications]);
    }

    public function viewed(Request $request, HelpdeskPopupNotification $notification): JsonResponse
    {
        $this->owned($request, $notification)->update(['viewed_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function dismiss(Request $request, HelpdeskPopupNotification $notification): JsonResponse
    {
        $this->owned($request, $notification)->update(['dismissed_at' => now()]);

        return response()->json(['success' => true]);
    }

    private function owned(Request $request, HelpdeskPopupNotification $notification): HelpdeskPopupNotification
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 404);

        return $notification;
    }
}
