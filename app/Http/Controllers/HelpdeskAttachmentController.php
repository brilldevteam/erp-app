<?php

namespace App\Http\Controllers;

use App\Models\HelpdeskAttachment;
use App\Models\HelpdeskReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;

class HelpdeskAttachmentController extends Controller
{
    private const INLINE_IMAGES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function show(Request $request, HelpdeskAttachment $attachment)
    {
        $user = Auth::user();
        $attachment->load(['ticket', 'reply']);

        abort_unless($attachment->ticket && $attachment->reply && Gate::allows('view', $attachment->ticket), 404);
        abort_if($attachment->reply->is_internal && !HelpdeskReply::canSeeInternal($user), 404);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];

        if ($request->boolean('inline') && in_array($attachment->mime_type, self::INLINE_IMAGES, true)) {
            return response()->file($disk->path($attachment->path), $headers + ['Content-Type' => $attachment->mime_type]);
        }

        return $disk->download($attachment->path, $attachment->original_name, $headers);
    }
}
