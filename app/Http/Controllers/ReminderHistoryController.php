<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReminderHistoryRequest;
use App\Models\Client;
use App\Models\ReminderDelivery;
use App\Models\ReminderRecipient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReminderHistoryController extends Controller
{
    public function index(ReminderHistoryRequest $request): InertiaResponse|RedirectResponse
    {
        $filters = $request->safe()->except(['page']);
        $records = ReminderDelivery::query()
            ->where('channel', 'email')
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['type'] ?? null, fn ($query, $value) => $query->where('reminder_type', $value))
            ->when($filters['audience'] ?? null, fn ($query, $value) => $query->where('audience', $value))
            ->when($filters['client_id'] ?? null, fn ($query, $value) => $query->where('client_id', $value))
            ->when($filters['due_from'] ?? null, fn ($query, $value) => $query->whereDate('due_date', '>=', $value))
            ->when($filters['due_to'] ?? null, fn ($query, $value) => $query->whereDate('due_date', '<=', $value))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(10)->withQueryString();

        if ($records->currentPage() > $records->lastPage()) {
            return to_route('reminders.index', $filters + ['page' => $records->lastPage()]);
        }

        $statusCounts = ReminderDelivery::query()->where('channel', 'email')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('reminders', [
            'records' => $records->through(fn (ReminderDelivery $delivery): array => [
                'id' => $delivery->id,
                'type' => $delivery->reminder_type,
                'client_name' => $delivery->client_name,
                'site_name' => $delivery->site_name,
                'subject_name' => $delivery->subject_name,
                'recipient' => $delivery->recipient,
                'recipient_name' => $delivery->recipient_name,
                'audience' => $delivery->audience,
                'site_address' => $delivery->site_address,
                'due_date' => $delivery->due_date->toDateString(),
                'offset' => $delivery->reminder_offset,
                'status' => $delivery->status,
                'sent_at' => $delivery->sent_at?->toIso8601String(),
                'attempt_count' => $delivery->attempt_count,
                'last_error' => $delivery->last_error,
                'created_at' => $delivery->created_at?->toIso8601String(),
            ]),
            'filters' => $filters,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
            'recipients' => ReminderRecipient::query()->orderBy('email')->get(['id', 'email'])
                ->map(fn (ReminderRecipient $recipient): array => [
                    'id' => $recipient->id,
                    'email' => $recipient->email,
                    'excluded' => ReminderRecipient::isClientContactEmail($recipient->email),
                ]),
            'fallbackAdmins' => User::query()->where('role', User::ROLE_ADMIN)->whereNotNull('email_verified_at')
                ->orderBy('name')->get(['id', 'name', 'email'])
                ->filter(fn (User $admin): bool => ! ReminderRecipient::isClientContactEmail($admin->email))->values(),
            'summary' => [
                'sent' => (int) $statusCounts->get('sent', 0),
                'in_progress' => (int) $statusCounts->get('pending', 0) + (int) $statusCounts->get('processing', 0),
                'needs_review' => (int) $statusCounts->get('failed', 0) + (int) $statusCounts->get('uncertain', 0),
            ],
        ]);
    }
}
