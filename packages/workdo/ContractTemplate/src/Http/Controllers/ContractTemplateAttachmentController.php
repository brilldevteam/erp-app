<?php

namespace Workdo\ContractTemplate\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Workdo\Contract\Models\Contract;
use Workdo\Contract\Models\ContractAttachment;
use Illuminate\Http\Request;

class ContractTemplateAttachmentController extends Controller
{
    public function store(Request $request, Contract $contractTemplate)
    {
        if (Auth::user()->can('create-contract-template-attachments')) {
            $validated = $request->validate([
                'media_paths' => 'required|array',
                'media_paths.*' => 'required|string'
            ]);

            foreach ($validated['media_paths'] as $mediaPath) {
                $contractTemplate->attachments()->create([
                    'file_name' => basename($mediaPath),
                    'file_path' => basename($mediaPath),
                    'uploaded_by' => Auth::id(),
                    'creator_id' => Auth::id(),
                    'created_by' => creatorId(),
                ]);
            }

            return back()->with('success', __('The attachments have been uploaded successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(ContractAttachment $attachment)
    {
        if (
            Auth::user()->can('delete-contract-template-attachments') &&
            ($attachment->uploaded_by === Auth::id() || $attachment->created_by === Auth::id())
        ) {
            $attachment->delete();
            return back()->with('success', __('The attachment has been deleted successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }
}
