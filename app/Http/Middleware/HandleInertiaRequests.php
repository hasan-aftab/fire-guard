<?php

namespace App\Http\Middleware;

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\FireNotification;
use App\Models\Inspection;
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
            'counts' => fn (): ?array => $request->user() ? [
                'clients' => Client::count(),
                'sites' => Site::count(),
                'assets' => Asset::count(),
                'inspections' => Inspection::count(),
                'certificates' => Certificate::count(),
                'technicians' => Technician::count(),
            ] : null,
            'fireNotifications' => fn (): ?array => $request->user() ? [
                'items' => FireNotification::where('user_id', $request->user()->id)
                    ->latest()->orderByDesc('id')->limit(50)->get(),
                'unreadCount' => FireNotification::where('user_id', $request->user()->id)->where('is_read', false)->count(),
            ] : null,
        ];
    }
}
