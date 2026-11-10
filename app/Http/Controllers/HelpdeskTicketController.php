<?php

namespace App\Http\Controllers;

use App\Events\CreateHelpdeskTicket;
use App\Events\UpdateHelpdeskTicket;
use App\Events\DestroyHelpdeskTicket;
use App\Http\Requests\StoreHelpdeskTicketRequest;
use App\Http\Requests\UpdateHelpdeskTicketRequest;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskCategory;
use App\Models\User;
use App\Services\HelpdeskNotificationService;
use App\Services\HelpdeskEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class HelpdeskTicketController extends Controller
{
    public function today()
    {
        if(Gate::allows('viewAny', HelpdeskTicket::class)){
            $query = HelpdeskTicket::query()
                ->visibleTo(Auth::user())
                ->with(['category', 'creator', 'requester', 'assignedAgent', 'replies' => fn ($replies) => $replies->visibleTo(Auth::user()), 'replies.creator'])
                ->whereIn('status', ['open', 'in_progress', 'waiting_customer', 'waiting_internal'])
                ->when(request('search'), fn($q) => $q->where(function($query) {
                    $query->where('title', 'like', '%' . request('search') . '%')
                          ->orWhere('ticket_id', 'like', '%' . request('search') . '%')
                          ->orWhere('description', 'like', '%' . request('search') . '%');
                }))
                ->latest();

            $tickets = $query->get();

            // Calculate statistics
            $stats = [
                'total' => $tickets->count(),
                'urgent' => $tickets->where('priority', 'urgent')->count(),
                'high' => $tickets->where('priority', 'high')->count(),
                'medium' => $tickets->where('priority', 'medium')->count(),
                'low' => $tickets->where('priority', 'low')->count(),
                'open' => $tickets->where('status', 'open')->count(),
                'in_progress' => $tickets->where('status', 'in_progress')->count(),
                'waiting_customer' => $tickets->where('status', 'waiting_customer')->count(),
                'waiting_internal' => $tickets->where('status', 'waiting_internal')->count(),
            ];

            $categories = HelpdeskCategory::where('is_active', true)
                ->get(['id', 'name', 'color']);

            return Inertia::render('helpdesk/tickets/today', [
                'tickets' => $tickets,
                'stats' => $stats,
                'categories' => $categories,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function index()
    {
        if(Gate::allows('viewAny', HelpdeskTicket::class)){
            $tickets = HelpdeskTicket::query()
                ->visibleTo(Auth::user())
                ->with(['category', 'creator', 'requester', 'assignedAgent'])
                ->when(request('title'), fn($q) => $q->where(function($query) {
                    $query->where('title', 'like', '%' . request('title') . '%')
                          ->orWhere('ticket_id', 'like', '%' . request('title') . '%');
                }))
                ->when(request('status'), fn($q) => $q->where('status', request('status')))
                ->when(request('priority'), fn($q) => $q->where('priority', request('priority')))
                ->when(request('category_id'), fn($q) => $q->where('category_id', request('category_id')))
                ->when(request('company_id') && Auth::user()->type === 'superadmin', fn($q) => $q->where('created_by', request('company_id')))
                ->when(
                    in_array(request('sort'), ['ticket_id', 'title', 'priority', 'status', 'created_at'], true),
                    fn($q) => $q->orderBy(request('sort'), request('direction') === 'desc' ? 'desc' : 'asc'),
                    fn($q) => $q->latest()
                )
                ->paginate(in_array((int) request('per_page'), [10, 25, 50, 100], true) ? (int) request('per_page') : 10)
                ->withQueryString();

            $categories = HelpdeskCategory::where('is_active', true)
                ->get(['id', 'name', 'color']);

            $companies = [];
            if(Auth::user()->type === 'superadmin') {
                $companies = User::where('type', 'company')
                    ->get(['id', 'name']);
            }

            return Inertia::render('helpdesk/tickets/index', [
                'tickets' => $tickets,
                'categories' => $categories,
                'companies' => $companies,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreHelpdeskTicketRequest $request, HelpdeskNotificationService $notifications, HelpdeskEmailService $emails)
    {
        if(Gate::allows('create', HelpdeskTicket::class)){
            $validated = $request->validated();

            $ticket = new HelpdeskTicket();
            $ticket->title = $validated['title'];
            $ticket->description = $validated['description'];
            $ticket->priority = $validated['priority'];
            $ticket->category_id = $validated['category_id'];

            if(Auth::user()->type === 'superadmin' && isset($validated['company_id'])) {
                $ticket->created_by = $validated['company_id'];
            } else {                
                $ticket->created_by = creatorId();
            }
            $ticket->requester_user_id = Auth::id();
   
            $ticket->save();
            $notifications->ticketCreated($ticket);
            $emails->ticketCreated($ticket->loadMissing(['creator', 'category']));

            CreateHelpdeskTicket::dispatch($request, $ticket);

            return back()->with('success', __('The ticket has been created successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    private const RECORD_STATUSES = ['open', 'in_progress', 'waiting_customer', 'waiting_internal', 'resolved', 'closed'];
    private const RECORD_PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** Every ticket the signed-in user may see, kept as permanent records, with a summary that follows the filters. */
    public function records(Request $request)
    {
        if (!Gate::allows('viewAny', HelpdeskTicket::class)) {
            return back()->with('error', __('Permission denied'));
        }

        $user = Auth::user();
        $supportSide = $user->type === 'superadmin' || ($user->isPlatformSupport() && $user->can('view-all-support-tickets'));
        $date = fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;

        $filtered = function () use ($request, $user, $supportSide, $date) {
            return HelpdeskTicket::query()
                ->visibleTo($user)
                ->when(trim((string) $request->query('search')) !== '', function ($query) use ($request) {
                    $search = '%' . trim((string) $request->query('search')) . '%';
                    $query->where(fn ($match) => $match
                        ->where('ticket_id', 'like', $search)
                        ->orWhere('title', 'like', $search)
                        ->orWhereHas('requester', fn ($requester) => $requester->where('name', 'like', $search))
                        ->orWhereHas('creator', fn ($company) => $company->where('name', 'like', $search)));
                })
                ->when(in_array($request->query('status'), self::RECORD_STATUSES, true), fn ($query) => $query->where('status', $request->query('status')))
                ->when(in_array($request->query('priority'), self::RECORD_PRIORITIES, true), fn ($query) => $query->where('priority', $request->query('priority')))
                ->when(ctype_digit((string) $request->query('category_id')), fn ($query) => $query->where('category_id', (int) $request->query('category_id')))
                ->when($supportSide && ctype_digit((string) $request->query('company_id')), fn ($query) => $query->where('created_by', (int) $request->query('company_id')))
                ->when($supportSide && $request->query('agent_id') === 'unassigned', fn ($query) => $query->whereNull('assigned_agent_id'))
                ->when($supportSide && ctype_digit((string) $request->query('agent_id')), fn ($query) => $query->where('assigned_agent_id', (int) $request->query('agent_id')))
                ->when($date($request->query('date_from')), fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
                ->when($date($request->query('date_to')), fn ($query, $to) => $query->whereDate('created_at', '<=', $to));
        };

        $countBy = fn (string $column) => $filtered()->selectRaw("{$column} as record_key, count(*) as record_count")->groupBy($column)->pluck('record_count', 'record_key');
        $averageSeconds = fn (string $column) => ($row = $filtered()->whereNotNull($column)->selectRaw("AVG(TIMESTAMPDIFF(SECOND, created_at, {$column})) as seconds")->first())
            && $row->seconds !== null ? (int) round((float) $row->seconds) : null;
        $named = function ($counts, array $names, string $empty) {
            return collect($counts)->map(fn ($count, $key) => [
                'label' => ($key === '' || $key === null) ? $empty : ($names[$key] ?? $empty),
                'count' => (int) $count,
            ])->sortByDesc('count')->values();
        };

        $byStatus = $countBy('status');
        $byCategory = $countBy('category_id');
        $summary = [
            'total' => $filtered()->count(),
            'by_status' => collect(self::RECORD_STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($byStatus[$status] ?? 0)]),
            'by_priority' => collect(self::RECORD_PRIORITIES)->mapWithKeys(fn ($priority) => [$priority => (int) ($countBy('priority')[$priority] ?? 0)]),
            'by_category' => $named($byCategory, HelpdeskCategory::whereIn('id', $byCategory->keys()->filter())->pluck('name', 'id')->all(), __('No category')),
            'avg_first_response_seconds' => $averageSeconds('first_response_at'),
            'avg_resolution_seconds' => $averageSeconds('resolved_at'),
        ];
        if ($supportSide) {
            $byCompany = $countBy('created_by');
            $byAgent = $countBy('assigned_agent_id');
            $summary['by_company'] = $named($byCompany, User::whereIn('id', $byCompany->keys()->filter())->pluck('name', 'id')->all(), __('Unknown'))->take(10)->values();
            $summary['by_agent'] = $named($byAgent, User::whereIn('id', $byAgent->keys()->filter())->pluck('name', 'id')->all(), __('Unassigned'));
        }

        $sort = in_array($request->query('sort'), ['created_at', 'ticket_id', 'priority', 'status', 'resolved_at'], true) ? $request->query('sort') : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100], true) ? (int) $request->query('per_page') : 10;

        $records = $filtered()
            ->with(['category:id,name,color', 'creator:id,name', 'requester:id,name', 'assignedAgent:id,name'])
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('helpdesk/records/index', [
            'records' => $records,
            'summary' => $summary,
            'filters' => $request->only(['search', 'status', 'priority', 'category_id', 'company_id', 'agent_id', 'date_from', 'date_to', 'sort', 'direction', 'per_page']),
            'categories' => HelpdeskCategory::orderBy('name')->get(['id', 'name']),
            'companies' => $supportSide ? User::where('type', 'company')->orderBy('name')->get(['id', 'name']) : [],
            'agents' => $supportSide
                ? User::where('type', 'staff')->whereHas('roles', fn ($query) => $query->whereIn('name', ['support-agent', 'support-manager']))->orderBy('name')->get(['id', 'name'])
                : [],
            'supportSide' => $supportSide,
        ]);
    }

    public function show(HelpdeskTicket $helpdeskTicket)
    {
        if(Gate::allows('view', $helpdeskTicket)){
            $helpdeskTicket->load(['category', 'creator', 'requester', 'assignedAgent', 'replies' => fn ($replies) => $replies->visibleTo(Auth::user()), 'replies.creator', 'replies.files', 'statusHistories' => fn ($history) => $history->latest('id')]);
            // Who handled the ticket is support-side information.
            if (Gate::allows('viewInternalNotes', $helpdeskTicket)) {
                $helpdeskTicket->load(['assignmentHistories' => fn ($history) => $history->latest('id')]);
            }

            return Inertia::render('helpdesk/tickets/show', [
                'ticket' => $helpdeskTicket,
                'supportAgents' => Auth::user()->can('assign-helpdesk-tickets')
                    ? User::where('type', 'staff')->whereHas('roles', fn ($query) => $query->whereIn('name', ['support-agent', 'support-manager']))->orderBy('name')->get(['id', 'name', 'email'])
                    : [],
            ]);
        }
        else{
            return redirect()->route('helpdesk-tickets.index')->with('error', __('Permission denied'));
        }
    }

    public function assign(Request $request, HelpdeskTicket $helpdeskTicket, HelpdeskNotificationService $notifications, HelpdeskEmailService $emails)
    {
        abort_unless(Gate::allows('assign', $helpdeskTicket), 403);
        $validated = $request->validate(['assigned_agent_id' => ['nullable', 'integer', 'exists:users,id']]);
        $agent = isset($validated['assigned_agent_id'])
            ? User::whereKey($validated['assigned_agent_id'])->where('type', 'staff')->whereHas('roles', fn ($query) => $query->whereIn('name', ['support-agent', 'support-manager']))->firstOrFail()
            : null;
        $previous = $helpdeskTicket->assignedAgent;

        if ((int) $helpdeskTicket->assigned_agent_id !== (int) ($agent?->id)) {
            $history = $helpdeskTicket->assignmentHistories()->create([
                'from_agent_id' => $previous?->id,
                'to_agent_id' => $agent?->id,
                'changed_by' => Auth::id(),
                'from_agent_name' => $previous?->name,
                'to_agent_name' => $agent?->name,
                'changed_by_name' => Auth::user()->name,
            ]);
            $helpdeskTicket->update(['assigned_agent_id' => $agent?->id]);
            if ($agent) {
                $notifications->assigned($helpdeskTicket, $agent);
                $emails->assigned($helpdeskTicket->loadMissing(['creator', 'category']), $agent, $history->id);
            }
        }

        return back()->with('success', __('Ticket assignment updated.'));
    }

    public function update(UpdateHelpdeskTicketRequest $request, HelpdeskTicket $helpdeskTicket, HelpdeskNotificationService $notifications, HelpdeskEmailService $emails)
    {
        if(Gate::allows('update', $helpdeskTicket)){
            $validated = $request->validated();
            $oldStatus = $helpdeskTicket->status;

            $helpdeskTicket->title = $validated['title'];
            $helpdeskTicket->description = $validated['description'];
            $helpdeskTicket->status = $validated['status'];
            $helpdeskTicket->priority = $validated['priority'];
            $helpdeskTicket->category_id = $validated['category_id'];


            if($validated['status'] === 'resolved' && !$helpdeskTicket->resolved_at) {
                $helpdeskTicket->resolved_at = now();
            }
            if($validated['status'] === 'closed' && !$helpdeskTicket->closed_at) {
                $helpdeskTicket->closed_at = now();
            }
            if(!in_array($validated['status'], ['resolved', 'closed'], true)) {
                $helpdeskTicket->resolved_at = null;
                $helpdeskTicket->closed_at = null;
            }

            $helpdeskTicket->save();

            if ($oldStatus !== $helpdeskTicket->status) {
                $history = $helpdeskTicket->statusHistories()->create([
                    'from_status' => $oldStatus,
                    'to_status' => $helpdeskTicket->status,
                    'changed_by' => Auth::id(),
                    'changed_by_name' => Auth::user()->name,
                ]);
                if ($helpdeskTicket->status === 'resolved') {
                    $notifications->resolved($helpdeskTicket);
                }
                $emails->statusChanged($helpdeskTicket->loadMissing(['requester', 'creator', 'category']), $history);
            }

            UpdateHelpdeskTicket::dispatch($request, $helpdeskTicket);

            return back()->with('success', __('The ticket has been updated successfully'));
        }
        else{
            return redirect()->route('helpdesk-tickets.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(HelpdeskTicket $helpdeskTicket)
    {
        if(Gate::allows('delete', $helpdeskTicket)){
            DestroyHelpdeskTicket::dispatch($helpdeskTicket);

            $helpdeskTicket->delete();

           return back()->with('success', __('The ticket has been deleted.'));
        }
        else{
            return redirect()->route('helpdesk-tickets.index')->with('error', __('Permission denied'));
        }
    }
}
