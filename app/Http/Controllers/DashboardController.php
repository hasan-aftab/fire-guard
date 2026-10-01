<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Inspection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class DashboardController extends Controller
{
    public function __invoke(DashboardFilterRequest $request): Response
    {
        $today = today();
        $asOfDate = $today->toDateString();
        $alertEnd = $today->copy()->addDays(30)->toDateString();
        $calendarEnd = $today->copy()->addDays(90)->toDateString();
        $assetDueEvents = static fn (string $from, string $to) => DB::table('assets')
            ->join('sites', 'sites.id', '=', 'assets.site_id')
            ->join('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
            ->where('assets.status', 'active')
            ->whereBetween('assets.next_service_due', [$from, $to])
            ->selectRaw("'asset' as source, assets.id as event_id, assets.next_service_due as due_date, asset_types.type_name, sites.site_name");
        $inspectionDueEvents = static fn (string $from, string $to) => DB::table('inspections')
            ->join('assets', 'assets.id', '=', 'inspections.asset_id')
            ->join('sites', 'sites.id', '=', 'assets.site_id')
            ->join('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
            ->where('assets.status', 'active')
            ->whereBetween('inspections.next_due_date', [$from, $to])
            ->selectRaw("'inspection' as source, inspections.id as event_id, inspections.next_due_date as due_date, asset_types.type_name, sites.site_name");

        $calendarMonth = $request->string('calendar_month')->toString();
        if ($calendarMonth === '') {
            $firstDueDate = DB::query()->fromSub(
                $assetDueEvents($asOfDate, $calendarEnd)->select('assets.next_service_due as due_date')
                    ->union($inspectionDueEvents($asOfDate, $calendarEnd)->select('inspections.next_due_date as due_date')),
                'due_dates',
            )->orderBy('due_date')->value('due_date');
            $calendarMonth = substr(is_string($firstDueDate) ? $firstDueDate : $asOfDate, 0, 7);
        }
        $calendarStart = max($asOfDate, $calendarMonth.'-01');
        $calendarLastDay = $today->copy()->setDate((int) substr($calendarMonth, 0, 4), (int) substr($calendarMonth, 5, 2), 1)->endOfMonth()->toDateString();
        $calendarLastDay = min($calendarEnd, $calendarLastDay);

        $assetQuery = Asset::query()
            ->select(['id', 'site_id', 'asset_type_id', 'serial_number', 'location_desc', 'next_service_due'])
            ->with(['site:id,site_name', 'assetType:id,type_name'])
            ->where('status', 'active');
        $mapAsset = fn (Asset $asset): array => [
            'assetId' => $asset->id,
            'serialNumber' => $asset->serial_number,
            'locationDesc' => $asset->location_desc,
            'nextServiceDue' => $asset->next_service_due?->toDateString(),
            'typeName' => $asset->assetType->type_name,
            'siteName' => $asset->site->site_name,
        ];

        $attentionQuery = (clone $assetQuery)->where('next_service_due', '<=', $alertEnd);
        if ($request->filled('attention_from')) {
            $attentionQuery->where('next_service_due', '>=', $request->string('attention_from')->toString());
        }
        if ($request->filled('attention_to')) {
            $attentionQuery->where('next_service_due', '<=', $request->string('attention_to')->toString());
        }

        $certificatesQuery = Certificate::query()
            ->select(['id', 'site_id', 'certificate_type', 'expiry_date'])
            ->with('site:id,site_name')
            ->where('expiry_date', '<=', $alertEnd);
        if ($request->filled('cert_from')) {
            $certificatesQuery->where('expiry_date', '>=', $request->string('cert_from')->toString());
        }
        if ($request->filled('cert_to')) {
            $certificatesQuery->where('expiry_date', '<=', $request->string('cert_to')->toString());
        }

        return Inertia::render('dashboard', [
            'summary' => function () use ($asOfDate, $alertEnd, $calendarEnd, $assetDueEvents, $inspectionDueEvents): array {
                $results = Inspection::query()->select('result')->selectRaw('count(*) as aggregate')->groupBy('result')->pluck('aggregate', 'result');
                $dueDates = DB::query()->fromSub(
                    $assetDueEvents($asOfDate, $calendarEnd)->select('assets.next_service_due as due_date')
                        ->union($inspectionDueEvents($asOfDate, $calendarEnd)->select('inspections.next_due_date as due_date')),
                    'due_dates',
                )->distinct()->orderBy('due_date')->pluck('due_date')->all();

                return [
                    'asOfDate' => $asOfDate,
                    'stats' => [
                        'activeClients' => Client::where('is_active', true)->count(),
                        'totalAssets' => Asset::count(),
                        'overdueInspections' => Asset::where('status', 'active')->where('next_service_due', '<', $asOfDate)->count(),
                        'expiringCerts' => Certificate::where('expiry_date', '<=', $alertEnd)->count(),
                    ],
                    'complianceRate' => [
                        'pass' => (int) $results->get('pass', 0),
                        'fail' => (int) $results->get('fail', 0),
                        'needs_repair' => (int) $results->get('needs_repair', 0),
                        'total' => (int) $results->sum(),
                    ],
                    'upcomingDates' => $dueDates,
                ];
            },
            'calendar' => function () use ($calendarMonth, $calendarStart, $calendarLastDay, $assetDueEvents, $inspectionDueEvents) {
                $events = DB::query()->fromSub(
                    $assetDueEvents($calendarStart, $calendarLastDay)
                        ->unionAll($inspectionDueEvents($calendarStart, $calendarLastDay)),
                    'due_events',
                )->orderBy('due_date')->orderBy('source')->orderBy('event_id');
                $page = $events->paginate(10, ['*'], 'calendar_page');
                if ($page->currentPage() > $page->lastPage()) {
                    $page = $events->paginate(10, ['*'], 'calendar_page', $page->lastPage());
                }

                return [
                    'month' => $calendarMonth,
                    'events' => $page->through(fn (stdClass $event): array => [
                        'id' => $event->source.'-'.$event->event_id,
                        'source' => $event->source,
                        'dueDate' => $event->due_date,
                        'typeName' => $event->type_name,
                        'siteName' => $event->site_name,
                    ]),
                ];
            },
            'attention' => function () use ($attentionQuery, $mapAsset) {
                $attentionQuery->orderBy('next_service_due')->orderBy('id');
                $page = $attentionQuery->paginate(10, ['*'], 'attention_page');
                if ($page->currentPage() > $page->lastPage()) {
                    $page = $attentionQuery->paginate(10, ['*'], 'attention_page', $page->lastPage());
                }

                return $page->through($mapAsset);
            },
            'certificates' => function () use ($certificatesQuery) {
                $certificatesQuery->orderBy('expiry_date')->orderBy('id');
                $page = $certificatesQuery->paginate(10, ['*'], 'cert_page');
                if ($page->currentPage() > $page->lastPage()) {
                    $page = $certificatesQuery->paginate(10, ['*'], 'cert_page', $page->lastPage());
                }

                return $page->through(fn (Certificate $certificate): array => [
                    'certificateId' => $certificate->id,
                    'certificateType' => $certificate->certificate_type,
                    'expiryDate' => $certificate->expiry_date->toDateString(),
                    'siteName' => $certificate->site->site_name,
                ]);
            },
            'filters' => $request->validated(),
        ]);
    }
}
