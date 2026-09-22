<?php

namespace Workdo\VideoProduction\Http\Controllers;

use App\Models\User;
use App\Services\AuthSessionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;
use Workdo\Taskly\Models\Project;

class ClientAccessController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $this->authorizeManagement($project);

        if ($project->clients()->whereHas('roles', fn ($query) => $query->where('name', 'production-client'))->exists()) {
            throw ValidationException::withMessages([
                'email' => __('This production project already has a client login.'),
            ]);
        }

        $capacity = canCreateUser();
        if (! $capacity['can_create']) {
            throw ValidationException::withMessages(['email' => $capacity['message']]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'send_welcome_email' => ['sometimes', 'boolean'],
        ]);

        $user = DB::transaction(function () use ($validated, $project) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'password' => Hash::make($validated['password']),
                'type' => 'client',
                'is_enable_login' => true,
                'is_disable' => false,
                'lang' => company_setting('defaultLanguage') ?? 'en',
                'email_verified_at' => now(),
                'creator_id' => Auth::id(),
                'created_by' => creatorId(),
            ]);

            $user->assignRole($this->productionClientRole());
            $project->clients()->syncWithoutDetaching([$user->id]);

            return $user;
        });

        if ($request->boolean('send_welcome_email')) {
            $this->sendWelcomeEmail($user, $project);
        }

        return back()->with('success', __('Production client login created successfully.'));
    }

    public function update(Request $request, Project $project, User $user)
    {
        $this->authorizeClient($project, $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_enable_login' => ['required', 'boolean'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'is_enable_login' => $validated['is_enable_login'],
            'is_disable' => ! $validated['is_enable_login'],
        ]);

        return back()->with('success', __('Production client profile updated successfully.'));
    }

    public function changePassword(Request $request, Project $project, User $user)
    {
        $this->authorizeClient($project, $user);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ]);

        return back()->with('success', __('The password changed successfully.'));
    }

    public function impersonate(Project $project, User $user, AuthSessionService $authSessions)
    {
        $this->authorizeClient($project, $user);
        abort_if(! $user->is_enable_login || $user->is_disable, 422, __('This client login is disabled.'));

        Session::put('impersonator_id', Auth::id());
        Session::put('impersonator_return_route', 'project.index');
        Auth::login($user);
        $authSessions->initializeWebSession(request());

        return redirect()->route('video-production.client.overview')
            ->with('success', __('You are now logged in as :name', ['name' => $user->name]));
    }

    private function authorizeManagement(Project $project): void
    {
        abort_unless(
            Auth::user()->can('manage-video-production-client-access')
            && $project->category === 'production'
            && $project->created_by === creatorId(),
            403
        );
    }

    private function authorizeClient(Project $project, User $user): void
    {
        $this->authorizeManagement($project);
        abort_unless(
            $user->type === 'client'
            && $user->created_by === creatorId()
            && $user->hasRole('production-client')
            && $project->clients()->where('users.id', $user->id)->exists(),
            404
        );
    }

    private function productionClientRole(): Role
    {
        $role = Role::where('name', 'production-client')
            ->where('guard_name', 'web')
            ->where('created_by', creatorId())
            ->first();

        if (! $role) {
            $role = new Role();
            $role->name = 'production-client';
            $role->label = 'Production Client';
            $role->guard_name = 'web';
            $role->editable = false;
            $role->created_by = creatorId();
            $role->save();
        }

        $permissions = Permission::whereIn('name', [
            'view-video-production',
            'view-video-production-dashboard',
            'manage-profile',
            'edit-profile',
            'change-password-profile',
        ])->get();
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    private function sendWelcomeEmail(User $user, Project $project): void
    {
        try {
            SetConfigEmail();
            $html = '<div style="font-family:Arial,sans-serif;color:#172033;line-height:1.6">'
                .'<h2>'.e(__('Your production portal is ready')).'</h2>'
                .'<p>'.e(__('An account has been created for :project.', ['project' => $project->name])).'</p>'
                .'<p><strong>'.e(__('Login email')).':</strong> '.e($user->email).'</p>'
                .'<p><a href="'.e(route('login')).'">'.e(__('Open production portal')).'</a></p>'
                .'<p>'.e(__('Use the password shared with you separately. You can change it from Security Settings after signing in.')).'</p>'
                .'</div>';

            Mail::html($html, fn ($mail) => $mail->to($user->email)->subject(__('Your production portal login')));
        } catch (Throwable $exception) {
            Log::warning('Production client welcome email failed', [
                'project_id' => $project->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
            session()->flash('error', __('The login was created, but the welcome email could not be sent. Check the company email settings.'));
        }
    }
}
