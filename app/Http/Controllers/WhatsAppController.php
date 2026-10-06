<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersFireTrackerRecords;
use App\Jobs\SendWhatsAppReminder;
use App\Models\ReminderDelivery;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\MetaWhatsAppClient;
use App\Services\WhatsAppPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;
use Throwable;

class WhatsAppController extends Controller
{
    use FiltersFireTrackerRecords;

    public function index(Request $request): InertiaResponse|RedirectResponse
    {
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['today', '7', '30'])],
            'status' => ['nullable', Rule::in(['queued', 'processing', 'submitted', 'sent', 'delivered', 'read', 'failed', 'skipped', 'uncertain'])],
            'type' => ['nullable', Rule::in(['asset', 'certificate', 'test'])],
            'account_id' => ['nullable', 'integer', Rule::exists('whatsapp_accounts', 'id')],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        unset($filters['page']);
        $period = $filters['period'] ?? '30';
        $start = $period === 'today' ? today()->startOfDay() : now()->subDays($period === '7' ? 6 : 29)->startOfDay();
        $base = WhatsAppMessage::query()->where('is_test', false)->where('created_at', '>=', $start);
        $summary = [
            'submitted' => (clone $base)->whereNotNull('submitted_at')->count(),
            'sent' => (clone $base)->whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'delivered' => (clone $base)->whereIn('status', ['delivered', 'read'])->count(),
            'read' => (clone $base)->where('status', 'read')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'active_accounts' => WhatsAppAccount::query()->where('is_active', true)->where('is_test', false)->count(),
            'skipped_reminders' => ReminderDelivery::query()->where('channel', 'whatsapp')->where('status', 'skipped')->where('created_at', '>=', $start)->count(),
        ];
        $outcomes = (clone $base)->whereIn('status', ['sent', 'delivered', 'read', 'failed'])->count();
        $summary['delivery_rate'] = $outcomes === 0 ? null : round($summary['delivered'] * 100 / $outcomes, 1);
        $byType = [];
        foreach (['asset', 'certificate'] as $type) {
            $query = (clone $base)->whereHas('reminderDelivery', fn ($q) => $q->where('reminder_type', $type));
            $byType[$type] = [
                'submitted' => (clone $query)->whereNotNull('submitted_at')->count(),
                'delivered' => (clone $query)->whereIn('status', ['delivered', 'read'])->count(),
                'read' => (clone $query)->where('status', 'read')->count(),
                'failed' => (clone $query)->where('status', 'failed')->count(),
            ];
        }
        $accounts = WhatsAppAccount::query()->withCount([
            'messages as messages_count' => fn ($q) => $q->where('is_test', false)->where('created_at', '>=', $start),
            'messages as delivered_count' => fn ($q) => $q->where('is_test', false)->where('created_at', '>=', $start)->whereIn('status', ['delivered', 'read']),
            'messages as outcomes_count' => fn ($q) => $q->where('is_test', false)->where('created_at', '>=', $start)->whereIn('status', ['sent', 'delivered', 'read', 'failed']),
        ])->withMax('messages', 'created_at')->orderByDesc('is_default')->orderBy('id')->get()
            ->map(fn (WhatsAppAccount $account): array => [
                'id' => $account->id, 'name' => $account->name, 'display_phone_number' => $account->display_phone_number,
                'phone_number_id' => $account->phone_number_id, 'waba_id' => $account->waba_id,
                'is_active' => $account->is_active, 'is_test' => $account->is_test, 'is_default' => $account->is_default,
                'last_verified_at' => $account->last_verified_at?->toIso8601String(), 'last_error' => $account->last_error,
                'messages_count' => $account->messages_count, 'delivered_count' => $account->delivered_count,
                'delivery_rate' => $account->outcomes_count === 0 ? null : round($account->delivered_count * 100 / $account->outcomes_count, 1),
                'last_used_at' => $account->messages_max_created_at,
            ]);
        $history = WhatsAppMessage::query()->with(['reminderDelivery', 'account'])
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['type'] ?? null, function ($q, $value): void {
                $value === 'test' ? $q->where('is_test', true) : $q->whereHas('reminderDelivery', fn ($r) => $r->where('reminder_type', $value));
            })
            ->when($filters['account_id'] ?? null, fn ($q, $value) => $q->where('whatsapp_account_id', $value))
            ->when($filters['search'] ?? null, function ($query) use ($request): void {
                $pattern = $this->searchPattern($request);
                $query->where(function ($messages) use ($pattern): void {
                    $this->whereSearchLike($messages, 'whatsapp_messages.recipient', $pattern);
                    $messages->orWhereHas('reminderDelivery', function ($deliveries) use ($pattern): void {
                        $this->whereSearchLike($deliveries, 'reminder_deliveries.client_name', $pattern);
                        $this->whereSearchLike($deliveries, 'reminder_deliveries.site_name', $pattern, 'or');
                    });
                });
            })
            ->when($filters['from'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '<=', $value))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(10)->withQueryString();
        if ($history->currentPage() > $history->lastPage()) {
            return to_route('whatsapp.index', $filters + ['page' => $history->lastPage()]);
        }

        return Inertia::render('whatsapp', [
            'filters' => $filters, 'summary' => $summary, 'byType' => $byType, 'accounts' => $accounts,
            'templates' => WhatsAppTemplate::query()->orderBy('waba_id')->orderBy('purpose')->get(),
            'messages' => $history->through(fn (WhatsAppMessage $message): array => [
                'id' => $message->id, 'is_test' => $message->is_test, 'recipient' => $message->recipient,
                'status' => $message->status, 'account_name' => $message->account?->name,
                'sender_display_phone_number' => $message->sender_display_phone_number,
                'phone_number_id' => $message->phone_number_id, 'waba_id' => $message->waba_id,
                'template_name' => $message->template_name, 'template_language' => $message->template_language,
                'meta_message_id' => $message->meta_message_id, 'attempt_count' => $message->attempt_count,
                'submitted_at' => $message->submitted_at?->toIso8601String(),
                'sent_at' => $message->sent_at?->toIso8601String(), 'delivered_at' => $message->delivered_at?->toIso8601String(),
                'read_at' => $message->read_at?->toIso8601String(), 'failed_at' => $message->failed_at?->toIso8601String(),
                'failure_code' => $message->failure_code, 'last_error' => $message->last_error,
                'created_at' => $message->created_at?->toIso8601String(),
                'client_name' => $message->reminderDelivery?->client_name, 'site_name' => $message->reminderDelivery?->site_name,
                'subject_name' => $message->reminderDelivery?->subject_name,
                'reminder_type' => $message->reminderDelivery?->reminder_type,
                'due_date' => $message->reminderDelivery?->due_date?->toDateString(),
                'reminder_offset' => $message->reminderDelivery?->reminder_offset,
                'can_retry' => $message->status === 'failed' && $message->meta_message_id === null && $message->attempt_count < 2,
            ]),
        ]);
    }

    public function storeAccount(Request $request, WhatsAppPhone $phone): RedirectResponse
    {
        $data = $this->accountData($request, $phone);
        DB::transaction(function () use ($data): void {
            $default = (bool) $data['is_default'];
            unset($data['is_default']);
            $account = WhatsAppAccount::query()->create($data + ['is_default' => false]);
            if ($default) {
                $this->makeDefault($account);
            }
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'WhatsApp account added. Verify its connection before sending.']);

        return back();
    }

    public function updateAccount(Request $request, WhatsAppAccount $account, WhatsAppPhone $phone): RedirectResponse
    {
        $data = $this->accountData($request, $phone, $account);
        DB::transaction(function () use ($account, $data): void {
            $default = (bool) $data['is_default'];
            unset($data['is_default']);
            if ($data['access_token'] === null || $data['access_token'] === '') {
                unset($data['access_token']);
            }
            $changed = collect(['display_phone_number', 'phone_number_id', 'waba_id', 'access_token'])
                ->contains(fn (string $key): bool => isset($data[$key]) && $data[$key] !== $account->{$key});
            if ($changed) {
                $data['last_verified_at'] = null;
                $data['last_error'] = null;
            }
            $account->update($data);
            if (! $account->is_active || $account->is_test || ! $default) {
                $account->update(['is_default' => false, 'default_slot' => null]);
            } elseif ($default) {
                $this->makeDefault($account);
            }
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'WhatsApp account updated.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function accountData(Request $request, WhatsAppPhone $phone, ?WhatsAppAccount $account = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_phone_number' => ['required', 'string', 'max:40', function (string $attribute, mixed $value, \Closure $fail) use ($phone, $request): void {
                try {
                    $phone->normalize((string) $value, $request->boolean('is_test'));
                } catch (InvalidArgumentException) {
                    $fail('Enter a valid international phone number beginning with +.');
                }
            }],
            'phone_number_id' => ['required', 'regex:/^[0-9]{5,32}$/', Rule::unique('whatsapp_accounts', 'phone_number_id')->ignore($account?->id)],
            'waba_id' => ['required', 'regex:/^[0-9]{5,32}$/'],
            'access_token' => [$account === null ? 'required' : 'nullable', 'string', 'max:4096'],
            'is_active' => ['required', 'boolean'], 'is_test' => ['required', 'boolean'], 'is_default' => ['required', 'boolean'],
        ]);
        $data['display_phone_number'] = $phone->normalize($data['display_phone_number'], (bool) $data['is_test']);
        if ($data['is_default'] && (! $data['is_active'] || $data['is_test'])) {
            throw ValidationException::withMessages(['is_default' => 'Only an active production account can be the default sender.']);
        }

        return $data;
    }

    private function makeDefault(WhatsAppAccount $account): void
    {
        WhatsAppAccount::query()->lockForUpdate()->get(['id']);
        WhatsAppAccount::query()->where('is_default', true)->update(['is_default' => false, 'default_slot' => null]);
        $account->update(['is_default' => true, 'default_slot' => 1]);
    }

    public function setDefault(WhatsAppAccount $account): RedirectResponse
    {
        if (! $account->is_active || $account->is_test) {
            abort(422, 'Activate this account before making it the default sender.');
        }
        DB::transaction(fn () => $this->makeDefault($account));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Default sender updated.']);

        return back();
    }

    public function toggleActive(WhatsAppAccount $account): RedirectResponse
    {
        DB::transaction(function () use ($account): void {
            $active = ! $account->is_active;
            $account->update(['is_active' => $active, 'is_default' => $active && $account->is_default,
                'default_slot' => $active && $account->is_default ? 1 : null]);
            if (! $active && WhatsAppAccount::query()->where('is_default', true)->doesntExist()) {
                $next = WhatsAppAccount::query()->where('is_active', true)->where('is_test', false)->whereNotNull('last_verified_at')->whereNull('last_error')->orderBy('id')->first();
                if ($next !== null) {
                    $this->makeDefault($next);
                }
            }
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account availability updated.']);

        return back();
    }

    public function verify(WhatsAppAccount $account, MetaWhatsAppClient $meta, WhatsAppPhone $phone): RedirectResponse
    {
        try {
            $result = $meta->phoneNumbers($account);
            $actual = null;
            foreach (($result['data']['data'] ?? []) as $item) {
                if (is_array($item) && (string) ($item['id'] ?? '') === $account->phone_number_id) {
                    $actual = $item;
                    break;
                }
            }
            $matches = $result['ok'] && $actual !== null
                && $phone->normalize((string) ($actual['display_phone_number'] ?? ''), $account->is_test) === $account->display_phone_number;
            $error = $matches ? null : ($result['ok'] ? 'Phone Number ID or display number does not match this WABA.' : 'Meta connection failed (code '.$result['code'].').');
        } catch (Throwable) {
            $matches = false;
            $error = 'Meta connection could not be verified.';
        }
        $account->update(['last_verified_at' => $matches ? now() : null, 'last_error' => $error]);
        Inertia::flash('toast', ['type' => $matches ? 'success' : 'error', 'message' => $matches ? 'Connection verified.' : $error]);

        return back();
    }

    public function saveTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', Rule::exists('whatsapp_accounts', 'id')],
            'purpose' => ['required', Rule::in(['asset', 'certificate'])],
            'name' => ['required', 'regex:/^[a-z0-9_]{1,512}$/'],
            'language' => ['required', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2})?$/'],
            'parameter_keys' => ['required', 'array', 'max:10'],
            'parameter_keys.*' => ['required', Rule::in(['client_name', 'site_name', 'subject_name', 'due_date', 'timing'])],
            'is_enabled' => ['required', 'boolean'],
        ]);
        $account = WhatsAppAccount::query()->findOrFail((int) $data['account_id']);
        WhatsAppTemplate::query()->updateOrCreate(
            ['waba_id' => $account->waba_id, 'purpose' => $data['purpose']],
            ['name' => $data['name'], 'language' => $data['language'], 'parameter_keys' => $data['parameter_keys'],
                'is_enabled' => $data['is_enabled'], 'meta_status' => null, 'category' => null, 'last_synced_at' => null, 'last_error' => null],
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template mapping saved. Sync with Meta before use.']);

        return back();
    }

    public function syncTemplates(WhatsAppAccount $account, MetaWhatsAppClient $meta): RedirectResponse
    {
        try {
            $result = $meta->templates($account);
        } catch (Throwable) {
            $result = ['ok' => false, 'code' => null, 'data' => []];
        }
        $mappings = WhatsAppTemplate::query()->where('waba_id', $account->waba_id)->get();
        foreach ($mappings as $mapping) {
            $found = null;
            if ($result['ok']) {
                foreach (($result['data']['data'] ?? []) as $item) {
                    if (is_array($item) && ($item['name'] ?? null) === $mapping->name && ($item['language'] ?? null) === $mapping->language) {
                        $found = $item;
                        break;
                    }
                }
            }
            $body = null;
            $otherDynamic = false;
            foreach (($found['components'] ?? []) as $component) {
                if (! is_array($component)) {
                    continue;
                }
                if (($component['type'] ?? null) === 'BODY') {
                    $body = $component;
                } elseif (str_contains(json_encode($component) ?: '', '{{')) {
                    $otherDynamic = true;
                }
            }
            $text = (string) ($body['text'] ?? '');
            preg_match_all('/\{\{([^}]+)\}\}/', $text, $matches);
            $numeric = array_filter($matches[1], fn (string $value): bool => ctype_digit($value) && (int) $value > 0);
            $positions = array_values(array_unique(array_map('intval', $numeric)));
            sort($positions);
            $expected = range(1, count($mapping->parameter_keys));
            if ($mapping->parameter_keys === []) {
                $expected = [];
            }
            $parametersMatch = count($numeric) === count($matches[1]) && $positions === $expected && ! $otherDynamic;
            $mapping->update([
                'meta_status' => $found['status'] ?? null, 'category' => $found['category'] ?? null,
                'last_synced_at' => now(),
                'last_error' => ! $result['ok']
                    ? sprintf('Meta template lookup failed (code %s).', $result['code'] ?? 'unknown')
                    : ($found === null ? 'Template was not found in this WABA.' : (! $parametersMatch ? 'Only numbered body parameters matching this mapping are supported.' : null)),
            ]);
        }
        Inertia::flash('toast', ['type' => $result['ok'] ? 'success' : 'error', 'message' => $result['ok'] ? 'Template status synced from Meta.' : 'Could not sync templates from Meta.']);

        return back();
    }

    public function sendTest(Request $request, WhatsAppPhone $phone): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', Rule::exists('whatsapp_accounts', 'id')],
            'template_id' => ['required', 'integer', Rule::exists('whatsapp_templates', 'id')],
            'recipient' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($phone): void {
                try {
                    $phone->normalize((string) $value);
                } catch (InvalidArgumentException) {
                    $fail('Enter a valid international test recipient beginning with +.');
                }
            }],
        ]);
        $account = WhatsAppAccount::query()->findOrFail((int) $data['account_id']);
        $template = WhatsAppTemplate::query()->findOrFail((int) $data['template_id']);
        if (! $account->is_active || $account->last_verified_at === null || $account->last_error !== null
            || $template->waba_id !== $account->waba_id || ! $template->is_enabled || $template->meta_status !== 'APPROVED' || $template->last_error !== null) {
            throw ValidationException::withMessages(['template_id' => 'Choose an approved template for a verified active sender.']);
        }
        $message = WhatsAppMessage::query()->create([
            'whatsapp_account_id' => $account->id, 'whatsapp_template_id' => $template->id,
            'recipient' => $phone->normalize($data['recipient']), 'is_test' => true, 'status' => 'queued',
            'test_values' => ['client_name' => 'Test customer', 'site_name' => 'Test site', 'subject_name' => 'Test equipment',
                'due_date' => today()->format('j F Y'), 'timing' => 'Test reminder'],
        ]);
        SendWhatsAppReminder::dispatch(null, $message->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Test message queued. It is excluded from reminder metrics.']);

        return back();
    }

    public function retry(WhatsAppMessage $message): RedirectResponse
    {
        DB::transaction(function () use ($message): void {
            $message = WhatsAppMessage::query()->whereKey($message->id)->lockForUpdate()->firstOrFail();
            if ($message->status !== 'failed' || $message->meta_message_id !== null || $message->attempt_count >= 2) {
                abort(409, 'Only a definitively rejected message can be retried once.');
            }
            $message->update(['status' => 'queued', 'failed_at' => null, 'failure_code' => null, 'last_error' => null]);
            $message->reminderDelivery?->update(['status' => 'pending', 'failed_at' => null, 'last_error' => null]);
        });
        SendWhatsAppReminder::dispatch($message->reminder_delivery_id, $message->is_test ? $message->id : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'One controlled retry queued.']);

        return back();
    }
}
