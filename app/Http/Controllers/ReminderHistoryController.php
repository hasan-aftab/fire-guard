<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReminderHistoryRequest;
use App\Mail\AssetInspectionReminderMail;
use App\Mail\CertificateExpiryReminderMail;
use App\Models\Client;
use App\Models\ReminderDelivery;
use App\Models\ReminderRecipient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

class ReminderHistoryController extends Controller
{
    public function index(ReminderHistoryRequest $request): InertiaResponse|RedirectResponse
    {
        $filters = $request->safe()->except(['page']);
        $records = ReminderDelivery::query()
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

        $statusCounts = ReminderDelivery::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

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
            'recipients' => ReminderRecipient::query()->orderBy('email')->get(['id', 'email']),
            'fallbackAdmins' => User::query()->where('role', User::ROLE_ADMIN)->whereNotNull('email_verified_at')
                ->orderBy('name')->get(['id', 'name', 'email']),
            'summary' => [
                'sent' => (int) $statusCounts->get('sent', 0),
                'in_progress' => (int) $statusCounts->get('pending', 0) + (int) $statusCounts->get('processing', 0),
                'needs_review' => (int) $statusCounts->get('failed', 0) + (int) $statusCounts->get('uncertain', 0),
            ],
        ]);
    }

    public function preview(ReminderDelivery $reminderDelivery): HttpResponse
    {
        return $this->previewResponse($this->mailable($reminderDelivery));
    }

    public function samplePreview(Request $request, string $type): HttpResponse
    {
        return $this->previewResponse($this->mailable($this->sample($request, $type), true));
    }

    private function previewResponse(Mailable $mail): HttpResponse
    {
        return response($mail->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function testEmail(Request $request, ReminderDelivery $reminderDelivery): RedirectResponse
    {
        return $this->sendTestCopy($request, $this->mailable($reminderDelivery, true), $reminderDelivery->id);
    }

    public function sampleTestEmail(Request $request, string $type): RedirectResponse
    {
        return $this->sendTestCopy($request, $this->mailable($this->sample($request, $type), true), null);
    }

    private function sendTestCopy(Request $request, Mailable $mail, ?int $deliveryId): RedirectResponse
    {
        $recipient = $request->user()->email;
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return back()->withErrors(['test_email' => 'Your account needs a valid email address to receive a test copy.']);
        }

        try {
            Mail::to($recipient)->send($mail);
        } catch (Throwable $exception) {
            Log::warning('Reminder test email failed', ['delivery_id' => $deliveryId, 'user_id' => $request->user()->id, 'error_type' => class_basename($exception)]);

            return back()->withErrors(['test_email' => 'The test copy could not be delivered. Please try again.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Test copy sent to your email inbox.']);

        return back();
    }

    private function mailable(ReminderDelivery $delivery, bool $preview = false): Mailable
    {
        return match ($delivery->reminder_type) {
            'asset' => new AssetInspectionReminderMail($delivery, $preview),
            'certificate' => new CertificateExpiryReminderMail($delivery, $preview),
            default => abort(404),
        };
    }

    private function sample(Request $request, string $type): ReminderDelivery
    {
        abort_unless(in_array($type, ['asset', 'certificate'], true), 404);
        $offset = $type === 'asset' ? 7 : 30;

        return new ReminderDelivery([
            'channel' => 'email',
            'reminder_type' => $type,
            'subject_id' => 0,
            'audience' => 'staff',
            'recipient' => $request->user()->email,
            'recipient_name' => $request->user()->name,
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
    }
}
