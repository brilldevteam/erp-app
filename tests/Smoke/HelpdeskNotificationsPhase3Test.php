<?php

namespace Tests\Smoke;

use App\Jobs\SendHelpdeskEmail;
use App\Mail\CommonEmailTemplate;
use App\Models\HelpdeskEmailDelivery;
use App\Models\HelpdeskReply;
use App\Models\HelpdeskTicket;
use App\Models\Setting;
use App\Models\User;
use App\Services\HelpdeskEmailContent;
use App\Services\HelpdeskEmailService;
use App\Services\HelpdeskNotificationSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Settings scope Phase 3: Helpdesk support mailbox and per-event email switches.
 * Runs against the local copy of live data and rolls everything back.
 */
class HelpdeskNotificationsPhase3Test extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $company;
    private User $client;
    private HelpdeskTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::where('type', 'superadmin')->orderBy('id')->first();
        $company = User::where('type', 'company')->orderBy('id')->first();
        if (!$admin || !$company) {
            $this->markTestSkipped('Needs a super admin and a company in the local copy.');
        }

        [$this->admin, $this->company] = [$admin, $company];
        $this->client = User::factory()->create(['type' => 'client', 'created_by' => $company->id, 'email' => 'client-'.uniqid().'@example.test']);
        $this->ticket = HelpdeskTicket::create([
            'title' => 'Printer <script>x</script>', 'description' => 'It is broken', 'status' => 'open', 'priority' => 'high',
            'created_by' => $company->id, 'requester_user_id' => $this->client->id,
        ]);

        // Start every test from a clean switch state.
        Setting::where('created_by', $admin->id)
            ->where(fn ($q) => $q->where('key', 'like', 'helpdesk_mail_%')->orWhereIn('key', ['helpdesk_support_email', 'Helpdesk Ticket', 'Helpdesk Ticket Reply']))
            ->delete();
        Cache::flush();
        Queue::fake();
        Mail::fake();
    }

    private function admin(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key, 'created_by' => $this->admin->id], ['value' => $value, 'is_public' => false]);
        }
        Cache::flush();
    }

    private function delivery(string $key): ?HelpdeskEmailDelivery
    {
        return HelpdeskEmailDelivery::where('delivery_key', $key)->first();
    }

    private function fresh(): HelpdeskTicket
    {
        return $this->ticket->fresh(['creator', 'requester', 'assignedAgent', 'category']);
    }

    public function test_new_ticket_goes_to_the_support_mailbox_or_falls_back_to_super_admins(): void
    {
        $this->admin(['helpdesk_mail_ticket_created_support' => 'on']);
        app(HelpdeskEmailService::class)->ticketCreated($this->fresh());
        $fallback = $this->delivery("ticket-created:{$this->ticket->id}");
        $this->assertNotNull($fallback);
        $this->assertContains($this->admin->email, $fallback->recipients);

        $fallback->delete();
        $this->admin(['helpdesk_support_email' => 'support@example.test,help@example.test']);
        app(HelpdeskEmailService::class)->ticketCreated($this->fresh());
        $delivery = $this->delivery("ticket-created:{$this->ticket->id}");
        $this->assertSame(['support@example.test', 'help@example.test'], $delivery->recipients);
        $this->assertSame('helpdesk:ticket_created_support', $delivery->template);
    }

    public function test_switches_fall_back_to_legacy_values_and_new_events_start_off(): void
    {
        $settings = app(HelpdeskNotificationSettings::class);
        foreach (array_keys(HelpdeskNotificationSettings::EVENTS) as $event) {
            $this->assertFalse($settings->enabled($event), "{$event} must be off with no settings");
        }

        $this->admin(['Helpdesk Ticket' => 'on', 'Helpdesk Ticket Reply' => 'on']);
        $settings = app(HelpdeskNotificationSettings::class);
        $this->assertTrue($settings->enabled('ticket_created_support'));
        $this->assertTrue($settings->enabled('reply_to_customer'));
        $this->assertTrue($settings->enabled('status_changed'));
        $this->assertFalse($settings->enabled('ticket_created_confirmation'));
        $this->assertFalse($settings->enabled('ticket_created_company'));

        $this->admin(['helpdesk_mail_reply_to_customer' => 'off']);
        $this->assertFalse(app(HelpdeskNotificationSettings::class)->enabled('reply_to_customer'));
        $this->assertTrue(app(HelpdeskNotificationSettings::class)->enabled('reply_to_support'));
    }

    public function test_customer_confirmation_and_company_notification_are_optional(): void
    {
        app(HelpdeskEmailService::class)->ticketCreated($this->fresh());
        $this->assertNull($this->delivery("ticket-confirmation:{$this->ticket->id}"));
        $this->assertNull($this->delivery("ticket-company:{$this->ticket->id}"));

        $this->admin([
            'helpdesk_mail_ticket_created_confirmation' => 'on',
            'helpdesk_mail_ticket_created_company' => 'on',
            'helpdesk_support_email' => 'support@example.test',
        ]);
        app(HelpdeskEmailService::class)->ticketCreated($this->fresh());

        $confirmation = $this->delivery("ticket-confirmation:{$this->ticket->id}");
        $this->assertSame([$this->client->email], $confirmation->recipients);
        $this->assertSame('support@example.test', $confirmation->payload['reply_to']);

        $companyEmail = company_setting('company_email', $this->company->id) ?: $this->company->email;
        $this->assertSame([$companyEmail], $this->delivery("ticket-company:{$this->ticket->id}")->recipients);
    }

    public function test_a_company_is_not_told_about_its_own_ticket(): void
    {
        $this->admin(['helpdesk_mail_ticket_created_company' => 'on']);
        $own = HelpdeskTicket::create([
            'title' => 'Own ticket', 'description' => 'x', 'status' => 'open', 'priority' => 'low',
            'created_by' => $this->company->id, 'requester_user_id' => $this->company->id,
        ]);

        app(HelpdeskEmailService::class)->ticketCreated($own->fresh(['creator', 'requester', 'category']));
        $this->assertNull($this->delivery("ticket-company:{$own->id}"));
    }

    public function test_replies_go_to_the_right_side(): void
    {
        $this->admin([
            'helpdesk_mail_reply_to_support' => 'on',
            'helpdesk_mail_reply_to_customer' => 'on',
            'helpdesk_support_email' => 'support@example.test',
        ]);

        $customerReply = HelpdeskReply::create(['ticket_id' => $this->ticket->id, 'message' => 'Any news?', 'is_internal' => false, 'created_by' => $this->client->id]);
        app(HelpdeskEmailService::class)->replied($this->fresh(), $customerReply, $this->client);
        $toSupport = $this->delivery("ticket-reply:{$customerReply->id}");
        $this->assertSame('reply_to_support', $toSupport->event);
        $this->assertSame(['support@example.test'], $toSupport->recipients);

        $supportReply = HelpdeskReply::create(['ticket_id' => $this->ticket->id, 'message' => 'Fixed', 'is_internal' => false, 'created_by' => $this->admin->id]);
        app(HelpdeskEmailService::class)->replied($this->fresh(), $supportReply, $this->admin);
        $toCustomer = $this->delivery("ticket-reply:{$supportReply->id}");
        $this->assertSame('reply_to_customer', $toCustomer->event);
        $this->assertSame([$this->client->email], $toCustomer->recipients);
        $this->assertSame('support@example.test', $toCustomer->payload['reply_to']);

        $note = HelpdeskReply::create(['ticket_id' => $this->ticket->id, 'message' => 'SECRET', 'is_internal' => true, 'created_by' => $this->admin->id]);
        app(HelpdeskEmailService::class)->replied($this->fresh(), $note, $this->admin);
        $this->assertNull($this->delivery("ticket-reply:{$note->id}"));
    }

    public function test_each_event_has_its_own_wording_and_escapes_ticket_text(): void
    {
        $payload = [
            'company_name' => HelpdeskTicket::emailText('Acme'),
            'requester_name' => HelpdeskTicket::emailText('Jane'),
            'ticket_name' => HelpdeskTicket::emailText('Printer <script>x</script>'),
            'ticket_id' => '00042',
            'ticket_url' => 'https://example.test/helpdesk/42',
            'ticket_description' => HelpdeskTicket::emailText('Line "one"'),
            'status' => HelpdeskTicket::emailText('resolved'),
        ];

        $subjects = collect(array_keys(HelpdeskNotificationSettings::EVENTS))
            ->mapWithKeys(fn ($event) => [$event => HelpdeskEmailContent::render($event, $payload)['subject']]);
        $this->assertCount(7, $subjects->unique());
        $this->assertStringContainsString('New Helpdesk ticket 00042', $subjects['ticket_created_support']);
        $this->assertStringContainsString('We received your support ticket 00042', $subjects['ticket_created_confirmation']);
        $this->assertStringContainsString('resolved', $subjects['status_changed']);

        $html = HelpdeskEmailContent::render('ticket_created_support', $payload)['html'];
        // emailText() strips tags and escapes the rest, so no markup from ticket text reaches the email.
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Printer x', $html);
        $this->assertStringContainsString('Line &quot;one&quot;', $html);
        $this->assertStringContainsString('https://example.test/helpdesk/42', $html);
    }

    public function test_job_sends_event_emails_through_the_central_mailer_with_reply_to(): void
    {
        $this->admin([
            'email_host' => 'smtp.central.test', 'email_fromAddress' => 'central@example.test',
            'helpdesk_mail_ticket_created_confirmation' => 'on', 'helpdesk_support_email' => 'support@example.test',
        ]);
        app(HelpdeskEmailService::class)->ticketCreated($this->fresh());
        $delivery = $this->delivery("ticket-confirmation:{$this->ticket->id}");

        Mail::fake();
        (new SendHelpdeskEmail($delivery->id))->handle();

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame('smtp.central.test', config('mail.mailers.smtp.host'));
        Mail::assertSent(CommonEmailTemplate::class, fn (CommonEmailTemplate $mail) =>
            $mail->hasTo($this->client->email)
            && str_contains($mail->template->subject, 'We received your support ticket')
            && $mail->replyToAddress === 'support@example.test'
        );
    }

    public function test_emails_send_after_the_response_without_a_queue_worker_and_retry_failures(): void
    {
        $this->admin([
            'email_host' => 'smtp.central.test', 'email_fromAddress' => 'central@example.test',
            'helpdesk_mail_ticket_created_confirmation' => 'on',
        ]);

        app(HelpdeskEmailService::class)->ticketCreated($this->fresh());
        $delivery = $this->delivery("ticket-confirmation:{$this->ticket->id}");
        $this->assertSame('pending', $delivery->status);

        // Sent when the request finishes - nothing goes to a queue that needs a worker.
        app()->terminate();
        $this->assertSame('sent', $delivery->fresh()->status);
        Mail::assertSent(CommonEmailTemplate::class, fn (CommonEmailTemplate $mail) => $mail->hasTo($this->client->email));
        Queue::assertNothingPushed();

        // A send that failed earlier stays pending and goes out with the next Helpdesk email.
        $stale = HelpdeskEmailDelivery::create([
            'delivery_key' => 'stale-'.uniqid(), 'ticket_id' => $this->ticket->id, 'event' => 'ticket_created_confirmation',
            'template' => HelpdeskEmailService::TEMPLATE_PREFIX.'ticket_created_confirmation',
            'recipients' => [$this->client->email], 'payload' => $delivery->payload, 'status' => 'pending', 'attempts' => 1,
        ]);
        HelpdeskEmailDelivery::whereKey($stale->id)->update(['updated_at' => now()->subMinutes(5)]);

        $second = HelpdeskTicket::create([
            'title' => 'Second', 'description' => 'Another one', 'status' => 'open', 'priority' => 'low',
            'created_by' => $this->ticket->created_by, 'requester_user_id' => $this->client->id,
        ]);
        app(HelpdeskEmailService::class)->ticketCreated($second->fresh(['creator', 'requester', 'assignedAgent', 'category']));
        app()->terminate();

        $this->assertSame('sent', $stale->fresh()->status);
        $this->assertSame('sent', $this->delivery("ticket-confirmation:{$second->id}")->status);
    }
}
