<?php

namespace App\Services;

class CentralAISettingsService
{
    public function agentConfig(): ?array
    {
        return $this->config('ai_agent', 'ai_agent_enabled');
    }

    public function assistantConfig(): ?array
    {
        return $this->config('ai', 'ai_assistant_enabled');
    }

    /**
     * Browser-safe availability for the signed-in user's company: booleans only, never credentials.
     */
    public function availability(): array
    {
        return [
            'ai_assistant_available' => $this->assistantConfig() !== null,
            'ai_agent_available' => $this->agentConfig() !== null,
        ];
    }

    private function config(string $prefix, string $companySwitch): ?array
    {
        $provider = admin_setting("{$prefix}_provider");
        $model = admin_setting("{$prefix}_model");
        $apiKey = admin_setting("{$prefix}_api_key");
        $enabled = company_setting($companySwitch);

        if ($enabled === null || $enabled === '') {
            $enabled = filled($apiKey);
        } else {
            $enabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
        }

        if (!$enabled || blank($provider) || blank($model) || blank($apiKey)) {
            return null;
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'api_key' => $apiKey,
        ];
    }
}
