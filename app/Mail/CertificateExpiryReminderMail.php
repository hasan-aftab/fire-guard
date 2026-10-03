<?php

namespace App\Mail;

use App\Models\ReminderDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateExpiryReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReminderDelivery $delivery, public bool $preview = false) {}

    public function envelope(): Envelope
    {
        $timing = $this->delivery->reminder_offset < 0 ? 'Expired'
            : ($this->delivery->reminder_offset === 0 ? 'Expires today' : 'Expires in '.$this->delivery->reminder_offset.' days');

        $subject = 'Certificate expiry · '.$timing.' · '.$this->delivery->site_name;

        return new Envelope(subject: $this->preview ? 'TEST COPY · '.$subject : $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.certificate-reminder', text: 'mail.certificate-reminder-text');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
