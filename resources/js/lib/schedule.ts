import {
    formatLongDate,
    rangeLabel,
    shortDate,
    weekdayShort,
} from '@/lib/dates';
import type { IsoDate } from '@/lib/dates';
import { isIsoDate } from '@/lib/dates';
import type {
    DashboardKpis,
    DashboardSchedule,
    ScheduleItemKind,
    ScheduleOverdueMode,
    ScheduleType,
} from '@/types/fire-tracker';

/**
 * The dashboard's schedule is state in the query string, not component state.
 * Everything below is a translation between a URL and that state, which means a
 * reload, a shared link and a browser Back all land on the same view without the
 * panel holding a second copy of the truth.
 *
 * Nothing here counts days. The server resolves the window, which preset it is,
 * where the Next/Later boundary falls and every row's status, because those are
 * day-count comparisons and `.ai/rules/js.md` keeps them in PHP. This module
 * only decides what to ask for and how to name the answer.
 */

/** The user-facing name for each record kind. */
export const KIND_LABELS: Record<ScheduleItemKind, string> = {
    asset: 'Inspection',
    certificate: 'Certificate',
};

export function kindLabel(kind: ScheduleItemKind, plural = false): string {
    return plural
        ? kind === 'asset'
            ? 'Inspections'
            : 'Certificates'
        : KIND_LABELS[kind];
}

export const SCHEDULE_TYPES: ScheduleType[] = ['all', 'asset', 'certificate'];

export const OVERDUE_MODES: ScheduleOverdueMode[] = [
    'include',
    'only',
    'exclude',
];

export type ScheduleState = {
    type: ScheduleType;
    /** A preset key such as `7d`, or null to let the server choose. */
    horizon: string | null;
    /** A custom inclusive range; set together or not at all. */
    from: IsoDate | null;
    to: IsoDate | null;
    overdue: ScheduleOverdueMode;
    day: IsoDate | null;
};

/** What the page shows before anyone touches it. */
export const DEFAULT_STATE: ScheduleState = {
    type: 'all',
    horizon: null,
    from: null,
    to: null,
    overdue: 'include',
    day: null,
};

export function scheduleState(
    partial: Partial<ScheduleState> = {},
): ScheduleState {
    return { ...DEFAULT_STATE, ...partial };
}

/**
 * Reads state out of a URL.
 *
 * Every field is validated rather than trusted: a hand-edited or stale link
 * should land on the nearest view that makes sense, never on a panel that
 * renders nothing with no way back.
 */
export function stateFromQuery(
    params: URLSearchParams | Record<string, string | undefined>,
): ScheduleState {
    const read = (key: string): string | undefined =>
        params instanceof URLSearchParams
            ? (params.get(key) ?? undefined)
            : params[key];

    const type = read('schedule_type');
    const overdue = read('schedule_overdue');
    const from = read('schedule_from');
    const to = read('schedule_to');
    const day = read('schedule_day');

    // A half-specified custom range is not a range. Falling back to the
    // default window keeps the two ends consistent instead of showing one.
    const hasRange = isIsoDate(from) && isIsoDate(to);

    return {
        type: SCHEDULE_TYPES.includes(type as ScheduleType)
            ? (type as ScheduleType)
            : 'all',
        horizon: read('schedule_horizon') || null,
        from: hasRange ? from : null,
        to: hasRange ? to : null,
        overdue: OVERDUE_MODES.includes(overdue as ScheduleOverdueMode)
            ? (overdue as ScheduleOverdueMode)
            : 'include',
        day: isIsoDate(day) ? day : null,
    };
}

/**
 * Writes state back to a query string, leaving out anything already at its
 * default. That keeps a shared link short and makes "reset" the empty query,
 * which is what makes two tiles visibly interchangeable when both are idle.
 */
export function stateToQuery(state: ScheduleState): Record<string, string> {
    const query: Record<string, string> = {};

    if (state.type !== DEFAULT_STATE.type) {
        query.schedule_type = state.type;
    }

    if (state.overdue !== DEFAULT_STATE.overdue) {
        query.schedule_overdue = state.overdue;
    }

    if (state.horizon !== null) {
        query.schedule_horizon = state.horizon;
    }

    if (state.from !== null && state.to !== null) {
        query.schedule_from = state.from;
        query.schedule_to = state.to;
    }

    if (state.day !== null) {
        query.schedule_day = state.day;
    }

    return query;
}

export function statesMatch(a: ScheduleState, b: ScheduleState): boolean {
    return (
        a.type === b.type &&
        a.overdue === b.overdue &&
        a.horizon === b.horizon &&
        a.from === b.from &&
        a.to === b.to &&
        a.day === b.day
    );
}

export function isDefaultState(state: ScheduleState): boolean {
    return statesMatch(state, DEFAULT_STATE);
}

/** The three tiles that narrow the schedule. The pass-rate card is not one. */
export type TileId = 'overdue' | 'dueSoon' | 'certificates';

export const TILE_IDS: TileId[] = ['overdue', 'dueSoon', 'certificates'];

/**
 * The state each tile measures.
 *
 * This is the contract that makes a tile's headline number and the list it
 * opens the same measurement: the tile asks the server for exactly the scope its
 * number was counted over. The horizon keys come from the KPI payload rather
 * than from a table here, so a tile cannot drift from its own count.
 *
 * A tile also clears any selected day, because "the next 7 days" is a question
 * about the whole window, not about whichever day happened to be lit.
 */
export function tileState(tile: TileId, kpis: DashboardKpis): ScheduleState {
    if (tile === 'overdue') {
        return scheduleState({ overdue: 'only' });
    }

    if (tile === 'dueSoon') {
        return scheduleState({
            horizon: kpis.dueSoon.horizonPreset,
            overdue: 'exclude',
        });
    }

    return scheduleState({
        type: 'certificate',
        horizon: kpis.certificates.horizonPreset,
    });
}

/** The tile whose scope the current state is, or null when it is a free view. */
export function activeTile(
    state: ScheduleState,
    kpis: DashboardKpis,
): TileId | null {
    return (
        TILE_IDS.find((tile) => statesMatch(state, tileState(tile, kpis))) ??
        null
    );
}

const HORIZON_LABELS: Record<string, string> = {
    '7d': '7 days',
    '14d': '14 days',
    '30d': '30 days',
    '60d': '60 days',
    '90d': '90 days',
};

/**
 * A preset's label. The offsets are server-owned, so this only names the ones
 * the product offers and falls back to the raw key for anything else rather
 * than inventing a number the server did not send.
 */
export function horizonLabel(key: string): string {
    return HORIZON_LABELS[key] ?? key;
}

/**
 * The line that states what the panel is showing, including the work it is
 * deliberately leaving out.
 *
 * A range label on its own would quietly omit every overdue row above it, and
 * would quietly omit whatever falls past the window too. Both are named.
 */
export function scopeSentence(
    schedule: DashboardSchedule,
    state: ScheduleState,
): string {
    if (state.overdue === 'only') {
        const parts = [`Overdue only · ${schedule.totalCount}`];

        if (schedule.nextAfter) {
            parts.push(
                `next ${kindLabel('asset', true).toLowerCase()} ${
                    schedule.nextAfter.daysUntil === 0
                        ? 'due today'
                        : `due in ${schedule.nextAfter.daysUntil} days`
                }`,
            );
        }

        return `${parts.join(' · ')}`;
    }

    const window = rangeLabel(schedule.from, schedule.to);
    const parts = [
        `${window} · ${schedule.totalCount} record${
            schedule.totalCount === 1 ? '' : 's'
        }`,
    ];

    if (state.overdue === 'include' && schedule.overdueCount > 0) {
        parts.push(`incl. ${schedule.overdueCount} overdue`);
    }

    if (state.day !== null) {
        const noun = schedule.totalCount === 1 ? 'record' : 'records';
        parts.splice(
            0,
            1,
            `${weekdayShort(state.day)} ${shortDate(state.day)} · ${
                schedule.totalCount
            } ${noun}`,
        );
    }

    return parts.join(' · ');
}

/**
 * The kind tabs, labelled from the counts the server measured. The numbers
 * describe the current window and overdue mode, so switching tabs cannot leave
 * a stale figure behind.
 */
export function typeTabs(schedule: DashboardSchedule): {
    id: ScheduleType;
    label: string;
    count: number;
}[] {
    return [
        { id: 'all', label: 'All', count: schedule.counts.all },
        { id: 'asset', label: KIND_LABELS.asset, count: schedule.counts.asset },
        {
            id: 'certificate',
            label: KIND_LABELS.certificate,
            count: schedule.counts.certificate,
        },
    ];
}

/**
 * Breaks a count down by record kind, so "4 overdue" next to an "Overdue
 * inspections" figure elsewhere on the page cannot be a lie.
 */
export function kindBreakdown(
    byKind: Record<ScheduleItemKind, number>,
    noun = 'overdue',
): string {
    const parts = (Object.keys(byKind) as ScheduleItemKind[])
        .filter((kind) => byKind[kind] > 0)
        .map((kind) => {
            const count = byKind[kind];

            return `${count} ${
                count === 1
                    ? kindLabel(kind).toLowerCase()
                    : kindLabel(kind, true).toLowerCase()
            }`;
        });

    return parts.length === 0 ? `No ${noun}` : `${parts.join(' · ')}`;
}

/** The oldest overdue date in words, for the overdue group's heading. */
export function sinceSentence(date: IsoDate | null): string {
    return date === null ? '' : `since ${formatLongDate(date)}`;
}
