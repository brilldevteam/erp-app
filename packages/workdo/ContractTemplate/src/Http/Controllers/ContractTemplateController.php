<?php

namespace Workdo\ContractTemplate\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\User;
use Workdo\Contract\Models\Contract;
use Workdo\Contract\Models\ContractType;
use Workdo\Contract\Http\Requests\StoreContractRequest;
use Workdo\Contract\Http\Requests\UpdateContractRequest;
use Workdo\ContractTemplate\Http\Requests\ContractTemplateActionRequest;
use Workdo\ContractTemplate\Events\CreateContractTemplate;
use Workdo\ContractTemplate\Events\UpdateContractTemplate;
use Workdo\ContractTemplate\Events\DestroyContractTemplate;
use Workdo\ContractTemplate\Events\DuplicateContractTemplate;
use Workdo\ContractTemplate\Events\ConvertTemplateToContract;
use Workdo\ContractTemplate\Events\ConvertContractToTemplate;

class ContractTemplateController extends Controller
{
    public function index()
    {
        if (Auth::user()->can('manage-contract-templates')) {

            $templates = Contract::query()
                ->with(['contractType', 'user'])
                ->where('source_type', 'template')
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-contract-templates')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-contract-templates')) {
                        $q->where(function ($subQ) {
                            $subQ->where('creator_id', Auth::id())->orWhere('user_id', Auth::id());
                        });
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('subject'), function ($q) {
                    $searchTerm = request('subject');
                    $numericSearch = preg_replace('/[^0-9.]/', '', $searchTerm);

                    $q->where(function ($query) use ($searchTerm, $numericSearch) {
                        $query->where('subject', 'like', '%' . $searchTerm . '%')
                            ->orWhere('description', 'like', '%' . $searchTerm . '%')
                            ->orWhere('template_number', 'like', '%' . $searchTerm . '%')
                            ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                                $userQuery->where('name', 'like', '%' . $searchTerm . '%');
                            });
                        if ($numericSearch) {
                            $query->orWhere('value', 'like', '%' . $numericSearch . '%');
                        }
                    });
                })
                ->when(request('type_id') && request('type_id') !== 'all', fn($q) => $q->where('type_id', request('type_id')))
                ->when(request('status') && request('status') !== 'all', fn($q) => $q->where('status', request('status')))
                ->when(request('user_id') && request('user_id') !== 'all', fn($q) => $q->where('user_id', request('user_id')))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();


            $contractTypes = ContractType::where(function ($q) {
                if (Auth::user()->can('manage-any-contract-types')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-contract-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
                ->where('is_active', true)
                ->pluck('name', 'id');

            $users = User::where('created_by', creatorId())
                ->pluck('name', 'id');

            return Inertia::render('ContractTemplate/ContractTemplates/Index', [
                'templates' => $templates,
                'contractTypes' => $contractTypes,
                'users' => $users,
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function create()
    {
        if (Auth::user()->can('create-contract-templates')) {

            $contractTypes = ContractType::where(function ($q) {
                if (Auth::user()->can('manage-any-contract-types')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-contract-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
                ->where('is_active', true)
                ->pluck('name', 'id');

            $users = User::where('created_by', creatorId())
                ->pluck('name', 'id');

            return Inertia::render('ContractTemplate/ContractTemplates/Create', [
                'contractTypes' => $contractTypes,
                'users' => $users,
            ]);
        } else {
            return redirect()->route('contract-templates.index')->with('error', __('Permission denied'));
        }
    }

    public function store(StoreContractRequest $request)
    {
        if (Auth::user()->can('create-contract-templates')) {
            $validated = $request->validated();

            $validated['source_type'] = 'template';
            $validated['status'] = $validated['status'] ?? 'draft';
            $validated['creator_id'] = Auth::id();
            $validated['created_by'] = creatorId();

            // Handle 'none' values
            if ($validated['user_id'] === 'none') {
                $validated['user_id'] = null;
            }
            if ($validated['type_id'] === 'none') {
                $validated['type_id'] = null;
            }

            $template = Contract::create($validated);

            CreateContractTemplate::dispatch($request, $template);

            return back()->with('success', __('The contract template has been created successfully.'));
        } else {
            return redirect()->route('contract-templates.index')->with('error', __('Permission denied'));
        }
    }

    public function show(Contract $contractTemplate)
    {
        if (
            Auth::user()->can('view-contract-templates') &&
            $contractTemplate->source_type == 'template' &&
            $contractTemplate->created_by == creatorId() &&
            ($contractTemplate->user_id == Auth::id() ||
                $contractTemplate->creator_id == Auth::id())
        ) {

            $contractTemplate->load([
                'contractType',
                'user',
                'attachments' => function ($query) {
                    if (Auth::user()->can('manage-any-contract-template-attachments')) {
                        $query->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-contract-template-attachments')) {
                        $query->where('uploaded_by', Auth::id());
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                },
                'attachments.uploader',
                'comments' => function ($query) {
                    if (Auth::user()->can('manage-any-contract-template-comments')) {
                        $query->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-contract-template-comments')) {
                        $query->where('user_id', Auth::id());
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                },
                'comments.user',
                'notes' => function ($query) {
                    if (Auth::user()->can('manage-any-contract-template-notes')) {
                        $query->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-contract-template-notes')) {
                        $query->where('user_id', Auth::id());
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                },
                'notes.user'
            ]);


            $contractTypes = ContractType::where(function ($q) {
                if (Auth::user()->can('manage-any-contract-types')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-contract-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
                ->where('is_active', true)
                ->pluck('name', 'id');

            $users = User::where('created_by', creatorId())
                ->pluck('name', 'id');

            return Inertia::render('ContractTemplate/ContractTemplates/Show', [
                'template' => $contractTemplate,
                'contractTypes' => $contractTypes,
                'users' => $users,
            ]);
        } else {
            return redirect()->route('contract-templates.index')->with('error', __('Permission denied'));
        }
    }

    public function edit(Contract $contractTemplate)
    {
        if (
            Auth::user()->can('edit-contract-templates') &&
            $contractTemplate->source_type == 'template' &&
            $contractTemplate->created_by == creatorId() &&
            ($contractTemplate->user_id == Auth::id() ||
                $contractTemplate->creator_id == Auth::id())
        ) {
            $contractTypes = ContractType::where(function ($q) {
                if (Auth::user()->can('manage-any-contract-types')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-contract-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
                ->where('is_active', true)
                ->pluck('name', 'id');

            $users = User::where('created_by', creatorId())
                ->pluck('name', 'id');

            return Inertia::render('ContractTemplate/ContractTemplates/Edit', [
                'template' => $contractTemplate,
                'contractTypes' => $contractTypes,
                'users' => $users,
            ]);
        } else {
            return redirect()->route('contract-templates.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateContractRequest $request, Contract $contractTemplate)
    {
        if (Auth::user()->can('edit-contract-templates')) {
            $validated = $request->validated();
            $contractTemplate->update($validated);

            UpdateContractTemplate::dispatch($request, $contractTemplate);

            return back()->with('success', __('The contract template has been updated successfully.'));
        } else {
            return redirect()->route('contract-templates.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(Contract $contractTemplate)
    {
        if (
            Auth::user()->can('delete-contract-templates') &&
            $contractTemplate->source_type == 'template' &&
            $contractTemplate->created_by == creatorId() &&
            ($contractTemplate->user_id == Auth::id() ||
                $contractTemplate->creator_id == Auth::id())
        ) {
            DestroyContractTemplate::dispatch($contractTemplate);
            $contractTemplate->delete();
            return back()->with('success', __('The contract template has been deleted successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function convertToContract(ContractTemplateActionRequest $request, Contract $contractTemplate)
    {
        if (Auth::user()->can('convert-template-to-contract')) {
            $validated = $request->validated();

            $contract = new Contract();
            $contract->fill($validated);
            $contract->source_type = 'contract';
            $contract->creator_id = Auth::id();
            $contract->created_by = creatorId();
            $contract->description = $contractTemplate->description;
            $contract->save();

            if ($request->boolean('comments_duplicate', false)) {
                foreach ($contractTemplate->comments as $comment) {
                    $contract->comments()->create([
                        'comment' => $comment->comment,
                        'user_id' => Auth::id(),
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId(),
                    ]);
                }
            }

            if ($request->boolean('notes_duplicate', false)) {
                foreach ($contractTemplate->notes as $note) {
                    $contract->notes()->create([
                        'note' => $note->note,
                        'user_id' => Auth::id(),
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId(),
                    ]);
                }
            }

            if ($request->boolean('attachments_duplicate', false)) {
                foreach ($contractTemplate->attachments as $attachment) {
                    $contract->attachments()->create([
                        'file_name' => $attachment->file_name,
                        'file_path' => $attachment->file_path,
                        'uploaded_by' => Auth::id(),
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId(),
                    ]);
                }
            }

            ConvertTemplateToContract::dispatch($request, $contractTemplate, $contract);

            return back()->with('success', __('The contract template has been converted successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function duplicate(ContractTemplateActionRequest $request, Contract $contractTemplate)
    {
        if (Auth::user()->can('duplicate-contract-templates')) {
            $validated = $request->validated();

            $newTemplate = new Contract();
            $newTemplate->fill($validated);
            $newTemplate->source_type = 'template';
            $newTemplate->creator_id = Auth::id();
            $newTemplate->created_by = creatorId();
            $newTemplate->description = $contractTemplate->description;
            $newTemplate->save();

            if ($request->boolean('comments_duplicate', false)) {
                foreach ($contractTemplate->comments as $comment) {
                    $newTemplate->comments()->create([
                        'comment' => $comment->comment,
                        'user_id' => Auth::id(),
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId(),
                    ]);
                }
            }

            if ($request->boolean('notes_duplicate', false)) {
                foreach ($contractTemplate->notes as $note) {
                    $newTemplate->notes()->create([
                        'note' => $note->note,
                        'user_id' => Auth::id(),
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId(),
                    ]);
                }
            }

            if ($request->boolean('attachments_duplicate', false)) {
                foreach ($contractTemplate->attachments as $attachment) {
                    $newTemplate->attachments()->create([
                        'file_name' => $attachment->file_name,
                        'file_path' => $attachment->file_path,
                        'uploaded_by' => Auth::id(),
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId(),
                    ]);
                }
            }

            DuplicateContractTemplate::dispatch($request, $contractTemplate, $newTemplate);

            return back()->with('success', __('The contract template has been duplicated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function updateStatus(Request $request, Contract $contractTemplate)
    {
        if (Auth::user()->can('change-status-contract-templates')) {
            $request->validate([
                'status' => 'required|in:draft,active,archived'
            ]);

            $contractTemplate->update(['status' => $request->status]);

            return back()->with('success', __('The contract template has been updated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function convertToTemplate(ContractTemplateActionRequest $request, Contract $contract)
    {
        if (Auth::user()->can('convert-contract-to-template')) {
            $validated = $request->validated();

            $template = new Contract();
            $template->fill($validated);
            $template->source_type = 'template';
            $template->creator_id = Auth::id();
            $template->created_by = creatorId();
            $template->description = $contract->description;
            $template->save();

            if ($request->boolean('comments_duplicate', false)) {
                foreach ($contract->comments as $comment) {
                    $template->comments()->create([
                        'comment' => $comment->comment,
                        'user_id' => Auth::id(),
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId(),
                    ]);
                }
            }

            if ($request->boolean('notes_duplicate', false)) {
                foreach ($contract->notes as $note) {
                    $template->notes()->create([
                        'note' => $note->note,
                        'user_id' => Auth::id(),
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId(),
                    ]);
                }
            }

            if ($request->boolean('attachments_duplicate', false)) {
                foreach ($contract->attachments as $attachment) {
                    $template->attachments()->create([
                        'file_name' => $attachment->file_name,
                        'file_path' => $attachment->file_path,
                        'uploaded_by' => Auth::id(),
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId(),
                    ]);
                }
            }

            ConvertContractToTemplate::dispatch($contract, $template);

            return back()->with('success', __('The contract has been converted successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function preview(Contract $contractTemplate)
    {
        if (
            Auth::user()->can('preview-contract-templates') &&
            $contractTemplate->source_type == 'template' &&
            $contractTemplate->created_by == creatorId() &&
            ($contractTemplate->user_id == Auth::id() ||
                $contractTemplate->creator_id == Auth::id())
        ) {
            return Inertia::render('ContractTemplate/ContractTemplates/Preview', [
                'template' => $contractTemplate->load(['contractType', 'user'])
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function download(Contract $contractTemplate)
    {
        if (
            Auth::user()->can('view-contract-templates') &&
            $contractTemplate->source_type == 'template' &&
            $contractTemplate->created_by == creatorId() &&
            ($contractTemplate->user_id == Auth::id() ||
                $contractTemplate->creator_id == Auth::id())
        ) {
            $contractTemplate->load(['contractType', 'user']);
            
            $html = $this->generateTemplateHtml($contractTemplate);
            
            $filename = 'contract-template-' . ($contractTemplate->template_number ?? $contractTemplate->id) . '.pdf';
            
            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }
    
    private function generateTemplateHtml(Contract $template)
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Contract Template - ' . $template->subject . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
                .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                .subtitle { font-size: 16px; color: #666; }
                .section { margin-bottom: 30px; }
                .section-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .info-item { margin-bottom: 10px; }
                .label { font-weight: bold; }
                .description { margin-top: 20px; line-height: 1.6; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">' . htmlspecialchars($template->subject) . '</div>
                <div class="subtitle">Template #' . htmlspecialchars($template->template_number ?? 'Not Generated') . '</div>
            </div>
            
            <div class="info-grid">
                <div>
                    <div class="section-title">Template Information</div>
                    <div class="info-item">
                        <span class="label">Subject:</span> ' . htmlspecialchars($template->subject) . '
                    </div>
                    <div class="info-item">
                        <span class="label">Template Number:</span> ' . htmlspecialchars($template->template_number ?? 'Not Generated') . '
                    </div>
                    <div class="info-item">
                        <span class="label">Contract Type:</span> ' . htmlspecialchars($template->contractType->name ?? '-') . '
                    </div>
                    <div class="info-item">
                        <span class="label">Status:</span> ' . htmlspecialchars(ucfirst($template->status)) . '
                    </div>
                </div>
                
                <div>
                    <div class="section-title">Template Details</div>
                    ' . ($template->user ? '<div class="info-item"><span class="label">Assigned To:</span> ' . htmlspecialchars($template->user->name) . '</div>' : '') . '
                    ' . ($template->value ? '<div class="info-item"><span class="label">Template Value:</span> $' . number_format($template->value, 2) . '</div>' : '') . '
                    ' . ($template->start_date ? '<div class="info-item"><span class="label">Start Date:</span> ' . date('M d, Y', strtotime($template->start_date)) . '</div>' : '') . '
                    ' . ($template->end_date ? '<div class="info-item"><span class="label">End Date:</span> ' . date('M d, Y', strtotime($template->end_date)) . '</div>' : '') . '
                </div>
            </div>
            
            ' . ($template->description ? '<div class="section"><div class="section-title">Description</div><div class="description">' . $template->description . '</div></div>' : '') . '
            
            <div style="margin-top: 50px; text-align: center; font-size: 12px; color: #666;">
                Generated on ' . date('M d, Y') . '
            </div>
        </body>
        </html>';
    }
}
