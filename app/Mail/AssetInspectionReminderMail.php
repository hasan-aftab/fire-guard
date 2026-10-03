<?php

namespace App\Mail;

use App\Models\ReminderDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssetInspectionReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReminderDelivery $delivery, public bool $preview = false) {}

    public function envelope(): Envelope
    {
        $timing = $this->delivery->reminder_offset < 0 ? 'Overdue'
            : ($this->delivery->reminder_offset === 0 ? 'Due today' : 'Due in '.$this->delivery->reminder_offset.' days');

        $subject = 'Equipment inspection · '.$timing.' · '.$this->delivery->site_name;

        return new Envelope(subject: $this->preview ? 'TEST COPY · '.$subject : $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.asset-reminder', text: 'mail.asset-reminder-text');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
