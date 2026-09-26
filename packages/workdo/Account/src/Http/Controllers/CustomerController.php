<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Workdo\Account\Services\PartyPortalAccountService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\Customer;
use Workdo\Account\Http\Requests\StoreCustomerRequest;
use Workdo\Account\Http\Requests\UpdateCustomerRequest;
use Workdo\Account\Events\CreateCustomer;
use Workdo\Account\Events\UpdateCustomer;
use Workdo\Account\Events\DestroyCustomer;

class CustomerController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-customers')){
            $customers = Customer::query()
                ->with('user:id,name,email,mobile_no,avatar,is_disable,is_enable_login')
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-customers')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-customers')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('company_name'), fn($q) => $q->where('company_name', 'like', '%' . request('company_name') . '%'))
                ->when(request('customer_code'), fn($q) => $q->where('customer_code', 'like', '%' . request('customer_code') . '%'))
                ->when(request('tax_number'), fn($q) => $q->where('tax_number', 'like', '%' . request('tax_number') . '%'))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            return Inertia::render('Account/Customers/Index', [
                'customers' => $customers,
                'editCustomer' => request('edit') ? Customer::with('user:id,is_enable_login,is_disable')->where('created_by', creatorId())->find(request('edit')) : null,
            ]);
        }
        return back()->with('error', __('Permission denied'));
    }

    public function store(StoreCustomerRequest $request, PartyPortalAccountService $portalAccounts)
    {
        if(Auth::user()->can('create-customers')){
            $validated = $request->validated();

            $customer = new Customer();
            $customer->company_name = $validated['company_name'];
            $customer->contact_person_name = $validated['contact_person_name'];
            $customer->contact_person_email = $validated['contact_person_email'] ?? null;
            $customer->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $customer->tax_number = $validated['tax_number'] ?? null;
            $customer->payment_terms = $validated['payment_terms'] ?? null;
            $customer->billing_address = $validated['billing_address'];
            $customer->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : $validated['shipping_address'];
            $customer->same_as_billing = $validated['same_as_billing'] ?? false;
            $customer->notes = $validated['notes'] ?? null;
            $customer->creator_id = Auth::id();
            $customer->created_by = creatorId();
            $user = DB::transaction(function () use ($customer, $validated, $portalAccounts) {
                $user = $portalAccounts->create($customer, 'client', $validated, creatorId(), Auth::id());
                $customer->save();
                return $user;
            });

            CreateCustomer::dispatch($request, $customer);
            $portalAccounts->sendAccessNotifications($user, $validated['password'] ?? null);

            if ($request->query('return_to') === 'quotation') {
                return back()->with('success', __('The customer has been created successfully.'))->with('createdCustomerUserId', $customer->user_id);
            }

            return redirect()->route('account.customers.index')->with('success', __('The customer has been created successfully.'));
        }
        return redirect()->route('account.customers.index')->with('error', __('Permission denied'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, PartyPortalAccountService $portalAccounts)
    {
        if(Auth::user()->can('edit-customers')){
            $validated = $request->validated();

            $customer->company_name = $validated['company_name'];
            $customer->contact_person_name = $validated['contact_person_name'];
            $customer->contact_person_email = $validated['contact_person_email'] ?? null;
            $customer->contact_person_mobile = $validated['contact_person_mobile'] ?? null;
            $customer->tax_number = $validated['tax_number'] ?? null;
            $customer->payment_terms = $validated['payment_terms'] ?? null;
            $customer->billing_address = $validated['billing_address'];
            $customer->shipping_address = $validated['same_as_billing'] ? $validated['billing_address'] : ($validated['shipping_address'] ?? null);
            $customer->same_as_billing = $validated['same_as_billing'] ?? false;
            $customer->notes = $validated['notes'] ?? null;
            $wasEnabled = (bool) $customer->user?->is_enable_login;
            $user = DB::transaction(function () use ($customer, $validated, $portalAccounts) {
                $user = $portalAccounts->sync($customer, 'client', $validated, creatorId(), Auth::id());
                $customer->save();
                return $user;
            });

            UpdateCustomer::dispatch($request, $customer);
            $portalAccounts->sendAccessNotifications($user, $validated['password'] ?? null, $wasEnabled);

            return back()->with('success', __('The customer details are updated successfully.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    public function destroy(Customer $customer, PartyPortalAccountService $portalAccounts)
    {
        if(Auth::user()->can('delete-customers')){
            DB::transaction(function () use ($customer, $portalAccounts) {
                $portalAccounts->disable($customer);
                DestroyCustomer::dispatch($customer);
                $customer->delete();
            });
            return back()->with('success', __('The customer has been deleted.'));
        }
        return back()->with('error', __('Permission denied'));
    }
}
