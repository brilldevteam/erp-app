<?php

namespace Tests\Smoke;

use App\Mail\CommonEmailTemplate;
use App\Models\HelpdeskAttachment;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskReply;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskPopupNotification;
use App\Models\HelpdeskEmailDelivery;
use App\Models\Notification;
use App\Models\User;
use App\Services\AuthSessionService;
use App\Services\HelpdeskNotificationService;
use App\Services\HelpdeskEmailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Runs through the real routes on the local copy with `phpunit -c phpunit.smoke.xml`. Everything is rolled back.
 * Companies come from SMOKE_USER_ID style local data, so the test skips itself when they are missing.
 */
class HelpdeskSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $company;
    private User $otherCompany;
    private HelpdeskTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $admin = User::where('type', 'superadmin')->first();
        $companies = User::where('type', 'company')->whereIn('id', [950, 664])->orderByDesc('id')->get();
        if (!$admin || $companies->count() < 2) {
            $this->markTestSkipped('Needs the local copy with companies 950 and 664.');
        }

        [$this->admin, $this->company, $this->otherCompany] = [$admin, $companies[0], $companies[1]];
        $this->ticket = HelpdeskTicket::create(['title' => 'Check <b>title</b>', 'description' => 'Body', 'status' => 'open', 'priority' => 'low', 'created_by' => $this->company->id]);
        Storage::fake('local');
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([AuthSessionService::SESSION_KEY => max(1, (int) ($user->security_version ?? 1))]);
    }

    private function reply(User $user, array $data = [], ?HelpdeskTicket $ticket = null)
    {
        return $this->as($user)->post(route('helpdesk-replies.store', ($ticket ?? $this->ticket)->id), $data + ['message' => 'Hello'], ['Accept' => 'application/json']);
    }

    private function note(): HelpdeskReply
    {
        return HelpdeskReply::create(['ticket_id' => $this->ticket->id, 'message' => 'SECRET NOTE', 'is_internal' => true, 'created_by' => $this->admin->id]);
    }

    private function turnRepliesEmailOn(): void
    {
        foreach (['Helpdesk Ticket Reply' => 'on', 'email_fromAddress' => 'support@example.test', 'email_host' => 'localhost'] as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key, 'created_by' => $this->admin->id], ['value' => $value, 'is_public' => 0]);
        }
        cache()->flush();
    }

    public function test_superadmin_helpdesk_notification_settings_persist_and_replace_the_legacy_switches(): void
    {
        if (!$this->admin->can('manage-email-notification-settings')) {
            $this->markTestSkipped('The email notification settings permission is not available locally.');
        }

        $events = array_fill_keys(array_keys(\App\Services\HelpdeskNotificationSettings::EVENTS), 'off');
        $this->as($this->admin)->post(route('settings.helpdesk-notifications.update'), ['settings' => [
            'support_email' => 'Support@Example.test, help@example.test',
            'ticket_created_support' => 'on',
        ] + $events + ['ticket_created_support' => 'on']])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'helpdesk_support_email', 'value' => 'support@example.test,help@example.test', 'created_by' => $this->admin->id, 'is_public' => 0]);
        $this->assertDatabaseHas('settings', ['key' => 'helpdesk_mail_ticket_created_support', 'value' => 'on', 'created_by' => $this->admin->id, 'is_public' => 0]);

        $props = $this->as($this->admin)->get(route('settings.index'))->assertOk()->viewData('page')['props'];
        $this->assertSame('support@example.test, help@example.test', $props['helpdeskNotificationSettings']['support_email']);
        $this->assertSame('on', $props['helpdeskNotificationSettings']['events']['ticket_created_support']);
        $this->assertSame('off', $props['helpdeskNotificationSettings']['events']['reply_to_customer']);

        // The legacy switches no longer appear in, or save through, Email Notification Settings.
        $listed = collect($props['notifications'])->flatten(1)->pluck('action')->all();
        $this->assertNotContains('Helpdesk Ticket', $listed);
        $this->assertNotContains('Helpdesk Ticket Reply', $listed);
        $this->as($this->admin)->post(route('email.notification.setting.store'), ['mail_noti' => ['Helpdesk Ticket' => 'on']])->assertStatus(422);

        // Invalid addresses and company users are refused.
        $this->as($this->admin)->post(route('settings.helpdesk-notifications.update'), ['settings' => ['support_email' => 'not-an-email'] + $events])
            ->assertSessionHasErrors('settings.support_email');
        $this->as($this->company)->post(route('settings.helpdesk-notifications.update'), ['settings' => ['support_email' => ''] + $events])
            ->assertForbidden();
    }

    public function test_helpdesk_email_switch_controls_delivery_queueing(): void
    {
        Queue::fake();
        $ticket = $this->ticket->fresh(['creator', 'category']);

        DB::table('settings')->updateOrInsert(
            ['key' => 'Helpdesk Ticket', 'created_by' => $this->admin->id],
            ['value' => 'off', 'is_public' => 0]
        );
        cache()->flush();
        app(HelpdeskEmailService::class)->ticketCreated($ticket);
        $this->assertSame(0, HelpdeskEmailDelivery::where('delivery_key', "ticket-created:{$ticket->id}")->count());

        DB::table('settings')->where('key', 'Helpdesk Ticket')->where('created_by', $this->admin->id)->update(['value' => 'on']);
        cache()->flush();
        app(HelpdeskEmailService::class)->ticketCreated($ticket);
        $this->assertSame(1, HelpdeskEmailDelivery::where('delivery_key', "ticket-created:{$ticket->id}")->count());
        Queue::assertNothingPushed();
    }

    public function test_internal_notes_never_reach_a_company_page(): void
    {
        $this->note();

        foreach ([route('helpdesk-tickets.show', $this->ticket->id), route('helpdesk-tickets.today')] as $url) {
            $response = $this->as($this->company)->get($url);
            $response->assertOk();
            $this->assertStringNotContainsString('SECRET NOTE', json_encode($response->viewData('page')['props']), $url);
        }

        $admin = $this->as($this->admin)->get(route('helpdesk-tickets.show', $this->ticket->id));
        $this->assertStringContainsString('SECRET NOTE', json_encode($admin->viewData('page')['props']));
    }

    public function test_internal_note_sends_no_email_but_a_public_reply_does(): void
    {
        $this->turnRepliesEmailOn();
        Mail::fake();
        // Only this test's emails: older pending deliveries in the database would be retried too.
        HelpdeskEmailDelivery::where('status', 'pending')->update(['status' => 'failed']);

        $this->reply($this->admin, ['is_internal' => 1])->assertOk();
        Mail::assertNothingOutgoing();

        $this->reply($this->admin)->assertOk();
        Mail::assertSent(CommonEmailTemplate::class, fn ($mail) => !str_contains($mail->template->content, '<b>title</b>') && str_contains($mail->template->content, 'title'));
        $reply = HelpdeskReply::where('ticket_id', $this->ticket->id)->where('is_internal', false)->latest('id')->firstOrFail();
        app(HelpdeskEmailService::class)->replied($this->ticket->fresh(['requester', 'assignedAgent', 'creator', 'category']), $reply, $this->admin);
        Mail::assertSent(CommonEmailTemplate::class, 1);
        $this->assertSame(1, HelpdeskEmailDelivery::where('delivery_key', "ticket-reply:{$reply->id}")->count());
    }

    public function test_a_company_cannot_create_an_internal_note(): void
    {
        $this->reply($this->company, ['is_internal' => 1])->assertOk();

        $this->assertFalse((bool) HelpdeskReply::where('ticket_id', $this->ticket->id)->latest('id')->value('is_internal'));
    }

    public function test_replies_to_closed_or_foreign_tickets_are_refused(): void
    {
        $this->reply($this->otherCompany)->assertNotFound();

        $this->ticket->update(['status' => 'closed']);
        $this->reply($this->company)->assertStatus(422);
        $this->assertSame(0, HelpdeskReply::where('ticket_id', $this->ticket->id)->count());
    }

    public function test_a_mail_failure_still_returns_success_and_keeps_one_reply(): void
    {
        $this->turnRepliesEmailOn();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->reply($this->company)->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, HelpdeskReply::where('ticket_id', $this->ticket->id)->count());
        $delivery = HelpdeskEmailDelivery::where('ticket_id', $this->ticket->id)->firstOrFail();
        // Kept pending so it is retried with the next Helpdesk email; failed only after the last try.
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame('SMTP down', $delivery->last_error);
    }

    public function test_bad_list_parameters_are_ignored(): void
    {
        $tickets = $this->as($this->admin)->get(route('helpdesk-tickets.index', ['sort' => 'id;drop', 'direction' => 'x', 'per_page' => 99999]));

        $tickets->assertOk();
        $this->assertSame(10, $tickets->viewData('page')['props']['tickets']['per_page']);
    }

    public function test_attachments_are_private_and_follow_the_ticket(): void
    {
        $this->reply($this->company, ['files' => [UploadedFile::fake()->create('report.pdf', 20, 'application/pdf')]])->assertOk();
        $attachment = HelpdeskAttachment::where('ticket_id', $this->ticket->id)->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringStartsWith('helpdesk/', $attachment->path);

        $this->as($this->company)->get(route('helpdesk-attachments.show', $attachment->id))->assertOk();
        $this->as($this->admin)->get(route('helpdesk-attachments.show', $attachment->id))->assertOk();
        $this->as($this->otherCompany)->get(route('helpdesk-attachments.show', $attachment->id))->assertNotFound();

        auth()->logout();
        $this->flushSession();
        $this->get(route('helpdesk-attachments.show', $attachment->id))->assertRedirect();
    }

    public function test_files_on_internal_notes_stay_hidden_from_the_company(): void
    {
        $this->reply($this->admin, ['is_internal' => 1, 'files' => [UploadedFile::fake()->create('note.pdf', 20, 'application/pdf')]])->assertOk();
        $attachment = HelpdeskAttachment::where('ticket_id', $this->ticket->id)->firstOrFail();

        $this->as($this->company)->get(route('helpdesk-attachments.show', $attachment->id))->assertNotFound();
        $this->as($this->admin)->get(route('helpdesk-attachments.show', $attachment->id))->assertOk();
    }

    public function test_unsafe_files_and_old_style_attachment_strings_are_not_accepted(): void
    {
        $this->reply($this->company, ['files' => [UploadedFile::fake()->create('shell.php', 5, 'application/x-php')]])->assertStatus(422);

        $this->reply($this->company, ['attachments' => ['someone-elses-file.pdf']])->assertOk();
        $this->assertNull(HelpdeskReply::where('ticket_id', $this->ticket->id)->latest('id')->value('attachments'));
    }

    public function test_deleting_a_reply_removes_its_stored_file(): void
    {
        $this->reply($this->admin, ['files' => [UploadedFile::fake()->create('old.pdf', 20, 'application/pdf')]])->assertOk();
        $attachment = HelpdeskAttachment::where('ticket_id', $this->ticket->id)->firstOrFail();

        $this->as($this->admin)->deleteJson(route('helpdesk-replies.destroy', $attachment->reply_id))->assertOk();

        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_customers_only_see_their_own_tickets_while_the_company_sees_both(): void
    {
        $permissions = ['manage-helpdesk-tickets', 'manage-own-helpdesk-tickets', 'view-helpdesk-tickets', 'create-helpdesk-replies'];
        $first = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $second = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $first->givePermissionTo($permissions);
        $second->givePermissionTo($permissions);

        $firstTicket = HelpdeskTicket::create(['title' => 'First requester', 'description' => 'Body', 'status' => 'open', 'priority' => 'low', 'created_by' => $this->company->id, 'requester_user_id' => $first->id]);
        $secondTicket = HelpdeskTicket::create(['title' => 'Second requester', 'description' => 'Body', 'status' => 'open', 'priority' => 'low', 'created_by' => $this->company->id, 'requester_user_id' => $second->id]);

        $this->as($first)->get(route('helpdesk-tickets.show', $firstTicket))->assertOk();
        $this->as($first)->get(route('helpdesk-tickets.show', $secondTicket))->assertRedirect(route('helpdesk-tickets.index'));
        $this->reply($first, [], $secondTicket)->assertNotFound();

        $customerIds = collect($this->as($first)->get(route('helpdesk-tickets.index'))->viewData('page')['props']['tickets']['data'])->pluck('id');
        $this->assertTrue($customerIds->contains($firstTicket->id));
        $this->assertFalse($customerIds->contains($secondTicket->id));

        $companyIds = collect($this->as($this->company)->get(route('helpdesk-tickets.index'))->viewData('page')['props']['tickets']['data'])->pluck('id');
        $this->assertTrue($companyIds->contains($firstTicket->id));
        $this->assertTrue($companyIds->contains($secondTicket->id));
        $this->as($this->otherCompany)->get(route('helpdesk-tickets.show', $firstTicket))->assertNotFound();
    }

    public function test_customer_reply_reopens_a_resolved_ticket_and_records_lifecycle_dates(): void
    {
        $customer = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $customer->givePermissionTo(['view-helpdesk-tickets', 'create-helpdesk-replies']);
        $ticket = HelpdeskTicket::create([
            'title' => 'Resolved request',
            'description' => 'Body',
            'status' => 'resolved',
            'priority' => 'low',
            'created_by' => $this->company->id,
            'requester_user_id' => $customer->id,
            'resolved_at' => now()->subHour(),
        ]);

        $this->reply($customer, [], $ticket)->assertOk();

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNotNull($ticket->last_reply_at);
        $this->assertDatabaseHas('helpdesk_status_histories', ['ticket_id' => $ticket->id, 'from_status' => 'resolved', 'to_status' => 'open', 'changed_by' => $customer->id]);

        $this->reply($this->admin, [], $ticket)->assertOk();
        $this->assertNotNull($ticket->fresh()->first_response_at);
    }

    public function test_deleting_a_requester_keeps_ticket_and_reply_history(): void
    {
        $customer = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $ticket = HelpdeskTicket::create(['title' => 'Keep history', 'description' => 'Body', 'status' => 'open', 'priority' => 'low', 'created_by' => $this->company->id, 'requester_user_id' => $customer->id]);
        $reply = HelpdeskReply::create(['ticket_id' => $ticket->id, 'message' => 'Historical reply', 'created_by' => $customer->id, 'sender_name' => $customer->name]);
        $name = $customer->name;

        $customer->delete();

        $this->assertDatabaseHas('helpdesk_tickets', ['id' => $ticket->id, 'requester_user_id' => null]);
        $this->assertDatabaseHas('helpdesk_replies', ['id' => $reply->id, 'created_by' => null, 'sender_name' => $name]);
        $this->assertSame($name, $reply->fresh()->sender_display_name);
    }

    public function test_status_changes_are_recorded_and_company_with_tickets_cannot_be_deleted(): void
    {
        $category = HelpdeskCategory::where('is_active', true)->first();
        if (!$category) {
            $this->markTestSkipped('Needs one active Helpdesk category in the local copy.');
        }

        $ticket = HelpdeskTicket::create(['title' => 'Status history', 'description' => 'Body', 'status' => 'open', 'priority' => 'low', 'category_id' => $category->id, 'created_by' => $this->company->id, 'requester_user_id' => $this->company->id]);
        $this->as($this->admin)->put(route('helpdesk-tickets.update', $ticket), [
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => 'waiting_customer',
            'priority' => $ticket->priority,
            'category_id' => $category->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('helpdesk_status_histories', ['ticket_id' => $ticket->id, 'from_status' => 'open', 'to_status' => 'waiting_customer', 'changed_by' => $this->admin->id]);

        $response = $this->as($this->admin)->delete(route('users.destroy', $this->company));
        $response->assertSessionHas('error', 'This company has support tickets and cannot be deleted. The ticket history must be preserved.');
        $this->assertDatabaseHas('users', ['id' => $this->company->id]);
    }

    public function test_support_users_can_change_status_inline_but_company_users_cannot(): void
    {
        $ticket = HelpdeskTicket::create([
            'title' => 'Inline status',
            'description' => 'Body',
            'status' => 'open',
            'priority' => 'low',
            'created_by' => $this->company->id,
            'requester_user_id' => $this->company->id,
        ]);

        $this->as($this->admin)
            ->patch(route('helpdesk-tickets.status', $ticket), ['status' => 'resolved'])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertDatabaseHas('helpdesk_status_histories', [
            'ticket_id' => $ticket->id,
            'from_status' => 'open',
            'to_status' => 'resolved',
            'changed_by' => $this->admin->id,
        ]);

        $this->as($this->company)
            ->patch(route('helpdesk-tickets.status', $ticket), ['status' => 'closed'])
            ->assertForbidden();
        $this->assertSame('resolved', $ticket->fresh()->status);

        $agent = User::factory()->create(['type' => 'staff', 'created_by' => $this->admin->id, 'is_enable_login' => 1]);
        $agent->assignRole('support-agent');
        $ticket->update(['assigned_agent_id' => $agent->id]);

        $this->as($agent)
            ->patch(route('helpdesk-tickets.status', $ticket), ['status' => 'in_progress'])
            ->assertRedirect();
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_client_requester_can_change_own_ticket_status_but_another_client_cannot(): void
    {
        $requester = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $otherClient = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $permissions = ['manage-helpdesk-tickets', 'view-helpdesk-tickets'];
        $requester->givePermissionTo($permissions);
        $otherClient->givePermissionTo($permissions);
        $ticket = HelpdeskTicket::create([
            'title' => 'Client status',
            'description' => 'Body',
            'status' => 'open',
            'priority' => 'low',
            'created_by' => $this->company->id,
            'requester_user_id' => $requester->id,
        ]);

        $page = $this->as($requester)->get(route('helpdesk-tickets.show', $ticket));
        $page->assertOk();
        $this->assertTrue($page->viewData('page')['props']['canChangeStatus']);

        $this->as($requester)
            ->patch(route('helpdesk-tickets.status', $ticket), ['status' => 'in_progress'])
            ->assertRedirect();
        $this->assertSame('in_progress', $ticket->fresh()->status);

        $this->as($otherClient)
            ->patch(route('helpdesk-tickets.status', $ticket), ['status' => 'resolved'])
            ->assertForbidden();
        $this->assertSame('in_progress', $ticket->fresh()->status);
    }

    public function test_support_agent_only_reaches_assigned_tickets_and_loses_access_after_reassignment(): void
    {
        $firstAgent = User::factory()->create(['type' => 'staff', 'created_by' => $this->admin->id, 'is_enable_login' => 1]);
        $secondAgent = User::factory()->create(['type' => 'staff', 'created_by' => $this->admin->id, 'is_enable_login' => 1]);
        $firstAgent->assignRole('support-agent');
        $secondAgent->assignRole('support-agent');
        $ticket = HelpdeskTicket::create(['title' => 'Assigned only', 'description' => 'Body', 'status' => 'open', 'priority' => 'low', 'created_by' => $this->company->id, 'requester_user_id' => $this->company->id, 'assigned_agent_id' => $firstAgent->id]);

        $this->assertTrue($firstAgent->can('view-helpdesk-tickets'));
        $this->assertTrue($ticket->isVisibleTo($firstAgent));
        $this->assertTrue(Gate::forUser($firstAgent)->allows('view', $ticket));
        $this->as($firstAgent)->get(route('helpdesk-tickets.show', $ticket))->assertOk();
        $this->reply($firstAgent, ['is_internal' => 1], $ticket)->assertOk();
        $this->assertTrue((bool) HelpdeskReply::where('ticket_id', $ticket->id)->latest('id')->value('is_internal'));
        $this->assertFalse($firstAgent->can('manage-any-users'));
        $this->assertFalse($firstAgent->can('impersonate-users'));
        $this->assertFalse($firstAgent->can('manage-sales-invoices'));

        $this->as($this->admin)->put(route('helpdesk-tickets.assign', $ticket), ['assigned_agent_id' => $secondAgent->id])->assertRedirect();
        $this->assertDatabaseHas('helpdesk_assignment_histories', ['ticket_id' => $ticket->id, 'from_agent_id' => $firstAgent->id, 'to_agent_id' => $secondAgent->id, 'changed_by' => $this->admin->id]);

        $this->as($firstAgent)->get(route('helpdesk-tickets.show', $ticket))->assertRedirect(route('helpdesk-tickets.index'));
        $this->reply($firstAgent, [], $ticket)->assertNotFound();
        $this->as($secondAgent)->get(route('helpdesk-tickets.show', $ticket))->assertOk();
    }

    public function test_ticket_popups_use_the_correct_customer_and_company_wording(): void
    {
        $customer = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $service = app(HelpdeskNotificationService::class);
        $customerTicket = HelpdeskTicket::create(['title' => 'Customer request', 'description' => 'Body', 'status' => 'open', 'priority' => 'low', 'created_by' => $this->company->id, 'requester_user_id' => $customer->id]);
        $companyTicket = HelpdeskTicket::create(['title' => 'Company request', 'description' => 'Body', 'status' => 'open', 'priority' => 'low', 'created_by' => $this->company->id, 'requester_user_id' => $this->company->id]);

        $service->ticketCreated($customerTicket);
        $service->ticketCreated($companyTicket);

        $this->assertDatabaseHas('helpdesk_popup_notifications', [
            'user_id' => $this->admin->id,
            'ticket_id' => $customerTicket->id,
            'message' => "New support ticket from {$customer->name} under {$this->company->name}",
        ]);
        $this->assertDatabaseHas('helpdesk_popup_notifications', [
            'user_id' => $this->admin->id,
            'ticket_id' => $companyTicket->id,
            'message' => "New support ticket from {$this->company->name}",
        ]);
    }

    public function test_assignment_reply_and_resolution_popups_reach_only_the_intended_user(): void
    {
        $customer = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $agent = User::factory()->create(['type' => 'staff', 'created_by' => $this->admin->id, 'is_enable_login' => 1]);
        $agent->assignRole('support-agent');
        $ticket = HelpdeskTicket::create(['title' => 'Notify flow', 'description' => 'Body', 'status' => 'open', 'priority' => 'low', 'created_by' => $this->company->id, 'requester_user_id' => $customer->id, 'assigned_agent_id' => $agent->id]);
        $service = app(HelpdeskNotificationService::class);

        $service->assigned($ticket, $agent);
        $service->replied($ticket->fresh(['requester', 'assignedAgent']), $agent);
        $service->replied($ticket->fresh(['requester', 'assignedAgent']), $customer);
        $service->resolved($ticket->fresh('requester'));

        $this->assertDatabaseHas('helpdesk_popup_notifications', ['user_id' => $agent->id, 'ticket_id' => $ticket->id, 'event' => 'ticket_assigned', 'message' => "Support ticket from {$customer->name} under {$this->company->name} assigned to you"]);
        $this->assertDatabaseHas('helpdesk_popup_notifications', ['user_id' => $customer->id, 'ticket_id' => $ticket->id, 'event' => 'agent_reply']);
        $this->assertDatabaseHas('helpdesk_popup_notifications', ['user_id' => $agent->id, 'ticket_id' => $ticket->id, 'event' => 'requester_reply']);
        $this->assertDatabaseHas('helpdesk_popup_notifications', ['user_id' => $customer->id, 'ticket_id' => $ticket->id, 'event' => 'ticket_resolved', 'message' => "Your support ticket {$ticket->ticket_id} has been resolved"]);
        $this->assertSame(4, HelpdeskPopupNotification::where('ticket_id', $ticket->id)->count());
    }

    public function test_popups_persist_until_viewed_or_dismissed_and_cannot_be_acknowledged_by_someone_else(): void
    {
        $first = HelpdeskPopupNotification::create(['user_id' => $this->company->id, 'ticket_id' => $this->ticket->id, 'event' => 'agent_reply', 'message' => 'First']);
        $second = HelpdeskPopupNotification::create(['user_id' => $this->company->id, 'ticket_id' => $this->ticket->id, 'event' => 'agent_reply', 'message' => 'Second']);

        $this->as($this->company)->get(route('helpdesk-popup-notifications.index'))
            ->assertOk()->assertJsonCount(2, 'notifications');
        $this->as($this->otherCompany)->patchJson(route('helpdesk-popup-notifications.dismiss', $first))->assertNotFound();
        $this->as($this->company)->patchJson(route('helpdesk-popup-notifications.dismiss', $first))->assertOk();
        $this->as($this->company)->patchJson(route('helpdesk-popup-notifications.viewed', $second))->assertOk();
        $this->as($this->company)->get(route('helpdesk-popup-notifications.index'))
            ->assertOk()->assertJsonCount(0, 'notifications');
        $this->assertNotNull($first->fresh()->dismissed_at);
        $this->assertNotNull($second->fresh()->viewed_at);
    }

    private function records(User $user, array $query = []): array
    {
        $user->givePermissionTo('view-helpdesk-records');
        $response = $this->as($user)->get(route('helpdesk-tickets.records', $query + ['search' => 'RecChk', 'per_page' => 100]));
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    private function recordTicket(array $values): HelpdeskTicket
    {
        return HelpdeskTicket::create($values + ['description' => 'Body', 'status' => 'open', 'priority' => 'low', 'created_by' => $this->company->id]);
    }

    public function test_ticket_records_follow_each_role_scope(): void
    {
        $first = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $second = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $first->givePermissionTo(['manage-helpdesk-tickets', 'view-helpdesk-tickets']);
        $agent = User::factory()->create(['type' => 'staff', 'created_by' => $this->admin->id, 'is_enable_login' => 1]);
        $agent->assignRole('support-agent');

        $mine = $this->recordTicket(['title' => 'RecChk first', 'requester_user_id' => $first->id, 'assigned_agent_id' => $agent->id]);
        $theirs = $this->recordTicket(['title' => 'RecChk second', 'requester_user_id' => $second->id]);
        $foreign = $this->recordTicket(['title' => 'RecChk foreign', 'created_by' => $this->otherCompany->id]);

        $ids = fn (array $props) => collect($props['records']['data'])->pluck('id')->sort()->values()->all();

        $this->assertSame([$mine->id], $ids($this->records($first)));
        $this->assertSame(collect([$mine->id, $theirs->id])->sort()->values()->all(), $ids($this->records($this->company)));
        $this->assertSame([$mine->id], $ids($this->records($agent)));
        $this->assertSame(collect([$mine->id, $theirs->id, $foreign->id])->sort()->values()->all(), $ids($this->records($this->admin)));

        $customer = $this->records($first);
        $this->assertFalse($customer['supportSide']);
        $this->assertArrayNotHasKey('by_company', $customer['summary']);
        $this->assertSame(1, $customer['summary']['total']);
        $this->assertSame([], $customer['companies']);
    }

    public function test_ticket_records_require_their_own_permission(): void
    {
        $customer = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $customer->givePermissionTo(['manage-helpdesk-tickets', 'view-helpdesk-tickets', 'view-helpdesk-records']);
        $this->as($customer)->get(route('helpdesk-tickets.records'))->assertOk();

        $customer->revokePermissionTo('view-helpdesk-records');
        $this->as($customer)->get(route('helpdesk-tickets.records'))->assertForbidden();
    }

    public function test_ticket_records_keep_closed_and_old_tickets_and_the_summary_matches_the_list(): void
    {
        $this->recordTicket(['title' => 'RecChk legacy closed', 'status' => 'closed', 'closed_at' => now()]);
        $this->recordTicket(['title' => 'RecChk legacy resolved', 'status' => 'resolved', 'resolved_at' => now()]);
        $this->recordTicket(['title' => 'RecChk open', 'priority' => 'urgent']);

        $all = $this->records($this->admin);
        $this->assertSame(3, $all['records']['total']);
        $this->assertSame(3, $all['summary']['total']);
        $this->assertSame(1, $all['summary']['by_status']['closed']);
        $this->assertNull(collect($all['records']['data'])->firstWhere('title', 'RecChk legacy closed')['requester']);

        foreach ([['status' => 'closed'], ['priority' => 'urgent'], ['company_id' => $this->otherCompany->id]] as $filter) {
            $props = $this->records($this->admin, $filter);
            $this->assertSame($props['records']['total'], $props['summary']['total'], json_encode($filter));
        }
        $this->assertSame(0, $this->records($this->admin, ['company_id' => $this->otherCompany->id])['summary']['total']);
    }

    public function test_ticket_record_averages_ignore_tickets_without_a_response_or_resolution(): void
    {
        $created = now()->subHours(5)->startOfMinute();
        $this->recordTicket(['title' => 'RecChk answered', 'first_response_at' => $created->copy()->addHours(2), 'resolved_at' => $created->copy()->addHours(4), 'status' => 'resolved'])
            ->forceFill(['created_at' => $created])->saveQuietly();
        $this->recordTicket(['title' => 'RecChk waiting']);

        $summary = $this->records($this->admin)['summary'];

        $this->assertSame(7200, $summary['avg_first_response_seconds']);
        $this->assertSame(14400, $summary['avg_resolution_seconds']);
        $this->assertSame(2, $summary['total']);
    }

    public function test_ticket_records_ignore_bad_parameters(): void
    {
        $this->recordTicket(['title' => 'RecChk bad params']);

        $props = $this->records($this->admin, ['sort' => 'id;drop', 'direction' => 'x', 'per_page' => 99999, 'status' => 'nope', 'date_from' => '2026-99', 'category_id' => '1 or 1']);

        $this->assertSame(10, $props['records']['per_page']);
        $this->assertSame(1, $props['records']['total']);
    }

    public function test_ticket_history_is_shown_and_assignment_history_stays_with_the_support_side(): void
    {
        $customer = User::factory()->create(['type' => 'client', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $customer->givePermissionTo(['view-helpdesk-tickets']);
        $agent = User::factory()->create(['type' => 'staff', 'created_by' => $this->admin->id, 'is_enable_login' => 1]);
        $agent->assignRole('support-agent');
        $ticket = $this->recordTicket(['title' => 'RecChk history', 'requester_user_id' => $customer->id]);

        $this->as($this->admin)->put(route('helpdesk-tickets.assign', $ticket), ['assigned_agent_id' => $agent->id])->assertRedirect();
        $ticket->statusHistories()->create(['from_status' => 'open', 'to_status' => 'in_progress', 'changed_by' => $this->admin->id, 'changed_by_name' => $this->admin->name]);

        $admin = $this->as($this->admin)->get(route('helpdesk-tickets.show', $ticket))->viewData('page')['props']['ticket'];
        $this->assertNotEmpty($admin['status_histories']);
        $this->assertNotEmpty($admin['assignment_histories']);

        $mine = $this->as($customer)->get(route('helpdesk-tickets.show', $ticket))->viewData('page')['props']['ticket'];
        $this->assertNotEmpty($mine['status_histories']);
        $this->assertArrayNotHasKey('assignment_histories', $mine);
    }

    public function test_a_company_cannot_grant_itself_platform_support_access(): void
    {
        $this->as($this->company)->post(route('roles.store'), [
            'name' => 'escalate', 'label' => 'Escalate', 'permissions' => ['view-all-support-tickets'],
        ])->assertSessionHasErrors('permissions.0');
        $this->as($this->company)->post(route('roles.store'), [
            'name' => 'support-agent', 'label' => 'Support agent', 'permissions' => [],
        ])->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('roles', ['name' => 'escalate', 'created_by' => $this->company->id]);
    }

    public function test_company_staff_with_support_names_or_permissions_get_no_platform_access(): void
    {
        $role = \Spatie\Permission\Models\Role::create(['name' => 'support-agent-lookalike', 'label' => 'Lookalike', 'guard_name' => 'web', 'created_by' => $this->company->id]);
        $role->givePermissionTo(['view-helpdesk-tickets', 'manage-helpdesk-tickets', 'view-all-support-tickets', 'view-helpdesk-internal-notes', 'manage-support-team']);
        $staff = User::factory()->create(['type' => 'staff', 'created_by' => $this->company->id, 'is_enable_login' => 1]);
        $staff->assignRole($role);
        $staff->assignRole(\Spatie\Permission\Models\Role::where('name', 'support-agent')->where('created_by', $this->admin->id)->firstOrFail());

        $foreign = $this->recordTicket(['title' => 'RecChk other tenant', 'created_by' => $this->otherCompany->id]);
        $this->note();

        $this->assertFalse($staff->isPlatformSupport());
        $this->assertFalse($foreign->isVisibleTo($staff));
        $this->as($staff)->get(route('helpdesk-tickets.show', $foreign))->assertNotFound();
        $this->assertSame([], collect($this->records($staff)['records']['data'])->pluck('id')->all());
        $this->assertFalse(HelpdeskReply::canSeeInternal($staff));
        $this->as($staff)->get(route('support-agents.index'))->assertForbidden();
    }
}
