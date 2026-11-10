<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SupportAgentController extends Controller
{
    public function index()
    {
        abort_unless($this->canManageTeam(), 403);

        $agents = User::query()
            ->where('type', 'staff')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['support-agent', 'support-manager']))
            ->with('roles:id,name,label')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_enable_login', 'created_at']);

        return Inertia::render('helpdesk/agents/index', ['agents' => $agents]);
    }

    private function canManageTeam(): bool
    {
        $user = Auth::user();

        return $user->type === 'superadmin' || ($user->isPlatformSupport() && $user->can('manage-support-team'));
    }

    public function store(Request $request)
    {
        abort_unless($this->canManageTeam(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['support-agent', 'support-manager'])],
        ]);

        $owner = User::where('type', 'superadmin')->orderBy('id')->firstOrFail();
        $agent = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'type' => 'staff',
            'created_by' => $owner->id,
            'creator_id' => Auth::id(),
            'is_enable_login' => true,
            'email_verified_at' => now(),
        ]);
        $agent->assignRole($validated['role']);

        return back()->with('success', __('Support agent created successfully.'));
    }
}
