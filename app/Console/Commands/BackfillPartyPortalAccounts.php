<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;
use Workdo\Account\Services\PartyPortalAccountService;

class BackfillPartyPortalAccounts extends Command
{
    protected $signature = 'accounts:backfill-party-portals {--dry-run}';
    protected $description = 'Create disabled internal portal identities for customers and vendors that do not have one';

    public function handle(PartyPortalAccountService $accounts): int
    {
        $total = Customer::whereNull('user_id')->count() + Vendor::whereNull('user_id')->count();
        if ($this->option('dry-run')) {
            $this->info("{$total} customer/vendor records require a portal identity.");
            return self::SUCCESS;
        }

        $created = 0;
        Customer::whereNull('user_id')->orderBy('id')->chunkById(100, function ($customers) use ($accounts, &$created) {
            foreach ($customers as $customer) {
                DB::transaction(function () use ($accounts, $customer, &$created) {
                    $accounts->create($customer, 'client', $this->attributes($customer), $customer->created_by, (int) ($customer->creator_id ?: $customer->created_by));
                    $customer->save();
                    $created++;
                });
            }
        });
        Vendor::whereNull('user_id')->orderBy('id')->chunkById(100, function ($vendors) use ($accounts, &$created) {
            foreach ($vendors as $vendor) {
                DB::transaction(function () use ($accounts, $vendor, &$created) {
                    $accounts->create($vendor, 'vendor', $this->attributes($vendor), $vendor->created_by, (int) ($vendor->creator_id ?: $vendor->created_by));
                    $vendor->save();
                    $created++;
                });
            }
        });

        $this->info("Created {$created} disabled portal identities.");
        return self::SUCCESS;
    }

    private function attributes(Customer|Vendor $party): array
    {
        return [
            'company_name' => $party->company_name,
            'contact_person_email' => $party->contact_person_email,
            'contact_person_mobile' => $party->contact_person_mobile,
            'portal_access_enabled' => false,
        ];
    }
}
