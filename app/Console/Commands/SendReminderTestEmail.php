<?php

namespace App\Console\Commands;

use App\Actions\SendSampleReminderEmail;
use App\Models\ReminderRecipient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('fire-tracker:test-reminder-email {type : asset or certificate} {email : internal destination address}')]
#[Description('Send a labeled sample reminder to one internal email address')]
class SendReminderTestEmail extends Command
{
    public function handle(SendSampleReminderEmail $sender): int
    {
        $type = strtolower(trim((string) $this->argument('type')));
        $email = strtolower(trim((string) $this->argument('email')));

        if (! in_array($type, ['asset', 'certificate'], true)) {
            $this->error('Type must be asset or certificate.');

            return self::FAILURE;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
            $this->error('Enter a valid internal email address.');

            return self::FAILURE;
        }

        if (ReminderRecipient::isClientContactEmail($email)) {
            $this->error('Client contact addresses cannot receive internal reminders.');

            return self::FAILURE;
        }

        try {
            $sender->run($type, $email);
        } catch (Throwable $exception) {
            Log::warning('Reminder test email failed', ['type' => $type, 'error_type' => class_basename($exception)]);
            $this->error('The test email could not be sent. Check mail settings and logs.');

            return self::FAILURE;
        }

        $this->info("Sent {$type} test reminder to {$email}.");

        return self::SUCCESS;
    }
}
