<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplates\DocumentTemplateService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DocumentTemplateController extends Controller
{
    public function __construct(private readonly DocumentTemplateService $templates)
    {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('view-document-templates') || auth()->user()->can('manage-document-templates'), 403);

        $type = $request->get('type');
        $canManage = $this->canManageTemplates();
        $query = DocumentTemplate::query()
            ->forCompany(creatorId())
            ->when(in_array($type, DocumentTemplateService::TYPES, true), fn ($query) => $query->forType($type))
            ->when(!$canManage, fn ($query) => $query->active())
            ->latest();

        return Inertia::render('document-templates/index', [
            'templates' => $query->paginate(12)->withQueryString(),
            'filters' => ['type' => $type],
            'types' => DocumentTemplateService::TYPES,
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->can('create-document-templates'), 403);

        return Inertia::render('document-templates/form', [
            'template' => null,
            'types' => DocumentTemplateService::TYPES,
            'defaultConfig' => DocumentTemplateService::DEFAULT_CONFIG,
            'sampleDocument' => $this->templates->sampleDocument(DocumentTemplate::TYPE_QUOTATION),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('create-document-templates'), 403);

        $this->templates->create(creatorId(), auth()->id(), $this->validated($request));

        return back()->with('success', __('Template created successfully.'));
    }

    public function edit(DocumentTemplate $documentTemplate)
    {
        $this->authorizeCompany($documentTemplate);
        abort_unless(auth()->user()->can('edit-document-templates') || auth()->user()->can('manage-document-templates'), 403);

        return Inertia::render('document-templates/form', [
            'template' => $documentTemplate,
            'types' => DocumentTemplateService::TYPES,
            'defaultConfig' => DocumentTemplateService::DEFAULT_CONFIG,
            'sampleDocument' => $this->templates->sampleDocument($documentTemplate->type, $documentTemplate),
        ]);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate)
    {
        $this->authorizeCompany($documentTemplate);
        abort_unless(auth()->user()->can('edit-document-templates') || auth()->user()->can('manage-document-templates'), 403);

        $this->templates->update($documentTemplate, auth()->id(), $this->validated($request));

        return back()->with('success', __('Template updated successfully.'));
    }

    public function destroy(DocumentTemplate $documentTemplate)
    {
        $this->authorizeCompany($documentTemplate);
        abort_unless(auth()->user()->can('delete-document-templates') || auth()->user()->can('manage-document-templates'), 403);

        $this->templates->delete($documentTemplate);

        return redirect()->route('document-templates.index')->with('success', __('Template deleted successfully.'));
    }

    public function duplicate(DocumentTemplate $documentTemplate)
    {
        $this->authorizeCompany($documentTemplate);
        abort_unless(auth()->user()->can('create-document-templates'), 403);

        $copy = $this->templates->duplicate($documentTemplate, auth()->id());

        return redirect()->route('document-templates.edit', $copy)->with('success', __('Template duplicated successfully.'));
    }

    public function setDefault(DocumentTemplate $documentTemplate)
    {
        $this->authorizeCompany($documentTemplate);
        abort_unless(auth()->user()->can('set-default-document-templates'), 403);

        $this->templates->setDefault($documentTemplate);

        return back()->with('success', __('Default template updated successfully.'));
    }

    public function active(Request $request)
    {
        abort_unless(auth()->check(), 403);

        $validated = $request->validate([
            'type' => ['required', Rule::in(DocumentTemplateService::TYPES)],
        ]);

        $this->templates->ensureDefault(creatorId(), $validated['type']);

        return DocumentTemplate::query()
            ->forCompany(creatorId())
            ->forType($validated['type'])
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_default']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(DocumentTemplateService::TYPES)],
            'status' => ['required', Rule::in([DocumentTemplate::STATUS_ACTIVE, DocumentTemplate::STATUS_INACTIVE])],
            'is_default' => ['boolean'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'config_json' => ['required', 'array'],
            'terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'bank_details' => ['nullable', 'string'],
            'signature_url' => ['nullable', 'string', 'max:500'],
            'signature_text' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function authorizeCompany(DocumentTemplate $template): void
    {
        abort_unless((int) $template->company_id === (int) creatorId(), 404);
    }

    private function canManageTemplates(): bool
    {
        $user = auth()->user();

        return $user->can('manage-document-templates')
            || $user->can('create-document-templates')
            || $user->can('edit-document-templates')
            || $user->can('delete-document-templates')
            || $user->can('set-default-document-templates');
    }
}
