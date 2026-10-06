<?php

namespace App\Http\Controllers;

use App\Actions\BuildDashboardKpis;
use App\Actions\BuildScheduleAgenda;
use App\Http\Requests\DashboardFilterRequest;
use App\Models\Asset;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardFilterRequest $request): Response
    {
        $today = today();
        $asOfDate = $today->toDateString();

        return Inertia::render('dashboard', [
            'summary' => fn (): array => $this->summary($today, $asOfDate),
            'kpis' => fn (): array => app(BuildDashboardKpis::class)->handle($today),
            'schedule' => function () use ($request, $today): array {
                $type = $request->string('schedule_type')->toString();
                $overdue = $request->string('schedule_overdue')->toString();

                return app(BuildScheduleAgenda::class)->handle(
                    $today,
                    $request->string('schedule_from')->toString() ?: null,
                    $request->string('schedule_to')->toString() ?: null,
                    $type === 'asset' || $type === 'certificate' ? $type : 'all',
                    $request->string('schedule_horizon')->toString() ?: null,
                    $overdue === 'only' || $overdue === 'exclude' ? $overdue : 'include',
                    $request->string('schedule_day')->toString() ?: null,
                );
            },
            'filters' => $request->validated(),
        ]);
    }

    /**
     * The two standing counts that are not schedule-window work, plus the
     * shortcuts that keep each card pointed at the view it summarises.
     *
     * @return array<string, mixed>
     */
    private function summary(CarbonImmutable $today, string $asOfDate): array
    {
        $complianceMonths = (int) config('fire-tracker.dashboard.compliance_months', 12);
        $complianceSince = $today->subMonths($complianceMonths)->toDateString();

        return [
            'asOfDate' => $asOfDate,
            'complianceMonths' => $complianceMonths,
            'complianceSince' => $complianceSince,
            'links' => [
                'activeClients' => route('clients.index'),
                'totalAssets' => route('assets.index'),
                // Built here rather than in the browser so the pass rate keeps
                // pointing at the exact window and result filter it measures.
                'passRateInspections' => route('inspections.index', [
                    'from' => $complianceSince,
                ]),
            ],
            'stats' => [
                'activeClients' => Client::actionable()->count(),
                'totalAssets' => Asset::actionable()->count(),
            ],
        ];
    }
}
