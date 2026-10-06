<?php

namespace App\Actions;

use App\Models\Asset;
use App\Models\Certificate;
use App\Models\Inspection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the four dashboard KPI cards.
 *
 * Each card is a count over one named scope, and every scope resolves its date
 * bounds through BuildScheduleAgenda so a card can never disagree with the
 * Schedule rows it deep-links to. DashboardTest asserts that equality directly;
 * these two methods are the shared expression it is asserting about.
 *
 * Overdue work is counted across both kinds because a card that counts only one
 * would understate the work actually waiting. The due-soon and certificate
 * windows are preset-based, so clicking a card and landing on a Schedule whose
 * window is still on the default preset would be a dead end.
 *
 * @phpstan-type DashboardKpis array{
 *     overdue: array{
 *         total: int,
 *         byKind: array{asset: int, certificate: int}
 *     },
 *     dueSoon: array{total: int, horizonPreset: string, horizonDays: int},
 *     certificates: array{expired: int, expiringSoon: int, total: int, horizonPreset: string},
 *     passRate: array{percent: int, band: 'success'|'warning'|'danger', pass: int, fail: int, needsRepair: int, total: int}
 * }
 */
final class BuildDashboardKpis
{
    /** Preset whose window the "Due in 7 days" card covers. */
    public const DUE_SOON_PRESET = '7d';

    /** Preset whose window the certificate card's expiring-soon count covers. */
    public const CERTIFICATE_PRESET = '30d';

    /**
     * @return DashboardKpis
     */
    public function handle(CarbonImmutable $asOf): array
    {
        return [
            'overdue' => $this->overdue($asOf),
            'dueSoon' => $this->dueSoon($asOf),
            'certificates' => $this->certificates($asOf),
            'passRate' => $this->passRate($asOf),
        ];
    }

    /**
     * Overdue ignores the selected window on purpose: work that is already late
     * is overdue regardless of which horizon the Schedule is showing.
     *
     * @return array{total: int, byKind: array{asset: int, certificate: int}}
     */
    private function overdue(CarbonImmutable $asOf): array
    {
        $assets = Asset::query()->actionable()
            ->whereNotNull('next_service_due')
            ->where('next_service_due', '<', $asOf->toDateString())
            ->count();
        $certificates = Certificate::query()->actionable()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $asOf->toDateString())
            ->count();

        return [
            'total' => $assets + $certificates,
            'byKind' => ['asset' => $assets, 'certificate' => $certificates],
        ];
    }

    /**
     * Work falling due from today through the end of the due-soon preset. This
     * deliberately excludes overdue work, which the previous card owns.
     *
     * @return array{total: int, horizonPreset: string, horizonDays: int}
     */
    private function dueSoon(CarbonImmutable $asOf): array
    {
        $end = BuildScheduleAgenda::presetEnd($asOf, self::DUE_SOON_PRESET);
        $scope = fn (string $column): Builder => $this->windowed($column, $asOf, $end);

        $total = $scope('next_service_due')->count() + $scope('expiry_date')->count();

        return [
            'total' => $total,
            'horizonPreset' => self::DUE_SOON_PRESET,
            'horizonDays' => (int) $asOf->diffInDays($end) + 1,
        ];
    }

    /**
     * Split by urgency: already expired, then expiring inside the certificate
     * preset window. Because the window starts today, nothing inside it can also
     * be expired, so the two counts never overlap.
     *
     * @return array{expired: int, expiringSoon: int, total: int, horizonPreset: string}
     */
    private function certificates(CarbonImmutable $asOf): array
    {
        $end = BuildScheduleAgenda::presetEnd($asOf, self::CERTIFICATE_PRESET);
        $asOfDate = $asOf->toDateString();

        // Expired work predates the window, so it is counted from its own scope
        // rather than the in-window one: anything already expired can never be
        // inside a window that starts today.
        $expired = Certificate::query()->actionable()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $asOfDate)
            ->count();
        $expiringSoon = $this->windowed('expiry_date', $asOf, $end)->count();

        return [
            'expired' => $expired,
            'expiringSoon' => $expiringSoon,
            'total' => $expired + $expiringSoon,
            'horizonPreset' => self::CERTIFICATE_PRESET,
        ];
    }

    /**
     * Share of inspections recorded as a pass across the compliance window,
     * banded for the card's colour. Rounded rather than truncated so a genuine
     * two-thirds pass rate reads 67, not 66.
     *
     * The per-result counts ride along because the compliance gauge draws them;
     * returning them here keeps one query feeding both the card and the chart.
     *
     * @return array{percent: int, band: 'success'|'warning'|'danger', pass: int, fail: int, needsRepair: int, total: int}
     */
    private function passRate(CarbonImmutable $asOf): array
    {
        $since = $asOf->subMonths($this->complianceMonths());

        $results = Inspection::query()->actionable()
            ->where('inspection_date', '>=', $since->toDateString())
            ->select('result')->selectRaw('count(*) as aggregate')
            ->groupBy('result')->pluck('aggregate', 'result');

        $total = (int) $results->sum();
        $pass = (int) $results->get('pass', 0);

        return [
            'percent' => $total === 0 ? 0 : (int) round($pass / $total * 100),
            'band' => $this->band($total === 0 ? 0 : $pass / $total * 100),
            'pass' => $pass,
            'fail' => (int) $results->get('fail', 0),
            'needsRepair' => (int) $results->get('needs_repair', 0),
            'total' => $total,
        ];
    }

    /**
     * The in-window half of a kind's scope: dated work from the as-of date
     * through the window end, inclusive at both ends.
     *
     * @return Builder<Asset>|Builder<Certificate>
     */
    private function windowed(string $column, CarbonImmutable $asOf, CarbonImmutable $end): Builder
    {
        $query = $column === 'expiry_date'
            ? Certificate::query()->actionable()
            : Asset::query()->actionable();

        return $query->whereNotNull($column)
            ->whereBetween($column, [$asOf->toDateString(), $end->toDateString()]);
    }

    private function complianceMonths(): int
    {
        return (int) config('fire-tracker.dashboard.compliance_months', 12);
    }

    /** @return 'success'|'warning'|'danger' */
    private function band(float $percent): string
    {
        if ($percent >= (float) config('fire-tracker.dashboard.pass_rate.success', 80)) {
            return 'success';
        }

        if ($percent >= (float) config('fire-tracker.dashboard.pass_rate.warning', 60)) {
            return 'warning';
        }

        return 'danger';
    }
}
