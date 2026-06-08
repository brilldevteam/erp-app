<?php

namespace Workdo\ContractTemplate\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Workdo\Contract\Models\Contract;
use Workdo\Contract\Models\ContractComment;

class ContractTemplateCommentController extends Controller
{
    public function store(Request $request, Contract $contractTemplate)
    {
        if (Auth::user()->can('create-contract-template-comments')) {
            $request->validate([
                'comment' => 'required|string'
            ]);

            $contractTemplate->comments()->create([
                'comment' => $request->comment,
                'user_id' => Auth::id(),
                'creator_id' => Auth::id(),
                'created_by' => creatorId(),
            ]);

            return back()->with('success', __('The comment has been added successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function update(Request $request, ContractComment $comment)
    {
        if (Auth::user()->can('edit-contract-template-comments')) {
            $request->validate([
                'comment' => 'required|string'
            ]);

            $comment->update([
                'comment' => $request->comment
            ]);

            return back()->with('success', __('The comment has been updated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(ContractComment $comment)
    {
        if (Auth::user()->can('delete-contract-template-comments')) {
            $comment->delete();
            return back()->with('success', __('The comment has been deleted successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }
}
