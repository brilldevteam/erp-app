<?php

namespace Workdo\Account\Services;

use App\Events\CreateUser;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class PartyPortalAccountService
{
    public function create(Model $party, string $type, array $attributes, int $tenantId, int $actorId): User
    {
        $enabled = (bool) ($attributes['portal_access_enabled'] ?? false);
        $email = $this->normalizedEmail($attributes['contact_person_email'] ?? null);
        $user = User::create([
            'name' => $attributes['company_name'],
            'email' => $enabled ? $email : $this->placeholderEmail($type, $tenantId),
            'mobile_no' => $attributes['contact_person_mobile'] ?? null,
            'password' => Hash::make($enabled ? $attributes['password'] : Str::password(40)),
            'type' => $type,
            'is_enable_login' => $enabled,
            'is_disable' => $enabled ? 0 : 1,
            'lang' => company_setting('defaultLanguage', $tenantId) ?? 'en',
            'email_verified_at' => $enabled && admin_setting('enableEmailVerification') === 'on' ? null : now(),
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);

        $this->assignRole($user, $type, $tenantId);
        $party->user_id = $user->id;

        return $user;
    }

    public function sync(Model $party, string $type, array $attributes, int $tenantId, int $actorId): User
    {
        $user = $party->user;
        if (!$user) {
            return $this->create($party, $type, $attributes, $tenantId, $actorId);
        }

        $enabled = (bool) ($attributes['portal_access_enabled'] ?? false);
        $wasEnabled = (bool) $user->is_enable_login;
        $user->name = $attributes['company_name'];
        $user->mobile_no = $attributes['contact_person_mobile'] ?? null;
        $user->email = $enabled
            ? $this->normalizedEmail($attributes['contact_person_email'] ?? null)
            : ($wasEnabled || !$this->isPlaceholderEmail($user->email) ? $this->placeholderEmail($type, $tenantId) : $user->email);
        $user->is_enable_login = $enabled;
        $user->is_disable = $enabled ? 0 : 1;
        if (!empty($attributes['password'])) {
            $user->password = Hash::make($attributes['password']);
            $user->password_changed_at = now();
        }
        if ($enabled && !$wasEnabled) {
            $user->email_verified_at = admin_setting('enableEmailVerification') === 'on' ? null : now();
        }
        $user->save();
        $this->assignRole($user, $type, $tenantId);

        return $user;
    }

    public function disable(Model $party): void
    {
        if ($party->user) {
            $party->user->forceFill(['is_enable_login' => false, 'is_disable' => 1])->save();
        }
    }

    public function sendAccessNotifications(User $user, ?string $plainPassword, bool $wasEnabled = false): void
    {
        if (!$user->is_enable_login || $wasEnabled || !$plainPassword) {
            return;
        }

        CreateUser::dispatch(new Request(['password' => $plainPassword]), $user);
        if (company_setting('New User', $user->created_by) === 'on') {
            EmailTemplate::sendEmailTemplate('New User', [$user->email], [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $plainPassword,
            ]);
        }
        if (admin_setting('enableEmailVerification') === 'on') {
            SetConfigEmail($user->created_by);
            $user->sendEmailVerificationNotification();
        }
    }

    private function assignRole(User $user, string $type, int $tenantId): void
    {
        $role = Role::where('name', $type)->where('created_by', $tenantId)->where('guard_name', 'web')->first();
        if (!$role) {
            throw new RuntimeException(ucfirst($type).' role is missing for this company.');
        }
        $user->syncRoles([$role]);
    }

    private function normalizedEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    private function isPlaceholderEmail(?string $email): bool
    {
        return str_ends_with(strtolower((string) $email), '@import.local');
    }

    private function placeholderEmail(string $type, int $tenantId): string
    {
        do {
            $email = $type.'.'.$tenantId.'.'.Str::lower(Str::random(20)).'@import.local';
        } while (User::where('email', $email)->exists());

        return $email;
    }
}
