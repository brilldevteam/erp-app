<?php

namespace Workdo\PettyCashManagement\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Workdo\PettyCashManagement\Models\PettyCash;
use Workdo\PettyCashManagement\Models\PettyCashAttachment;
use Workdo\PettyCashManagement\Models\PettyCashRequest;
use Workdo\PettyCashManagement\Services\PettyCashAttachmentService;

class PettyCashAttachmentController extends Controller
{
    /** The request or fund, after checking the permission and that the user may see that record. */
    private function record(string $type, int $id, string $action): Model
    {
        $user = Auth::user();

        if ($type === PettyCashAttachment::REQUEST) {
            abort_unless($user->can($action === 'view' ? 'view-petty-cash-requests' : 'edit-petty-cash-requests'), 403);
            $record = PettyCashRequest::where('created_by', creatorId())->findOrFail($id);
            if (!$user->can('manage-any-petty-cash-requests')) {
                abort_unless($user->can('manage-own-petty-cash-requests')
                    && ((int) $record->creator_id === (int) $user->id || (int) $record->user_id === (int) $user->id), 404);
            }

            return $record;
        }

        abort_unless($user->can($action === 'view' ? 'view-petty-cashes' : 'edit-petty-cashes'), 403);
        $record = PettyCash::where('created_by', creatorId())->findOrFail($id);
        if (!$user->can('manage-any-petty-cashes')) {
            abort_unless($user->can('manage-own-petty-cashes') && (int) $record->creator_id === (int) $user->id, 404);
        }

        return $record;
    }

    public function store(Request $request, string $type, int $id, PettyCashAttachmentService $service)
    {
        $record = $this->record($type, $id, 'edit');
        $request->validate(
            array_merge($service::rules(), ['attachments' => 'required|array|min:1|max:' . $service::MAX_FILES]),
            $service::messages()
        );

        $paths = [];
        try {
            DB::transaction(function () use ($request, $record, $type, $service, &$paths) {
                $record::whereKey($record->id)->lockForUpdate()->first();
                $service->store($type, $record, $request->file('attachments', []), $paths);
            });
        } catch (\Throwable $e) {
            $service->cleanup($paths);
            throw $e;
        }

        return back()->with('success', __('Supporting documents saved.'));
    }

    public function download(Request $request, PettyCashAttachment $attachment)
    {
        $this->record($attachment->attachable_type, (int) $attachment->attachable_id, 'view');
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        $inline = $request->boolean('preview') && in_array($attachment->file_type, ['application/pdf', 'image/jpeg', 'image/png'], true);

        return Storage::disk('local')->response($attachment->file_path, $attachment->file_name, [
            'Content-Type' => $attachment->file_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    public function destroy(PettyCashAttachment $attachment, PettyCashAttachmentService $service)
    {
        $this->record($attachment->attachable_type, (int) $attachment->attachable_id, 'edit');

        DB::transaction(function () use ($attachment) {
            $attachment->update(['removed_by' => Auth::id()]);
            $attachment->delete();
        });
        $service->cleanup([$attachment->file_path]);

        return back()->with('success', __('Supporting document removed.'));
    }
}
