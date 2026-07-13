<?php

namespace App\Services\BulkImport;

use App\Events\CreateUser;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class ImportedClientUserService
{
    public function create(array $attributes, int $tenantId, int $actorId): User
    {
        return $this->createUser($attributes, $tenantId, $actorId, true, true);
    }

    public function createImportContact(array $attributes, int $tenantId, int $actorId): User
    {
        return $this->createUser($attributes, $tenantId, $actorId, false, false);
    }

    private function createUser(
        array $attributes,
        int $tenantId,
        int $actorId,
        bool $enforceUserLimit,
        bool $enableLogin
    ): User
    {
        if ($enforceUserLimit) {
            $limit = canCreateUser($tenantId);
            if (!$limit['can_create']) {
                throw new RuntimeException($limit['message']);
            }
        }

        $role = Role::where('name', 'client')
            ->where('created_by', $tenantId)
            ->where('guard_name', 'web')
            ->first();

        if (!$role) {
            throw new RuntimeException('Client role is missing for this company.');
        }

        $password = Str::password(14);
        $verificationEnabled = $enableLogin && admin_setting('enableEmailVerification') === 'on';
        $user = User::create([
            'name' => $attributes['name'],
            'email' => strtolower($attributes['email']),
            'mobile_no' => $attributes['mobile_no'] ?? null,
            'password' => Hash::make($password),
            'type' => 'client',
            'is_enable_login' => $enableLogin,
            'is_disable' => $enableLogin ? 0 : 1,
            'lang' => company_setting('defaultLanguage', $tenantId) ?? 'en',
            'email_verified_at' => $verificationEnabled ? null : now(),
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);

        $user->assignRole($role);
        if ($enableLogin) {
            CreateUser::dispatch(new Request($attributes), $user);
        }

        if ($enableLogin && company_setting('New User', $tenantId) === 'on') {
            EmailTemplate::sendEmailTemplate('New User', [$user->email], [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $password,
            ]);
        }

        if ($verificationEnabled) {
            SetConfigEmail($tenantId);
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }
}
