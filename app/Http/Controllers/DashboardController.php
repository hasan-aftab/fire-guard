<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Client;
use App\Models\Inspection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardFilterRequest $request): Response
    {
        $today = today();
        $asOfDate = $today->toDateString();
        $alertEnd = $today->copy()->addDays(30)->toDateString();
        $calendarEnd = $today->copy()->addDays(90)->toDateString();

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
            'summary' => function () use ($assetQuery, $mapAsset, $asOfDate, $alertEnd, $calendarEnd): array {
                $results = Inspection::query()->select('result')->selectRaw('count(*) as aggregate')->groupBy('result')->pluck('aggregate', 'result');
                $upcomingQuery = (clone $assetQuery)->whereBetween('next_service_due', [$asOfDate, $calendarEnd]);

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
                    'upcomingTotal' => (clone $upcomingQuery)->count(),
                    'upcomingDates' => (clone $upcomingQuery)->select('next_service_due')->reorder('next_service_due')->distinct()->pluck('next_service_due')->all(),
                    'upcomingInspections' => $upcomingQuery->orderBy('next_service_due')->orderBy('id')->limit(10)->get()->map($mapAsset),
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
