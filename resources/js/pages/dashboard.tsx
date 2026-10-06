import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    Clock,
    Flame,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import { dashboard } from '@/routes';
import { AttentionTile } from '@/components/attention-tile';
import { ComplianceCard } from '@/components/compliance-card';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { SchedulePanel } from '@/components/schedule-panel';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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
    const [error, setError] = useState('');

    if (!summary || !schedule) {
        return (
            <div>
                <Head title="Dashboard" />
                <PageHeader
                    title="Dashboard"
                    description="Overview of your fire safety operations"
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
                setError('');
                setLoadingSchedule(true);
            },
            onError: () =>
                setError('The schedule could not load. Please try again.'),
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
                description="Overview of your fire safety operations"
            />

            {error && (
                <Alert variant="destructive" className="mb-6">
                    <AlertTriangle />
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Active clients"
                    value={summary.stats.activeClients}
                    icon={Building2}
                    description="Registered companies"
                    href={summary.links.activeClients}
                />
                <StatCard
                    label="Total assets"
                    value={summary.stats.totalAssets}
                    icon={Flame}
                    description="Fire safety equipment"
                    href={summary.links.totalAssets}
                />

                {kpis && (
                    <>
                        <AttentionTile
                            label="Overdue"
                            value={kpis.overdue.total}
                            icon={AlertTriangle}
                            tone="destructive"
                            detail={`${kpis.overdue.byKind.asset} inspections · ${kpis.overdue.byKind.certificate} certificates`}
                            action="Show in schedule"
                            active={selectedTile === 'overdue'}
                            onClick={() => focusTile('overdue')}
                        />
                        <AttentionTile
                            label={`Due in ${horizonDaysLabel(kpis, 'dueSoon')}`}
                            value={kpis.dueSoon.total}
                            icon={Clock}
                            detail="Excludes anything already overdue"
                            action="Show in schedule"
                            active={selectedTile === 'dueSoon'}
                            onClick={() => focusTile('dueSoon')}
                        />
                    </>
                )}
            </div>

            <div className="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
                {kpis ? (
                    <>
                        <AttentionTile
                            label="Certificates needing attention"
                            value={kpis.certificates.total}
                            icon={ShieldCheck}
                            detail={`${kpis.certificates.expired} expired · ${kpis.certificates.expiringSoon} expiring`}
                            action="Show in schedule"
                            active={selectedTile === 'certificates'}
                            onClick={() => focusTile('certificates')}
                        />

                        <div className="lg:col-span-2">
                            <ComplianceCard
                                passRate={kpis.passRate}
                                months={summary.complianceMonths}
                                href={summary.links.passRateInspections}
                            />
                        </div>
                    </>
                ) : (
                    <div className="lg:col-span-3">
                        <ComplianceCard
                            passRate={{
                                percent: 0,
                                band: 'warning',
                                pass: 0,
                                fail: 0,
                                needsRepair: 0,
                                total: 0,
                            }}
                            months={summary.complianceMonths}
                        />
                    </div>
                )}
            </div>

            <SchedulePanel
                schedule={schedule}
                kpis={kpis}
                filters={filters}
                loading={loadingSchedule}
                onChange={applySchedule}
            />
        </div>
    );
}

/**
 * The tile's own label states the range it counts over, using the span the KPI
 * measured rather than a number typed here. A tile that said "Due in 7 days"
 * while counting 14 would be a lie the reader cannot check.
 */
function horizonDaysLabel(kpis: DashboardKpis, card: 'dueSoon'): string {
    return `${kpis[card].horizonDays - 1} days`;
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
