<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Workdo\Account\Services\PartyPortalAccountService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\Vendor;
use Workdo\Account\Http\Requests\StoreVendorRequest;
use Workdo\Account\Http\Requests\UpdateVendorRequest;
use Workdo\Account\Events\CreateVendor;
use Workdo\Account\Events\UpdateVendor;
use Workdo\Account\Events\DestroyVendor;
use Illuminate\Support\Facades\Schema;

class VendorController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-vendors')){
            $vendors = Vendor::query()
                ->with('user:id,name,email,mobile_no,avatar,is_disable,is_enable_login')
                ->when(Schema::hasTable('project_contracts'), function ($query) {
                    $query->with(['projectContracts' => function ($contractQuery) {
                        $contractQuery->where('created_by', creatorId())
                            ->with('project:id,name')
                            ->withSum('purchaseInvoices as amount_paid', 'paid_amount')
                            ->latest();
                    }]);
                })
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-vendors')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-vendors')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('company_name'), fn($q) => $q->where('company_name', 'like', '%' . request('company_name') . '%'))
                ->when(request('vendor_code'), fn($q) => $q->where('vendor_code', 'like', '%' . request('vendor_code') . '%'))
                ->when(request('tax_number'), fn($q) => $q->where('tax_number', 'like', '%' . request('tax_number') . '%'))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            return Inertia::render('Account/Vendors/Index', [
                'vendors' => $vendors,
                'editVendor' => request('edit') ? Vendor::with('user:id,is_enable_login,is_disable')->where('created_by', creatorId())->find(request('edit')) : null,
                'openCreate' => request()->boolean('create'),
                'returnTo' => request('return_to') === 'project.contractors.index' ? request('return_to') : null,
            ]);
        }
        return back()->with('error', __('Permission denied'));
    }



    public function store(StoreVendorRequest $request, PartyPortalAccountService $portalAccounts)
    {
        if(Auth::user()->can('create-vendors')){
            $validated = $request->validated();

            $vendor = new Vendor();
            $vendor->company_name = $validated['company_name'];
            $vendor->contact_person_name = $validated['contact_person_name'];
            $vendor->contact_person_email = $validated['contact_person_email'] ?? null;
            $vendor->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $vendor->tax_number = $validated['tax_number'] ?? null;
            $vendor->payment_terms = $validated['payment_terms'] ?? null;
            $vendor->billing_address = $validated['billing_address'];
            $vendor->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : $validated['shipping_address'];
            $vendor->same_as_billing = $validated['same_as_billing'] ?? false;
            $vendor->notes = $validated['notes'] ?? null;
            $vendor->creator_id = Auth::id();
            $vendor->created_by = creatorId();
            $user = DB::transaction(function () use ($vendor, $validated, $portalAccounts) {
                $user = $portalAccounts->create($vendor, 'vendor', $validated, creatorId(), Auth::id());
                $vendor->save();
                return $user;
            });

            CreateVendor::dispatch($request, $vendor);
            $portalAccounts->sendAccessNotifications($user, $validated['password'] ?? null);

            $redirectRoute = ($validated['return_to'] ?? null) === 'project.contractors.index'
                ? 'project.contractors.index'
                : 'account.vendors.index';

            return redirect()->route($redirectRoute)->with('success', __('The vendor has been created successfully.'));
        }
        return redirect()->route('account.vendors.index')->with('error', __('Permission denied'));
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor, PartyPortalAccountService $portalAccounts)
    {
        if(Auth::user()->can('edit-vendors')){
            $validated = $request->validated();

            $vendor->company_name = $validated['company_name'];
            $vendor->contact_person_name = $validated['contact_person_name'];
            $vendor->contact_person_email = $validated['contact_person_email'] ?? null;
            $vendor->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $vendor->tax_number = $validated['tax_number'] ?? null;
            $vendor->payment_terms = $validated['payment_terms'] ?? null;
            $vendor->billing_address = $validated['billing_address'];
            $vendor->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : $validated['shipping_address'];
            $vendor->same_as_billing = $validated['same_as_billing'] ?? false;
            $vendor->notes = $validated['notes'] ?? null;
            $wasEnabled = (bool) $vendor->user?->is_enable_login;
            $user = DB::transaction(function () use ($vendor, $validated, $portalAccounts) {
                $user = $portalAccounts->sync($vendor, 'vendor', $validated, creatorId(), Auth::id());
                $vendor->save();
                return $user;
            });

            UpdateVendor::dispatch($request, $vendor);
            $portalAccounts->sendAccessNotifications($user, $validated['password'] ?? null, $wasEnabled);

            return back()->with('success', __('The vendor details are updated successfully.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    public function destroy(Vendor $vendor, PartyPortalAccountService $portalAccounts)
    {
        if(Auth::user()->can('delete-vendors')){
            if (Schema::hasTable('project_contracts') && $vendor->projectContracts()->exists()) {
                return back()->with('error', __('This vendor cannot be deleted because project contracts are linked to it.'));
            }
            DB::transaction(function () use ($vendor, $portalAccounts) {
                $portalAccounts->disable($vendor);
                DestroyVendor::dispatch($vendor);
                $vendor->delete();
            });
            return back()->with('success', __('The vendor has been deleted.'));
        }
        return back()->with('error', __('Permission denied'));
    }
}
