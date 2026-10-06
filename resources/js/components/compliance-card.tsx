import { Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { DashboardKpis } from '@/types/fire-tracker';

type PassRate = DashboardKpis['passRate'];

const SEGMENTS = [
    { key: 'pass', label: 'Pass' },
    { key: 'needsRepair', label: 'Needs repair' },
    { key: 'fail', label: 'Fail' },
] as const;

type SegmentKey = (typeof SEGMENTS)[number]['key'];

/**
 * The bar's segments are tinted by outcome, not by the score's band. The one
 * place the band shows is the headline, because "67%" reading in amber next to
 * a green bar segment would be claiming the bar is wrong.
 *
 * `text-destructive` rather than raw red: this project has no `text-danger`, and
 * a hardcoded hex would not survive the dark theme.
 */
const SEGMENT_STYLES: Record<SegmentKey, string> = {
    pass: 'bg-success',
    needsRepair: 'bg-warning',
    fail: 'bg-destructive',
};

const DOT_STYLES: Record<SegmentKey, string> = {
    pass: 'bg-success',
    needsRepair: 'bg-warning',
    fail: 'bg-destructive',
};

const HEADLINE_STYLES: Record<PassRate['band'], string> = {
    success: 'text-foreground',
    warning: 'text-warning',
    danger: 'text-destructive',
};

/**
 * Inspection outcomes over a rolling window, as one stacked bar.
 *
 * A semicircle gauge was tried and abandoned: it can only carry two arcs, so the
 * middle outcome had no mark of its own and read as pass-versus-everything.
 * Three segments in a row beside their counts say what they mean.
 */
export function ComplianceCard({
    passRate,
    months,
    href,
}: {
    passRate: PassRate;
    months: number;
    href?: string;
}) {
    const { pass, needsRepair, fail, total, percent, band } = passRate;
    const hasData = total > 0;
    const scope = `${months} months`;

    const counts: Record<SegmentKey, number> = { pass, needsRepair, fail };

    /**
     * The bar is one image to a screen reader, so the label carries the whole
     * measurement rather than leaving the reader to add up three numbers.
     */
    const summary = hasData
        ? `${percent}% of ${total} inspections passed over the last ${scope}: ` +
          SEGMENTS.map(
              (segment) => `${segment.label} ${counts[segment.key]}`,
          ).join(', ')
        : `No inspection results in the last ${scope}`;

    const card = (
        <Card className="h-full gap-0 py-0" data-status={band}>
            <CardHeader className="py-3 pb-0">
                <CardTitle className="flex items-center gap-2 text-xs font-medium text-muted-foreground sm:text-sm">
                    <span className="flex size-7 items-center justify-center rounded-md bg-muted">
                        <ShieldCheck
                            className="size-3.5 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </span>
                    Compliance · {scope}
                </CardTitle>
            </CardHeader>

            <CardContent className="flex flex-1 flex-col py-1.5 pb-3">
                {hasData ? (
                    <>
                        <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <p
                                className={`text-2xl font-bold tracking-tight sm:text-3xl ${HEADLINE_STYLES[band]}`}
                            >
                                {percent}%
                            </p>
                            <p className="text-xs text-muted-foreground tabular-nums">
                                {total} inspections
                            </p>
                        </div>

                        <div
                            className="mt-2 flex h-2 w-full gap-0.5 overflow-hidden rounded-full bg-muted"
                            role="img"
                            aria-label={summary}
                        >
                            {SEGMENTS.map((segment) => {
                                const count = counts[segment.key];

                                // A zero-count outcome contributes no width, so
                                // the bar never implies an outcome that is absent.
                                if (count === 0) {
                                    return null;
                                }

                                return (
                                    <div
                                        key={segment.key}
                                        className={`h-full ${SEGMENT_STYLES[segment.key]} first:rounded-l-full last:rounded-r-full`}
                                        style={{
                                            width: `${(count / total) * 100}%`,
                                        }}
                                    />
                                );
                            })}
                        </div>

                        <dl className="mt-2 flex flex-wrap gap-x-2 gap-y-0.5">
                            {SEGMENTS.map((segment) => (
                                <div
                                    key={segment.key}
                                    className="flex items-center gap-1 text-[11px]"
                                >
                                    <dt className="flex items-center gap-1 text-muted-foreground">
                                        <span
                                            className={`size-2 shrink-0 rounded-full ${DOT_STYLES[segment.key]}`}
                                            aria-hidden="true"
                                        />
                                        {segment.key === 'needsRepair' ? (
                                            <>
                                                <span className="sm:hidden">
                                                    Repair
                                                </span>
                                                <span className="hidden sm:inline">
                                                    Needs repair
                                                </span>
                                            </>
                                        ) : (
                                            segment.label
                                        )}
                                    </dt>
                                    <dd className="font-semibold tabular-nums">
                                        {counts[segment.key]}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </>
                ) : (
                    <div
                        className="flex flex-1 flex-col justify-center py-3"
                        role="status"
                    >
                        <p className="text-sm font-medium">
                            No inspections yet
                        </p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Pass rate appears after the first inspection.
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );

    if (!href) {
        return card;
    }

    return (
        <Link
            href={href}
            className="group block h-full rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            aria-label={`${summary}. View these inspections`}
        >
            {card}
        </Link>
    );
}
