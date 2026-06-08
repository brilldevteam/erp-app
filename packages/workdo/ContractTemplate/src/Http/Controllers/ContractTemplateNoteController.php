<?php

namespace Workdo\ContractTemplate\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Workdo\Contract\Models\Contract;
use Workdo\Contract\Models\ContractNote;

class ContractTemplateNoteController extends Controller
{
    public function store(Request $request, Contract $contractTemplate)
    {
        if (Auth::user()->can('create-contract-template-notes')) {
            $request->validate([
                'note' => 'required|string'
            ]);

            $contractTemplate->notes()->create([
                'note' => $request->note,
                'user_id' => Auth::id(),
                'creator_id' => Auth::id(),
                'created_by' => creatorId(),

            ]);

            return back()->with('success', __('The note has been added successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function update(Request $request, ContractNote $note)
    {
        if (Auth::user()->can('edit-contract-template-notes')) {
            $request->validate([
                'note' => 'required|string'
            ]);

            $note->update([
                'note' => $request->note
            ]);

            return back()->with('success', __('The note has been updated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(ContractNote $note)
    {
        if (Auth::user()->can('delete-contract-template-notes')) {
            $note->delete();
            return back()->with('success', __('The note has been deleted successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }
}
