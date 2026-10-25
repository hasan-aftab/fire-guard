# FireGuard

Fire safety compliance tracker for businesses. Local development uses MySQL; automated tests use SQLite in memory. See [AGENTS.md](AGENTS.md) for setup and verification commands.

## Reminder emails

Scheduled inspection and certificate reminders go to the addresses in **Reminders → Who receives reminders**. When that list is empty, verified admin users receive them. Client contact addresses are excluded.

To send yourself a sample of either internal email design, run:

```bash
php artisan fire-tracker:test-reminder-email asset you@yourcompany.com
php artisan fire-tracker:test-reminder-email certificate you@yourcompany.com
```

Replace the address with your internal inbox. Each command sends one email marked **TEST COPY**, using fictional record details and the app's configured mail transport. It does not create a delivery history record or change the scheduled recipient list. With local Mailpit settings, check Mailpit for the message; with a live SMTP transport, check the supplied inbox.

The scheduler command is `php artisan fire-tracker:send-reminders` and is configured in `routes/console.php`.
