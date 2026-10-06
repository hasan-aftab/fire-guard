import { CalendarClock } from 'lucide-react';
import { ScheduleAgenda } from '@/components/schedule-agenda';
import { ScheduleDayStrip } from '@/components/schedule-day-strip';
import { Spinner } from '@/components/ui/spinner';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    DEFAULT_STATE,
    activeTile,
    horizonLabel,
    isDefaultState,
    scopeSentence,
    stateFromQuery,
    tileState,
    typeTabs,
} from '@/lib/schedule';
import type { ScheduleState } from '@/lib/schedule';
import { cn } from '@/lib/utils';
import type {
    DashboardFilters,
    DashboardKpis,
    DashboardSchedule,
    ScheduleOverdueMode,
} from '@/types/fire-tracker';

/**
 * The schedule card: the range controls, then the rows they describe.
 *
 * The panel holds no selection state of its own. The query string is the state,
 * the server answers it, and every control here is a function that rewrites that
 * query. That is what makes the four things agree by construction — the tiles
 * above, this panel, the strip, and the URL someone pastes into a chat.
 */
export function SchedulePanel({
    schedule,
    kpis,
    filters,
    loading,
    onChange,
}: {
    schedule: DashboardSchedule | null;
    kpis: DashboardKpis | null;
    filters: DashboardFilters;
    loading: boolean;
    onChange: (next: ScheduleState) => void;
}) {
    const state = stateFromQuery(filters);
    const selectedTile = kpis === null ? null : activeTile(state, kpis);

    const apply = (next: ScheduleState) => onChange(next);

    return (
        <Card className="relative flex flex-col gap-0 py-0" aria-busy={loading}>
            <CardHeader className="gap-3 border-b py-3">
                <div className="flex flex-col items-start gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <CardTitle className="flex items-center gap-2 text-base leading-7">
                        <span className="flex size-7 items-center justify-center rounded-md bg-muted">
                            <CalendarClock
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </span>
                        Upcoming schedule
                    </CardTitle>

                    {schedule && (
                        <p
                            className="text-xs text-muted-foreground tabular-nums"
                            aria-live="polite"
                        >
                            {scopeSentence(schedule, state)}
                        </p>
                    )}
                </div>

                {schedule && kpis && (
                    <div className="flex flex-wrap items-center gap-2">
                        {/*
                            The range control. Each preset states its own size, so
                            "14 days" is unambiguous next to a strip of 15 cells,
                            and the offsets come from the server rather than from
                            a table here.
                        */}
                        <div
                            className="flex flex-wrap items-center gap-1"
                            role="group"
                            aria-label="Range"
                        >
                            {Object.keys(schedule.horizonPresets).map(
                                (preset) => {
                                    const active =
                                        state.horizon === preset ||
                                        (state.horizon === null &&
                                            preset ===
                                                schedule.defaultHorizonPreset);

                                    return (
                                        <Button
                                            key={preset}
                                            type="button"
                                            size="sm"
                                            variant={
                                                active ? 'secondary' : 'ghost'
                                            }
                                            aria-pressed={active}
                                            disabled={
                                                loading || schedule.windowHidden
                                            }
                                            onClick={() =>
                                                apply({
                                                    ...state,
                                                    horizon: preset,
                                                })
                                            }
                                            className={cn(
                                                'h-8 px-2.5 text-xs',
                                                active &&
                                                    'bg-accent font-semibold text-foreground ring-1 ring-foreground/20',
                                            )}
                                        >
                                            {horizonLabel(preset)}
                                        </Button>
                                    );
                                },
                            )}
                        </div>

                        <span className="text-xs text-muted-foreground">·</span>

                        {/*
                            The overdue mode. This is the control that decides
                            whether the backlog is on screen, so it is named
                            rather than implied by a filter chip.
                        */}
                        <div
                            className="flex flex-wrap items-center gap-1"
                            role="group"
                            aria-label="Overdue records"
                        >
                            {(
                                [
                                    ['include', 'Include overdue'],
                                    ['only', 'Overdue only'],
                                    ['exclude', 'Hide overdue'],
                                ] as const
                            ).map(([mode, label]) => {
                                const active = state.overdue === mode;

                                return (
                                    <Button
                                        key={mode}
                                        type="button"
                                        size="sm"
                                        variant={active ? 'secondary' : 'ghost'}
                                        aria-pressed={active}
                                        disabled={loading}
                                        onClick={() =>
                                            apply({
                                                ...state,
                                                overdue:
                                                    mode as ScheduleOverdueMode,
                                            })
                                        }
                                        className={cn(
                                            'h-8 px-2.5 text-xs',
                                            active &&
                                                'bg-accent font-semibold text-foreground ring-1 ring-foreground/20',
                                        )}
                                    >
                                        {label}
                                    </Button>
                                );
                            })}
                        </div>

                        {!isDefaultState(state) && (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                disabled={loading}
                                onClick={() => apply(DEFAULT_STATE)}
                                className="ml-auto h-8 px-2.5 text-xs"
                            >
                                Reset
                            </Button>
                        )}
                    </div>
                )}

                {schedule && (
                    <div
                        className="flex flex-wrap items-center gap-2"
                        role="group"
                        aria-label="Record kind"
                    >
                        {typeTabs(schedule).map((tab) => {
                            const active = state.type === tab.id;

                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    aria-pressed={active}
                                    aria-label={`${tab.label}, ${tab.count} ${
                                        tab.count === 1 ? 'record' : 'records'
                                    }`}
                                    disabled={loading}
                                    onClick={() =>
                                        apply({ ...state, type: tab.id })
                                    }
                                    className={cn(
                                        'rounded-full px-3 py-1 text-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-50',
                                        active
                                            ? 'bg-accent font-semibold text-foreground ring-1 ring-foreground/20'
                                            : 'font-medium text-muted-foreground hover:bg-accent/40',
                                    )}
                                >
                                    {tab.label}
                                    {/* The space lives inside the span so the
                                        accessible name reads "All 7" rather than
                                        "All7"; the margin is visual only. */}
                                    <span className="ml-1 tabular-nums opacity-70">
                                        {' '}
                                        {tab.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                )}
            </CardHeader>

            <CardContent className="flex flex-col p-0">
                {schedule ? (
                    <>
                        {/*
                            Overdue-only is the one mode with no window. Showing a
                            strip over a range that is not filtering anything would
                            invite the reader to click days that do nothing.
                        */}
                        {!schedule.windowHidden && (
                            <div className="border-b px-4 py-3">
                                <ScheduleDayStrip
                                    days={schedule.days}
                                    selected={state.day}
                                    onSelect={(day) => apply({ ...state, day })}
                                />
                            </div>
                        )}

                        <ScheduleAgenda
                            groups={schedule.groups}
                            returnedCount={schedule.returnedCount}
                            totalCount={schedule.totalCount}
                            truncated={schedule.truncated}
                            maxItems={schedule.maxItems}
                            overdueMode={state.overdue}
                            overdueCount={schedule.overdueCount}
                            windowItemCount={schedule.windowItemCount}
                            asOfDate={schedule.from}
                            onShowOverdue={
                                selectedTile === 'overdue'
                                    ? undefined
                                    : () =>
                                          apply(
                                              kpis
                                                  ? tileState('overdue', kpis)
                                                  : DEFAULT_STATE,
                                          )
                            }
                            // Widening the scope, not resetting the page: the
                            // reader keeps their range and type, and the day is
                            // dropped because an offer is about the whole scope.
                            onShowWindow={() =>
                                apply({
                                    ...state,
                                    overdue: 'include',
                                    day: null,
                                })
                            }
                        />
                    </>
                ) : (
                    <div
                        className="flex items-center justify-center gap-2 px-4 py-12 text-muted-foreground"
                        role="status"
                    >
                        <Spinner className="size-5" />
                        <span className="text-sm">Loading schedule</span>
                    </div>
                )}
            </CardContent>

            {loading && schedule && (
                <div
                    className="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-card/70"
                    role="status"
                >
                    <Spinner className="size-6" />
                    <span className="sr-only">Loading schedule</span>
                </div>
            )}
        </Card>
    );
}
