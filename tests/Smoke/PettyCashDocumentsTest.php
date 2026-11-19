<?php

namespace Tests\Smoke;

use App\Models\User;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Workdo\PettyCashManagement\Models\PettyCash;
use Workdo\PettyCashManagement\Models\PettyCashAttachment;
use Workdo\PettyCashManagement\Models\PettyCashCategory;
use Workdo\PettyCashManagement\Models\PettyCashRequest;

/** Supporting documents on petty cash requests and funds; draft funds can be edited and deleted. */
class PettyCashDocumentsTest extends TestCase
{
    use DatabaseTransactions;

    private User $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->company = User::findOrFail(950);
        $this->actingAs($this->company)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($this->company->security_version ?? 1)),
        ]);
        PettyCash::where('created_by', 950)->delete();
    }

    private function fund(array $values = []): PettyCash
    {
        return PettyCash::create(array_merge([
            'pettycash_number' => 'PC-TEST-' . uniqid(), 'date' => '2026-11-10', 'opening_balance' => 0, 'added_amount' => 500,
            'total_balance' => 500, 'total_expense' => 0, 'closing_balance' => 500, 'status' => 1,
            'creator_id' => 950, 'created_by' => 950,
        ], $values));
    }

    private function category(): PettyCashCategory
    {
        return PettyCashCategory::create(['name' => 'Docs ' . uniqid(), 'creator_id' => 950, 'created_by' => 950]);
    }

    private function pendingRequest(): PettyCashRequest
    {
        return PettyCashRequest::create([
            'user_id' => 950, 'categorie_id' => $this->category()->id, 'requested_amount' => 40, 'status' => 0,
            'remarks' => 'Taxi', 'creator_id' => 950, 'created_by' => 950,
        ]);
    }

    public function test_request_created_with_supporting_documents(): void
    {
        $category = $this->category();

        $this->from(route('petty-cash-management.petty-cash-requests.index'))->post(route('petty-cash-management.petty-cash-requests.store'), [
            'user_id' => 950, 'categorie_id' => $category->id, 'requested_amount' => 55, 'remarks' => 'Receipt attached',
            'attachments' => [UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'), UploadedFile::fake()->image('bill.jpg')],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $request = PettyCashRequest::where('categorie_id', $category->id)->firstOrFail();
        $files = $request->attachments()->get();
        $this->assertCount(2, $files);
        $this->assertEqualsCanonicalizing(['receipt.pdf', 'bill.jpg'], $files->pluck('file_name')->all());
        foreach ($files as $file) {
            Storage::disk('local')->assertExists($file->file_path);
        }

        // The list sends the documents with each request, without the storage path.
        $props = $this->get(route('petty-cash-management.petty-cash-requests.index'), [
            'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ])->assertOk()->json('props');
        $row = collect($props['pettycashrequests']['data'])->firstWhere('id', $request->id);
        $this->assertCount(2, $row['attachments']);
        $this->assertArrayNotHasKey('file_path', $row['attachments'][0]);
    }

    public function test_bad_file_type_is_rejected_and_nothing_is_saved(): void
    {
        $category = $this->category();

        $this->from(route('petty-cash-management.petty-cash-requests.index'))->post(route('petty-cash-management.petty-cash-requests.store'), [
            'user_id' => 950, 'categorie_id' => $category->id, 'requested_amount' => 55, 'remarks' => '',
            'attachments' => [UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')],
        ])->assertSessionHasErrors('attachments.0');

        $this->assertFalse(PettyCashRequest::where('categorie_id', $category->id)->exists());
    }

    public function test_documents_can_be_added_downloaded_and_removed_later(): void
    {
        $request = $this->pendingRequest();

        $this->from('/petty-cash-management/petty-cash-requests')->post(route('petty-cash-management.attachments.store', ['request', $request->id]), [
            'attachments' => [UploadedFile::fake()->create('invoice.pdf', 50, 'application/pdf')],
        ])->assertSessionHas('success');
        $file = $request->attachments()->firstOrFail();

        $this->get(route('petty-cash-management.attachments.download', $file->id))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=invoice.pdf');
        $this->get(route('petty-cash-management.attachments.download', $file->id) . '?preview=1')->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename=invoice.pdf');

        $this->from('/petty-cash-management/petty-cash-requests')->delete(route('petty-cash-management.attachments.destroy', $file->id))
            ->assertSessionHas('success');
        $this->assertSame(0, $request->attachments()->count());
        Storage::disk('local')->assertMissing($file->file_path);
    }

    public function test_no_more_than_five_documents(): void
    {
        $request = $this->pendingRequest();
        $files = fn (int $n) => array_map(fn ($i) => UploadedFile::fake()->create("doc{$i}.pdf", 5, 'application/pdf'), range(1, $n));

        $this->post(route('petty-cash-management.attachments.store', ['request', $request->id]), ['attachments' => $files(4)])->assertSessionHas('success');
        $this->post(route('petty-cash-management.attachments.store', ['request', $request->id]), ['attachments' => $files(2)])->assertSessionHasErrors('attachments');
        $this->assertSame(4, $request->attachments()->count());
    }

    public function test_another_company_cannot_reach_the_documents(): void
    {
        $request = $this->pendingRequest();
        $this->post(route('petty-cash-management.attachments.store', ['request', $request->id]), [
            'attachments' => [UploadedFile::fake()->create('private.pdf', 5, 'application/pdf')],
        ])->assertSessionHas('success');
        $file = $request->attachments()->firstOrFail();

        $other = User::where('type', 'company')->where('id', '!=', 950)->orderBy('id')->firstOrFail();
        $this->actingAs($other)->withSession([AuthSessionService::SESSION_KEY => max(1, (int) ($other->security_version ?? 1))]);
        if (!$other->can('view-petty-cash-requests')) {
            $this->markTestSkipped('Second company has no petty cash access.');
        }

        $this->get(route('petty-cash-management.attachments.download', $file->id))->assertNotFound();
        $this->delete(route('petty-cash-management.attachments.destroy', $file->id))->assertNotFound();
        $this->post(route('petty-cash-management.attachments.store', ['request', $request->id]), [
            'attachments' => [UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')],
        ])->assertNotFound();
        $this->assertSame(1, $request->attachments()->count());
    }

    public function test_fund_created_with_documents_and_draft_fund_can_be_edited_and_deleted_even_if_not_newest(): void
    {
        $this->fund(['date' => '2026-10-06', 'closing_balance' => 300]);

        $this->from(route('petty-cash-management.petty-cashes.index'))->post(route('petty-cash-management.petty-cashes.store'), [
            'date' => '2026-09-30', 'added_amount' => 120, 'remarks' => 'Top up',
            'attachments' => [UploadedFile::fake()->image('bank-slip.png')],
        ])->assertSessionHasNoErrors();

        $draft = PettyCash::where('created_by', 950)->where('date', '2026-09-30')->firstOrFail();
        $this->assertSame('0', (string) $draft->status);
        $this->assertSame(1, $draft->attachments()->count());

        // Older date than the approved fund, so it is not the newest entry; it is still a draft and can be changed.
        $this->from(route('petty-cash-management.petty-cashes.index'))->put(route('petty-cash-management.petty-cashes.update', $draft->id), [
            'date' => '2026-09-30', 'added_amount' => 150, 'remarks' => 'Top up corrected',
        ])->assertSessionHas('success');
        $this->assertEqualsWithDelta(150, (float) $draft->fresh()->added_amount, 0.001);

        $path = $draft->attachments()->first()->file_path;
        $this->from(route('petty-cash-management.petty-cashes.index'))->delete(route('petty-cash-management.petty-cashes.destroy', $draft->id))
            ->assertSessionHas('success');
        $this->assertNull(PettyCash::find($draft->id));
        $this->assertSame(0, PettyCashAttachment::where('attachable_type', 'fund')->where('attachable_id', $draft->id)->count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_approved_fund_and_approved_request_cannot_be_edited_or_deleted(): void
    {
        $fund = $this->fund(['added_amount' => 500]);

        $this->from(route('petty-cash-management.petty-cashes.index'))->put(route('petty-cash-management.petty-cashes.update', $fund->id), [
            'date' => '2026-11-10', 'added_amount' => 900, 'remarks' => '',
        ])->assertSessionHas('error');
        $this->from(route('petty-cash-management.petty-cashes.index'))->delete(route('petty-cash-management.petty-cashes.destroy', $fund->id))
            ->assertSessionHas('error');
        $this->assertEqualsWithDelta(500, (float) $fund->fresh()->added_amount, 0.001);

        $request = $this->pendingRequest();
        $request->update(['status' => '1', 'approved_amount' => 40]);
        $this->from(route('petty-cash-management.petty-cash-requests.index'))->put(route('petty-cash-management.petty-cash-requests.update', $request->id), [
            'user_id' => 950, 'categorie_id' => $request->categorie_id, 'requested_amount' => 99, 'remarks' => 'changed',
        ])->assertSessionHas('error');
        $this->assertSame('1', (string) $request->fresh()->status);
        $this->assertEqualsWithDelta(40, (float) $request->fresh()->requested_amount, 0.001);
    }

    public function test_pending_request_can_still_be_edited_and_deleted(): void
    {
        $request = $this->pendingRequest();
        $request->attachments()->create([
            'attachable_type' => 'request', 'file_name' => 'a.pdf', 'file_path' => 'petty-cash/950/a.pdf',
            'file_type' => 'application/pdf', 'file_size' => 10, 'uploaded_by' => 950, 'created_by' => 950,
        ]);

        $this->from(route('petty-cash-management.petty-cash-requests.index'))->put(route('petty-cash-management.petty-cash-requests.update', $request->id), [
            'user_id' => 950, 'categorie_id' => $request->categorie_id, 'requested_amount' => 65, 'remarks' => 'More',
        ])->assertSessionHas('success');
        $this->assertEqualsWithDelta(65, (float) $request->fresh()->requested_amount, 0.001);

        $this->from(route('petty-cash-management.petty-cash-requests.index'))->delete(route('petty-cash-management.petty-cash-requests.destroy', $request->id))
            ->assertSessionHas('success');
        $this->assertNull(PettyCashRequest::find($request->id));
        $this->assertSame(0, PettyCashAttachment::where('attachable_type', 'request')->where('attachable_id', $request->id)->count());
    }
}
