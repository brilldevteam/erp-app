<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\PartyAttachment;
use Workdo\Account\Models\Vendor;
use Workdo\Account\Services\PartyAttachmentService;
use Workdo\Account\Models\CustomerActivityLog;

class PartyAttachmentController extends Controller
{
    private function checkAccess(Model $party, string $permission): void
    {
        abort_unless(auth()->user()->can($permission), 403);
        abort_unless((int) $party->created_by === (int) creatorId(), 404);

        $resource = $party instanceof Customer ? 'customers' : 'vendors';
        $hasScope = auth()->user()->can("manage-any-{$resource}")
            || (auth()->user()->can("manage-own-{$resource}") && (int) $party->creator_id === (int) auth()->id());
        abort_unless($hasScope, 404);
    }

    private function download(Request $request, Model $party, PartyAttachment $attachment, string $permission)
    {
        $this->checkAccess($party, $permission);
        abort_unless($attachment->attachable_type === $party->getMorphClass() && (int) $attachment->attachable_id === (int) $party->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        $inline = $request->boolean('preview') && in_array($attachment->file_type, ['application/pdf', 'image/jpeg', 'image/png'], true);
        return Storage::disk('local')->response($attachment->file_path, $attachment->file_name, [
            'Content-Type' => $attachment->file_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    private function store(Request $request, Model $party, string $permission, PartyAttachmentService $service)
    {
        $this->checkAccess($party, $permission);
        $request->validate(array_merge($service::rules(), ['attachments' => 'required|array|min:1|max:5']));
        $paths = [];

        try {
            DB::transaction(function () use ($party, $request, $service, &$paths) {
                $locked = $party->newQuery()->whereKey($party->getKey())->lockForUpdate()->firstOrFail();
                $service->store($locked, $request->file('attachments', []), $paths);
            });
        } catch (\Throwable $e) {
            $service->cleanup($paths);
            throw $e;
        }

        return back()->with('success', __('Supporting documents saved.'));
    }

    private function destroy(Model $party, PartyAttachment $attachment, string $permission, PartyAttachmentService $service)
    {
        $this->checkAccess($party, $permission);
        abort_unless($attachment->attachable_type === $party->getMorphClass() && (int) $attachment->attachable_id === (int) $party->id, 404);
        DB::transaction(function () use ($attachment) {
            $attachment->update(['removed_by' => auth()->id()]);
            $attachment->delete();
        });
        $service->cleanup([$attachment->file_path]);
        return back()->with('success', __('Supporting document removed.'));
    }

    public function downloadCustomer(Request $request, Customer $customer, PartyAttachment $attachment)
    {
        return $this->download($request, $customer, $attachment, 'view-customers');
    }

    public function storeCustomer(Request $request, Customer $customer, PartyAttachmentService $service)
    {
        $response = $this->store($request, $customer, 'edit-customers', $service);
        $this->logCustomerFile($customer, 'customer.attachment_added', __('Customer attachment added'), count($request->file('attachments', [])));
        return $response;
    }

    public function destroyCustomer(Customer $customer, PartyAttachment $attachment, PartyAttachmentService $service)
    {
        $fileName = $attachment->file_name;
        $response = $this->destroy($customer, $attachment, 'edit-customers', $service);
        $this->logCustomerFile($customer, 'customer.attachment_removed', __('Customer attachment removed'), 1, $fileName);
        return $response;
    }

    private function logCustomerFile(Customer $customer, string $eventType, string $title, int $count, ?string $fileName = null): void
    {
        CustomerActivityLog::create([
            'customer_id' => $customer->id,
            'actor_id' => auth()->id(),
            'created_by' => $customer->created_by,
            'event_type' => $eventType,
            'subject_type' => $customer->getMorphClass(),
            'subject_id' => $customer->id,
            'title' => $title,
            'metadata' => array_filter(['file_count' => $count, 'file_name' => $fileName]),
            'occurred_at' => now(),
        ]);
    }

    public function downloadVendor(Request $request, Vendor $vendor, PartyAttachment $attachment)
    {
        return $this->download($request, $vendor, $attachment, 'view-vendors');
    }

    public function storeVendor(Request $request, Vendor $vendor, PartyAttachmentService $service)
    {
        return $this->store($request, $vendor, 'edit-vendors', $service);
    }

    public function destroyVendor(Vendor $vendor, PartyAttachment $attachment, PartyAttachmentService $service)
    {
        return $this->destroy($vendor, $attachment, 'edit-vendors', $service);
    }
}
