<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $mailMessage,
        public readonly string $pdf,
        public readonly string $filename,
        public readonly int $tenantId,
    ) {
    }

    public function build(): self
    {
        $mail = $this->subject($this->mailSubject)
            ->view('emails.document')
            ->with(['content' => $this->mailMessage])
            ->attachData($this->pdf, $this->filename, ['mime' => 'application/pdf']);

        $from = company_setting('email_fromAddress', $this->tenantId);
        if ($from) {
            $mail->from($from, company_setting('company_name', $this->tenantId) ?: config('app.name'));
        }

        return $mail;
    }
}
