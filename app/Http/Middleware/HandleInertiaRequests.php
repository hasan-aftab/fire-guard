<?php

namespace App\Http\Middleware;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Client;
use App\Models\FireNotification;
use App\Models\GeneratedReport;
use App\Models\Inspection;
use App\Models\ReminderDelivery;
use App\Models\Site;
use App\Models\Technician;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'today' => today()->toDateString(),
            // Shipped because "today" only means anything next to the zone it was
            // resolved in. A browser in another zone must not re-derive the
            // calendar date or it will disagree with every count on the page.
            'timezone' => config('app.timezone'),
            'fileUploadLimits' => [
                'photoBytes' => (int) config('fire-tracker.photo_max_kilobytes') * 1024,
                'documentBytes' => (int) config('fire-tracker.document_max_kilobytes') * 1024,
            ],
            // Live-work counts use each model's actionable() scope so they agree
            // with the dashboard by construction. Lookup tables below stay
            // inventory counts on purpose — see ReferenceTypeManagementTest.
            'counts' => fn (): ?array => $request->user() ? [
                'clients' => Client::actionable()->count(),
                'sites' => Site::actionable()->count(),
                'assets' => Asset::actionable()->count(),
                'inspections' => Inspection::actionable()->count(),
                'certificates' => Certificate::actionable()->count(),
                'technicians' => Technician::actionable()->count(),
                'assetTypes' => AssetType::query()->count(),
                'certificateTypes' => CertificateType::query()->count(),
                'reports' => GeneratedReport::query()->count(),
                'reminders' => ReminderDelivery::query()->where('channel', 'email')->count(),
            ] : null,
            'fireNotifications' => fn (): ?array => $request->user() ? [
                'items' => FireNotification::where('user_id', $request->user()->id)
                    ->latest()->orderByDesc('id')->limit(50)->get(),
                'unreadCount' => FireNotification::where('user_id', $request->user()->id)->where('is_read', false)->count(),
            ] : null,
        ];
    }
}
