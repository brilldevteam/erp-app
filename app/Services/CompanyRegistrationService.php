<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CompanyRegistrationService
{
    public function create(
        string $name,
        string $email,
        ?string $password = null,
        bool $emailVerified = false,
        bool $sendWelcomeEmail = false
    ): User {
        $adminUser = User::where('type', 'superadmin')->first();

        $user = DB::transaction(function () use ($name, $email, $password, $emailVerified, $adminUser) {
            $user = User::create([
                'name' => $name,
                'email' => strtolower($email),
                'password' => $password ? Hash::make($password) : null,
                'email_verified_at' => $emailVerified ? now() : null,
                'type' => 'company',
                'lang' => admin_setting('defaultLanguage') ?? 'en',
                'created_by' => $adminUser?->id,
                'is_disable' => 0,
                'is_enable_login' => 1,
            ]);

            User::CompanySetting($user->id);
            User::MakeRole($user->id);
            $user->assignRole('company');

            return $user;
        });

        event(new Registered($user));

        if ($sendWelcomeEmail && $password && admin_setting('New User') === 'on') {
            EmailTemplate::sendEmailTemplate('New User', [$user->email], [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $password,
            ], $adminUser?->id);
        }

        return $user;
    }
}
