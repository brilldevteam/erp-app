<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Throwable;

class SocialAuthSettingsService
{
    public const SECRET_KEYS = [
        'google_client_secret',
        'microsoft_client_secret',
    ];

    public function adminPayload(): array
    {
        $settings = $this->settings();

        return [
            'social_login_enabled' => ($settings['social_login_enabled'] ?? 'off') === 'on',
            'google_login_enabled' => ($settings['google_login_enabled'] ?? 'off') === 'on',
            'google_client_id' => $settings['google_client_id'] ?? '',
            'google_client_secret_configured' => $this->hasSecret('google_client_secret'),
            'google_callback_url' => $this->callbackUrl('google'),
            'microsoft_login_enabled' => ($settings['microsoft_login_enabled'] ?? 'off') === 'on',
            'microsoft_client_id' => $settings['microsoft_client_id'] ?? '',
            'microsoft_client_secret_configured' => $this->hasSecret('microsoft_client_secret'),
            'microsoft_tenant_id' => $settings['microsoft_tenant_id'] ?? 'common',
            'microsoft_callback_url' => $this->callbackUrl('microsoft'),
        ];
    }

    public function publicStatus(): array
    {
        $masterEnabled = $this->value('social_login_enabled', 'off') === 'on';

        return [
            'enabled' => $masterEnabled,
            'google' => [
                'enabled' => $masterEnabled && $this->providerIsConfigured('google'),
            ],
            'microsoft' => [
                'enabled' => $masterEnabled && $this->providerIsConfigured('microsoft'),
            ],
        ];
    }

    public function providerConfig(string $provider): array
    {
        if (!in_array($provider, ['google', 'microsoft'], true)) {
            throw ValidationException::withMessages([
                'provider' => __('The selected social login provider is not supported.'),
            ]);
        }

        if (!$this->publicStatus()[$provider]['enabled']) {
            throw ValidationException::withMessages([
                'provider' => __('This social login provider is currently unavailable.'),
            ]);
        }

        $prefix = $provider.'_';

        return [
            'client_id' => $this->value($prefix.'client_id'),
            'client_secret' => $this->secret($prefix.'client_secret'),
            'redirect' => $this->callbackUrl($provider),
            'tenant_id' => $provider === 'microsoft'
                ? ($this->value('microsoft_tenant_id', 'common') ?: 'common')
                : null,
        ];
    }

    public function update(array $input): void
    {
        $this->assertProviderCanBeEnabled('google', $input);
        $this->assertProviderCanBeEnabled('microsoft', $input);

        $plainValues = [
            'social_login_enabled' => $this->onOff($input['social_login_enabled'] ?? false),
            'google_login_enabled' => $this->onOff($input['google_login_enabled'] ?? false),
            'google_client_id' => trim((string) ($input['google_client_id'] ?? '')),
            'microsoft_login_enabled' => $this->onOff($input['microsoft_login_enabled'] ?? false),
            'microsoft_client_id' => trim((string) ($input['microsoft_client_id'] ?? '')),
            'microsoft_tenant_id' => trim((string) ($input['microsoft_tenant_id'] ?? 'common')) ?: 'common',
        ];

        foreach ($plainValues as $key => $value) {
            $this->store($key, $value);
        }

        $this->updateSecret(
            'google_client_secret',
            $input['google_client_secret'] ?? null,
            (bool) ($input['clear_google_client_secret'] ?? false)
        );
        $this->updateSecret(
            'microsoft_client_secret',
            $input['microsoft_client_secret'] ?? null,
            (bool) ($input['clear_microsoft_client_secret'] ?? false)
        );

        $this->clearCache();
    }

    public function sanitize(array $settings): array
    {
        foreach (self::SECRET_KEYS as $key) {
            unset($settings[$key]);
        }

        return $settings;
    }

    public function callbackUrl(string $provider): string
    {
        return rtrim((string) config('app.url'), '/')."/auth/{$provider}/callback";
    }

    private function assertProviderCanBeEnabled(string $provider, array $input): void
    {
        if (!(bool) ($input[$provider.'_login_enabled'] ?? false)) {
            return;
        }

        $clientId = trim((string) ($input[$provider.'_client_id'] ?? ''));
        $newSecret = trim((string) ($input[$provider.'_client_secret'] ?? ''));
        $clearSecret = (bool) ($input['clear_'.$provider.'_client_secret'] ?? false);

        if ($clientId === '') {
            throw ValidationException::withMessages([
                "settings.{$provider}_client_id" => __('A client ID is required before enabling this provider.'),
            ]);
        }

        if ($clearSecret || ($newSecret === '' && !$this->hasSecret($provider.'_client_secret'))) {
            throw ValidationException::withMessages([
                "settings.{$provider}_client_secret" => __('A client secret is required before enabling this provider.'),
            ]);
        }
    }

    private function providerIsConfigured(string $provider): bool
    {
        return $this->value($provider.'_login_enabled', 'off') === 'on'
            && filled($this->value($provider.'_client_id'))
            && $this->hasSecret($provider.'_client_secret');
    }

    private function settings(): array
    {
        $admin = $this->superAdmin();

        if (!$admin) {
            return [];
        }

        return Setting::where('created_by', $admin->id)
            ->pluck('value', 'key')
            ->toArray();
    }

    private function value(string $key, ?string $default = null): ?string
    {
        return $this->settings()[$key] ?? $default;
    }

    private function hasSecret(string $key): bool
    {
        return filled($this->secret($key));
    }

    private function secret(string $key): ?string
    {
        $encrypted = $this->value($key);

        if (blank($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    private function updateSecret(string $key, mixed $value, bool $clear): void
    {
        if ($clear) {
            $this->store($key, null);
            return;
        }

        $value = trim((string) $value);

        if ($value !== '') {
            $this->store($key, Crypt::encryptString($value));
        }
    }

    private function store(string $key, mixed $value): void
    {
        $admin = $this->superAdmin();

        if (!$admin) {
            throw ValidationException::withMessages([
                'settings' => __('A superadmin account is required to save social login settings.'),
            ]);
        }

        Setting::updateOrCreate(
            ['key' => $key, 'created_by' => $admin->id],
            ['value' => $value, 'is_public' => false]
        );
    }

    private function superAdmin(): ?User
    {
        return User::where('type', 'superadmin')->first();
    }

    private function onOff(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'on' : 'off';
    }

    private function clearCache(): void
    {
        Cache::forget('admin_settings');
        Cache::forget('admin_settings_public');
    }
}
