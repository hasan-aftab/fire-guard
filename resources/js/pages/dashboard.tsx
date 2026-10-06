import { Head, Link, router, usePoll } from '@inertiajs/react';
import { AlertTriangle, Clock, ShieldCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { dashboard } from '@/routes';
import { AttentionTile } from '@/components/attention-tile';
import { ComplianceCard } from '@/components/compliance-card';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { SchedulePanel } from '@/components/schedule-panel';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatLongDate } from '@/lib/dates';
import {
    DEFAULT_STATE,
    activeTile,
    stateFromQuery,
    stateToQuery,
    tileState,
} from '@/lib/schedule';
import type { ScheduleState, TileId } from '@/lib/schedule';
import type {
    DashboardFilters,
    DashboardKpis,
    DashboardSchedule,
    DashboardSummary,
} from '@/types/fire-tracker';

export default function Dashboard({
    summary,
    kpis,
    schedule,
    filters,
}: {
    summary: DashboardSummary | null;
    kpis: DashboardKpis | null;
    schedule: DashboardSchedule | null;
    filters: DashboardFilters;
}) {
    const [loadingSchedule, setLoadingSchedule] = useState(false);
    const [scheduleError, setScheduleError] = useState('');
    const [retryState, setRetryState] = useState<ScheduleState | null>(null);

    usePoll(60_000, { only: ['summary', 'kpis', 'schedule'] });

    // Inertia throttles polling in background tabs. Refresh as soon as the
    // dashboard is visible again, including after Port Moresby midnight.
    useEffect(() => {
        const refreshOnReturn = () => {
            if (!document.hidden) {
                router.reload({ only: ['summary', 'kpis', 'schedule'] });
            }
        };

        document.addEventListener('visibilitychange', refreshOnReturn);

        return () =>
            document.removeEventListener('visibilitychange', refreshOnReturn);
    }, []);

    if (!summary) {
        return (
            <div>
                <Head title="Dashboard" />
                <PageHeader
                    title="Dashboard"
                    description="Fire safety at a glance"
                />
                <EmptyState
                    icon={AlertTriangle}
                    title="Failed to load dashboard"
                    description="There was an error loading the dashboard data. Please try refreshing the page."
                    action={
                        <Button onClick={() => router.reload()}>Retry</Button>
                    }
                />
            </div>
        );
    }

    if (summary.isFirstRun) {
        return (
            <div>
                <Head title="Dashboard" />
                <PageHeader
                    title="Dashboard"
                    description={`As of ${formatLongDate(summary.asOfDate)} · Port Moresby time`}
                />
                <EmptyState
                    icon={ShieldCheck}
                    title="Set up your first site"
                    description="Add a client and site, then record equipment to start tracking due dates."
                    action={
                        <Button asChild>
                            <Link href={summary.links.activeClients}>
                                Add a client
                            </Link>
                        </Button>
                    }
                />
            </div>
        );
    }

    const state = stateFromQuery(filters);
    const selectedTile = kpis === null ? null : activeTile(state, kpis);

    /**
     * The whole page's state change, in one place.
     *
     * Everything that narrows the schedule — a tile, a preset, a kind tab, a day
     * — funnels through here and becomes a query string. Nothing keeps a second
     * copy, so Back works, a pasted link works, and the tiles cannot end up
     * describing a window the rows do not fill.
     */
    const applySchedule = (next: ScheduleState) => {
        router.get(dashboard.url(), stateToQuery(next), {
            only: ['schedule', 'filters', 'kpis'],
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                setScheduleError('');
                setRetryState(next);
                setLoadingSchedule(true);
            },
            onSuccess: () => setRetryState(null),
            onError: () =>
                setScheduleError('Work could not refresh. Try again.'),
            onFinish: () => setLoadingSchedule(false),
        });
    };

    /**
     * A tile narrows the schedule rather than navigating away, so the number that
     * was clicked and the rows that answer it are on screen together. Clicking
     * the lit tile clears it, which is why the same handler serves both
     * directions.
     */
    const focusTile = (tile: TileId) => {
        if (kpis === null) {
            return;
        }

        applySchedule(
            selectedTile === tile ? DEFAULT_STATE : tileState(tile, kpis),
        );
    };

    return (
        <div>
            <Head title="Dashboard" />
            <PageHeader
                title="Dashboard"
                description={`As of ${formatLongDate(summary.asOfDate)} · Port Moresby time`}
            />

            <div className="mb-4 grid grid-cols-2 gap-2.5 xl:grid-cols-4 xl:gap-4">
                {kpis ? (
                    <>
                        <AttentionTile
                            label="Overdue"
                            value={kpis.overdue.total}
                            icon={AlertTriangle}
                            tone="destructive"
                            detail={`${kpis.overdue.byKind.asset} equipment · ${kpis.overdue.byKind.certificate} certificates`}
                            active={selectedTile === 'overdue'}
                            onClick={() => focusTile('overdue')}
                        />
                        <AttentionTile
                            label="Due next 14 days"
                            value={kpis.dueSoon.total}
                            icon={Clock}
                            detail="From today"
                            active={selectedTile === 'dueSoon'}
                            onClick={() => focusTile('dueSoon')}
                        />
                        <AttentionTile
                            label="Certificate alerts"
                            value={kpis.certificates.total}
                            icon={ShieldCheck}
                            detail={`${kpis.certificates.expired} expired · ${kpis.certificates.expiringSoon} expiring`}
                            active={selectedTile === 'certificates'}
                            onClick={() => focusTile('certificates')}
                        />
                        <ComplianceCard
                            passRate={kpis.passRate}
                            months={summary.complianceMonths}
                            href={summary.links.passRateInspections}
                        />
                    </>
                ) : loadingSchedule ? (
                    Array.from({ length: 4 }, (_, index) => (
                        <Card key={index} className="min-h-32 gap-2 py-3">
                            <CardContent className="space-y-3">
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="h-9 w-14" />
                                <Skeleton className="h-3 w-32 max-w-full" />
                            </CardContent>
                        </Card>
                    ))
                ) : (
                    <Card className="col-span-2 min-h-32 xl:col-span-4">
                        <CardContent className="flex h-full items-center justify-center">
                            <div className="flex items-center gap-3 text-sm">
                                <span>Dashboard numbers could not load.</span>
                                <Button onClick={() => router.reload()}>
                                    Retry
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            <SchedulePanel
                schedule={schedule}
                kpis={kpis}
                filters={filters}
                today={summary.asOfDate}
                loading={loadingSchedule}
                error={scheduleError}
                onChange={applySchedule}
                onRetry={() =>
                    retryState
                        ? applySchedule(retryState)
                        : router.reload({ only: ['schedule'] })
                }
            />
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
