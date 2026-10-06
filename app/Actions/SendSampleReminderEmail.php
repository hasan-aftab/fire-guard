<?php

namespace App\Actions;

use App\Mail\AssetInspectionReminderMail;
use App\Mail\CertificateExpiryReminderMail;
use App\Models\ReminderDelivery;
use Illuminate\Support\Facades\Mail;

class SendSampleReminderEmail
{
    /**
     * Send a labeled sample reminder to one address without recording it.
     *
     * The delivery is built in memory only: a row would corrupt reminder
     * history and the sidebar counts that read from it.
     *
     * @throws \Throwable when the mail transport rejects the message
     */
    public function run(string $type, string $email): void
    {
        $offset = $type === 'asset' ? 7 : 30;
        $delivery = new ReminderDelivery([
            'channel' => 'email',
            'reminder_type' => $type,
            'subject_id' => 0,
            'audience' => 'configured',
            'recipient' => $email,
            'recipient_name' => 'team',
            'client_name' => 'Example PNG Business',
            'client_contact_name' => 'Site manager',
            'client_email' => 'contact@example.test',
            'site_name' => 'Waigani Office',
            'site_address' => 'Port Moresby, Papua New Guinea',
            'subject_name' => $type === 'asset' ? 'Fire extinguisher · EX-204' : 'Annual fire safety certificate',
            'due_date' => today()->addDays($offset)->toDateString(),
            'reminder_offset' => $offset,
            'status' => 'pending',
        ]);

        $mail = $type === 'asset'
            ? new AssetInspectionReminderMail($delivery, true)
            : new CertificateExpiryReminderMail($delivery, true);
        Mail::to($email)->send($mail);
    }
}
