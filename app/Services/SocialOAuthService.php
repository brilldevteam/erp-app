<?php

namespace App\Services;

use App\Data\SocialProviderUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use League\OAuth2\Client\Provider\GenericProvider;
use RuntimeException;

class SocialOAuthService
{
    public function __construct(
        private readonly SocialAuthSettingsService $settings
    ) {
    }

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $config = $this->settings->providerConfig($provider);

        if ($provider === 'google') {
            $this->configureGoogle($config);

            return Socialite::driver('google')
                ->scopes(['openid', 'profile', 'email'])
                ->redirect();
        }

        $client = $this->microsoftProvider($config);
        $authorizationUrl = $client->getAuthorizationUrl([
            'scope' => ['openid', 'profile', 'email', 'User.Read'],
        ]);
        $request->session()->put('social_auth.microsoft_state', $client->getState());

        return redirect()->away($authorizationUrl);
    }

    public function user(Request $request, string $provider): SocialProviderUser
    {
        $config = $this->settings->providerConfig($provider);

        if ($provider === 'google') {
            $this->configureGoogle($config);
            $socialUser = Socialite::driver('google')->user();
            $raw = is_array($socialUser->user ?? null) ? $socialUser->user : [];
            $email = (string) ($socialUser->getEmail() ?? '');

            return new SocialProviderUser(
                (string) $socialUser->getId(),
                (string) ($socialUser->getName() ?: $socialUser->getNickname() ?: $email),
                $email,
                $socialUser->getAvatar(),
                (bool) ($raw['verified_email'] ?? $raw['email_verified'] ?? false),
            );
        }

        $expectedState = (string) $request->session()->pull('social_auth.microsoft_state', '');
        $state = (string) $request->query('state', '');

        if ($expectedState === '' || $state === '' || !hash_equals($expectedState, $state)) {
            throw new RuntimeException('Invalid OAuth state.');
        }

        $client = $this->microsoftProvider($config);
        $token = $client->getAccessToken('authorization_code', [
            'code' => (string) $request->query('code'),
        ]);
        $owner = $client->getResourceOwner($token)->toArray();
        $email = (string) ($owner['mail'] ?? $owner['userPrincipalName'] ?? '');

        return new SocialProviderUser(
            (string) ($owner['id'] ?? ''),
            (string) ($owner['displayName'] ?? $email),
            $email,
            null,
            $email !== '',
        );
    }

    private function configureGoogle(array $config): void
    {
        Config::set('services.google', [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect' => $config['redirect'],
        ]);
    }

    private function microsoftProvider(array $config): GenericProvider
    {
        $tenant = rawurlencode($config['tenant_id'] ?: 'common');

        return new GenericProvider([
            'clientId' => $config['client_id'],
            'clientSecret' => $config['client_secret'],
            'redirectUri' => $config['redirect'],
            'urlAuthorize' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize",
            'urlAccessToken' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
            'scopes' => ['openid', 'profile', 'email', 'User.Read'],
            'scopeSeparator' => ' ',
            'responseResourceOwnerId' => 'id',
        ]);
    }
}
