<?php

namespace App\Jobs;

use App\Events\DefaultData;
use App\Events\GivePermissionToRole;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class InitializePlanData implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public string $modules,
        public ?int $clientRoleId = null,
        public ?int $staffRoleId = null,
    ) {}

    public function handle(): void
    {
        DefaultData::dispatch($this->userId, $this->modules);

        if ($this->clientRoleId) {
            GivePermissionToRole::dispatch($this->clientRoleId, 'client', $this->modules);
        }

        if ($this->staffRoleId) {
            GivePermissionToRole::dispatch($this->staffRoleId, 'staff', $this->modules);
        }
    }
}
