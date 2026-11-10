<?php

namespace App\Http\Controllers;

use App\Events\CreateHelpdeskReply;
use App\Events\DestroyHelpdeskReply;
use App\Http\Requests\StoreHelpdeskReplyRequest;
use App\Models\HelpdeskReply;
use App\Models\HelpdeskTicket;
use App\Services\HelpdeskNotificationService;
use App\Services\HelpdeskEmailService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class HelpdeskReplyController extends Controller
{
    public function store(StoreHelpdeskReplyRequest $request, HelpdeskTicket $ticket, HelpdeskNotificationService $notifications, HelpdeskEmailService $emails)
    {
        if(Auth::user()->can('create-helpdesk-replies')){
            if (Gate::denies('reply', $ticket)) {
                return response()->json(['error' => __('Ticket not found')], 404);
            }
            if ($ticket->status === 'closed') {
                return response()->json(['error' => __('This ticket is closed.')], 422);
            }

            $validated = $request->validated();
            $storedFiles = [];

            try {
                $reply = DB::transaction(function () use ($request, $ticket, $validated, &$storedFiles) {
                    $reply = new HelpdeskReply();
                    $reply->ticket_id = $ticket->id;
                    $reply->message = $validated['message'];
                    $reply->is_internal = Auth::user()->isSupportSide() && Auth::user()->can('create-helpdesk-internal-notes') && $request->boolean('is_internal');
                    $reply->created_by = Auth::id();
                    $reply->sender_name = Auth::user()->name;
                    $reply->save();

                    foreach ($request->file('files', []) as $file) {
                        $path = $file->store('helpdesk/' . $ticket->id, 'local');
                        if (!$path) {
                            throw new \RuntimeException('The attachment could not be stored.');
                        }
                        $storedFiles[] = $path;
                        $reply->files()->create([
                            'ticket_id' => $ticket->id,
                            'uploaded_by' => Auth::id(),
                            'disk' => 'local',
                            'path' => $path,
                            'original_name' => basename($file->getClientOriginalName()),
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ]);
                    }

                    return $reply;
                });
            } catch (\Throwable $th) {
                Storage::disk('local')->delete($storedFiles);
                report($th);

                return response()->json(['error' => __('The reply could not be saved. Please try again.')], 500);
            }

            $wasResolved = $ticket->status === 'resolved';
            $isRequesterSide = Auth::user()->type !== 'superadmin'
                && (int) $ticket->assigned_agent_id !== (int) Auth::id();

            $ticket->last_reply_at = now();
            if (!$ticket->first_response_at && !$isRequesterSide) {
                $ticket->first_response_at = now();
            }
            if ($wasResolved && $isRequesterSide) {
                $ticket->status = 'open';
                $ticket->resolved_at = null;
                $ticket->closed_at = null;
            }
            $ticket->save();

            if ($wasResolved && $isRequesterSide) {
                $ticket->statusHistories()->create([
                    'from_status' => 'resolved',
                    'to_status' => 'open',
                    'changed_by' => Auth::id(),
                    'changed_by_name' => Auth::user()->name,
                ]);
            }

            CreateHelpdeskReply::dispatch($request, $reply);

            if (!$reply->is_internal) {
                $ticket = $ticket->fresh(['requester', 'assignedAgent', 'creator', 'category']);
                $notifications->replied($ticket, Auth::user());
                $emails->replied($ticket, $reply, Auth::user());
            }

            return response()->json([
                'success' => true,
                'message' => __('Reply added successfully'),
                'reply' => $reply->load(['creator', 'files'])->toArray(),
            ]);
        }
        else{
            return response()->json(['error' => __('Permission denied')], 403);
        }
    }

    public function destroy(HelpdeskReply $reply)
    {
        if(Auth::user()->can('delete-helpdesk-replies')){
            $helpdeskReply = $reply->load('ticket');
            if (!$helpdeskReply->ticket || Gate::denies('deleteReply', $helpdeskReply->ticket)) {
                return response()->json(['error' => __('Reply not found')], 404);
            }
            DestroyHelpdeskReply::dispatch($helpdeskReply);
            $helpdeskReply->delete();

            session()->flash('success', __('Reply deleted successfully'));

            return response()->json([
                'success' => true,
                'message' => __('Reply deleted successfully')
            ]);
        }
        else{
            return response()->json(['error' => __('Permission denied')], 403);
        }
    }
}
