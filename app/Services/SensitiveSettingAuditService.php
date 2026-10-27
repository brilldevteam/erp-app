<?php

namespace App\Services;

use App\Models\SensitiveSettingAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SensitiveSettingAuditService
{
    public function __construct(private Request $request)
    {
    }

    public function record(string $group, string $key, string $action): void
    {
        SensitiveSettingAudit::create([
            'user_id' => Auth::id(),
            'company_id' => function_exists('creatorId') ? creatorId() : Auth::id(),
            'setting_group' => $group,
            'setting_key' => $key,
            'action' => $action,
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 500),
        ]);
    }

    public function recordSubmittedSecret(
        string $group,
        string $key,
        bool $wasConfigured,
        mixed $replacement,
        bool $clear
    ): void {
        if ($clear) {
            $this->record($group, $key, 'cleared');
            return;
        }

        if (filled($replacement)) {
            $this->record($group, $key, $wasConfigured ? 'replaced' : 'configured');
        }
    }
}
