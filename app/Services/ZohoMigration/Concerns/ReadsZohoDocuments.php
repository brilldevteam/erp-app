<?php

namespace App\Services\ZohoMigration\Concerns;

use App\Models\User;
use Carbon\Carbon;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;

/**
 * Shared reading rules for Zoho document exports (estimates, invoices, …).
 */
trait ReadsZohoDocuments
{
    /**
     * ISO dates (CSV exports, and XLSX dates as staged) are unambiguous; anything else uses the migration's
     * configured source format, and a value that does not fit it exactly is rejected rather than guessed.
     */
    protected function date(string $value, string $sourceFormat): ?string
    {
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}:\d{2})?$/', $value)) {
            $date = Carbon::createFromFormat('Y-m-d', substr($value, 0, 10));

            return $date && $date->format('Y-m-d') === substr($value, 0, 10) ? $date->format('Y-m-d') : null;
        }
        $format = strtr($sourceFormat, ['dd' => 'd', 'MM' => 'm', 'yyyy' => 'Y']);
        try {
            $date = Carbon::createFromFormat('!'.$format, $value);
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->format($format) === $value ? $date->format('Y-m-d') : null;
    }

    /**
     * Zoho's built-in Discount account. Zoho books invoice lines at full price and debits their item discounts
     * here (credit notes credit it back), so imported journals do the same to keep Zoho's trial balance.
     */
    protected function discountAccount(int $tenantId): ?ChartOfAccount
    {
        $matches = ChartOfAccount::where('created_by', $tenantId)->whereRaw('LOWER(account_name) = ?', ['discount'])->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** The customer's login user, found by Zoho Display Name as imported in the Customers stage. */
    protected function customerUser(string $name, int $tenantId): ?User
    {
        $value = mb_strtolower(trim($name));

        return User::where('created_by', $tenantId)->where('type', 'client')->whereRaw('LOWER(name) = ?', [$value])->first()
            ?? Customer::where('created_by', $tenantId)->whereRaw('LOWER(company_name) = ?', [$value])->first()?->user;
    }

    /** The vendor's login user, found by Zoho Display Name as imported in the Vendors stage. */
    protected function vendorUser(string $name, int $tenantId): ?User
    {
        $value = mb_strtolower(trim($name));

        return User::where('created_by', $tenantId)->where('type', 'vendor')->whereRaw('LOWER(name) = ?', [$value])->first()
            ?? Vendor::where('created_by', $tenantId)->whereRaw('LOWER(company_name) = ?', [$value])->first()?->user;
    }
}
