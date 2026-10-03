<?php

namespace App\Console\Commands;

use App\Actions\DiscoverFireReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fire-tracker:send-reminders')]
#[Description('Discover and queue internal inspection and certificate reminders')]
class SendFireReminders extends Command
{
    public function handle(DiscoverFireReminders $reminders): int
    {
        $count = $reminders->run(today()->toImmutable());
        $this->info("Created {$count} reminder records.");

        return self::SUCCESS;
    }
}
