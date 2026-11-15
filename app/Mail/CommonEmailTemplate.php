<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommonEmailTemplate extends Mailable
{
    use Queueable, SerializesModels;
    public $template;
    public $user_id;
    public $fromAddress;
    public $fromName;
    public $replyToAddress;
    /**<
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($template, $user_id, ?string $fromAddress = null, ?string $fromName = null, ?string $replyToAddress = null)
    {
        $this->template = $template;
        $this->user_id = $user_id;
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
        $this->replyToAddress = $replyToAddress;
    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $mail = $this->from(
                // Explicit sender, else the company's own, else the active (possibly central) mailer's sender.
                $this->fromAddress ?: (company_setting('email_fromAddress', $this->user_id) ?: config('mail.from.address')),
                $this->fromName ?: $this->template->from
            );

        if ($this->replyToAddress) {
            $mail->replyTo($this->replyToAddress);
        }

        return $mail->markdown('emails.common_email_template')
                ->subject($this->template->subject)
                ->with('content', $this->template->content);
    }
}
