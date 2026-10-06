<?php

namespace App\Actions;

use App\Models\Asset;
use App\Models\Certificate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the dashboard Schedule agenda: one ordered list of the work that needs
 * attention, merging asset service dates with certificate expiry dates.
 *
 * Four rules define the result:
 *
 * 1. Overdue work is *always* fetched, whatever horizon is requested. A narrower
 *    window must never hide work that is already late. Whether it is then
 *    *shown* is the overdue mode's decision: include (default), only, or
 *    exclude.
 * 2. The selectable window only ever reaches forward from the as-of date, and
 *    is capped at MAX_HORIZON_DAYS cells. Anything past the cap is clamped and
 *    the effective window is reported back so the UI can state what it is showing.
 * 3. Rows are handed to the view already sorted into three groups -- Overdue,
 *    Next N days, Later -- because which side of the N-day line a row falls on
 *    is a day-count comparison, and day counts are resolved here rather than in
 *    the browser. The boundary is the NEXT_GROUP_PRESET offset, the same value
 *    the "Due next 14 days" tile counts against.
 * 4. A selected day narrows every group at once, so the strip and the agenda
 *    always describe the same set of rows.
 *
 * Windows are expressed as an inclusive offset from the as-of date: the "14d"
 * preset means today through today + 14, which is 15 cells and exactly the band
 * Asset::serviceStatus() calls due soon. Preset keys, offsets and the default
 * live in config('fire-tracker.dashboard') so the KPI scopes, the strip and the
 * agenda cannot drift apart. horizonDays reports the number of calendar days
 * the window actually spans, which is one more than its offset.
 *
 * Status is taken from the models' own accessors (Asset::serviceStatus(),
 * Certificate::status) so the day-count thresholds stay defined only in PHP.
 *
 * All three groups are always present, in a fixed order, with an empty item
 * list when they hold nothing. Deciding whether to *render* an empty group is
 * the view's call, made on itemCount; keeping the slots means neither the view
 * nor a test has to index around a missing group.
 *
 * @phpstan-type ScheduleType 'all'|'asset'|'certificate'
 * @phpstan-type ScheduleOverdueMode 'include'|'only'|'exclude'
 * @phpstan-type ScheduleStatus 'overdue'|'due_soon'|'ok'
 * @phpstan-type ScheduleItemKind 'asset'|'certificate'
 * @phpstan-type ScheduleGroupId 'overdue'|'next'|'later'
 * @phpstan-type ScheduleItem array{
 *     key: string,
 *     kind: ScheduleItemKind,
 *     entityId: int,
 *     dueDate: string,
 *     isOverdue: bool,
 *     daysUntil: int,
 *     status: ScheduleStatus,
 *     typeName: string,
 *     siteName: string,
 *     clientName: string,
 *     reference: string|null,
 *     locationDesc: string|null,
 *     href: string
 * }
 * @phpstan-type ScheduleGroup array{
 *     id: ScheduleGroupId,
 *     label: string,
 *     itemCount: int,
 *     byKind: array{asset: int, certificate: int},
 *     firstDate: string|null,
 *     items: list<ScheduleItem>
 * }
 * @phpstan-type ScheduleDay array{
 *     date: string,
 *     isToday: bool,
 *     itemCount: int
 * }
 * @phpstan-type SchedulePayload array{
 *     from: string,
 *     to: string,
 *     horizonDays: int,
 *     horizonOffset: int,
 *     horizonPreset: string|null,
 *     type: ScheduleType,
 *     overdueMode: ScheduleOverdueMode,
 *     day: string|null,
 *     windowHidden: bool,
 *     groupBoundaryDays: int,
 *     groups: list<ScheduleGroup>,
 *     counts: array{all: int, asset: int, certificate: int},
 *     days: list<ScheduleDay>,
 *     overdueSince: string|null,
 *     overdueByKind: array{asset: int, certificate: int},
 *     nextAfter: array{date: string, daysUntil: int}|null,
 *     overdueCount: int,
 *     windowItemCount: int,
 *     totalCount: int,
 *     page: int,
 *     lastPage: int,
 *     returnedCount: int,
 *     truncated: bool,
 *     maxItems: int,
 *     maxHorizonDays: int,
 *     horizonPresets: array<string, int>,
 *     defaultHorizonPreset: string
 * }
 */
final class BuildScheduleAgenda
{
    /**
     * Upper bound on rendered rows; the true total is always reported too.
     */
    public const MAX_ITEMS = 60;

    /**
     * Hard ceiling on how many calendar days the window may span. The
     * reachable offset is one less, because the as-of day is always a cell.
     */
    public const MAX_HORIZON_DAYS = 90;

    /**
     * Preset whose offset also decides where the "Next N days" group ends and
     * the "Later" group begins. Tying the boundary to the same preset the
     * "Due next 14 days" tile counts against is what makes that tile's number and
     * the group's row count the same measurement rather than two that agree.
     */
    public const NEXT_GROUP_PRESET = '14d';

    /**
     * Overdue modes, in the order the request may name them.
     *
     * @return list<ScheduleOverdueMode>
     */
    public static function overdueModes(): array
    {
        return ['include', 'only', 'exclude'];
    }

    /**
     * Inclusive horizon presets keyed by label, each an offset in days from the
     * as-of date. The 14-day preset is the default and shares its value with
     * Asset::DUE_SOON_DAYS so the default window covers the whole due-soon band.
     *
     * @return array<string, int>
     */
    public static function horizonPresets(): array
    {
        /** @var array<string, int> $presets */
        $presets = config('fire-tracker.dashboard.horizon_presets', []);

        return $presets;
    }

    /** Key of the preset used when the request does not name one. */
    public static function defaultHorizonPreset(): string
    {
        return (string) config('fire-tracker.dashboard.default_horizon', '14d');
    }

    /**
     * Resolves a preset key to its inclusive window end. An unknown or missing
     * key falls back to the default preset so a bad query string cannot widen
     * or invert the window.
     */
    public static function presetEnd(CarbonImmutable $asOf, ?string $preset): CarbonImmutable
    {
        $presets = self::horizonPresets();
        $offset = $presets[$preset] ?? $presets[self::defaultHorizonPreset()] ?? 0;

        return $asOf->addDays($offset);
    }

    /**
     * @param  ScheduleType  $type
     * @param  ScheduleOverdueMode  $overdueMode
     * @return SchedulePayload
     */
    public function handle(
        CarbonImmutable $asOf,
        ?string $from = null,
        ?string $to = null,
        string $type = 'all',
        ?string $horizon = null,
        string $overdueMode = 'include',
        ?string $day = null,
        ?string $dateMode = null,
        int $page = 1,
    ): array {
        $asOfDate = $asOf->toDateString();
        $windowStart = $this->resolveWindowStart($asOf, $from);
        $windowEnd = $this->resolveWindowEnd($asOf, $windowStart, $to, $horizon);

        // An unrecognised mode or day falls back to the default rather than
        // failing the visit, so a hand-edited query string cannot produce an
        // empty dashboard with no way back.
        $mode = in_array($overdueMode, self::overdueModes(), true) ? $overdueMode : 'include';
        $dayKey = $day !== null && CarbonImmutable::hasFormat($day, 'Y-m-d') ? $day : null;

        $customBounds = match ($dateMode) {
            'through' => $to === null ? null : [null, $to],
            'from' => $from === null ? null : [$from, null],
            'between' => $from === null || $to === null ? null : [$from, $to],
            default => null,
        };

        $assets = $this->assetItems($asOf, $asOfDate, $windowStart, $windowEnd, $customBounds);
        $certificates = $this->certificateItems($asOf, $asOfDate, $windowStart, $windowEnd, $customBounds);

        // Tab counts are resolved against every *other* active dimension, so
        // "Certs 2" stays true while the Asset tab is the selected one. They
        // count the uncapped set, which is what the rows are allowed to be
        // compared against when the row cap hides some of them.
        $counts = [
            'all' => $this->visibleCount([...$assets, ...$certificates], $mode, $dayKey),
            'asset' => $this->visibleCount($assets, $mode, $dayKey),
            'certificate' => $this->visibleCount($certificates, $mode, $dayKey),
        ];

        /** @var list<ScheduleItem> $items */
        $items = match ($type) {
            'asset' => $assets,
            'certificate' => $certificates,
            default => [...$assets, ...$certificates],
        };

        // Overdue first (oldest first), then upcoming (soonest first). Ties are
        // broken by kind then id so the ordering is stable between requests.
        usort($items, static fn (array $left, array $right): int => [$left['dueDate'], $left['kind'], $left['entityId']]
            <=> [$right['dueDate'], $right['kind'], $right['entityId']]);

        // The sorted list is kept intact so each mode can still report the half it
        // hides. Filtering in place and then counting from the survivors reports
        // zero for exactly the scope the reader cannot otherwise see.
        $sorted = $items;

        $items = array_values(array_filter(
            $items,
            fn (array $item): bool => $this->isVisible($item, $mode, $dayKey),
        ));

        $totalCount = count($items);
        $lastPage = max(1, (int) ceil($totalCount / self::MAX_ITEMS));
        $currentPage = min(max(1, $page), $lastPage);
        $capped = array_slice($items, ($currentPage - 1) * self::MAX_ITEMS, self::MAX_ITEMS);
        $groups = $this->buildGroups($capped);

        // Overdue-only mode hides the forward window and exclude mode hides the
        // backlog, so each of these is counted from the intact list while still
        // honouring the day filter — a narrowed day must not offer to reveal
        // another day's work. totalCount stays mode-filtered because it is the
        // denominator for "showing X of Y" among the rows actually returned.
        $windowItemCount = $this->visibleCount($sorted, 'exclude', $dayKey);
        $allOverdue = $this->visibleItems($sorted, 'only');

        $horizonOffset = (int) $windowStart->diffInDays($windowEnd);

        return [
            'from' => $windowStart->toDateString(),
            'to' => $windowEnd->toDateString(),
            'horizonDays' => $horizonOffset + 1,
            'horizonOffset' => $horizonOffset,
            'horizonPreset' => $this->matchedPreset($windowStart, $windowEnd, $asOfDate),
            'type' => $type,
            'overdueMode' => $mode,
            'day' => $dayKey,
            // "Overdue only" answers a question that has no window, so the strip
            // and the range control are suppressed rather than shown as inert.
            'windowHidden' => $mode === 'only' || $customBounds !== null,
            'groupBoundaryDays' => $this->nextGroupDays(),
            'groups' => $groups,
            'counts' => $counts,
            'days' => $mode === 'only' || $customBounds !== null
                ? []
                : $this->buildDays($items, $windowStart, $windowEnd, $asOfDate),
            'overdueSince' => $allOverdue[0]['dueDate'] ?? null,
            'overdueByKind' => $this->countByKind($allOverdue),
            'nextAfter' => $mode === 'only' ? null : $this->nextAfter($asOf, $asOfDate, $windowEnd, $type),
            'overdueCount' => count($allOverdue),
            'windowItemCount' => $windowItemCount,
            'totalCount' => $totalCount,
            'page' => $currentPage,
            'lastPage' => $lastPage,
            'returnedCount' => count($capped),
            'truncated' => $totalCount > self::MAX_ITEMS,
            'maxItems' => self::MAX_ITEMS,
            'maxHorizonDays' => self::MAX_HORIZON_DAYS,
            'horizonPresets' => self::horizonPresets(),
            'defaultHorizonPreset' => self::defaultHorizonPreset(),
        ];
    }

    /**
     * Days past the as-of date that still count as "soon". Read from the same
     * preset the "Due next 14 days" tile counts against.
     */
    public function nextGroupDays(): int
    {
        $presets = self::horizonPresets();

        return $presets[self::NEXT_GROUP_PRESET] ?? 0;
    }

    /**
     * Splits the visible rows into the three agenda groups.
     *
     * All three are always returned in a fixed order with an empty item list
     * when they hold nothing, so a client can index them by position and a test
     * can assert on `groups.0` without first checking that a group exists.
     * Whether a group is *rendered* is the view's decision, made on itemCount.
     *
     * @param  list<ScheduleItem>  $items
     * @return list<ScheduleGroup>
     */
    private function buildGroups(array $items): array
    {
        $boundary = $this->nextGroupDays();

        $overdue = [];
        $next = [];
        $later = [];

        foreach ($items as $item) {
            if ($item['isOverdue']) {
                $overdue[] = $item;
            } elseif ($item['daysUntil'] <= $boundary) {
                $next[] = $item;
            } else {
                $later[] = $item;
            }
        }

        return [
            $this->group('overdue', 'Overdue', $overdue),
            $this->group('next', 'Next '.$boundary.' days', $next),
            $this->group('later', 'Later', $later),
        ];
    }

    /**
     * @param  list<ScheduleItem>  $items
     * @param  ScheduleGroupId  $id
     * @return ScheduleGroup
     */
    private function group(string $id, string $label, array $items): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'itemCount' => count($items),
            'byKind' => $this->countByKind($items),
            'firstDate' => $items[0]['dueDate'] ?? null,
            'items' => $items,
        ];
    }

    /**
     * One cell per calendar day across the whole window, including days with no
     * work, so the day strip can render a contiguous run of cells whose length
     * always matches the window it claims to cover. The rows themselves are not
     * repeated here; they live in the agenda groups.
     *
     * "Today" is resolved against the as-of date rather than the window start,
     * so a custom range that begins later cannot flag its first day as today.
     *
     * @param  list<ScheduleItem>  $items
     * @return list<ScheduleDay>
     */
    private function buildDays(
        array $items,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        string $asOfDate,
    ): array {
        /** @var array<string, int> $countsByDate */
        $countsByDate = [];

        foreach ($items as $item) {
            $countsByDate[$item['dueDate']] = ($countsByDate[$item['dueDate']] ?? 0) + 1;
        }

        $days = [];

        for ($date = $windowStart; $date <= $windowEnd; $date = $date->addDay()) {
            $key = $date->toDateString();

            $days[] = [
                'date' => $key,
                'isToday' => $key === $asOfDate,
                'itemCount' => $countsByDate[$key] ?? 0,
            ];
        }

        return $days;
    }

    /**
     * The soonest actionable work falling due after the window closes, so the
     * card can say what a narrower range is leaving out instead of implying the
     * queue ends at the window edge.
     *
     * @param  ScheduleType  $type
     * @return array{date: string, daysUntil: int}|null
     */
    private function nextAfter(
        CarbonImmutable $asOf,
        string $asOfDate,
        CarbonImmutable $windowEnd,
        string $type,
    ): ?array {
        $dates = [];

        if ($type !== 'certificate') {
            $dates[] = Asset::query()->actionable()
                ->whereNotNull('next_service_due')
                ->where('next_service_due', '>', $windowEnd->toDateString())
                ->min('next_service_due');
        }

        if ($type !== 'asset') {
            $dates[] = Certificate::query()->actionable()
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '>', $windowEnd->toDateString())
                ->min('expiry_date');
        }

        $dates = array_values(array_filter(
            $dates,
            static fn (?string $date): bool => $date !== null,
        ));

        if ($dates === []) {
            return null;
        }

        $next = CarbonImmutable::createFromFormat('Y-m-d', min($dates));

        if ($next === null) {
            return null;
        }

        return [
            'date' => $next->toDateString(),
            'daysUntil' => (int) $asOf->diffInDays($next, false),
        ];
    }

    /**
     * Whether one item survives the overdue mode and the selected day.
     *
     * @param  ScheduleItem  $item
     * @param  ScheduleOverdueMode  $mode
     */
    private function isVisible(array $item, string $mode, ?string $day): bool
    {
        if ($mode === 'only' && ! $item['isOverdue']) {
            return false;
        }

        if ($mode === 'exclude' && $item['isOverdue']) {
            return false;
        }

        return $day === null || $item['dueDate'] === $day;
    }

    /**
     * @param  list<ScheduleItem>  $items
     * @param  ScheduleOverdueMode  $mode
     */
    private function visibleCount(array $items, string $mode, ?string $day): int
    {
        return count($this->visibleItems($items, $mode, $day));
    }

    /**
     * @param  list<ScheduleItem>  $items
     * @param  ScheduleOverdueMode  $mode
     * @return list<ScheduleItem>
     */
    private function visibleItems(array $items, string $mode, ?string $day = null): array
    {
        return array_values(array_filter(
            $items,
            fn (array $item): bool => $this->isVisible($item, $mode, $day),
        ));
    }

    /**
     * The preset a resolved window corresponds to, or null for a custom range.
     *
     * A preset only matches when the window also *starts* on the as-of date:
     * resolveWindowStart() allows a custom range to begin later, and without
     * that check a custom 3-day range ending on a preset boundary would be
     * reported as that preset.
     */
    private function matchedPreset(
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        string $asOfDate,
    ): ?string {
        foreach (self::horizonPresets() as $key => $offset) {
            if (
                $offset === (int) $windowStart->diffInDays($windowEnd)
                && $windowStart->toDateString() === $asOfDate
            ) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  list<ScheduleItem>  $items
     * @return array{asset: int, certificate: int}
     */
    private function countByKind(array $items): array
    {
        $counts = ['asset' => 0, 'certificate' => 0];

        foreach ($items as $item) {
            $counts[$item['kind']]++;
        }

        return $counts;
    }

    /**
     * Overdue work ignores the requested window entirely; only the forward
     * window is bounded. A missing due date cannot be grouped under a day, so
     * those records never reach the agenda.
     *
     * @param  array{?string, ?string}|null  $customBounds
     * @return list<ScheduleItem>
     */
    private function assetItems(
        CarbonImmutable $asOf,
        string $asOfDate,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        ?array $customBounds = null,
    ): array {
        $items = Asset::query()
            ->actionable()
            ->select(['id', 'site_id', 'asset_type_id', 'serial_number', 'location_desc', 'next_service_due', 'status'])
            ->with(['assetType:id,type_name', 'site:id,site_name,client_id', 'site.client:id,company_name'])
            ->whereNotNull('next_service_due')
            ->when($customBounds !== null, function (Builder $query) use ($customBounds): void {
                [$from, $to] = $customBounds;
                if ($from !== null) {
                    $query->where('next_service_due', '>=', $from);
                }
                if ($to !== null) {
                    $query->where('next_service_due', '<=', $to);
                }
            }, function (Builder $query) use ($asOfDate, $windowStart, $windowEnd): void {
                $query->where(function (Builder $query) use ($asOfDate, $windowStart, $windowEnd): void {
                    $query->where('next_service_due', '<', $asOfDate)
                        ->orWhereBetween('next_service_due', [$windowStart->toDateString(), $windowEnd->toDateString()]);
                });
            })
            ->get()
            ->map(fn (Asset $asset): array => $this->assetItem($asOf, $asset))
            ->all();

        return array_values($items);
    }

    /**
     * @param  array{?string, ?string}|null  $customBounds
     * @return list<ScheduleItem>
     */
    private function certificateItems(
        CarbonImmutable $asOf,
        string $asOfDate,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        ?array $customBounds = null,
    ): array {
        $items = Certificate::query()
            ->actionable()
            ->select(['id', 'site_id', 'certificate_type_id', 'expiry_date', 'status'])
            ->with(['certificateType:id,name', 'site:id,site_name,client_id', 'site.client:id,company_name'])
            ->whereNotNull('expiry_date')
            ->when($customBounds !== null, function (Builder $query) use ($customBounds): void {
                [$from, $to] = $customBounds;
                if ($from !== null) {
                    $query->where('expiry_date', '>=', $from);
                }
                if ($to !== null) {
                    $query->where('expiry_date', '<=', $to);
                }
            }, function (Builder $query) use ($asOfDate, $windowStart, $windowEnd): void {
                $query->where(function (Builder $query) use ($asOfDate, $windowStart, $windowEnd): void {
                    $query->where('expiry_date', '<', $asOfDate)
                        ->orWhereBetween('expiry_date', [$windowStart->toDateString(), $windowEnd->toDateString()]);
                });
            })
            ->get()
            ->map(function (Certificate $certificate) use ($asOf): array {
                $dueDate = $certificate->expiry_date->toDateString();

                return [
                    'key' => 'certificate:'.$certificate->id,
                    'kind' => 'certificate',
                    'entityId' => $certificate->id,
                    'dueDate' => $dueDate,
                    'isOverdue' => $dueDate < $asOf->toDateString(),
                    'daysUntil' => (int) $asOf->diffInDays($certificate->expiry_date, false),
                    'status' => match ($certificate->status) {
                        'expired' => 'overdue',
                        'expiring_soon' => 'due_soon',
                        default => 'ok',
                    },
                    'typeName' => $certificate->certificateType->name,
                    'siteName' => $certificate->site->site_name,
                    'clientName' => $certificate->site->client->company_name,
                    'reference' => null,
                    'locationDesc' => null,
                    'href' => route('certificates.index', [
                        'certificate_type_id' => $certificate->certificate_type_id,
                        'site_id' => $certificate->site_id,
                    ]),
                ];
            })
            ->all();

        return array_values($items);
    }

    /**
     * @return ScheduleItem
     */
    private function assetItem(CarbonImmutable $asOf, Asset $asset): array
    {
        $dueDate = $asset->next_service_due->toDateString();

        return [
            'key' => 'asset:'.$asset->id,
            'kind' => 'asset',
            'entityId' => $asset->id,
            'dueDate' => $dueDate,
            'isOverdue' => $dueDate < $asOf->toDateString(),
            'daysUntil' => (int) $asOf->diffInDays($asset->next_service_due, false),
            'status' => match ($asset->service_status) {
                'overdue' => 'overdue',
                'due_soon' => 'due_soon',
                default => 'ok',
            },
            'typeName' => $asset->assetType->type_name,
            'siteName' => $asset->site->site_name,
            'clientName' => $asset->site->client->company_name,
            'reference' => $asset->serial_number,
            'locationDesc' => $asset->location_desc,
            'href' => route('assets.index', ['search' => $asset->serial_number]),
        ];
    }

    /**
     * The window never starts before the as-of date: anything earlier is
     * already overdue and reaches the agenda through the overdue branch.
     */
    private function resolveWindowStart(CarbonImmutable $asOf, ?string $from): CarbonImmutable
    {
        if ($from === null || ! CarbonImmutable::hasFormat($from, 'Y-m-d')) {
            return $asOf;
        }

        $requested = CarbonImmutable::createFromFormat('Y-m-d', $from);

        if ($requested === null) {
            return $asOf;
        }

        return $requested > $asOf ? $requested : $asOf;
    }

    /**
     * Resolves the window end and guarantees it can never be inverted.
     *
     * Precedence is explicit end, then a preset key, then the default preset.
     * An explicit `to` is more specific than a preset, so it wins when both are
     * sent. A preset always measures its offset from the as-of date; the start
     * is left alone, which is why a custom start combined with a preset offset
     * reports no matching preset.
     */
    private function resolveWindowEnd(
        CarbonImmutable $asOf,
        CarbonImmutable $windowStart,
        ?string $to,
        ?string $horizon,
    ): CarbonImmutable {
        $ceiling = $asOf->addDays(self::MAX_HORIZON_DAYS - 1);

        if ($to !== null && CarbonImmutable::hasFormat($to, 'Y-m-d')) {
            $requested = CarbonImmutable::createFromFormat('Y-m-d', $to);

            if ($requested === null) {
                return $this->clampToStart(self::presetEnd($asOf, $horizon), $ceiling, $windowStart);
            }

            return $this->clampToStart($requested, $ceiling, $windowStart);
        }

        return $this->clampToStart(self::presetEnd($asOf, $horizon), $ceiling, $windowStart);
    }

    /**
     * Applies the two hard limits on an end date: the horizon ceiling, and never
     * earlier than the window start.
     */
    private function clampToStart(
        CarbonImmutable $end,
        CarbonImmutable $ceiling,
        CarbonImmutable $windowStart,
    ): CarbonImmutable {
        $clamped = $end <= $ceiling ? $end : $ceiling;

        return $clamped >= $windowStart ? $clamped : $windowStart;
    }
}
